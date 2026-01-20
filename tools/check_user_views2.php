<?php
require_once __DIR__ . '/../config/db.php';
$res1 = $pdo->query('SELECT COUNT(*) AS c FROM vw_user_requests')->fetch(PDO::FETCH_ASSOC);
var_dump($res1);
$res2 = $pdo->query('SELECT COUNT(*) AS c FROM vw_user_registrations')->fetch(PDO::FETCH_ASSOC);
var_dump($res2);
