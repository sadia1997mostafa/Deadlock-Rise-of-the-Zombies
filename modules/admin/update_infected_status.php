<?php
// Admin raw action: change an infected person's status to 'safe' or 'deceased'
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ?page=home'); exit; }
require_login();
$uid = current_user_id();
if (!is_super_admin($pdo, $uid)) { header('Location: ?page=home&err=role'); exit; }

$safeId = isset($_POST['safe_id']) ? (int)$_POST['safe_id'] : 0;
$newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
$allowed = ['safe','deceased'];
if (!$safeId || !in_array($newStatus, $allowed, true)) {
    $_SESSION['flash'] = 'Invalid request';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=home&as=admin'));
    exit;
}

try {
    $pdo->beginTransaction();
    // fetch safe row with zone
    $st = $pdo->prepare("SELECT id, user_id, zone_id, outbreak_status FROM `safe` WHERE id = ? FOR UPDATE");
    $st->execute([$safeId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Person not found');
    }

    // update safe row
    $upd = $pdo->prepare("UPDATE `safe` SET outbreak_status = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newStatus, $safeId]);

    // if marked deceased, increase death_count for zone (if present)
    if ($newStatus === 'deceased' && !empty($row['zone_id'])) {
        $z = (int)$row['zone_id'];
        $pdo->prepare("UPDATE zones SET death_count = COALESCE(death_count,0) + 1 WHERE id = ?")->execute([$z]);
    }

    $pdo->commit();
    $_SESSION['flash'] = 'Updated status successfully';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = 'Failed to update status: ' . $e->getMessage();
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=home&as=admin'));
exit;
