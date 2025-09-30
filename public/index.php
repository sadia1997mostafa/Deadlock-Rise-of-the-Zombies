<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

/**
 * Simple router + layout.
 * Includes inner views from /modules/... based on ?page=
 * and wraps them in a single consistent layout (card + CSS).
 *
 * Folder-name independent: all links use relative URLs (?page=...).
 */

$page = $_GET['page'] ?? 'login';

// Map routes -> module file paths
$routes = [
    // authentication
    'login'        => __DIR__ . '/../modules/auth/login.php',
    'signup'       => __DIR__ . '/../modules/auth/signup.php',
    'role_select'  => __DIR__ . '/../modules/auth/role_select.php',

    // role-specific home router (7 roles + public viewer)
    'home'         => __DIR__ . '/../modules/home/home_router.php',

    // optional generic dashboard (if you still need it)
    'dashboard'    => __DIR__ . '/../modules/core/dashboard.php',

    // super admin area
    'sa_requests'  => __DIR__ . '/../modules/super_admin/requests.php',
];

// Actions that should not be wrapped (they redirect or echo minimal text)
$raw_actions = [
    'role_switch'  => __DIR__ . '/../modules/auth/role_switch.php',
    'logout'       => __DIR__ . '/../modules/auth/logout.php',
];

// If it's a raw action, just run and exit
if (isset($raw_actions[$page])) {
    require $raw_actions[$page];
    exit;
}

// Choose which inner view to load, default to login
$view = $routes[$page] ?? $routes['login'];

// ==== layout rendering ====
ob_start();
require $view;           // this echoes the inner content (no <html> wrapper)
$content = ob_get_clean();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Deadlock – Rise of the Zombies</title>
  <link rel="stylesheet" href="assets/css/style.css"><!-- relative path -->
</head>
<body>
  <div class="card">
    <?= $content ?>
  </div>
</body>
</html>
