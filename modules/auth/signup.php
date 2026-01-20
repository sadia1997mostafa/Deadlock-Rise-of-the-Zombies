<?php
$msg = $err = '';


try {
  // roles table may not exist in lightweight deployments; omit role requests
  $roles = [];
} catch (Throwable $e) {
  $roles = [];
}


try {
  $zones = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
} catch (Throwable $e) {
  $zones = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';
  // role requests are not used in this deployment (ADMIN user is defined by constant)
  $roleId = 'none';

    if ($pass !== $pass2) {
        $err = "Passwords do not match.";
    } else {
    try {
      $pdo->beginTransaction();
      $hash = password_hash($pass, PASSWORD_DEFAULT);
    
      $zoneId = isset($_POST['zone_id']) && $_POST['zone_id'] !== '' ? (int)$_POST['zone_id'] : null;
      if ($zoneId) {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, zone_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hash, $zoneId]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hash]);
      }
      $uid = (int)$pdo->lastInsertId();

      $pdo->commit();
      $msg = "Signup complete. Login now. Viewer is the default role.";
    } catch (Throwable $e) {
      $pdo->rollBack();
      $err = "Signup failed: " . $e->getMessage();
    }
    }
}
?>
<div class="logo"><span class="dot"></span><h1>Create account</h1></div>
<small class="muted">Viewer accounts are created by signup. Admin access is controlled by the configured ADMIN_EMAIL constant.</small>
<?php if($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<form method="post">
  <label>Name</label>
  <input name="name" required>

  <label>Email</label>
  <input type="email" name="email" required>

  <label>Password</label>
  <input type="password" name="password" required>

  <label>Confirm Password</label>
  <input type="password" name="password2" required>

  <!-- role requests removed: admin is configured via constants -->

  <label>Zone (optional)</label>
  <select name="zone_id">
    <option value="">— No zone selected —</option>
    <?php foreach($zones as $z): ?>
      <option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <button class="btn" type="submit">Sign up</button>
</form>
<hr>
<a href="/zom/public/?page=login">Back to Login</a>
