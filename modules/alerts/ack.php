<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!(is_super_admin($pdo, current_user_id()) || in_array(get_acting_role(),['watch_officer','ops_admin'],true))) { header("Location: ?page=home"); exit; }
$id=(int)($_POST['id']??0);
if($id>0){
  // schema does not include acknowledged_by/acknowledged_at - only set status
  $pdo->prepare("UPDATE alerts SET status='acknowledged' WHERE id=? AND status='open'")->execute([$id]);
}
header("Location: ?page=home"); exit;
