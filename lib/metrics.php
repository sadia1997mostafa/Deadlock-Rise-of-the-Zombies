<?php
function m_count_zones(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM zones")->fetchColumn();
}

function m_count_alerts_last_hours(PDO $pdo, int $hours = 24): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE created_at >= NOW() - INTERVAL ? HOUR");
    $st->execute([$hours]);
    return (int)$st->fetchColumn();
}

function m_total_ever_infected(PDO $pdo): int {
  
  $sql = "SELECT COUNT(*) FROM (
      SELECT reporter_id AS uid FROM infection_events WHERE reporter_id IS NOT NULL
      UNION
      SELECT user_id AS uid FROM `safe` WHERE outbreak_status = 'infected' AND user_id IS NOT NULL
    ) t";
  $st = $pdo->query($sql);
  return (int)$st->fetchColumn();
}

function m_unique_reporters_last_hours(PDO $pdo, int $hours = 24): int {
  
  $st = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM `safe` WHERE outbreak_status = 'infected' AND updated_at >= NOW() - INTERVAL ? HOUR AND user_id IS NOT NULL");
  $st->execute([$hours]);
  return (int)$st->fetchColumn();
}

// alertbanner
function m_latest_alert(PDO $pdo): ?array {
   $sql = "SELECT a.id, a.title, z.name AS zone_name, a.created_at, a.status
            FROM alerts a
            JOIN zones z ON z.id = a.zone_id
            WHERE a.status IN ('open','acknowledged')
            ORDER BY a.created_at DESC
            LIMIT 1";
    $row = $pdo->query($sql)->fetch();
    return $row ?: null;
}

// zone risk table
function m_zone_risk_table(PDO $pdo): array {
    $sql = "
      SELECT
        z.id   AS zone_id,
        z.name AS zone_name,
        COALESCE((
          SELECT COUNT(*) FROM `safe` s
          WHERE s.zone_id = z.id AND s.outbreak_status = 'infected' AND s.updated_at >= NOW() - INTERVAL 24 HOUR
        ),0) AS cases_24h,
        COALESCE(z.death_count,0) AS death_count,
        (
          COALESCE((
            SELECT COUNT(*) FROM `safe` s2
            WHERE s2.zone_id = z.id AND s2.outbreak_status = 'infected' AND s2.updated_at >= NOW() - INTERVAL 24 HOUR
          ),0)
          + COALESCE(z.death_count,0) 
        ) AS danger_score
      FROM zones z
      ORDER BY danger_score DESC, z.name ASC
    ";
    return $pdo->query($sql)->fetchAll();
}

// active campaign
function m_active_campaigns(PDO $pdo, int $limit = 20, ?int $user_id = null): array {
   
    $limitInt = (int)$limit;
    if ($user_id) {
        $sql = "SELECT c.*, z.name AS zone_name, COALESCE(cr_counts.reg_count,0) AS reg_count, COALESCE(ur.is_registered,0) AS is_registered
          FROM medical_campaign c
          LEFT JOIN zones z ON c.zone_id = z.id
          LEFT JOIN (
            SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
          ) cr_counts ON cr_counts.campaign_id = c.id
          LEFT JOIN (
            SELECT campaign_id, 1 AS is_registered FROM medical_campaign_registrations WHERE user_id = ?
          ) ur ON ur.campaign_id = c.id
          WHERE c.state <> 'done'
          ORDER BY c.created_at DESC
          LIMIT " . $limitInt;
        $st = $pdo->prepare($sql);
        $st->execute([$user_id]);
    } else {
        $sql = "SELECT c.*, z.name AS zone_name, COALESCE(cr_counts.reg_count,0) AS reg_count, 0 AS is_registered
          FROM medical_campaign c
          LEFT JOIN zones z ON c.zone_id = z.id
          LEFT JOIN (
            SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
          ) cr_counts ON cr_counts.campaign_id = c.id
          WHERE c.state <> 'done'
          ORDER BY c.created_at DESC
          LIMIT " . $limitInt;
        $st = $pdo->prepare($sql);
        $st->execute();
    }
    $rows = $st->fetchAll();
    // normalize is_registered to boolean
    foreach ($rows as &$r) {
        $r['is_registered'] = !empty($r['is_registered']) ? true : false;
    }
    return $rows;
}





function m_count_infected_events_last_hours(PDO $pdo, int $hours = 24): int {
  
  $st = $pdo->prepare("SELECT COUNT(*) FROM `safe` WHERE outbreak_status = 'infected' AND updated_at >= NOW() - INTERVAL ? HOUR");
  $st->execute([$hours]);
  return (int)$st->fetchColumn();
}

