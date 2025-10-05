<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../lib/domain.php';

require_login();
$role=get_acting_role();
if (!(is_super_admin($pdo, current_user_id()) || in_array($role,['mission_commander'],true))) { header("Location: ?page=home"); exit; }

$zone_id=(int)($_POST['zone_id']??0);
$type=$_POST['event_type']??'report';
$severity=(int)($_POST['severity']??1);
$notes=trim($_POST['notes']??'');

if ($zone_id<=0 || !in_array($type,['report','cluster','outbreak'],true) || $severity<1 || $severity>5){ header("Location: ?page=home"); exit; }

$pdo->prepare("INSERT INTO infection_events (zone_id, event_type, severity, notes, created_by)
               VALUES (?,?,?,?,?)")->execute([$zone_id,$type,$severity,$notes?:null,current_user_id()]);
recalc_zone_danger($pdo,$zone_id);
header("Location: ?page=home"); exit;
