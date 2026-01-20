<?php
// modules/user/sql_runner.php
// User-facing SQL runner: discovers safe functions in lib/user_sql_functions.php

require_once __DIR__ . '/../../config/auth.php';
require_login(); // must be logged in
require_once __DIR__ . '/../../lib/user_sql_functions.php';

$queries = [];
$srcFile = realpath(__DIR__ . '/../../lib/user_sql_functions.php');
$src = file_exists($srcFile) ? file_get_contents($srcFile) : '';
if ($src) {
  if (preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $src, $m)) {
    $fnames = $m[1];
    foreach ($fnames as $fn) {
      if (!function_exists($fn)) continue;
      try {
        $ref = new ReflectionFunction($fn);
        $params = array_map(function($p){ return $p->getName(); }, $ref->getParameters());
        if (count($params) && strtolower($params[0]) === 'pdo') array_shift($params);
        $label = ucwords(str_replace(['_','-'], ' ', $fn));
        $queries[$label] = [$fn, $params];
      } catch (ReflectionException $e) {
        // ignore
      }
    }
  }
}

$result = null; $error = null;
// helper to execute a function by name with request data (array)
$execute_from = function(array $data) use (&$queries, $pdo, &$result, &$error) {
  $fn = $data['fn'] ?? null;
  $allowed = null;
  foreach ($queries as $label => $meta) { if ($meta[0] === $fn) { $allowed = $meta; break; } }
  if (! $allowed) { $error = 'Function not allowed'; return; }
  try {
    $params = [];
    $pnames = $allowed[1];
      foreach ($pnames as $pn) {
      $pn_l = strtolower($pn);
      // treat *_ids as arrays, *_id as single int
      if (preg_match('/(_ids$|^ids$)/', $pn_l) || $pn_l === 'zone_ids') {
        $raw = $data[$pn] ?? ($data['zone_ids'] ?? '');
        if (is_array($raw)) {
          $ids = array_values(array_map('intval', $raw));
        } else {
          $raw = (string)$raw;
          $ids = array_filter(array_map('intval', array_map('trim', explode(',', $raw))));
        }
        $params[] = $ids;
      } elseif (preg_match('/(_id$|^id$)/', $pn_l) || $pn_l === 'zone_id') {
        $val = $data[$pn] ?? ($data['zone_id'] ?? null);
        if (is_array($val)) $val = reset($val);
        $params[] = $val === null || $val === '' ? null : (int)$val;
      } elseif (strpos($pn_l, 'threshold') !== false || strpos($pn_l, 'thr') !== false) {
        $params[] = isset($data[$pn]) ? (float)$data[$pn] : (isset($data['threshold']) ? (float)$data['threshold'] : 0.01);
      } elseif (strpos($pn_l, 'since') !== false || strpos($pn_l, 'days') !== false) {
        $params[] = isset($data[$pn]) ? (int)$data[$pn] : (isset($data['since_days']) ? (int)$data['since_days'] : 30);
      } elseif ($pn_l === 'limit' || strpos($pn_l, 'limit') !== false) {
        $params[] = isset($data[$pn]) ? (int)$data[$pn] : 200;
      } elseif (preg_match('/page[_-]?size|pageSize/i', $pn_l) || $pn_l === 'page_size') {
        // default page_size when not provided
        $params[] = isset($data[$pn]) && $data[$pn] !== '' ? (int)$data[$pn] : 20;
      } else {
        $val = $data[$pn] ?? null;
        // avoid collision with router 'page' query parameter (e.g. ?page=user_sql_runner)
        if ($pn_l === 'page' && is_string($val) && $val === ($data['page'] ?? null)) {
          // router indicator present; treat as missing and use default page value
          $val = null;
        }
        // if this parameter is a page number and value is missing, provide default 1
        if ($pn_l === 'page') {
          $params[] = ($val === null || $val === '') ? 1 : (int)$val;
        } else {
          $params[] = $val;
        }
      }
    }
    $result = call_user_func_array($fn, array_merge([$pdo], $params));
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
};

// support POST (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fn'])) {
  $fn = $_POST['fn'];
  $allowed = null;
  foreach ($queries as $label => $meta) { if ($meta[0] === $fn) { $allowed = $meta; break; } }
  if (! $allowed) {
    $error = 'Function not allowed';
  } else {
    try {
      $params = [];
      $pnames = $allowed[1];
      foreach ($pnames as $pn) {
        $pn_l = strtolower($pn);
        if (preg_match('/(_ids$|^ids$)/', $pn_l) || $pn_l === 'zone_ids') {
          $raw = $_POST[$pn] ?? ($_POST['zone_ids'] ?? '');
          if (is_array($raw)) {
            $ids = array_values(array_map('intval', $raw));
          } else {
            $ids = array_filter(array_map('intval', array_map('trim', explode(',', (string)$raw))));
          }
          $params[] = $ids;
        } elseif (preg_match('/(_id$|^id$)/', $pn_l) || $pn_l === 'zone_id') {
          $val = $_POST[$pn] ?? ($_POST['zone_id'] ?? null);
          if (is_array($val)) $val = reset($val);
          $params[] = $val === null || $val === '' ? null : (int)$val;
        } elseif (strpos($pn_l, 'threshold') !== false || strpos($pn_l, 'thr') !== false) {
          $params[] = isset($_POST[$pn]) ? (float)$_POST[$pn] : (isset($_POST['threshold']) ? (float)$_POST['threshold'] : 0.01);
        } elseif (strpos($pn_l, 'since') !== false || strpos($pn_l, 'days') !== false) {
          $params[] = isset($_POST[$pn]) ? (int)$_POST[$pn] : (isset($_POST['since_days']) ? (int)$_POST['since_days'] : 30);
        } elseif ($pn_l === 'limit' || strpos($pn_l, 'limit') !== false) {
          $params[] = isset($_POST[$pn]) ? (int)$_POST[$pn] : 200;
        } elseif (preg_match('/page[_-]?size|pageSize/i', $pn_l) || $pn_l === 'page_size') {
          $val = $_POST[$pn] ?? null;
          $params[] = ($val === null || $val === '') ? 20 : (int)$val;
        } else {
          $val = $_POST[$pn] ?? null;
          if ($pn_l === 'page') {
            $params[] = ($val === null || $val === '') ? 1 : (int)$val;
          } else {
            $params[] = $val;
          }
        }
      }
      $result = call_user_func_array($fn, array_merge([$pdo], $params));
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }
}

// support GET quick-run: ?fn=function_name and optional params
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['fn'])) {
  $execute_from($_GET);
}