function m_count_infected_people(PDO $pdo): int {
  $st = $pdo->query("SELECT COUNT(*) FROM `safe` WHERE outbreak_status = 'infected'");
  return (int)$st->fetchColumn();
}






function m_latest_infected_last_hours(PDO $pdo, int $hours = 24, int $limit = 10): array {
  // Return latest distinct infected people based on `safe.updated_at` within the time window
  $sql = "SELECT s.user_id, s.name, s.zone_id, z.name AS zone_name, s.updated_at AS last_at \n"
    . "FROM `safe` s LEFT JOIN zones z ON z.id = s.zone_id \n"
    . "WHERE s.outbreak_status = 'infected' AND s.updated_at >= NOW() - INTERVAL ? HOUR \n"
    . "ORDER BY s.updated_at DESC LIMIT " . (int)$limit;
  $st = $pdo->prepare($sql);
  $st->execute([$hours]);
  $rows = $st->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'reporter_id' => $r['user_id'],
      'name' => $r['name'] ?: ('User '.$r['user_id']),
      'zone_name' => $r['zone_name'],
      'last_at' => $r['last_at']
    ];
  }
  return $out;
}

function m_surge_detector(PDO $pdo, int $days_back = 5): array {
    // Previously this used `infection_events` time-series. That data is no
    // longer relied on at runtime; delegate to the `safe`-based outbreak
    // situation generator which produces a comparable per-zone time series.
    return m_virus_outbreak_situation($pdo, $days_back);
}

  /**
   * Produce a short time-series based outbreak situation per zone using `safe.updated_at`.
   * Returns array of entries with keys: zone_id, time, new, avg_3day, change_vs_yesterday
   */
  function m_virus_outbreak_situation(PDO $pdo, int $days_back = 5): array {
      $sql = "
        SELECT z.id AS zone_id, DATE(s.updated_at) AS d, COUNT(*) AS cnt
        FROM zones z
        LEFT JOIN `safe` s
          ON s.zone_id = z.id
         AND s.outbreak_status = 'infected'
         AND s.updated_at >= CURDATE() - INTERVAL ? DAY
        GROUP BY z.id, DATE(s.updated_at)
        ORDER BY z.id, d DESC
      ";
      $st = $pdo->prepare($sql); $st->execute([$days_back]); $rows = $st->fetchAll();
      $byZone=[]; $today=new DateTimeImmutable('today'); $dates=[];
      for($i=0;$i<$days_back;$i++) $dates[]=$today->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
      foreach($rows as $r){ $z=(int)$r['zone_id']; $d=$r['d']??null; $c=(int)($r['cnt']??0); if(!isset($byZone[$z]))$byZone[$z]=[]; if($d)$byZone[$z][$d]=$c; }
      $out=[];
      foreach($byZone as $z=>$counts){
          $series=[]; foreach($dates as $d){ $series[$d]=$counts[$d]??0; }
          $d0=$dates[0]; $d1=$dates[1]??$d0; $d2=$dates[2]??$d1;
      $new=$series[$d0];
      
      }
      usort($out, fn($a,$b)=>($b['new']<=>$a['new']) ?: ($a['zone_id']<=>$b['zone_id']));
      return array_slice($out,0,8);
  }


function m_check_and_create_zone_alert(PDO $pdo, int $zone_id, int $threshold = 10) {
  if (!$zone_id) return false;
  // Count infected people in the zone updated in the last 24 hours
  $st = $pdo->prepare("SELECT COUNT(*) FROM `safe` WHERE zone_id = ? AND outbreak_status = 'infected' AND updated_at >= NOW() - INTERVAL 24 HOUR");
  $st->execute([$zone_id]); $count = (int)$st->fetchColumn();
  if ($count >= $threshold) {
    // create an alert if none recent
    $chk = $pdo->prepare("SELECT 1 FROM alerts WHERE zone_id = ? AND created_at >= NOW() - INTERVAL 24 HOUR LIMIT 1");
    $chk->execute([$zone_id]);
    if (!$chk->fetch()) {
      $pdo->prepare("INSERT INTO alerts (zone_id,title,status,created_at) VALUES (?,?,?,NOW())")->execute([$zone_id, 'Danger: high infection count', 'open']);
      return true;
    }
  }
  return false;
}


/**
 * Return active medical campaigns with zone name, registration count and whether
 * the supplied user is registered for each campaign.
 *
 * @param PDO $pdo
 * @param int $limit
 * @param int|null $user_id
 * @return array
 */

/**
 * Resolve a user's zone id. Prefer users.zone_id, fall back to safe.zone_id.
 * Returns 0 when none available.
 */
