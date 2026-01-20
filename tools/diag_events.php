<?php
require __DIR__ . '/../config/db.php';

try {
    $infected_people = (int)$pdo->query("SELECT COUNT(*) FROM `safe` WHERE outbreak_status = 'infected'")->fetchColumn();
    $recent_infected = (int)$pdo->query("SELECT COUNT(*) FROM `safe` WHERE outbreak_status='infected' AND updated_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();

    echo "infected_people: $infected_people\n";
    echo "infection_events_24h (recent infected updated/added): $recent_infected\n\n";

    echo "Latest infected (24h, up to 100):\n";
    $st = $pdo->query("SELECT id,user_id,name,zone_id,outbreak_status,updated_at FROM `safe` WHERE outbreak_status='infected' AND updated_at >= NOW() - INTERVAL 24 HOUR ORDER BY updated_at DESC LIMIT 100");
    $rows = $st->fetchAll();
    foreach ($rows as $r) {
        echo json_encode($r) . "\n";
    }

    echo "\nCounts by zone (24h) (infected updates):\n";
    $st = $pdo->query("SELECT zone_id, COUNT(*) AS cnt FROM `safe` WHERE outbreak_status='infected' AND updated_at >= NOW() - INTERVAL 24 HOUR GROUP BY zone_id ORDER BY cnt DESC");
    foreach ($st->fetchAll() as $r) echo json_encode($r) . "\n";

        echo "\nCurrently marked infected people (safe table):\n";
        $st = $pdo->query("SELECT id, user_id, name, zone_id, outbreak_status, updated_at FROM `safe` WHERE outbreak_status = 'infected' ORDER BY updated_at DESC");
        foreach ($st->fetchAll() as $r) echo json_encode($r) . "\n";

        echo "\nReporter -> user mapping (reporter_id shows user id in users table when available):\n";
        $st = $pdo->query("SELECT ie.reporter_id, u.email AS reporter_email, COUNT(*) AS cnt FROM infection_events ie LEFT JOIN users u ON u.id = ie.reporter_id WHERE ie.created_at >= NOW() - INTERVAL 24 HOUR GROUP BY ie.reporter_id ORDER BY cnt DESC");
        foreach ($st->fetchAll() as $r) echo json_encode($r) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
