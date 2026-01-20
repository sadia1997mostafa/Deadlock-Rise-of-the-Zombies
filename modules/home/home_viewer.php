<?php

// top-level POST tracer (keeps the original lightweight debug behavior)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $sess = [];
    if (function_exists('session_id')) $sess['id'] = session_id();
    if (isset($_SESSION) && is_array($_SESSION)) $sess['keys'] = array_keys($_SESSION);
    $line = '['.date('Y-m-d H:i:s').'] POST top: remote='.(
      $_SERVER['REMOTE_ADDR'] ?? 'cli')." method=".$_SERVER['REQUEST_METHOD'].' sess='.json_encode($sess).' POST_keys='.json_encode(array_keys($_POST))."\n";
    @file_put_contents(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'debug_home_viewer.log', $line, FILE_APPEND);
  } catch (Throwable $e) { /* ignore */ }
}

$zonesCount  = m_count_zones($pdo);
$alerts24h   = m_count_alerts_last_hours($pdo, 24);
$infected24h = m_count_infected_events_last_hours($pdo, 24);
$infected_people = m_count_infected_people($pdo);
// new metrics
$total_ever_infected = m_total_ever_infected($pdo);
$unique_reporters_24h = m_unique_reporters_last_hours($pdo, 24);
$latest_infected_24h = m_latest_infected_last_hours($pdo, 24, 10);
$latestAlert = m_latest_alert($pdo);
$zoneRisk    = m_zone_risk_table($pdo);
$logged      = is_logged_in();

// detect if any zone exceeds danger threshold
$dangerFound = false;
foreach ($zoneRisk as $zr) { if ((int)($zr['cases_24h'] ?? 0) >= DANGER_ALERT_THRESHOLD) { $dangerFound = true; break; } }

// All interactive writes are handled via a central action now. Keep this
// view read-only by default so rendering is simpler and less error-prone.
?>
<?php
// Prepare people list options and fetch via metrics helper
$peopleOpts = [
  's_name' => $_GET['s_name'] ?? '',
  's_zone' => $_GET['s_zone'] ?? '',
  // pass null when key absent so metrics helper can apply default logic
  's_health' => array_key_exists('s_health', $_GET) ? ($_GET['s_health'] ?? '') : null,
  'page' => max(1, (int)($_GET['s_p'] ?? 1)),
  'perPage' => 5,
  'current_user_id' => ($logged && function_exists('current_user_id')) ? current_user_id() : 0,
];

$peopleResult = function_exists('m_people_list') ? m_people_list($pdo, $peopleOpts) : ['rows'=>[], 'total'=>0, 'page'=>1, 'perPage'=>5, 'totalPages'=>1, 'from'=>0, 'to'=>0, 'currentHealth'=>''];

$survivors = $peopleResult['rows'];
$totalRows = $peopleResult['total'];
$pageNum = $peopleResult['page'];
$perPage = $peopleResult['perPage'];
$totalPages = $peopleResult['totalPages'];
$from = $peopleResult['from'];
$to = $peopleResult['to'];
$currentHealth = $peopleResult['currentHealth'];

$zonesList = function_exists('m_zones_list') ? m_zones_list($pdo) : $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
?>
  <div class="tile">
    <div class="tile-title">Zones Listed</div>
    <div class="tile-value"><?= (int)$zonesCount ?></div>
  </div>
  <div class="tile">
    <div class="tile-title">Alerts (24h)</div>
    <div class="tile-value"><?= (int)$alerts24h ?></div>
  </div>
  <div class="tile">
    <div class="tile-title">Infected People</div>
    <div class="tile-value"><?= (int)$total_ever_infected ?></div>
  </div>
  <div class="tile">
    <div class="tile-title">Infection Events (24h)</div>
    <div class="tile-value"><?= (int)$unique_reporters_24h ?></div>
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

<?php if ($dangerFound): ?>
  <div class="danger-banner">
    <div>
      <b>High risk detected</b> — One or more zones have recent case counts above the safe threshold.
    </div>
    <div>
      <span class="danger-badge">Take precautions</span>
    </div>
  </div>
<?php endif; ?>



