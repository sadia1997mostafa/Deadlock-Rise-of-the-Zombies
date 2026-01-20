<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!(is_super_admin($pdo, current_user_id()) || get_acting_role()==='ops_admin')) { header("Location: ?page=home"); exit; }

$region_id=(int)($_POST['region_id']??0);
$new_region = trim($_POST['new_region'] ?? '');
$name=trim($_POST['name']??'');

if ($name==='') { header("Location: ?page=home"); exit; }

try{
  if ($new_region !== '') {
    // create region first (default risk=1, active=1)
    $st = $pdo->prepare("INSERT INTO regions (name,risk_level,active) VALUES (?,?,?)");
    $st->execute([$new_region,1,1]);
    $region_id = (int)$pdo->lastInsertId();
  }

  if ($region_id > 0) {
    $pdo->prepare("INSERT INTO zones (region_id, name) VALUES (?,?)")->execute([$region_id,$name]);
  }
}catch(Throwable $e){}
header("Location: ?page=admin_zones"); exit;
