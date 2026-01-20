<?php
// modules/auth/login.php
// Simplified login: authenticates user and sets acting role based on ADMIN_EMAIL.

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // 1) Authenticate
    $stmt = $pdo->prepare("SELECT id, password_hash, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        // Logged in
        $_SESSION['user_id'] = (int)$user['id'];

        // Decide acting role by email: configured ADMIN_EMAIL is admin; everyone else is viewer
        $emailLower = strtolower($user['email'] ?? '');
        if ($emailLower === strtolower(ADMIN_EMAIL)) {
            set_acting_role('admin');
        } else {
            set_acting_role('viewer');
        }

        header("Location: ?page=home");
        exit;
    } else {
        $err = "Invalid email or password.";
    }
}
?>
<div class="logo"><span class="dot"></span><h1>Virus Outbreak Control</h1></div>
<small class="muted">Welcome back. Help stop the spread.</small>

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
