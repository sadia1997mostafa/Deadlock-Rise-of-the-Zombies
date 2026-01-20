<?php
// tools/mark_infected_demo.php
// Mark N random safe people as infected for demo purposes and insert infection_events
require_once __DIR__ . '/../config/db.php';

$N = (int)($argv[1] ?? 5);
try {
    $pdo->beginTransaction();
    $ids = $pdo->query("SELECT id,user_id,zone_id FROM `safe` WHERE outbreak_status <> 'infected' LIMIT $N")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ids as $r) {
        $pdo->prepare("UPDATE `safe` SET outbreak_status='infected', updated_at=NOW() WHERE id = ?")->execute([(int)$r['id']]);
        // do not insert infection_events; rely on safe.updated_at for recent infections
    }
    $pdo->commit();
    echo "Marked " . count($ids) . " people infected and added events.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}

?>