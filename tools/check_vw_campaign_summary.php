<?php
require_once __DIR__ . '/../config/db.php';
try {
    $st = $pdo->query('SELECT COUNT(*) as c FROM vw_campaign_summary');
    $c = (int)$st->fetchColumn();
    echo "vw_campaign_summary rows: $c\n";
    $st2 = $pdo->query('SELECT campaign_id, title, zone_name, capacity, registrations, slots_left, availability FROM vw_campaign_summary LIMIT 10');
    $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo sprintf("%d | %s | %s | cap=%s regs=%s slots=%s avail=%s\n",
            $r['campaign_id'], $r['title'] ?? '(no title)', $r['zone_name'] ?? '(no zone)',
            $r['capacity'] === null ? 'NULL' : $r['capacity'],
            $r['registrations'], $r['slots_left'], $r['availability']
        );
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
