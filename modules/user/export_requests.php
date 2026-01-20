<?php
// Export current user's requests as CSV
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';

if (!is_logged_in()) { header('Location: ?page=login'); exit; }
$uid = current_user_id();

$sql = "SELECT 'ambulance' AS type, id AS request_id, zone_id, details, status, created_at FROM ambulance_requests WHERE user_id = :uid
        UNION ALL
        SELECT 'icu' AS type, id AS request_id, zone_id, details, status, created_at FROM icu_requests WHERE user_id = :uid
        ORDER BY created_at DESC";
$st = $pdo->prepare($sql); $st->execute([':uid'=>$uid]); $rows = $st->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="my_requests_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output','w');
fputcsv($out, array_keys($rows[0] ?? ['type','request_id','zone_id','details','status','created_at']));
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;
