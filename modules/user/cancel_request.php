<?php
// Cancel a user's ambulance/icu request
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/user_sql_functions.php';

if (!is_logged_in()) { header('Location: ?page=login'); exit; }
$uid = current_user_id();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ?page=user_panel'); exit; }
$type = $_POST['req_type'] ?? '';
$req_id = isset($_POST['req_id']) ? (int)$_POST['req_id'] : 0;
if (!$req_id || !in_array($type, ['ambulance','icu'])) { $_SESSION['flash'] = 'Invalid request parameters.'; header('Location: ?page=user_panel'); exit; }

$res = user_cancel_request($pdo, $uid, $type, $req_id);
$_SESSION['flash'] = $res['message'] ?? 'Action completed.';
header('Location: ?page=user_panel'); exit;
