<?php
// Simple runtime query examples that connect using existing config/db.php
require_once __DIR__ . '/../config/db.php';

// `config/db.php` instantiates a PDO in $pdo
if (! isset($pdo) || ! $pdo instanceof PDO) {
    echo "DB connection is not available via config/db.php\n";
    exit(1);
}

function run($pdo, $sql, $params = []) {
    echo "\n--- SQL: " . trim(substr($sql,0,80)) . "...\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        print_r($r);
    }
}

// 1) SELECT variants
// 1) SELECT variants
run($pdo, 'SELECT id, name, email FROM users LIMIT 10');
run($pdo, 'SELECT DISTINCT zone_id FROM `safe` WHERE zone_id IS NOT NULL LIMIT 10');

// 2) Aggregates
run($pdo, 'SELECT COUNT(*) AS users_count FROM users');
run($pdo, 'SELECT COUNT(*) AS infection_events, COALESCE(SUM(cases),0) AS total_cases FROM infection_events');

// 3) Joins - zone summary view
run($pdo, 'SELECT * FROM vw_zone_infections LIMIT 10');

// 4) Subquery example
run($pdo, 'SELECT id, name FROM users WHERE id IN (SELECT user_id FROM `safe` WHERE outbreak_status = "infected") LIMIT 10');

// 5) DDL examples are included in sql/queries/10_constraints_and_alter_examples.sql as commented guidance; do NOT execute automatically here.

// 6) Window function example (MySQL 8+)
try {
    run($pdo, 'SELECT id, zone_id, cases, created_at, ROW_NUMBER() OVER (PARTITION BY zone_id ORDER BY created_at DESC) AS rn FROM infection_events LIMIT 20');
} catch (Exception $e) {
    echo "Window function demo skipped or error: " . $e->getMessage() . "\n";
}

echo "\n-- Query examples completed.\n";

?>