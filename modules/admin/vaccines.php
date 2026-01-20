<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
// allow only admin users to manage medical equipment
if (!is_super_admin($pdo, current_user_id())) { header('Location: ?page=home&err=role'); exit; }

// handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $name = trim($_POST['name'] ?? ''); $description = trim($_POST['description'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    if ($name !== '') {
      $pdo->prepare("INSERT INTO medical_equipments (name,description,stock) VALUES (?,?,?)")->execute([$name,$description?:null,$stock]);
    }
  } elseif ($action === 'edit') {
    $id = (int)($_POST['id']??0); $name = trim($_POST['name'] ?? ''); $description = trim($_POST['description'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    if ($id && $name !== '') {
      $pdo->prepare("UPDATE medical_equipments SET name=?,description=?,stock=? WHERE id=?")->execute([$name,$description?:null,$stock,$id]);
    }
  } elseif ($action === 'inc' || $action === 'dec') {
    $id = (int)($_POST['id']??0);
    $delta = ($action === 'inc') ? 1 : -1;
    if ($id) {
      // ensure non-negative
      $st = $pdo->prepare("SELECT stock FROM medical_equipments WHERE id=? LIMIT 1"); $st->execute([$id]); $cur = (int)$st->fetchColumn();
      $new = max(0, $cur + $delta);
      $pdo->prepare("UPDATE medical_equipments SET stock=? WHERE id=?")->execute([$new,$id]);
    }
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id']??0);
    if ($id) $pdo->prepare("DELETE FROM medical_equipments WHERE id=?")->execute([$id]);
  }
  header('Location: ?page=admin_vaccines'); exit;
}

$equipments = $pdo->query("SELECT * FROM medical_equipments ORDER BY name")->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Admin: Medical Equipment</h1></div>
<h3>Add Medical Equipment</h3>
<form method="post">
  <input type="hidden" name="action" value="add">
  <input name="name" placeholder="Equipment name" required>
  <input name="description" placeholder="Description">
  <input name="stock" type="number" min="0" value="0" placeholder="Stock count">
  <div class="row"><button class="btn" type="submit">Add</button></div>
</form>
<hr>
<h3>Existing Equipment</h3>
<?php foreach($equipments as $e): ?>
  <div class="card-like">
    <form method="post">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
      <input name="name" value="<?= htmlspecialchars($e['name']) ?>">
      <input name="description" value="<?= htmlspecialchars($e['description'] ?? '') ?>">
      <input name="stock" type="number" min="0" value="<?= (int)($e['stock'] ?? 0) ?>">
      <div class="row">
        <button class="btn" type="submit">Save</button>
      </div>
    </form>
    <form method="post" style="margin-top:6px">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
      <button class="btn alt" type="submit">Delete</button>
    </form>
  </div>
<?php endforeach; ?>
