<?php
// modules/home/home_viewer.php
// PUBLIC viewer dashboard (no login required)

$zonesCount  = m_count_zones($pdo);
$alerts24h   = m_count_alerts_last_hours($pdo, 24);
$infected24h = m_count_infected_events_last_hours($pdo, 24);
$latestAlert = m_latest_alert($pdo);        // must include 'status' in the SELECT
$zoneRisk    = m_zone_risk_table($pdo);
$surges      = m_surge_detector($pdo);
$logged      = is_logged_in();
?>
<div class="logo"><span class="dot"></span><h1>Zombie Outbreak (Viewer)</h1></div>

<!-- KPI tiles -->
<div class="kpis row" style="gap:10px;margin-bottom:12px;">
  <div class="tile">
    <div class="tile-title">Zones Listed</div>
    <div class="tile-value"><?= (int)$zonesCount ?></div>
  </div>
  <div class="tile">
    <div class="tile-title">Alerts (24h)</div>
    <div class="tile-value"><?= (int)$alerts24h ?></div>
  </div>
  <div class="tile">
    <div class="tile-title">Infection Events (24h)</div>
    <div class="tile-value"><?= (int)$infected24h ?></div>
  </div>
</div>

<!-- Latest active alert banner (open/ack only) -->
<?php if (!empty($latestAlert)): ?>
  <div class="alert warn" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <?php if (($latestAlert['status'] ?? '') === 'open'): ?>
        ⚠️ <b>[Pending]</b>
      <?php elseif (($latestAlert['status'] ?? '') === 'acknowledged'): ?>
        👁️ <b>[ACKNOWLEDGED]</b>
      <?php endif; ?>
      <?= htmlspecialchars($latestAlert['title'] ?? '') ?> —
      <i><?= htmlspecialchars($latestAlert['zone_name'] ?? '') ?></i>
    </div>
    <small class="muted"><?= htmlspecialchars($latestAlert['created_at'] ?? '') ?></small>
  </div>
<?php endif; ?>

<!-- Zone Risk table -->
<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Zone Risk (cases_24h)</h2>
  <table class="table">
    <thead>
      <tr><th>Zone</th><th>Danger</th><th>Cases 24h</th></tr>
    </thead>
    <tbody>
      <?php foreach ($zoneRisk as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['zone_name']) ?></td>
          <td><?= htmlspecialchars($r['danger_score']) ?></td>
          <td><?= (int)$r['cases_24h'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$zoneRisk): ?>
        <tr><td colspan="3"><em>No zones yet.</em></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Surge Detector -->
<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Surge Detector (MA3 ≥ 3 or Δ ≥ 2)</h2>
  <table class="table">
    <thead>
      <tr><th>Zone ID</th><th>Time</th><th>New</th><th>MA3</th><th>Δ</th></tr>
    </thead>
    <tbody>
      <?php foreach ($surges as $s): ?>
        <tr>
          <td><?= (int)$s['zone_id'] ?></td>
          <td><?= htmlspecialchars($s['time']) ?></td>
          <td><?= (int)$s['new'] ?></td>
          <td><?= htmlspecialchars($s['ma3']) ?></td>
          <td><?= (int)$s['delta'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$surges): ?>
        <tr><td colspan="5"><em>No surges detected.</em></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Survivors table (latest 20) -->
<?php
// --- Survivors with filtering + pagination ---

$perPage = 5;
$pageNum = max(1, (int)($_GET['s_p'] ?? 1));
$offset  = ($pageNum - 1) * $perPage;

$where   = [];
$params  = [];
// filters
if (!empty($_GET['s_name']))   { $where[] = "s.name LIKE ?";        $params[] = "%".$_GET['s_name']."%"; }
if (!empty($_GET['s_zone']))   { $where[] = "z.id = ?";              $params[] = (int)$_GET['s_zone']; }
if (!empty($_GET['s_health'])) { $where[] = "s.health_status = ?";   $params[] = $_GET['s_health']; }

// base SELECT pieces
$baseFrom = " FROM survivors s LEFT JOIN zones z ON s.zone_id = z.id ";
$cond     = $where ? (" WHERE ".implode(" AND ", $where)) : "";

// total count (for pagination)
$sqlCount = "SELECT COUNT(*)".$baseFrom.$cond;
$stCount  = $pdo->prepare($sqlCount);
$stCount->execute($params);
$totalRows = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// clamp page if out of range
if ($pageNum > $totalPages) { $pageNum = $totalPages; $offset = ($pageNum - 1) * $perPage; }

// page rows
$sqlRows = "
  SELECT s.id, s.name, s.age, s.gender, s.profession, s.skill,
         s.health_status, z.name AS zone_name
  ".$baseFrom.$cond."
  ORDER BY s.created_at DESC
  LIMIT ".$perPage." OFFSET ".$offset;   // ints are safe (casted above)

$stRows = $pdo->prepare($sqlRows);
$stRows->execute($params);
$survivors = $stRows->fetchAll();

// for zone dropdown
$zonesList = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();

// preserve role param if present
$currentAs = isset($_GET['as']) ? htmlspecialchars($_GET['as']) : '';