<!-- Zone Risk table -->
<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Zone Risk (cases_24h)</h2>
  <table class="table limit-3" data-section="zoneRisk">
    <thead>
      <tr><th>Zone</th><th>Danger</th><th>Deaths</th><th>Cases 24h</th></tr>
    </thead>
    <tbody>
      <?php foreach ($zoneRisk as $i => $r): ?>
  <?php $isDanger = ((int)$r['cases_24h'] >= (int)DANGER_ALERT_THRESHOLD); ?>
  <tr class="<?= ($isDanger ? 'danger-row' : '') . ($i >= 3 ? ' collapsed-row' : '') ?>" <?= $i >= 3 ? 'style="display:none"' : '' ?>>
          <td><?= htmlspecialchars($r['zone_name']) ?> <?= $isDanger ? '<span class="danger-badge">High</span>' : '' ?></td>
          <td><?= htmlspecialchars($r['danger_score']) ?></td>
          <td><?= (int)($r['death_count'] ?? 0) ?></td>
          <td><?= (int)$r['cases_24h'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$zoneRisk): ?>
        <tr><td colspan="4"><em>No zones yet.</em></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>



<!-- Active medical campaigns (user can register) -->
<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Active Medical Campaigns</h2>
  <?php
    
    $myZone = null;
    if ($logged) {
      
      $myZone = 0;
      $uid = current_user_id();
      if ($uid) {
        $st = $pdo->prepare("SELECT zone_id FROM `safe` WHERE user_id = ? LIMIT 1");
        if ($st && $st->execute([$uid])) {
          $val = $st->fetchColumn();
          $myZone = ($val !== false && $val !== null) ? (int)$val : 0;
        }
      }
    }
    
    // Fetch campaigns from metrics helper (provides zone name, reg_count and
    // whether current user is registered). This centralizes the logic in
    // lib/metrics.php similar to m_zone_risk_table().
    $campaignsList = function_exists('m_active_campaigns')
        ? m_active_campaigns($pdo, 20, ($logged ? current_user_id() : null))
        : [];
  ?>
  <table class="table limit-3" data-section="campaigns">
    <thead><tr><th>Title</th><th>Zone</th><th>State</th><th>Capacity</th><th>Registrations</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($campaignsList as $i => $c): ?>
  <tr class="<?= $i >= 3 ? 'collapsed-row' : '' ?>" <?= $i >= 3 ? 'style="display:none"' : '' ?>>
          <td><?= htmlspecialchars($c['title']) ?></td>
          <td><?= htmlspecialchars(!empty($c['zone_name']) ? $c['zone_name'] : 'Any') ?></td>
          <td><?= htmlspecialchars($c['state']) ?></td>
          <td><?= htmlspecialchars($c['capacity'] ?? 'unlimited') ?></td>
          <td><?= (int)$c['reg_count'] ?></td>
          <td>
            <a class="btn" href="?page=campaign_view&id=<?= (int)$c['id'] ?>">View</a>
            <?php if ($logged): ?>
              <form method="post" action="?page=campaign_view&id=<?= (int)$c['id'] ?>" style="display:inline-block;margin-left:6px;">
                <?php $isReg = !empty($c['is_registered']); ?>
                <?php if (!$isReg): ?>
                  <button class="btn" name="register">Register</button>
                <?php else: ?>
                  <button class="btn alt" name="cancel">Cancel</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
  <?php if (!$campaignsList): ?><tr><td colspan="6"><em>No active medical campaigns.</em></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Per-list collapsing: each table will show 3 rows with its own See more toggle -->
  
  


  <!-- medical equipment section -->

<div class="card-like" style="margin-top:12px;">
  <h2 style="margin:0 0 8px">Medical Equipment</h2>
  <?php $vrows = $pdo->query("SELECT id,name,stock,description FROM medical_equipments ORDER BY stock DESC LIMIT 20")->fetchAll(); ?>
  <table class="table limit-3" data-section="medical_equipments">
    <thead><tr><th>Name</th><th>Stock</th><th>Description</th></tr></thead>
    <tbody>
      <?php foreach ($vrows as $i => $v): ?>
        <tr class="<?= $i >= 3 ? 'collapsed-row' : '' ?>" <?= $i >= 3 ? 'style="display:none"' : '' ?> >
          <td><?= htmlspecialchars($v['name']) ?></td>
          <td><?= (int)$v['stock'] ?></td>
          <td><?= htmlspecialchars(substr($v['description'] ?? '',0,80)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$vrows): ?><tr><td colspan="3"><em>No medical equipment configured.</em></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>


