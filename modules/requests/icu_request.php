<?php
// modules/requests/icu_request.php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../tools/sql_examples.php';
require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $zone = $_POST['zone_id'] ?: null;
  $details = $_POST['details'] ?: null;
  $sqls = get_sql_examples();
  $stmt = $pdo->prepare($sqls['icu_insert']);
  $stmt->execute([$uid,$zone,$details]);
  header('Location: ?page=home'); exit;
}

$zones = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
?>
<h2>Request ICU</h2>
<form method="post">
  <label>Zone<br><select name="zone_id"><option value="">Any</option><?php foreach ($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?></select></label><br>
  <label>Details<br><input name="details"></label><br>
  <button class="btn">Request ICU</button>
</form>
