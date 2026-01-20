<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();

$uid = current_user_id();
if (!is_admin($pdo, $uid)) {
  echo "<p>Only admins can create campaigns.</p>";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $desc  = $_POST['description'] ?? null;
  $zone  = $_POST['zone_id'] ?: null;
  $cap   = $_POST['capacity'] ?: null;

  $sql = "INSERT INTO campaigns (title, description, creator_id, zone_id, capacity) VALUES (?,?,?,?,?)";
  $st = $pdo->prepare($sql);
  $st->execute([$title, $desc, $uid, $zone, $cap]);
  header('Location: ?page=campaigns');
  exit;
}

$zones = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
?>
<h2>Create Campaign</h2>
<form method="post">
  <label>Title<br><input name="title" required></label><br>
  <label>Description<br><textarea name="description"></textarea></label><br>
  <label>Zone<br><select name="zone_id"><option value="">Any</option><?php foreach($zones as $z): ?><option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?></select></label><br>
  <label>Capacity (optional)<br><input name="capacity" type="number"></label><br>
  <button class="btn" type="submit">Create</button>
</form>