<?php


$perPage = 5;
$pageNum = max(1, (int)($_GET['s_p'] ?? 1));
$offset  = ($pageNum - 1) * $perPage;


$where   = [];
$params  = [];

$hasName = isset($_GET['s_name']) && strlen(trim((string)($_GET['s_name'] ?? '')))>0;
$hasZone = isset($_GET['s_zone']) && strlen(trim((string)($_GET['s_zone'] ?? '')))>0;
if (!isset($_GET['s_health'])) {
  if (!$hasName && !$hasZone) {
    
    $currentHealth = 'infected';
    $_GET['s_health'] = $currentHealth;
  } else {
    
    $currentHealth = '';
  }
} else {
  $currentHealth = $_GET['s_health'];
}
// filters
if (!empty($_GET['s_name']))   { $where[] = "s.name LIKE ?";        $params[] = "%".$_GET['s_name']."%"; }
if (!empty($_GET['s_zone']))   { $where[] = "z.id = ?";              $params[] = (int)$_GET['s_zone']; }
if (!empty($_GET['s_health'])) { $where[] = "s.outbreak_status = ?";   $params[] = $_GET['s_health']; }

// base SELECT pieces
$baseFrom = " FROM `safe` s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN zones z ON s.zone_id = z.id ";
$cond     = $where ? (" WHERE ".implode(" AND ", $where)) : "";


$sqlCount = "SELECT COUNT(*)".$baseFrom.$cond;
$stCount  = $pdo->prepare($sqlCount);
$stCount->execute($params);
$totalRows = (int)$stCount->fetchColumn();


