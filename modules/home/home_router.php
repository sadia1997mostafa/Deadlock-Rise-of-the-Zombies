
<?php
// modules/home/home_router.php
// Decides which role-specific home to render.
// Viewer is PUBLIC (no login required). Other homes self-guard with ensure_acting_role().

$role = 'viewer';

// If logged in, prefer the session's acting role
if (function_exists('is_logged_in') && is_logged_in()) {
    if (function_exists('get_acting_role')) {
        $role = get_acting_role() ?: 'viewer';
    }
}

// Map role_key -> specific home view
$map = [
    'admin'             => __DIR__ . '/home_admin.php',
    'mission_commander' => __DIR__ . '/home_mission_commander.php',
    'viewer'            => __DIR__ . '/home_viewer.php', // public default
];

// Super Admin preview override: ?page=home&as=<role_key>
if (is_logged_in()
    && function_exists('is_super_admin') && function_exists('current_user_id')
    && is_super_admin($pdo, current_user_id())
    && isset($_GET['as'])
) {
    $as = strtolower(preg_replace('/[^a-z_]/', '', $_GET['as'])); // sanitize
    if (isset($map[$as])) {
        $role = $as;
    }
}

// Fallback to viewer if the role is unknown
$view = $map[$role] ?? $map['viewer'];

// Include the role-specific home file.
// Each non-viewer home file should start with:
//   ensure_acting_role($pdo, '<role_key>');
require $view;
