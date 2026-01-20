<?php


function admin_table_row_counts(PDO $pdo, array $tables = []) {
  if (empty($tables)) {
    $tables = ['users','zones','safe','infection_events','medical_campaign','medical_campaign_registrations','ambulance_requests','icu_requests','medical_equipments','alerts'];
  }
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $sql = "
      SELECT TABLE_NAME, TABLE_ROWS
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ($placeholders)
      ORDER BY TABLE_NAME
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($tables);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


//Zones with high death rate (having)
function zones_high_death_rate(PDO $pdo, $threshold = 0.01) {
    $sql = "
      SELECT id AS zone_id, name AS zone_name, population, death_count,
        (death_count / NULLIF(population,0)) AS death_rate
      FROM zones
      WHERE population > 0
      HAVING death_rate > :thr
      ORDER BY death_rate DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':thr' => (float)$threshold]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}




//Campaign capacity usage (registrations vs capacity)
function campaigns_capacity_usage(PDO $pdo) {
    $sql = "
      SELECT c.id AS campaign_id, c.title, c.zone_id, c.capacity,
             COALESCE(cr.reg_count,0) AS registrations,
             ROUND(100 * COALESCE(cr.reg_count,0) / NULLIF(c.capacity,0),1) AS pct_full,
             c.state
  FROM medical_campaign c
      LEFT JOIN (
        SELECT campaign_id, COUNT(*) AS reg_count
        FROM medical_campaign_registrations
        GROUP BY campaign_id
      ) cr ON cr.campaign_id = c.id
      ORDER BY pct_full DESC, registrations DESC
    ";
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


//Campaigns over capacity (registrations > capacity)
function campaigns_over_capacity(PDO $pdo) {
    $sql = "
      SELECT c.id AS campaign_id, c.title, c.capacity, s.registrations
  FROM medical_campaign c
      JOIN (
         SELECT campaign_id, COUNT(*) AS registrations
         FROM medical_campaign_registrations
         GROUP BY campaign_id
      ) s ON s.campaign_id = c.id
      WHERE c.capacity IS NOT NULL AND s.registrations > c.capacity
      ORDER BY (s.registrations - c.capacity) DESC
    ";
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}




//Open medical requests by zone (ambulance + ICU aggregates)
function medical_requests_by_zone(PDO $pdo) {
    $sql = "
      SELECT z.id AS zone_id, z.name AS zone_name,
        SUM(CASE WHEN ar.status = 'requested' THEN 1 ELSE 0 END) AS ambulance_requested,
        SUM(CASE WHEN ar.status = 'assigned' THEN 1 ELSE 0 END) AS ambulance_assigned,
        SUM(CASE WHEN ir.status = 'requested' THEN 1 ELSE 0 END) AS icu_requested,
        SUM(CASE WHEN ir.status = 'confirmed' THEN 1 ELSE 0 END) AS icu_confirmed
      FROM zones z
      LEFT JOIN ambulance_requests ar ON ar.zone_id = z.id
      LEFT JOIN icu_requests ir ON ir.zone_id = z.id
      GROUP BY z.id
      ORDER BY ambulance_requested DESC, icu_requested DESC
    ";
    $st = $pdo->query($sql);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


//Pending ambulance & ICU requests overall (summary)
function pending_requests_overview(PDO $pdo) {
    $results = [];
    $st = $pdo->query("SELECT 'ambulance' AS type, status, COUNT(*) AS cnt FROM ambulance_requests GROUP BY status");
    $results = array_merge($results, $st->fetchAll(PDO::FETCH_ASSOC));
    $st2 = $pdo->query("SELECT 'icu' AS type, status, COUNT(*) AS cnt FROM icu_requests GROUP BY status");
    $results = array_merge($results, $st2->fetchAll(PDO::FETCH_ASSOC));
    return $results;
}




//Vaccines low stock (threshold param)
function vaccines_low_stock(PDO $pdo, $threshold = 10) {
  $sql = "SELECT id, name, stock FROM medical_equipments WHERE stock <= :thr ORDER BY stock ASC";
    $st = $pdo->prepare($sql);
    $st->execute([':thr' => (int)$threshold]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}



//people in a set of zones
function admin_people_in_zones(PDO $pdo, array $zoneIds, $limit = 200) {
  if (count($zoneIds) === 0) return [];
  // ensure all zone ids are integers
  $zoneIds = array_values(array_map('intval', $zoneIds));
  $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
  // inline the limit as an integer to avoid binding it as a string which can break some MySQL/MariaDB versions
  $sql = "SELECT * FROM `safe` WHERE zone_id IN ($placeholders) LIMIT " . (int)$limit;
  $stmt = $pdo->prepare($sql);
  $stmt->execute($zoneIds);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


//people not in a set of zones
function admin_people_not_in_zones(PDO $pdo, array $zoneIds, $limit = 200) {
  if (count($zoneIds) === 0) {
    $sql = "SELECT * FROM `safe` LIMIT " . (int)$limit;
    $stmt = $pdo->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  }
  // ensure all zone ids are integers
  $zoneIds = array_values(array_map('intval', $zoneIds));
  $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
  $sql = "SELECT * FROM `safe` WHERE zone_id NOT IN ($placeholders) LIMIT " . (int)$limit;
  $stmt = $pdo->prepare($sql);
  $stmt->execute($zoneIds);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


//users who have a `safe` row
function admin_users_with_safe_exists(PDO $pdo, $limit = 200) {
    $sql = "
      SELECT u.id, u.name, u.email
      FROM users u
      WHERE EXISTS (
        SELECT 1 FROM `safe` s WHERE s.user_id = u.id
      )
      LIMIT :limit
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


//registrations referencing missing users
function admin_registrations_with_missing_user(PDO $pdo, $limit = 200) {
    $sql = "
      SELECT cr.*
      FROM medical_campaign_registrations cr
      WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.id = cr.user_id)
      LIMIT :limit
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


//recent registrations per campaign (last 7 days)
function admin_recent_regs_cte(PDO $pdo, $limit = 200) {
    $sql = "
      WITH recent_regs AS (
        SELECT campaign_id, COUNT(*) AS regs_last_7d
        FROM medical_campaign_registrations
        WHERE registered_at >= NOW() - INTERVAL 7 DAY
        GROUP BY campaign_id
      )
      SELECT c.id AS campaign_id, c.title, COALESCE(r.regs_last_7d,0) AS regs_last_7d
      FROM medical_campaign c
      LEFT JOIN recent_regs r ON r.campaign_id = c.id
      ORDER BY regs_last_7d DESC
      LIMIT :limit
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


function icu_req_seven_days(PDO $pdo, $limit = 200) {
    $sql="
    select count(*) as cnt from icu_requests 
    where created_at >= NOW() - INTERVAL 7 DAY";
    $st = $pdo->prepare($sql);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
}