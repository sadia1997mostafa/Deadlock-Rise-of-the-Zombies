<?php
require_once __DIR__ . '/../config/constants.php';

function recalc_zone_danger(PDO $pdo, int $zoneId): void {
    $sql = "SELECT event_type, severity
            FROM infection_events
            WHERE zone_id = ?
              AND created_at >= NOW() - INTERVAL " . (int)DANGER_LOOKBACK_DAYS . " DAY";
    $st = $pdo->prepare($sql);
    $st->execute([$zoneId]);
    $rows = $st->fetchAll();

    $score = 0.0;
    foreach ($rows as $r) {
        $w = DANGER_WEIGHTS[$r['event_type']] ?? 0;
        $score += ((int)$r['severity']) * $w;
    }

    $pdo->prepare("UPDATE zones SET danger_score = ? WHERE id = ?")->execute([$score, $zoneId]);

    if ($score >= DANGER_ALERT_THRESHOLD) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE zone_id=? AND status IN ('open','acknowledged')");
        $q->execute([$zoneId]);
        if ((int)$q->fetchColumn() === 0) {
            $title = "Danger high in zone #{$zoneId}";
            $pdo->prepare("INSERT INTO alerts (zone_id, title, status, threshold, danger_at_creation)
                           VALUES (?, ?, 'open', ?, ?)")
                ->execute([$zoneId, $title, DANGER_ALERT_THRESHOLD, $score]);
        }
    }
}
