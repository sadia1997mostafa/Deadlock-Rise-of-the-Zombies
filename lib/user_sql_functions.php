<?php

function _user_sql_limit_offset(int $page = 1, int $page_size = 20) {
    $page = max(1, $page);
    $page_size = max(1, min(100, $page_size));
    $limit = $page_size;
    $offset = ($page - 1) * $limit;
    return [$limit, $offset];
}

// 1) My requests (WHERE,union)
function user_get_my_requests(PDO $pdo, int $user_id, int $page = 1, int $page_size = 20, ?string $status = null) {
    list($limit, $offset) = _user_sql_limit_offset($page, $page_size);
    if ($status === null) {
        $sql = "
            SELECT id, 'ambulance' AS type, zone_id, created_at, status, details
            FROM ambulance_requests
            WHERE user_id = :user_id
            UNION ALL
            SELECT id, 'icu' AS type, zone_id, created_at, status, details
            FROM icu_requests
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $st = $pdo->prepare($sql);
        $st->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $sql = "
        SELECT id, 'ambulance' AS type, zone_id, created_at, status, details
        FROM ambulance_requests
        WHERE user_id = :user_id AND status = :status
        UNION ALL
        SELECT id, 'icu' AS type, zone_id, created_at, status, details
        FROM icu_requests
        WHERE user_id = :user_id AND status = :status
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $st->bindValue(':status', $status, PDO::PARAM_STR);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Active medical campaigns nearby (inner join example)
function user_get_active_campaigns(PDO $pdo, ?int $zone_id = null, int $page = 1, int $page_size = 20) {
    list($limit, $offset) = _user_sql_limit_offset($page, $page_size);
    $sql = "
    SELECT mc.id, mc.title AS name, mc.created_at AS created_at, mc.zone_id, z.name AS zone_name, mc.description AS description, mc.capacity
        FROM medical_campaign mc
        INNER JOIN zones z ON z.id = mc.zone_id
        WHERE mc.state <> 'done'";
    if ($zone_id !== null) {
        $sql .= " AND mc.zone_id = :zone_id";
    }
    $sql .= " ORDER BY mc.created_at LIMIT :limit OFFSET :offset";
    $st = $pdo->prepare($sql);
    if ($zone_id !== null) $st->bindValue(':zone_id', (int)$zone_id, PDO::PARAM_INT);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 3) Equipment availability (WHERE + expression)
function user_get_equipment_availability(PDO $pdo, ?int $zone_id = null, ?int $campaign_id = null, int $min_available = 0) {
    
    $sql = "
        SELECT me.id, me.name, me.stock AS total_stock,
               (me.stock) AS available
        FROM medical_equipments me
        WHERE me.stock >= :min_available
        ORDER BY available DESC
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':min_available', (int)$min_available, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


// infection events in last N days (JOINs)
function user_recent_infection_events(PDO $pdo, int $days = 7, int $limit = 50) {
    $days = max(1, (int)$days);
    $limit = max(1, min(500, (int)$limit));
    $sql = "SELECT ie.id, ie.zone_id, z.name AS zone_name, ie.reporter_id, u.name AS reporter_name, ie.event_type, ie.cases, ie.created_at
            FROM infection_events ie
            INNER JOIN zones z ON z.id = ie.zone_id
            LEFT JOIN users u ON u.id = ie.reporter_id
            WHERE ie.created_at >= NOW() - INTERVAL :days DAY
            ORDER BY ie.created_at DESC
            LIMIT :limit";
    $st = $pdo->prepare($sql);
    $st->bindValue(':days', $days, PDO::PARAM_INT);
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// campaign reg summary (having + aggregation, group by,)
function user_campaigns_registration_summary(PDO $pdo, int $min_open = 1) {
    $min_open = (int)$min_open;
    $sql = "SELECT mc.id AS campaign_id, mc.title, mc.zone_id, z.name AS zone_name, COALESCE(mc.capacity,0) AS capacity,
                   COALESCE(COUNT(r.id),0) AS registrations,
                   (COALESCE(mc.capacity,0) - COALESCE(COUNT(r.id),0)) AS slots_left
            FROM medical_campaign mc
            LEFT JOIN medical_campaign_registrations r ON r.campaign_id = mc.id
            LEFT JOIN zones z ON z.id = mc.zone_id
            GROUP BY mc.id
            HAVING slots_left >= :min_open
            ORDER BY slots_left DESC, mc.created_at DESC";
    $st = $pdo->prepare($sql);
    $st->bindValue(':min_open', $min_open, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// reg summary (NOT EXISTS,join)
function user_campaigns_without_registrations_notexists(PDO $pdo) {
    $sql = "SELECT mc.id AS campaign_id, mc.title, mc.zone_id, z.name AS zone_name, mc.created_at
            FROM medical_campaign mc
            LEFT JOIN zones z ON z.id = mc.zone_id
            WHERE NOT EXISTS (
                SELECT 1 FROM medical_campaign_registrations r WHERE r.campaign_id = mc.id
            )
            ORDER BY mc.created_at DESC";
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// top zones by case rate (aggregation, expression, having)
function user_top_zones_by_case_rate(PDO $pdo, int $days = 7, int $limit = 20) {
    $days = max(1, (int)$days);
    $limit = max(1, min(200, (int)$limit));
    $sql = "SELECT z.id AS zone_id, z.name AS zone_name, COALESCE(z.population,0) AS population,
                   COALESCE(SUM(ie.cases),0) AS total_cases,
                   (COALESCE(SUM(ie.cases),0) / NULLIF(GREATEST(COALESCE(z.population,0),1),0)) * 1000 AS cases_per_1000
            FROM zones z
            LEFT JOIN infection_events ie ON ie.zone_id = z.id AND ie.created_at >= NOW() - INTERVAL :days DAY
            GROUP BY z.id
            HAVING total_cases > 0
            ORDER BY cases_per_1000 DESC
            LIMIT :limit";
    $st = $pdo->prepare($sql);
    $st->bindValue(':days', $days, PDO::PARAM_INT);
    $st->bindValue(':limit', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


// 4) Campaign detail with registration count and whether user registered (subquery + EXISTS)
function user_get_campaign_detail(PDO $pdo, int $campaign_id, ?int $user_id = null) {
    $sql = "
        SELECT mc.*,
          (SELECT COUNT(*) FROM medical_campaign_registrations r WHERE r.campaign_id = mc.id) AS registered_count,
          EXISTS(SELECT 1 FROM medical_campaign_registrations r2 WHERE r2.campaign_id = mc.id AND r2.user_id = :user_id) AS user_registered
        FROM medical_campaign mc
        WHERE mc.id = :campaign_id
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':campaign_id', $campaign_id, PDO::PARAM_INT);
    $st->bindValue(':user_id', $user_id === null ? 0 : (int)$user_id, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    // convert user_registered from '0'/'1' to boolean
    $row['user_registered'] = (bool)$row['user_registered'];
    return $row;
}

// 5) Register for campaign (transactional write with checks)
function user_register_for_campaign(PDO $pdo, int $user_id, int $campaign_id) {
    try {
        $pdo->beginTransaction();
        // check duplicate
        $st = $pdo->prepare("SELECT COUNT(*) FROM medical_campaign_registrations WHERE user_id = :uid AND campaign_id = :cid");
        $st->execute([':uid' => $user_id, ':cid' => $campaign_id]);
        if ($st->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Already registered'];
        }
        // lock registration count
        $st2 = $pdo->prepare("SELECT capacity FROM medical_campaign WHERE id = :cid FOR UPDATE");
        $st2->execute([':cid' => $campaign_id]);
        $cap = $st2->fetchColumn();
        if ($cap !== false) {
            $st3 = $pdo->prepare("SELECT COUNT(*) FROM medical_campaign_registrations WHERE campaign_id = :cid");
            $st3->execute([':cid' => $campaign_id]);
            $reg_count = (int)$st3->fetchColumn();
            if ($cap !== null && $cap !== '' && $cap <= $reg_count) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Campaign is full'];
            }
        }
        // insert registration
        $ins = $pdo->prepare("INSERT INTO medical_campaign_registrations (user_id, campaign_id, registered_at) VALUES (:uid, :cid, NOW())");
        $ins->execute([':uid' => $user_id, ':cid' => $campaign_id]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Registered', 'registration_id' => (int)$pdo->lastInsertId()];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// 6) My registrations (JOIN + pagination)
function user_get_my_registrations(PDO $pdo, int $user_id, int $page = 1, int $page_size = 20) {
    list($limit, $offset) = _user_sql_limit_offset($page, $page_size);
    $sql = "
    SELECT r.id, r.campaign_id, mc.title AS campaign_name, mc.created_at AS campaign_created, mc.description, r.registered_at
        FROM medical_campaign_registrations r
        INNER JOIN medical_campaign mc ON mc.id = r.campaign_id
        WHERE r.user_id = :user_id
        ORDER BY r.registered_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 7) Zone summary (GROUP BY + HAVING)
function user_get_zone_summary(PDO $pdo, ?int $zone_id = null) {
    $sql = "
        SELECT z.id AS zone_id, z.name AS zone_name,
          COUNT(mc.id) AS active_campaigns,
          SUM(CASE WHEN icu.status = 'admitted' THEN 1 ELSE 0 END) AS icu_admitted
        FROM zones z
    LEFT JOIN medical_campaign mc ON mc.zone_id = z.id AND mc.state <> 'done'
        LEFT JOIN icu_requests icu ON icu.zone_id = z.id
        " . ($zone_id === null ? '' : ' WHERE z.id = :zone_id') . "
        GROUP BY z.id
        HAVING active_campaigns >= 0
        ORDER BY active_campaigns DESC
    ";
    $st = $pdo->prepare($sql);
    if ($zone_id !== null) $st->bindValue(':zone_id', (int)$zone_id, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 8) Search campaigns using UNION (set operation)
function user_search_campaigns_union(PDO $pdo, string $search_text, int $page = 1, int $page_size = 20) {
    list($limit, $offset) = _user_sql_limit_offset($page, $page_size);
    $like = '%' . str_replace('%','\\%',$search_text) . '%';
    $sql = "
        (SELECT id, title AS name, created_at AS created_at, description FROM medical_campaign WHERE title LIKE :like)
        UNION
        (SELECT id, title AS name, created_at AS created_at, description FROM medical_campaign WHERE description LIKE :like)
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':like', $like, PDO::PARAM_STR);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 9) Campaigns by id list (IN / NOT IN example)
function user_get_campaigns_by_ids(PDO $pdo, array $ids = []) {
    if (empty($ids)) return [];
    $ids = array_values(array_map('intval', $ids));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, title AS name, created_at AS created_at, description FROM medical_campaign WHERE id IN ($placeholders)";
    $st = $pdo->prepare($sql);
    $st->execute($ids);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 10) Campaigns with equipment - EXISTS / NOT EXISTS example
function user_get_campaigns_with_equipment(PDO $pdo, bool $only_with_equipment = true) {
    if ($only_with_equipment) {
        $sql = "
            SELECT mc.id, mc.title AS name
            FROM medical_campaign mc
            WHERE EXISTS (
                SELECT 1 FROM medical_equipments me WHERE me.stock > 0
            )
            ORDER BY mc.created_at DESC
        ";
    } else {
        $sql = "
            SELECT mc.id, mc.title AS name
            FROM medical_campaign mc
            WHERE NOT EXISTS (
                SELECT 1 FROM medical_equipments me WHERE me.campaign_id = mc.id
            )
            ORDER BY mc.created_at DESC
        ";
    }
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 11) CROSS JOIN demo (cartesian product) - use with caution (limited)
function user_cross_join_demo(PDO $pdo, int $limit = 100) {
    $sql = "SELECT z.name AS zone_name, me.name AS equipment_name
            FROM zones z
            CROSS JOIN (
                SELECT id, name FROM medical_equipments LIMIT 20
            ) me
            LIMIT :limit";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 12) LEFT / RIGHT / FULL OUTER join style examples (LEFT JOIN used; FULL not supported in MySQL)
function user_get_campaigns_with_registration_counts(PDO $pdo, int $min_open_slots = 1) {
    // demonstrates LEFT JOIN, GROUP BY, HAVING and an equi join
    $sql = "
    SELECT mc.id, mc.title AS name, mc.capacity, COALESCE(COUNT(r.id),0) AS registrations,
               (COALESCE(mc.capacity,0) - COALESCE(COUNT(r.id),0)) AS slots_left
        FROM medical_campaign mc
        LEFT JOIN medical_campaign_registrations r ON r.campaign_id = mc.id
        GROUP BY mc.id
        HAVING slots_left >= :min_open
        ORDER BY slots_left DESC
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':min_open', (int)$min_open_slots, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 13) Self join - find other campaigns in same zone (SELF JOIN)
function user_find_other_campaigns_same_zone(PDO $pdo, int $campaign_id, int $limit = 10) {
    $sql = "
    SELECT other.id, other.title AS name, other.created_at AS created_at
        FROM medical_campaign base
        JOIN medical_campaign other ON other.zone_id = base.zone_id AND other.id != base.id
        WHERE base.id = :cid
        ORDER BY other.created_at
        LIMIT :limit
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':cid', (int)$campaign_id, PDO::PARAM_INT);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 14) Non-equi join example: equipments with stock >= campaign capacity (non-equi condition)
function user_campaigns_with_sufficient_equipment(PDO $pdo, int $campaign_id) {
    $sql = "
    SELECT mc.id AS campaign_id, mc.title AS campaign_name, me.id AS equipment_id, me.name AS equipment_name, me.stock
        FROM medical_campaign mc
        JOIN medical_equipments me ON me.stock >= COALESCE(mc.capacity,0)
        WHERE mc.id = :cid
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':cid', (int)$campaign_id, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 15) Example using NOT IN to find campaigns without registrations
function user_get_campaigns_without_registrations(PDO $pdo) {
    $sql = "
    SELECT mc.id, mc.title AS name
        FROM medical_campaign mc
        WHERE mc.id NOT IN (SELECT DISTINCT campaign_id FROM medical_campaign_registrations)
        ORDER BY mc.created_at DESC
    ";
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}





/**
 * 2) Campaigns registration summary (GROUP BY + HAVING)
 *    Returns per-campaign registration counts and slots_left where slots_left >= min_open
 */



/**
 * 3) Campaigns without registrations using NOT EXISTS (demonstrates NOT EXISTS)
 */



/**
 * 4b) Top zones by case rate (cases per 1k population) — uses infection_events and zones.
 *    Returns zone_id, zone_name, population, total_cases, cases_per_1000
 */


/**
 * 4) Campaigns with zone info (outer join example) - includes campaigns with NULL zone
 */
function user_campaigns_with_zone_outer_join(PDO $pdo, int $page = 1, int $page_size = 50) {
    list($limit, $offset) = _user_sql_limit_offset($page, $page_size);
    $sql = "SELECT mc.id AS campaign_id, mc.title, mc.state, mc.capacity, z.id AS zone_id, z.name AS zone_name
            FROM medical_campaign mc
            LEFT JOIN zones z ON z.id = mc.zone_id
            ORDER BY mc.created_at DESC
            LIMIT :limit OFFSET :offset";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * 5) Action: mark a user as infected (transactional) and insert an infection_event if missing
 *    Returns array: ['ok'=>bool,'message'=>string]
 */
function user_mark_self_infected_event(PDO $pdo, int $user_id): array {
    $user_id = (int)$user_id;
    if ($user_id <= 0) return ['ok' => false, 'message' => 'Invalid user id'];
    try {
        $pdo->beginTransaction();
        // find safe row or create it
        $st = $pdo->prepare("SELECT id, zone_id FROM `safe` WHERE user_id = ? LIMIT 1"); $st->execute([$user_id]); $sr = $st->fetch(PDO::FETCH_ASSOC);
        if ($sr) {
            $safeId = (int)$sr['id'];
            $zoneId = isset($sr['zone_id']) ? (int)$sr['zone_id'] : 0;
            $pdo->prepare("UPDATE `safe` SET outbreak_status='infected', updated_at=NOW() WHERE id = ?")->execute([$safeId]);
        } else {
            // create a safe row; zone unknown
            $pdo->prepare("INSERT INTO `safe` (user_id,name,created_at,outbreak_status,updated_at) VALUES (?,?,?,?,NOW())")->execute([$user_id, 'User '.$user_id, 'infected', 'infected']);
            $safeId = (int)$pdo->lastInsertId();
            $zoneId = 0;
        }

        // if zone available, ensure an infection_events row exists in last 24h
        if ($zoneId) {
            $chk = $pdo->prepare("SELECT id FROM infection_events WHERE zone_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR LIMIT 1");
            $chk->execute([$zoneId]);
            $exists = (bool)$chk->fetchColumn();
            if (!$exists) {
                $ins = $pdo->prepare("INSERT INTO infection_events (zone_id, reporter_id, event_type, cases, created_at) VALUES (?, ?, ?, ?, NOW())");
                $ins->execute([$zoneId, $user_id, 'report', 1]);
            }
            // create alert if threshold reached (uses safe table counts)
            m_check_and_create_zone_alert($pdo, $zoneId, (int)DANGER_ALERT_THRESHOLD);
        }

        $pdo->commit();
        return ['ok' => true, 'message' => 'Marked infected'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => 'Error marking infected: ' . $e->getMessage()];
    }
}


/**
 * 6) Action: cancel a request (ambulance or icu) owned by the user
 *    $type must be 'ambulance' or 'icu'
 *    Returns ['ok'=>bool,'message'=>string]
 */
function user_cancel_request(PDO $pdo, int $user_id, string $type, int $request_id): array {
    $user_id = (int)$user_id; $request_id = (int)$request_id; $type = strtolower(trim($type));
    if ($user_id <= 0 || $request_id <= 0) return ['ok' => false, 'message' => 'Invalid parameters'];
    if (!in_array($type, ['ambulance','icu'])) return ['ok' => false, 'message' => 'Invalid request type'];
    $table = ($type === 'ambulance') ? 'ambulance_requests' : 'icu_requests';
    try {
        $pdo->beginTransaction();
        // verify ownership and current status
        $st = $pdo->prepare("SELECT status, user_id FROM {$table} WHERE id = ? LIMIT 1");
        $st->execute([$request_id]); $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $pdo->rollBack(); return ['ok'=>false,'message'=>'Request not found']; }
        if ((int)$row['user_id'] !== $user_id) { $pdo->rollBack(); return ['ok'=>false,'message'=>'Not allowed']; }
        $status = $row['status'] ?? '';
        // only allow canceling certain statuses (terminal statuses cannot be cancelled)
        $terminal = ['completed','cancelled','admitted','rejected'];
        if (in_array(strtolower($status), $terminal, true)) {
            $pdo->rollBack();
            return ['ok'=>false,'message'=>'Cannot cancel request in current status: ' . ($status ?: 'unknown')];
        }
        $pdo->prepare("UPDATE {$table} SET status = 'cancelled', updated_at = NOW() WHERE id = ?")->execute([$request_id]);
        $pdo->commit();
        return ['ok'=>true,'message'=>'Request cancelled'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'message'=>'Error cancelling request: '.$e->getMessage()];
    }
}