if (($currentHealth ?? '') === 'infected') {
  $perPage = max($perPage, $totalRows);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// clamp page if out of range
if ($pageNum > $totalPages) { $pageNum = $totalPages; $offset = ($pageNum - 1) * $perPage; }

// page rows

$meId = function_exists('current_user_id') && is_logged_in() ? (int)current_user_id() : 0;
if (($currentHealth ?? '') === 'infected') {
  if ($meId) {
    $orderBy = "(s.user_id = " . $meId . ") DESC, s.updated_at DESC";
  } else {
    $orderBy = "s.updated_at DESC";
  }
} else {
  // show current user first, then infected, then others by recent update
  if ($meId) {
    $orderBy = "(s.user_id = " . $meId . ") DESC, (s.outbreak_status = 'infected') DESC, s.updated_at DESC";
  } else {
    $orderBy = "(s.outbreak_status = 'infected') DESC, s.updated_at DESC";
  }
}

 $sqlRows = "
  SELECT s.id, COALESCE(u.name, s.name) AS name, s.age, s.gender, s.profession, s.skill,
    s.outbreak_status AS outbreak_status, z.name AS zone_name
  ".$baseFrom.$cond."
  ORDER BY " . $orderBy . "
  LIMIT ".$perPage." OFFSET ".$offset;   // ints are safe (casted above)

  
  try {
    $stRows = $pdo->prepare($sqlRows);
    $stRows->execute($params);
    $survivors = $stRows->fetchAll();
  } catch (PDOException $e) {
    // Retry with minimal column list
    $sqlRows = "
      SELECT s.id, COALESCE(u.name, s.name) AS name, s.age, s.gender,
        s.outbreak_status AS outbreak_status, z.name AS zone_name
      ".$baseFrom.$cond."
      ORDER BY s.created_at DESC
      LIMIT ".$perPage." OFFSET ".$offset;
    $stRows = $pdo->prepare($sqlRows);
    $stRows->execute($params);
    $survivors = $stRows->fetchAll();
  }

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
  <?php if ($logged): ?>
    <?php
      // resolve user's zone via metrics helper
      $myZoneId = (function_exists('m_resolve_user_zone') && function_exists('current_user_id')) ? m_resolve_user_zone($pdo, current_user_id()) : 0;
    ?>
  <form id="selfStatusForm" method="post" action="?page=self_status_action" style="margin-bottom:8px;display:flex;gap:8px;align-items:center;">
      <div style="display:flex;gap:8px;align-items:center;">
  <button type="submit" class="btn" name="mark_safe">Mark myself safe</button>
  <button type="submit" class="btn warn" name="mark_infected">Mark myself infected</button>
      </div>
      <div style="margin-left:12px;"> 
        <label style="font-size:0.9em;margin-right:6px;">My zone</label>
        <select name="zone_id">
          <option value="">-- Select zone --</option>
          <?php foreach ($zonesList as $z): ?>
            <option value="<?= (int)$z['id'] ?>" <?= ((int)$z['id'] === $myZoneId) ? 'selected' : '' ?>><?= htmlspecialchars($z['name']) ?></option>
          <?php endforeach; ?>
        </select>
          <button type="submit" class="btn alt" name="set_zone" style="margin-left:6px;">Save zone</button>
          <!-- Quick request actions for logged-in users -->
          <a class="btn" href="?page=ambulance_request" style="margin-left:8px;">Request Ambulance</a>
          <a class="btn alt" href="?page=icu_request" style="margin-left:6px;">Request ICU</a>
      </div>
    </form>
  <?php endif; ?>

  <h2 style="margin:0 0 8px">People (Health Status)</h2>

  <!-- search/filter form -->
    <form id="peopleSearchForm" method="get" action="?page=home" class="row"
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
    <option value="safe"  <?= (($_GET['s_health'] ?? '')==='safe')  ? 'selected' : '' ?>>Safe</option>
      <option value="infected" <?= (($_GET['s_health'] ?? '')==='infected') ? 'selected' : '' ?>>Infected</option>
      <option value="critical" <?= (($_GET['s_health'] ?? '')==='critical') ? 'selected' : '' ?>>Critical</option>
      <option value="recovered" <?= (($_GET['s_health'] ?? '')==='recovered') ? 'selected' : '' ?>>Recovered</option>
      <option value="deceased" <?= (($_GET['s_health'] ?? '')==='deceased') ? 'selected' : '' ?>>Deceased</option>
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

  <!-- people table -->
    <div id="peopleSection">
      <table class="table limit-3" data-section="people">
    <thead>
      <tr>
        <th>Name</th><th>Age</th><th>Gender</th>
        <th>Profession</th><th>Skill</th>
        <th>Health</th><th>Zone</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($survivors as $i => $s): ?>
        <?php $status = $s['outbreak_status'] ?? ($s['health_status'] ?? 'safe');
              $dangerRow = in_array($status, ['infected','critical']); ?>
        <tr class="<?= ($dangerRow ? 'danger-row' : '') . ($i >= 3 ? ' collapsed-row' : '') ?>" <?= $i >= 3 ? 'style="display:none"' : '' ?>>
          <td>
            <?= htmlspecialchars($s['name']) ?>
            <?php if ($dangerRow): ?> <span class="danger-badge"><?= htmlspecialchars(strtoupper($status)) ?></span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($s['age']) ?></td>
          <td><?= htmlspecialchars($s['gender']) ?></td>
          <td><?= htmlspecialchars($s['profession'] ?? '') ?></td>
          <td><?= htmlspecialchars($s['skill'] ?? '') ?></td>
          <td><?= htmlspecialchars($status) ?></td>
          <td><?= htmlspecialchars($s['zone_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$survivors): ?>
  <tr><td colspan="7"><em>No people match filters.</em></td></tr>
      <?php endif; ?>
    </tbody>
    </table>
  </div>

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

  <script>
    (function(){
      // Initialize per-table limit behavior. Accepts an optional root to scope
      // initialization (used after AJAX replacement).
      function initLimit3(root){
        root = root || document;
        var tables = root.querySelectorAll('table.limit-3');
        tables.forEach(function(table){
          // avoid double-initializing tables
          if (table.__limit3_init) return; table.__limit3_init = true;
          var hiddenRows = table.querySelectorAll('tbody tr.collapsed-row');
          if (!hiddenRows || hiddenRows.length === 0) return;
          var btn = document.createElement('button');
          btn.className = 'btn see-more';
          btn.type = 'button';
          btn.setAttribute('aria-expanded','false');
          btn.textContent = 'See more (' + hiddenRows.length + ')';
          table.parentNode.insertBefore(btn, table.nextSibling);
          btn.addEventListener('click', function(){
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (!expanded) {
              hiddenRows.forEach(function(r){ r.style.display = ''; });
              btn.textContent = 'Show less';
              btn.setAttribute('aria-expanded','true');
              setTimeout(function(){ table.scrollIntoView({behavior:'smooth'}); }, 50);
            } else {
              hiddenRows.forEach(function(r){ r.style.display = 'none'; });
              btn.textContent = 'See more (' + hiddenRows.length + ')';
              btn.setAttribute('aria-expanded','false');
              setTimeout(function(){ table.scrollIntoView({behavior:'smooth'}); }, 50);
            }
          });
        });
      }

      // Run on initial load
      initLimit3(document);

  // AJAX-search: intercept the people search form and update only the people section
      var searchForm = document.getElementById('peopleSearchForm');
      if (searchForm) {
        searchForm.addEventListener('submit', function(ev){
          ev.preventDefault();
          var params = new URLSearchParams(new FormData(searchForm)).toString();
          var url = (searchForm.getAttribute('action') || '?') + (params ? ('&' + params) : '');
          // If action already contains '?', avoid doubling
          if (url.indexOf('?') === -1) url = '?' + url;
          fetch(url, {credentials: 'same-origin'})
            .then(function(r){ return r.text(); })
            .then(function(html){
              var parser = new DOMParser();
              var doc = parser.parseFromString(html, 'text/html');
              var newSection = doc.getElementById('peopleSection');
              if (newSection) {
                var dest = document.getElementById('peopleSection');
                dest.innerHTML = newSection.innerHTML;
                // re-run limit3 init only within the replaced section
                initLimit3(dest);
                // update browser URL for shareability
                try { history.replaceState(null, '', url); } catch(e){}
              } else {
                // fallback: reload if replacement failed
                window.location.href = url;
              }
            }).catch(function(){ window.location.href = url; });
        });
      }

      // Intercept the self-status form (mark safe/infected) to update the People list in-place
      var selfForm = document.getElementById('selfStatusForm');
      if (selfForm) {
        // central handler that performs POST then refreshes the people section via a GET
        function submitSelfForm(clickedName){
          try {
            var fd = new FormData(selfForm);
            if (clickedName) fd.append(clickedName, '1');
            var action = selfForm.getAttribute('action') || '?page=home';
            // POST first to apply the change
            return fetch(action, { method: 'POST', body: fd, credentials: 'same-origin' })
              .then(function(resp){
                // ignore response body; now GET the viewer page and extract updated sections
                return fetch('?page=home', { credentials: 'same-origin' });
              })
              .then(function(r){ return r.text(); })
              .then(function(html){
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newPeople = doc.getElementById('peopleSection');
                var destPeople = document.getElementById('peopleSection');
                if (newPeople && destPeople) destPeople.innerHTML = newPeople.innerHTML;
                var newKpis = doc.querySelector('.kpis');
                var destKpis = document.querySelector('.kpis');
                if (newKpis && destKpis) destKpis.innerHTML = newKpis.innerHTML;
                initLimit3(destPeople);
              });
          } catch (e) { return Promise.reject(e); }
        }

        // handle button clicks (covers mouse/touch)
        selfForm.addEventListener('click', function(ev){
          var t = ev.target;
          if (!t || t.tagName !== 'BUTTON') return;
          var name = t.getAttribute('name');
          if (!name) return;
          ev.preventDefault();
          submitSelfForm(name).catch(function(){ window.location.reload(); });
        });

        // Note: we intentionally do not intercept the form submit event here.
        // Button clicks are handled above; allowing native submits provides a
        // reliable fallback if JavaScript errors occur elsewhere.
      }
    })();
  </script>

  <hr>
<?php if (!$logged): ?>
  <div class="row" style="gap:10px;">
    <a class="btn" href="?page=login">Login</a>
    <a class="btn alt" href="?page=signup">Sign up</a>
  </div>
<?php else: ?>
  <div class="row" style="gap:10px;">
          <a class="btn alt" href="?page=home">Switch Role</a>
    <a class="btn" href="?page=user_panel">User panel</a>
    <a class="btn" href="?page=logout">Logout</a>
  </div>
<?php endif; ?>
