<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Seed Admin</title>
    <link rel="stylesheet" href="/zom/public/assets/css/style.css"></head><body><div class="card">
        <div class="logo"><span class="dot"></span><h1>Seed Admin</h1></div>
    <small class="muted">Run once, then delete this file.</small>
    <form method="post">
      <label>Name</label><input name="name" value="Root Admin" required>
      <label>Email</label><input type="email" name="email" value="admin@zom.local" required>
      <label>Password</label><input type="password" name="password" value="admin123" required>
            <button class="btn" type="submit">Create Admin</button>
    </form></div></body></html>';
    exit;
}

try {
    $pdo->beginTransaction();
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)");
    $st->execute([$name,$email,$hash]);
    $uid = (int)$pdo->lastInsertId();

    $rid = (int)$pdo->query("SELECT id FROM roles WHERE role_key='admin'")->fetchColumn();
    $st = $pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?,?,NULL)");
    $st->execute([$uid, $rid]);

    $pdo->commit();
    echo "<div style='font-family:system-ui;color:#e5e7eb;background:#0b0f14;padding:20px'>Admin created ✅ — <b>$email</b><br><br>
          <a style='color:#b0ff6a' href='/zom/public/?page=login'>Go to Login</a><br><br>
          <small>Now delete <code>tools/seed_admin.php</code>.</small></div>";
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Failed: " . htmlspecialchars($e->getMessage());
}
