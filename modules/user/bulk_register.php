<?php
// Endpoint to bulk register the current user for multiple campaigns
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/user_sql_functions.php';

header('Content-Type: application/json');
if (!is_logged_in()) { echo json_encode(['ok'=>false,'message'=>'Login required']); exit; }
$uid = current_user_id();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'message'=>'POST required']); exit; }
$ids = $_POST['campaign_ids'] ?? ($_POST['campaign_id'] ? [$_POST['campaign_id']] : []);
if (!is_array($ids)) $ids = [$ids];
$ids = array_map('intval', $ids);
if (empty($ids)) { echo json_encode(['ok'=>false,'message'=>'No campaign ids provided']); exit; }

$out = ['ok'=>true,'results'=>[]];
foreach ($ids as $cid) {
    $res = user_register_for_campaign($pdo, $uid, $cid);
    $out['results'][] = ['campaign_id'=>$cid,'ok'=>!empty($res['ok']),'message'=>$res['message'] ?? ''];
}
echo json_encode($out);
exit;
