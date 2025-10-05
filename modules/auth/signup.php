<?php
$msg = $err = '';
$roles = $pdo->query("SELECT id, role_key, role_name
                      FROM roles
                      WHERE role_key IN ('mission_commander')
                      ORDER BY role_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';
    $roleId = $_POST['requested_role_id'] ?? 'none';

    if ($pass !== $pass2) {
        $err = "Passwords do not match.";
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);
            $uid = (int)$pdo->lastInsertId();

            if ($roleId !== 'none') {
                $stmt = $pdo->prepare("INSERT INTO role_requests (user_id, role_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$uid, (int)$roleId]);
            }
            $pdo->commit();
            $msg = "Signup complete. Login now. Viewer is default; any requested role awaits approval.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = "Signup failed: " . $e->getMessage();
        }
    }
}
?>
<div class="logo"><span class="dot"></span><h1>Create account</h1></div>
<small class="muted">Request a role now (optional). Viewer is always allowed.</small>
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

  <label>Request a role (optional)</label>
  <select name="requested_role_id">
    <option value="none">— No role now (Viewer) —</option>
    <?php foreach($roles as $r): ?>
      <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <button class="btn" type="submit">Sign up</button>
</form>
<hr>
<a href="/zom/public/?page=login">Back to Login</a>
