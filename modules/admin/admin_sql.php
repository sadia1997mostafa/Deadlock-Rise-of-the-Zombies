<?php
// modules/admin/admin_sql.php (moved from modules/tools/sql_examples_page.php)
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../tools/sql_examples.php';
require_login();
if (!is_super_admin($pdo, current_user_id())) { header('Location: ?page=home&err=role'); exit; }
?>
<div class="logo"><span class="dot"></span><h1>SQL Examples (Admin)</h1></div>
<p class="muted">Admin queries</p>

<?php
// --- Admin: expose functions from lib/sql_functions.php as runnable buttons for super-admins
require_once __DIR__ . '/../../lib/sql_functions.php';
$fn_result = null;
$fn_error = null;
$functions = [];
$srcFile = realpath(__DIR__ . '/../../lib/sql_functions.php');
$src = file_exists($srcFile) ? file_get_contents($srcFile) : '';
if ($src && preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $src, $m)) {
    foreach ($m[1] as $fn) {
        if (!function_exists($fn)) continue;
        try {
            $ref = new ReflectionFunction($fn);
            $params = array_map(function($p){ return $p->getName(); }, $ref->getParameters());
            if (count($params) && strtolower($params[0]) === 'pdo') array_shift($params);
            $label = ucwords(str_replace(['_','-'], ' ', $fn));
            $functions[$label] = [$fn, $params];
        } catch (ReflectionException $e) {
            // ignore
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['run_fn'])) {
    $fn = $_POST['run_fn'];
    $allowed = null;
    foreach ($functions as $label => $meta) { if ($meta[0] === $fn) { $allowed = $meta; break; } }
    if (! $allowed) {
        $fn_error = 'Function not allowed';
    } else {
        try {
            $params = [];
      foreach ($allowed[1] as $pn) {
        // accept array inputs directly (from multi-selects)
        if (isset($_POST[$pn]) && is_array($_POST[$pn])) {
          $params[] = $_POST[$pn];
          continue;
        }
        $pn_l = strtolower($pn);
        // CSV -> array for IDs or plural lists (tables, ids, user_ids, etc.)
        if ($pn_l === 'tables' || strpos($pn_l, 'table') !== false || strpos($pn_l, 'ids') !== false || (substr($pn_l, -1) === 's' && strlen($pn_l) > 3 && strpos($pn_l,'since')===false && strpos($pn_l,'days')===false && strpos($pn_l,'limit')===false && strpos($pn_l,'status')===false)) {
                    $raw = trim($_POST[$pn] ?? ($_POST['zone_ids'] ?? ''));
          // if value looks numeric list, convert to ints, else keep strings
          $parts = array_filter(array_map('trim', explode(',', $raw)), function($x){ return $x !== ''; });
          $ints = array_map(function($v){ return is_numeric($v) ? (int)$v : $v; }, $parts);
          $params[] = $ints;
        } elseif (strpos($pn_l, 'zone') !== false || strpos($pn_l, 'id') !== false) {
          // zone or id single/CSV handled above, but keep a fallback for 'zone' words
          $raw = trim($_POST[$pn] ?? ($_POST['zone_ids'] ?? ''));
          $ids = array_filter(array_map('intval', array_map('trim', explode(',', $raw))));
          $params[] = $ids;
        } elseif (strpos($pn_l, 'threshold') !== false || strpos($pn_l, 'thr') !== false) {
          $params[] = isset($_POST[$pn]) ? (float)$_POST[$pn] : (isset($_POST['threshold']) ? (float)$_POST['threshold'] : 0.01);
        } elseif (strpos($pn_l, 'since') !== false || strpos($pn_l, 'days') !== false) {
          $params[] = isset($_POST[$pn]) ? (int)$_POST[$pn] : (isset($_POST['since_days']) ? (int)$_POST['since_days'] : 30);
        } elseif ($pn_l === 'limit' || strpos($pn_l, 'limit') !== false) {
          $params[] = isset($_POST[$pn]) ? (int)$_POST[$pn] : 200;
        } else {
          $params[] = $_POST[$pn] ?? null;
        }
      }
            $fn_result = call_user_func_array($fn, array_merge([$pdo], $params));
        } catch (Throwable $e) {
            $fn_error = $e->getMessage();
        }
    }
}
?>

<h2>Functions</h2>

<?php if ($fn_error): ?><div class="alert error"><?= htmlspecialchars($fn_error) ?></div><?php endif; ?>

<div style="display:flex;gap:20px;align-items:flex-start;">
  <div style="width:360px;">
    <?php
      // fetch zones and tables once per page for dropdowns
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
    ?>
    <div class="list">
      <?php foreach ($functions as $label => $meta): $fname = $meta[0]; $params = $meta[1]; ?>
        <div class="item card-like" style="padding:12px;margin-bottom:10px;">
          <div style="font-weight:700;margin-bottom:6px;"><?= htmlspecialchars($label) ?></div>
          <form method="post" style="margin:0;">
            <input type="hidden" name="run_fn" value="<?= htmlspecialchars($fname) ?>">
            <?php foreach ($params as $pn): ?>
              <?php if (strtolower($pn) === 'tables'): ?>
                <?php // fetch table list once per page
                // $all_tables is prepared above
                ?>
                <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br>
                  <select name="<?= htmlspecialchars($pn) ?>[]" multiple style="width:100%;min-height:80px;">
                    <?php foreach ($all_tables as $tn): ?>
                      <option value="<?= htmlspecialchars($tn) ?>"><?= htmlspecialchars($tn) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label></div>
              <?php elseif (strpos(strtolower($pn),'zone') !== false || strtolower($pn) === 'zone_ids' || strtolower($pn) === 'zoneid' || strtolower($pn) === 'zone_id' || strpos(strtolower($pn),'ids') !== false): ?>
                  <?php // render zones multi-select (show id and name) ?>
                  <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?> (select one or more):<br>
                    <select name="<?= htmlspecialchars($pn) ?>[]" multiple style="width:100%;min-height:120px;">
                      <?php foreach ($all_zones as $zid => $zname): ?>
                        <option value="<?= htmlspecialchars($zid) ?>"><?= htmlspecialchars($zid . ' — ' . $zname) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label></div>
              <?php elseif (strpos(strtolower($pn),'thr') !== false): ?>
                <div style="margin-top:6px"><label><?= htmlspecialchars($pn) ?>:<br><input name="<?= htmlspecialchars($pn) ?>" value="0.01" style="width:100%"></label></div>
              <?php elseif (strpos(strtolower($pn),'limit') !== false || strpos(strtolower($pn),'since') !== false): ?>
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

  <div style="flex:1;">
    <?php if (is_array($fn_result)): ?>
      <h3>Result (<?= count($fn_result) ?> rows)</h3>
      <div class="card-like">
        <?php if (count($fn_result)===0): ?>
          <div class="muted">No rows returned.</div>
        <?php else: ?>
          <table class="table"><thead><tr>
          <?php foreach (array_keys((array)$fn_result[0]) as $col): ?>
            <th><?= htmlspecialchars($col) ?></th>
          <?php endforeach; ?>
          </tr></thead><tbody>
          <?php foreach ($fn_result as $row): ?><tr><?php foreach ($row as $v): ?><td><?= htmlspecialchars((string)$v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
          </tbody></table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
// end
