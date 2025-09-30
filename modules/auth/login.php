<?php
// modules/auth/login.php
// Login + smart redirect to the correct home page based on roles.

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // 1) Authenticate
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        // Logged in
        $_SESSION['user_id'] = (int)$user['id'];

        // 2) Fetch roles for this user
        $rolesStmt = $pdo->prepare("
            SELECT r.role_key
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $rolesStmt->execute([(int)$user['id']]);
        $roleRows = $rolesStmt->fetchAll();
        $roleKeys = array_values(array_unique(array_map(fn($row) => $row['role_key'], $roleRows)));

        // 3) Decide acting role + redirect
        if (in_array('super_admin', $roleKeys, true)) {
            set_acting_role('super_admin');
            header("Location: ?page=home");
            exit;
        }

        if (count($roleKeys) === 0) {
            // No granted roles -> viewer
            set_acting_role('viewer');
            header("Location: ?page=home");
            exit;
        }

        if (count($roleKeys) === 1) {
            // Exactly one role -> act as that role
            set_acting_role($roleKeys[0]);
            header("Location: ?page=home");
            exit;
        }

        // Multiple roles -> let the user choose
        set_acting_role('viewer'); // safe default until they pick
        header("Location: ?page=role_select");
        exit;

    } else {
        $err = "Invalid email or password.";
    }
}
?>
<div class="logo"><span class="dot"></span><h1>Zombie Outbreak Control</h1></div>
<small class="muted">Welcome back. Survive another day.</small>

<?php if($err): ?>
  <div class="alert error"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>

<form method="post">
  <label>Email</label>
  <input type="email" name="email" required>

  <label>Password</label>
  <input type="password" name="password" required>

  <button class="btn" type="submit">Login</button>
</form>

<div class="row">
  <a class="btn alt" href="?page=signup" style="text-align:center;">Create an account</a>
</div>
