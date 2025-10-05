<?php
function m_count_zones(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM zones")->fetchColumn();
}
function m_count_alerts_last_hours(PDO $pdo, int $hours = 24): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE created_at >= NOW() - INTERVAL ? HOUR");
    $st->execute([$hours]);
    return (int)$st->fetchColumn();
}
function m_count_infected_events_last_hours(PDO $pdo, int $hours = 24): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM infection_events WHERE created_at >= NOW() - INTERVAL ? HOUR");
    $st->execute([$hours]);
    return (int)$st->fetchColumn();
}
function m_zone_risk_table(PDO $pdo): array {
    $sql = "
      SELECT
        z.id   AS zone_id,
        z.name AS zone_name,
        z.danger_score,
        COALESCE((
          SELECT COUNT(*) FROM infection_events ie
          WHERE ie.zone_id = z.id
            AND ie.created_at >= NOW() - INTERVAL 24 HOUR
        ),0) AS cases_24h
      FROM zones z
      JOIN regions r ON r.id = z.region_id
      WHERE z.active=1 AND r.active=1
      ORDER BY z.danger_score DESC, z.name ASC
    ";
    return $pdo->query($sql)->fetchAll();
}
function m_surge_detector(PDO $pdo, int $days_back = 5): array {
    $sql = "
      SELECT z.id AS zone_id, DATE(ie.created_at) AS d, COUNT(*) AS cnt
      FROM zones z
      LEFT JOIN infection_events ie
        ON ie.zone_id = z.id
       AND ie.created_at >= CURDATE() - INTERVAL ? DAY
      GROUP BY z.id, DATE(ie.created_at)
      ORDER BY z.id, d DESC
    ";
    $st = $pdo->prepare($sql); $st->execute([$days_back]); $rows = $st->fetchAll();
    $byZone=[]; $today=new DateTimeImmutable('today'); $dates=[];
    for($i=0;$i<$days_back;$i++) $dates[]=$today->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    foreach($rows as $r){ $z=(int)$r['zone_id']; $d=$r['d']??null; $c=(int)($r['cnt']??0); if(!isset($byZone[$z]))$byZone[$z]=[]; if($d)$byZone[$z][$d]=$c; }
    $out=[];
    foreach($byZone as $z=>$counts){
        $series=[]; foreach($dates as $d){ $series[$d]=$counts[$d]??0; }
        $d0=$dates[0]; $d1=$dates[1]??$d0; $d2=$dates[2]??$d1;
        $new=$series[$d0]; $ma3=round(($series[$d0]+$series[$d1]+$series[$d2])/3,1); $delta=$series[$d0]-$series[$d1];
        if($ma3>=3 || $delta>=2){ $out[]=['zone_id'=>$z,'time'=>date('Y-m-d H:i:s'),'new'=>$new,'ma3'=>$ma3,'delta'=>$delta]; }
    }
    usort($out, fn($a,$b)=>($b['new']<=>$a['new']) ?: ($a['zone_id']<=>$b['zone_id']));
    return array_slice($out,0,8);
}
function m_latest_alert(PDO $pdo): ?array {
   $sql = "SELECT a.id, a.title, z.name AS zone_name, a.created_at, a.status
            FROM alerts a
            JOIN zones z ON z.id = a.zone_id
            WHERE a.status IN ('open','acknowledged')
            ORDER BY a.created_at DESC
            LIMIT 1";
    $row = $pdo->query($sql)->fetch();
    return $row ?: null;
}
