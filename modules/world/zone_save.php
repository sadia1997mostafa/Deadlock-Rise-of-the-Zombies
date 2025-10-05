<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!(is_super_admin($pdo, current_user_id()) || get_acting_role()==='admin')) { header("Location: ?page=home"); exit; }

$region_id=(int)($_POST['region_id']??0); $name=trim($_POST['name']??'');
if ($region_id<=0 || $name===''){ header("Location: ?page=home"); exit; }

try{
  $pdo->prepare("INSERT INTO zones (region_id, name) VALUES (?,?)")->execute([$region_id,$name]);
}catch(Throwable $e){}
header("Location: ?page=home"); exit;
