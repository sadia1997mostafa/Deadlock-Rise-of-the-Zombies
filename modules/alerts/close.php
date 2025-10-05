<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!(is_super_admin($pdo, current_user_id()) || in_array(get_acting_role(),['mission_commander','admin'],true))) { header("Location: ?page=home"); exit; }
$id=(int)($_POST['id']??0);
if($id>0){
  $pdo->prepare("UPDATE alerts SET status='closed', closed_by=?, closed_at=NOW()
                 WHERE id=? AND status IN ('open','acknowledged')")->execute([current_user_id(),$id]);
}
header("Location: ?page=home"); exit;
