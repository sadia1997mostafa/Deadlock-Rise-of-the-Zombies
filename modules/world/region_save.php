<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
if (!(is_super_admin($pdo, current_user_id()) || get_acting_role()==='admin')) { header("Location: ?page=home"); exit; }

$id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $risk=(int)($_POST['risk_level']??1); $active=(int)($_POST['active']??1);
if ($name==='' || $risk<1 || $risk>5){ header("Location: ?page=home"); exit; }

try{
  if($id){
    $st=$pdo->prepare("UPDATE regions SET name=?, risk_level=?, active=? WHERE id=?");
    $st->execute([$name,$risk,$active,$id]);
  }else{
    $st=$pdo->prepare("INSERT INTO regions (name, risk_level, active) VALUES (?,?,?)");
    $st->execute([$name,$risk,$active]);
  }
}catch(Throwable $e){}
header("Location: ?page=home"); exit;