?>
<h2>User SQL Runner</h2>
<p>Interactive user queries (read-only helpers and safe actions). Only available to logged-in users.</p>

<div style="display:flex;gap:16px;align-items:flex-start;">
  <div style="width:360px;">
    <?php
    // fetch lookup lists once per page for dropdowns
      $all_zones = [];
      try {
        $zt = $pdo->query("SELECT id, name FROM zones ORDER BY name");
        $zrows = $zt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($zrows as $zr) $all_zones[$zr['id']] = $zr['name'];
      } catch (Throwable $e) { $all_zones = []; }
      $all_tables = [];
      try {
        $tst = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
        $all_tables = $tst->fetchAll(PDO::FETCH_COLUMN);
      } catch (Throwable $e) { $all_tables = []; }

      // campaigns for campaign_id dropdowns
      $all_campaigns = [];
      try {
        $ct = $pdo->query("SELECT id, title FROM medical_campaign ORDER BY title");
        $crows = $ct->fetchAll(PDO::FETCH_ASSOC);
        foreach ($crows as $cr) $all_campaigns[$cr['id']] = $cr['title'];
      } catch (Throwable $e) { $all_campaigns = []; }

      // equipments for equipment_id dropdowns
      $all_equipments = [];
      try {
        $et = $pdo->query("SELECT id, name FROM medical_equipments ORDER BY name");
        $erows = $et->fetchAll(PDO::FETCH_ASSOC);
        foreach ($erows as $er) $all_equipments[$er['id']] = $er['name'];
      } catch (Throwable $e) { $all_equipments = []; }

      // users for user_id dropdowns (name + email)
      $all_users = [];
      try {
        $ut = $pdo->query("SELECT id, COALESCE(name,email) AS display FROM users ORDER BY display");
        $urows = $ut->fetchAll(PDO::FETCH_ASSOC);
        foreach ($urows as $ur) $all_users[$ur['id']] = $ur['display'];
      } catch (Throwable $e) { $all_users = []; }
    ?>
    <div class="list">
    <?php foreach ($queries as $label => $meta): $fname = $meta[0]; $params = $meta[1]; ?>
      <div class="item card-like" style="padding:12px;margin-bottom:10px;">
        <div style="font-weight:700;margin-bottom:6px;<?= "" ?>"><?= htmlspecialchars($label) ?></div>
        <form method="post" style="margin:0;">
          <input type="hidden" name="fn" value="<?= htmlspecialchars($fname) ?>">
          <?php foreach ($params as $pn):
                  $pn_l = strtolower($pn);
          ?>
            <?php if ($pn_l === 'tables'): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                <select name="<?= htmlspecialchars($pn) ?>[]" multiple style="width:100%;min-height:80px;">
                  <?php foreach ($all_tables as $tn): ?>
                    <option value="<?= htmlspecialchars($tn) ?>"><?= htmlspecialchars($tn) ?></option>
                  <?php endforeach; ?>
                </select>
              </label></div>
            <?php elseif (substr($pn_l, -4) === '_ids' || $pn_l === 'ids'): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?> (select one or more):<br>
                <select name="<?= htmlspecialchars($pn) ?>[]" multiple style="width:100%;min-height:120px;">
                  <?php if (strpos($pn_l,'campaign') !== false): ?>
                    <?php foreach ($all_campaigns as $id => $title): ?>
                      <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id . ' — ' . $title) ?></option>
                    <?php endforeach; ?>
                  <?php elseif (strpos($pn_l,'equipment') !== false || strpos($pn_l,'equip') !== false): ?>
                    <?php foreach ($all_equipments as $id => $title): ?>
                      <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($id . ' — ' . $title) ?></option>
                    <?php endforeach; ?>
                  <?php elseif (strpos($pn_l,'zone') !== false): ?>
                    <?php foreach ($all_zones as $zid => $zname): ?>
                      <option value="<?= htmlspecialchars($zid) ?>"><?= htmlspecialchars($zid . ' — ' . $zname) ?></option>
                    <?php endforeach; ?>
                  <?php elseif (strpos($pn_l,'user') !== false): ?>
                    <?php foreach ($all_users as $uid => $uname): ?>
                      <option value="<?= htmlspecialchars($uid) ?>"><?= htmlspecialchars($uid . ' — ' . $uname) ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="">(no lookup)</option>
                  <?php endif; ?>
                </select>
              </label></div>
            <?php elseif ($pn_l === 'zone_id'): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                <select name="<?= htmlspecialchars($pn) ?>" style="width:100%;">
                  <option value="">(any)</option>
                  <?php foreach ($all_zones as $zid => $zname): ?>
                    <option value="<?= htmlspecialchars($zid) ?>"><?= htmlspecialchars($zid . ' — ' . $zname) ?></option>
                  <?php endforeach; ?>
                </select>
              </label></div>
            <?php elseif ($pn_l === 'campaign_id'): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                <select name="<?= htmlspecialchars($pn) ?>" style="width:100%;">
                  <option value="">(choose campaign)</option>
                  <?php foreach ($all_campaigns as $id => $title): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($title . ' (' . $id . ')') ?></option>
                  <?php endforeach; ?>
                </select>
              </label></div>
            <?php elseif ($pn_l === 'equipment_id' || (strpos($pn_l,'equipment') !== false && substr($pn_l,-3)==='id')): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                <select name="<?= htmlspecialchars($pn) ?>" style="width:100%;">
                  <option value="">(choose equipment)</option>
                  <?php foreach ($all_equipments as $id => $title): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($title . ' (' . $id . ')') ?></option>
                  <?php endforeach; ?>
                </select>
              </label></div>
            <?php elseif ($pn_l === 'user_id'): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                <select name="<?= htmlspecialchars($pn) ?>" style="width:100%;">
                  <option value="">(choose user)</option>
                  <?php foreach ($all_users as $id => $display): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($display . ' (' . $id . ')') ?></option>
                  <?php endforeach; ?>
                </select>
              </label></div>
            <?php elseif (strpos($pn_l,'thr') !== false): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br><input name="<?= htmlspecialchars($pn) ?>" value="0.01" style="width:100%"></label></div>
            <?php elseif (strpos($pn_l,'limit') !== false || strpos($pn_l,'since') !== false): ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br><input name="<?= htmlspecialchars($pn) ?>" value="50" style="width:100%"></label></div>
            <?php else: ?>
              <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br><input name="<?= htmlspecialchars($pn) ?>" style="width:100%"></label></div>
            <?php endif; ?>
          <?php endforeach; ?>
          <div style="text-align:right;margin-top:8px;"><button class="btn alt" type="submit">Run</button></div>
        </form>
      </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- right: results / error -->
  <div style="flex:1;max-height:72vh;overflow:auto;border:1px solid #ddd;padding:10px;">
    <?php if ($error): ?>
      <h3 style="color:darkred">Error</h3>
      <pre><?= htmlspecialchars($error) ?></pre>
    <?php endif; ?>

    <?php if (is_array($result)): ?>
      <h3>Results (<?= count($result) ?> rows)</h3>
        <?php if (count($result) === 0): ?>
        <p>(no rows)</p>
      <?php else: ?>
        <table class="table"><thead><tr>
        <?php foreach (array_keys($result[0]) as $col): ?>
          <th><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
        </tr></thead><tbody>
        <?php foreach ($result as $row): ?>
          <tr><?php foreach ($row as $cell): ?>
            <td><?= htmlspecialchars((string)$cell) ?></td>
          <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php
// end
// end
