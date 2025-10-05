<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
ensure_acting_role($pdo, 'admin');

$reqId   = (int)($_POST['req_id'] ?? 0);
$userId  = (int)($_POST['user_id'] ?? 0);
$roleKey = trim($_POST['role_key'] ?? '');
$action  = $_POST['action'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if (!$reqId || !$userId || !$roleKey || !in_array($action, ['approve','reject'], true)) {
    header("Location: /zom/public/?page=sa_requests");
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
    $stmt->execute([$roleKey]);
    $roleId = (int)$stmt->fetchColumn();

    if ($action === 'approve') {
        $assign = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
        $assign->execute([$userId, $roleId, current_user_id()]);

        $upd = $pdo->prepare("UPDATE role_requests
                              SET status='approved', reviewed_by=?, reviewed_at=NOW(), reviewer_comment=?
                              WHERE id=?");
        $upd->execute([current_user_id(), $comment ?: null, $reqId]);
    } else {
        $upd = $pdo->prepare("UPDATE role_requests
                              SET status='rejected', reviewed_by=?, reviewed_at=NOW(), reviewer_comment=?
                              WHERE id=?");
        $upd->execute([current_user_id(), $comment ?: null, $reqId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
}
header("Location: /zom/public/?page=sa_requests");
exit;
