<?php

require_once __DIR__ . '/../../config/auth.php';
require_login(); if (!is_admin($pdo, current_user_id())) { echo "<p>Admin only</p>"; exit; }
require_once __DIR__ . '/../../lib/sql_functions.php';

// Discover functions declared in lib/sql_functions.php and build a UI for them.
$queries = [];
$srcFile = realpath(__DIR__ . '/../../lib/sql_functions.php');
$src = file_exists($srcFile) ? file_get_contents($srcFile) : '';
if ($src) {
  // extract function names defined in that file
  if (preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $src, $m)) {
    $fnames = $m[1];
    foreach ($fnames as $fn) {
      // skip if function doesn't exist (safety) or is internal
      if (!function_exists($fn)) continue;
      try {
        $ref = new ReflectionFunction($fn);
        $params = array_map(function($p){ return $p->getName(); }, $ref->getParameters());
        // remove first param (PDO $pdo) if present
        if (count($params) && strtolower($params[0]) === 'pdo') array_shift($params);
        // try to extract a human-friendly title from a docblock above the function in the source
        $label = ucwords(str_replace(['_','-'], ' ', $fn));
        if ($src) {
          // look for a PHPDoc comment immediately preceding the function definition
          $pattern = '/\/\*\*(?:.|\s)*?\*\/\s*function\s+' . preg_quote($fn, '/') . '\s*\(/i';
          if (preg_match($pattern, $src, $m)) {
            // capture the docblock text
            if (preg_match('/\/\*\*(.*?)\*\//s', $m[0], $c)) {
              $doc = trim($c[1]);
              // extract a @title: value if present
              if (preg_match('/@title\s*:\s*(.+)/i', $doc, $t)) {
                $label = trim($t[1]);
              } else {
                // otherwise take the first non-empty line of the docblock (strip leading * and spaces)
                $lines = preg_split('/\r?\n/', $doc);
                foreach ($lines as $ln) {
                  $ln = preg_replace('/^[\s\*\/]+/', '', $ln);
                  if (strlen(trim($ln)) > 0) { $label = trim($ln); break; }
                }
              }
            }
          }
        }
        $queries[$label] = [$fn, $params];
      } catch (ReflectionException $e) {
        // ignore
      }
    }
  }
}

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fn'])) {
  $fn = $_POST['fn'];
  // ensure function is one we discovered
  $allowed = null;
  foreach ($queries as $label => $meta) {
    if ($meta[0] === $fn) { $allowed = $meta; break; }
  }
  if (! $allowed) {
    $error = 'Function not allowed';
  } else {
    try {
      $params = [];
      $pnames = $allowed[1];
      foreach ($pnames as $pn) {
        $pn_l = strtolower($pn);
        if (strpos($pn_l, 'zone') !== false || strpos($pn_l, 'ids') !== false) {
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
      $result = call_user_func_array($fn, array_merge([$pdo], $params));
    } catch (Exception $e) {
      $error = $e->getMessage();
    }
  }
}

// Render UI
?><h2>Admin SQL Runner</h2>
<p>Click a button to run the query. Only admins can run these.</p>
<div style="display:flex;flex-wrap:wrap;gap:12px;">
<?php foreach ($queries as $label => $meta):
    $fname = $meta[0]; $params = $meta[1];
?>
  <form method="post" style="border:1px solid #ddd;padding:10px;width:320px;">
    <input type="hidden" name="fn" value="<?= htmlspecialchars($fname) ?>">
    <strong><?= htmlspecialchars($label) ?></strong>
    <div style="margin-top:6px">
    <?php foreach ($params as $pn): ?>
      <?php if ($pn === 'zone_ids'): ?>
        <label>zone_ids (comma list):<br><input name="zone_ids" value="" style="width:100%"></label>
      <?php elseif ($pn === 'threshold'): ?>
        <label>threshold (decimal):<br><input name="threshold" value="0.01" style="width:100%"></label>
      <?php elseif ($pn === 'since_days'): ?>
        <label>since_days (int):<br><input name="since_days" value="30" style="width:100%"></label>
      <?php elseif ($pn === 'limit'): ?>
        <label>limit (int):<br><input name="limit" value="50" style="width:100%"></label>
      <?php else: ?>
        <label><?= htmlspecialchars($pn) ?>:<br><input name="<?= htmlspecialchars($pn) ?>" style="width:100%"></label>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>
    <div style="margin-top:8px"><button class="btn" type="submit">Run</button></div>
  </form>
<?php endforeach; ?>
</div>

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

<?php
// end file
