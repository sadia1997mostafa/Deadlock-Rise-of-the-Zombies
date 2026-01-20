<?php
// modules/admin/icu_handle.php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../tools/sql_examples.php';
require_login(); if (!is_admin($pdo, current_user_id())) { echo "<p>Admin only</p>"; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ?page=admin_icu_requests'); exit; }
$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$sqls = get_sql_examples();
if ($action === 'confirm') {
  $pdo->prepare($sqls['icu_update_confirm'])->execute([$id]);
} elseif ($action === 'admit') {
  $pdo->prepare($sqls['icu_update_admit'])->execute([$id]);
} elseif ($action === 'reject') {
  $pdo->prepare($sqls['icu_update_reject'])->execute([$id]);
}
header('Location: ?page=admin_icu_requests'); exit;
