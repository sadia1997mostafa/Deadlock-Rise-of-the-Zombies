<?php
// Admin runtime SQL examples for virus_outbreak project
// Place this file under modules/admin/ and access it via the app or run via CLI for demonstrations.
require_once __DIR__ . '/../../config/db.php';

if (! isset($pdo) || ! $pdo instanceof PDO) {
    echo "DB connection not available (check config/db.php)\n";
    exit(1);
}

function run_sql(PDO $pdo, $sql, $params = []) {
    echo "\n--- SQL: " . preg_replace('/\s+/', ' ', trim($sql)) . "\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        foreach ($rows as $r) print_r($r);
    } else {
        echo "(no rows)\n";
    }
}

echo "Admin Query Examples (runtime)\n";

// 1) Simple SELECT & DISTINCT
run_sql($pdo, 'SELECT id, name, email FROM users LIMIT 10');
run_sql($pdo, 'SELECT DISTINCT zone_id FROM `safe` WHERE zone_id IS NOT NULL LIMIT 10');

// 2) Aggregates, GROUP BY, HAVING
run_sql($pdo, 'SELECT zone_id, SUM(cases) AS total_cases FROM infection_events GROUP BY zone_id ORDER BY total_cases DESC LIMIT 10');
run_sql($pdo, 'SELECT zone_id, SUM(cases) AS cases_24h FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) > 10');

// 3) JOINs
run_sql($pdo, 'SELECT s.name AS person_name, s.outbreak_status, z.name AS zone_name FROM `safe` s JOIN zones z ON s.zone_id = z.id LIMIT 10');

// 4) Subquery (correlated)
run_sql($pdo, "SELECT id, name FROM users WHERE id IN (SELECT user_id FROM `safe` WHERE outbreak_status = 'infected') LIMIT 10");

// 5) Window function (MySQL 8+) - wrapped in try/catch because older servers may fail
try {
    run_sql($pdo, 'SELECT id, zone_id, cases, created_at, ROW_NUMBER() OVER (PARTITION BY zone_id ORDER BY created_at DESC) AS rn FROM infection_events LIMIT 20');
} catch (PDOException $e) {
    echo "Window function query skipped (server may not support MySQL 8+): " . $e->getMessage() . "\n";
}

// 6) Set operations (UNION)
run_sql($pdo, 'SELECT user_id FROM medical_campaign_registrations UNION SELECT user_id FROM ambulance_requests');

// 7) Example of a transaction: decrement medical equipment stock safely (demo only)
try {
    $pdo->beginTransaction();
    // choose an equipment id that exists
    $eId = (int)($argv[1] ?? 1);
    $stmt = $pdo->prepare('SELECT stock FROM medical_equipments WHERE id = ? FOR UPDATE');
    $stmt->execute([$eId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stock = (int)$row['stock'];
        echo "Equipment id={$eId} current stock={$stock}\n";
        if ($stock > 0) {
            $stmt2 = $pdo->prepare('UPDATE medical_equipments SET stock = stock - 1 WHERE id = ?');
            $stmt2->execute([$eId]);
            echo "Decremented equipment stock by 1\n";
        } else {
            echo "Stock is zero, cannot decrement\n";
        }
    } else {
        echo "Equipment id={$eId} not found\n";
    }
    $pdo->commit();
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage() . "\n";
    $pdo->rollBack();
}

// 8) DDL guidance (do not execute here) - placed in sql/queries/10_constraints_and_alter_examples.sql
echo "\nDDL/ALTER examples are in sql/queries/10_constraints_and_alter_examples.sql (do not run from web UI)\n";

echo "\nAdmin examples complete.\n";

?>
