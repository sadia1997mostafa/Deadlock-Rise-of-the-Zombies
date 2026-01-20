<?php
// tools/check_user_views.php - quick check for vw_user_requests and vw_user_registrations
require_once __DIR__ . '/../config/db.php';
try {
    $c = (int)$pdo->query('SELECT COUNT(*) FROM vw_user_requests')->fetchColumn();
    echo "vw_user_requests: $c\n";
} catch (Throwable $e) {
    echo "vw_user_requests error: " . $e->getMessage() . "\n";
}
try {
    $c2 = (int)$pdo->query('SELECT COUNT(*) FROM vw_user_registrations')->fetchColumn();
    echo "vw_user_registrations: $c2\n";
} catch (Throwable $e) {
    echo "vw_user_registrations error: " . $e->getMessage() . "\n";
}