function m_resolve_user_zone(PDO $pdo, ?int $user_id): int {
  if (!$user_id) return 0;
  try {
    $st = $pdo->prepare("SELECT zone_id FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $v = $st->fetchColumn();
    if ($v) return (int)$v;
  } catch (Throwable $e) { /* ignore and fallback */ }
  try {
    $st2 = $pdo->prepare("SELECT zone_id FROM `safe` WHERE user_id = ? LIMIT 1");
    $st2->execute([$user_id]);
    $v2 = $st2->fetchColumn();
    if ($v2) return (int)$v2;
  } catch (Throwable $e) { /* ignore */ }
  return 0;
}


/**
 * Return a simple id/name list of zones for dropdowns.
 */
function m_zones_list(PDO $pdo): array {
  return $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
}


/**
 * Return people list (the "safe" rows) for the People section with filters and pagination.
 * Accepts options: s_name, s_zone, s_health, page, perPage, current_user_id
 * Returns: ['rows'=>..., 'total'=>int, 'page'=>int, 'perPage'=>int, 'totalPages'=>int, 'from'=>int, 'to'=>int, 'currentHealth'=>string]
 */
function m_people_list(PDO $pdo, array $opts = []): array {
  $perPage = isset($opts['perPage']) ? max(1,(int)$opts['perPage']) : 5;
  $pageNum = isset($opts['page']) ? max(1,(int)$opts['page']) : 1;
  $s_name = trim((string)($opts['s_name'] ?? ''));
  $s_zone = isset($opts['s_zone']) && $opts['s_zone'] !== '' ? (int)$opts['s_zone'] : null;
  $s_health = isset($opts['s_health']) ? (string)$opts['s_health'] : '';
  $meId = isset($opts['current_user_id']) ? (int)$opts['current_user_id'] : 0;

  // default behavior: when s_health is not provided and no other filters, default to infected
  if (!isset($opts['s_health'])) {
    if ($s_name === '' && ($s_zone === null)) {
      $currentHealth = 'infected';
      $s_health = $currentHealth;
    } else {
      $currentHealth = '';
    }
  } else {
    $currentHealth = $s_health;
  }

  $where = [];
  $params = [];
  if ($s_name !== '') { $where[] = "s.name LIKE ?"; $params[] = "%".$s_name."%"; }
  if ($s_zone !== null) { $where[] = "z.id = ?"; $params[] = $s_zone; }
  if ($s_health !== '') { $where[] = "s.outbreak_status = ?"; $params[] = $s_health; }

  $baseFrom = " FROM `safe` s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN zones z ON s.zone_id = z.id ";
  $cond = $where ? (" WHERE ".implode(" AND ", $where)) : "";

  $sqlCount = "SELECT COUNT(*)".$baseFrom.$cond;
  $stCount = $pdo->prepare($sqlCount);
  $stCount->execute($params);
  $totalRows = (int)$stCount->fetchColumn();

  if ($currentHealth === 'infected') {
    $perPage = max($perPage, $totalRows);
  }

  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($pageNum > $totalPages) { $pageNum = $totalPages; }
  $offset = ($pageNum - 1) * $perPage;

  if ($currentHealth === 'infected') {
    if ($meId) {
      $orderBy = "(s.user_id = " . $meId . ") DESC, s.updated_at DESC";
    } else {
      $orderBy = "s.updated_at DESC";
    }
  } else {
    if ($meId) {
      $orderBy = "(s.user_id = " . $meId . ") DESC, (s.outbreak_status = 'infected') DESC, s.updated_at DESC";
    } else {
      $orderBy = "(s.outbreak_status = 'infected') DESC, s.updated_at DESC";
    }
  }

  $sqlRows = "SELECT s.id, COALESCE(u.name, s.name) AS name, s.age, s.gender, s.profession, s.skill,
    s.outbreak_status AS outbreak_status, z.name AS zone_name
    ". $baseFrom . $cond . "\n  ORDER BY " . $orderBy . "\n  LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

  try {
    $stRows = $pdo->prepare($sqlRows);
    $stRows->execute($params);
    $rows = $stRows->fetchAll();
  } catch (PDOException $e) {
    // fallback to minimal columns
    $sqlRows = "SELECT s.id, COALESCE(u.name, s.name) AS name, s.age, s.gender,
      s.outbreak_status AS outbreak_status, z.name AS zone_name
      ". $baseFrom . $cond . "\n      ORDER BY s.created_at DESC\n      LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stRows = $pdo->prepare($sqlRows);
    $stRows->execute($params);
    $rows = $stRows->fetchAll();
  }

  $from = $totalRows ? ($offset + 1) : 0;
  $to = min($offset + $perPage, $totalRows);

  return [
    'rows' => $rows,
    'total' => $totalRows,
    'page' => $pageNum,
    'perPage' => $perPage,
    'totalPages' => $totalPages,
    'from' => $from,
    'to' => $to,
    'currentHealth' => $currentHealth,
  ];
}
