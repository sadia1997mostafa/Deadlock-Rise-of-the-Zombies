<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$uid  = current_user_id();
$role = $_GET['role'] ?? 'viewer';

if (!user_has_role($pdo, $uid, $role)) {
    header("Location: /zom/public/?page=role_select&err=role");
    exit;
}
set_acting_role($role);
header("Location: /zom/public/?page=dashboard");
exit;
