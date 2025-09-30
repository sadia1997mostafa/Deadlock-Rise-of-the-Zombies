<?php
require_login();
$uid = current_user_id();

$sql = "SELECT r.role_key, r.role_name
        FROM user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ?
        ORDER BY r.role_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$myRoles = $stmt->fetchAll();

$err = (isset($_GET['err']) && $_GET['err']==='role') ? "You don't have access to that role." : '';
?>
<div class="logo"><span class="dot"></span><h1>Choose how to continue</h1></div>
<small class="muted">Viewer is always available. Approved roles appear below.</small>
<?php if($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<h2>Your options</h2>
<div class="list">
  <a class="item" href="/zom/public/?page=role_switch&role=viewer">Login as Viewer</a>
  <?php foreach($myRoles as $r): ?>
    <a class="item" href="/zom/public/?page=role_switch&role=<?= urlencode($r['role_key']) ?>">
      Login as <?= htmlspecialchars($r['role_name']) ?>
    </a>
  <?php endforeach; ?>
</div>
<hr>
<div class="row">
  <a class="btn alt" href="/zom/public/?page=logout" style="text-align:center;">Logout</a>
</div>
