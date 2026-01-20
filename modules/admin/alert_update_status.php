<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
// permission check similar to ack/close
if (!(is_super_admin($pdo, current_user_id()) || in_array(get_acting_role(), ['watch_officer','ops_admin'], true))) { header('Location: ?page=home'); exit; }

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['open','acknowledged','closed'];
if (!$id || !in_array($status, $allowed, true)) {
  $_SESSION['flash'] = 'Invalid request';
  header('Location: ?page=admin_alerts'); exit;
}

try {
  $pdo->beginTransaction();
  if ($status === 'acknowledged') {
    // schema doesn't include acknowledged_by/acknowledged_at: only set status
    $stmt = $pdo->prepare("UPDATE alerts SET status='acknowledged' WHERE id=?");
    $stmt->execute([$id]);
  } elseif ($status === 'closed') {
    // schema doesn't include closed_by/closed_at: only set status
    $stmt = $pdo->prepare("UPDATE alerts SET status='closed' WHERE id=?");
    $stmt->execute([$id]);
  } else { // open
    $stmt = $pdo->prepare("UPDATE alerts SET status='open' WHERE id=?");
    $stmt->execute([$id]);
  }
  $pdo->commit();
  $_SESSION['flash'] = 'Updated alert status';
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash'] = 'Failed to update alert status: ' . $e->getMessage();
}

header('Location: ?page=admin_alerts'); exit;
