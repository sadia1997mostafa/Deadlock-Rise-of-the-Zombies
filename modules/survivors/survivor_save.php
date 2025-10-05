<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

require_login();
$role = get_acting_role();
if (!(is_super_admin($pdo, current_user_id()) || $role === 'admin')) {
    header("Location: ?page=home"); exit;
}

$name = trim($_POST['name'] ?? '');
$age = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$gender = $_POST['gender'] ?? null;
$profession = trim($_POST['profession'] ?? '');
$skill = trim($_POST['skill'] ?? '');
$zone_id = $_POST['zone_id'] !== '' ? (int)$_POST['zone_id'] : null;

if ($name === '') { header("Location: ?page=home"); exit; }

try {
    $pdo->prepare("INSERT INTO survivors (name, age, gender, profession, skill, zone_id)
                   VALUES (?,?,?,?,?,?)")
        ->execute([$name, $age, $gender ?: null, $profession ?: null, $skill ?: null, $zone_id]);
} catch (Throwable $e) {
    // log if needed
}
header("Location: ?page=home"); exit;
