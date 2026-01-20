<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!is_super_admin($pdo, current_user_id())) { header('Location: ?page=home&err=role'); exit; }

// handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $id = (int)($_POST['id']??0);
  $danger = (float)($_POST['danger_score'] ?? 0);
  $active = isset($_POST['active']) ? 1 : 0;
  $cases = (int)($_POST['cases']??0);
  $death_count = isset($_POST['death_count']) ? (int)$_POST['death_count'] : null;
  if ($id) {
    // Detect which columns exist and update only those present.
    $hasDanger = (bool)$pdo->query("SHOW COLUMNS FROM zones LIKE 'danger_score'")->fetch();
    $hasActive = (bool)$pdo->query("SHOW COLUMNS FROM zones LIKE 'active'")->fetch();
    $hasDeath  = (bool)$pdo->query("SHOW COLUMNS FROM zones LIKE 'death_count'")->fetch();

    $updates = [];
    $params = [];
    if ($hasDanger) { $updates[] = 'danger_score = ?'; $params[] = $danger; }
    if ($hasActive) { $updates[] = 'active = ?'; $params[] = $active; }
    if ($hasDeath && $death_count !== null) { $updates[] = 'death_count = ?'; $params[] = $death_count; }

    if ($updates) {
      $sql = 'UPDATE zones SET ' . implode(', ', $updates) . ' WHERE id = ?';
      $params[] = $id;
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }

    if ($cases > 0) {
      $pdo->prepare("INSERT INTO alerts (zone_id,title,status,created_at) VALUES (?,?,?,NOW())")->execute([$id, 'Manual update: reported cases '.$cases, 'open']);
    }
  }
  header('Location: ?page=admin_zones'); exit;
}

$zones = $pdo->query("SELECT z.*, r.name AS region_name, (
  SELECT COUNT(*) FROM `safe` s WHERE s.zone_id = z.id AND s.outbreak_status='infected' AND s.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
) AS cases_24h FROM zones z JOIN regions r ON z.region_id = r.id ORDER BY r.name, z.name")->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Admin: Zones</h1></div>
<?php if (is_super_admin($pdo, current_user_id())): ?>
  <div class="card-like" style="margin-bottom:12px;">
    <h3>Create new zone</h3>
    <form method="post" action="?page=zone_save" class="row" style="gap:8px;align-items:center;">
      <select name="region_id">
        <option value="">-- Select existing region (optional) --</option>
        <?php foreach($pdo->query('SELECT id,name FROM regions ORDER BY name')->fetchAll() as $reg): ?>
          <option value="<?= (int)$reg['id'] ?>"><?= htmlspecialchars($reg['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input name="new_region" placeholder="Or create new region (name)">
      <input name="name" placeholder="Zone name" required>
      <button class="btn" type="submit">Add Zone</button>
    </form>
  </div>
<?php endif; ?>
<?php foreach($zones as $z): ?>
  <div class="card-like">
    <h3><?= htmlspecialchars($z['name']) ?> <small class="muted">(<?= htmlspecialchars($z['region_name']) ?>)</small></h3>
    <form method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
      <label>Danger score</label>
      <input name="danger_score" value="<?= htmlspecialchars($z['danger_score'] ?? 0) ?>">
  <label>Death count</label>
  <input name="death_count" value="<?= (int)($z['death_count'] ?? 0) ?>">
      <label>Cases to add (optional)</label>
      <input name="cases" value="0">
      <label><input type="checkbox" name="active" <?= (!empty($z['active']) && $z['active']) ? 'checked' : '' ?>> Active</label>
      <div class="row"><button class="btn" type="submit">Save</button></div>
    </form>
  </div>
<?php endforeach; ?>