// helper to build pagination/filter links
function qstr(array $overrides = []) {
    $q = $_GET;               // start from current GET
    $q['page'] = 'home';      // ensure viewer page
    if (!isset($q['as'])) unset($q['as']);  // we'll add it conditionally below
    foreach ($overrides as $k=>$v) {
        if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    // important: keep only whitelisted keys
    $allowed = ['page','as','s_name','s_zone','s_health','s_p'];
    $filtered = [];
    foreach ($allowed as $k) if (isset($q[$k])) $filtered[$k] = $q[$k];
    $qs = http_build_query($filtered);
    return '/Deadlock-Rise-of-the-Zombies/public/'.($qs ? ('?'.$qs) : '');
}

// compute range text
$from = $totalRows ? ($offset + 1) : 0;
$to   = min($offset + $perPage, $totalRows);
?>
<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Survivors</h2>

  <!-- search/filter form -->
  <form method="get" action="/Deadlock-Rise-of-the-Zombies/public/" class="row"
        style="gap:8px;flex-wrap:wrap;margin-bottom:10px;">
    <input type="hidden" name="page" value="home">
    <?php if ($currentAs !== ''): ?>
      <input type="hidden" name="as" value="<?= $currentAs ?>">
    <?php endif; ?>
    <!-- when filtering, reset to first page -->
    <input type="hidden" name="s_p" value="1">

    <input name="s_name" value="<?= htmlspecialchars($_GET['s_name'] ?? '') ?>" placeholder="Search name...">

    <select name="s_zone">
      <option value="">All Zones</option>
      <?php foreach ($zonesList as $z): ?>
        <option value="<?= (int)$z['id'] ?>" <?= ((string)$z['id'] === (string)($_GET['s_zone'] ?? '')) ? 'selected' : '' ?>>
          <?= htmlspecialchars($z['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="s_health">
      <option value="">All Health</option>
      <option value="healthy"  <?= (($_GET['s_health'] ?? '')==='healthy')  ? 'selected' : '' ?>>Healthy</option>
      <option value="infected" <?= (($_GET['s_health'] ?? '')==='infected') ? 'selected' : '' ?>>Infected</option>
      <option value="critical" <?= (($_GET['s_health'] ?? '')==='critical') ? 'selected' : '' ?>>Critical</option>
      <option value="turned"   <?= (($_GET['s_health'] ?? '')==='turned')   ? 'selected' : '' ?>>Turned</option>
    </select>

    <button class="btn" type="submit">Filter</button>
    <a class="btn alt" href="<?= qstr(['as'=>$currentAs ?: null, 's_name'=>null, 's_zone'=>null, 's_health'=>null, 's_p'=>null]) ?>">Reset</a>
  </form>

  <!-- results & pager -->
  <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px;">
    <small class="muted">
      Showing <?= number_format($from) ?>–<?= number_format($to) ?> of <?= number_format($totalRows) ?>
    </small>
    <div class="row" style="gap:8px;">
      <?php if ($pageNum > 1): ?>
        <a class="btn alt" href="<?= qstr(['as'=>$currentAs ?: null, 's_p'=>$pageNum-1]) ?>">Prev</a>
      <?php else: ?>
        <button class="btn alt" disabled>Prev</button>
      <?php endif; ?>

      <?php if ($pageNum < $totalPages): ?>
        <a class="btn" href="<?= qstr(['as'=>$currentAs ?: null, 's_p'=>$pageNum+1]) ?>">Next</a>
      <?php else: ?>
        <button class="btn" disabled>Next</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- survivors table -->
  <table class="table">
    <thead>
      <tr>
        <th>Name</th><th>Age</th><th>Gender</th>
        <th>Profession</th><th>Skill</th>
        <th>Health</th><th>Zone</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($survivors as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['age']) ?></td>
          <td><?= htmlspecialchars($s['gender']) ?></td>
          <td><?= htmlspecialchars($s['profession']) ?></td>
          <td><?= htmlspecialchars($s['skill']) ?></td>
          <td><?= htmlspecialchars($s['health_status']) ?></td>
          <td><?= htmlspecialchars($s['zone_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$survivors): ?>
        <tr><td colspan="7"><em>No survivors match filters.</em></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- bottom pager duplicate (optional) -->
  <div class="row" style="justify-content:flex-end;gap:8px;margin-top:8px;">
    <?php if ($pageNum > 1): ?>
      <a class="btn alt" href="<?= qstr(['as'=>$currentAs ?: null, 's_p'=>$pageNum-1]) ?>">Prev</a>
    <?php else: ?>
      <button class="btn alt" disabled>Prev</button>
    <?php endif; ?>

    <?php if ($pageNum < $totalPages): ?>
      <a class="btn" href="<?= qstr(['as'=>$currentAs ?: null, 's_p'=>$pageNum+1]) ?>">Next</a>
    <?php else: ?>
      <button class="btn" disabled>Next</button>
    <?php endif; ?>
  </div>
</div>

<hr>
<?php if (!$logged): ?>
  <div class="row" style="gap:10px;">
    <a class="btn" href="?page=login">Login</a>
    <a class="btn alt" href="?page=signup">Sign up</a>
  </div>
<?php else: ?>
  <div class="row" style="gap:10px;">
    <a class="btn alt" href="?page=role_select">Switch Role</a>
    <a class="btn" href="?page=logout">Logout</a>
  </div>
<?php endif; ?>
