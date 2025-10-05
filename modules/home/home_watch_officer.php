<?php
ensure_acting_role($pdo, 'watch_officer');
$alerts = $pdo->query("
  SELECT a.id, z.name AS zone_name, a.title, a.status, a.created_at
  FROM alerts a JOIN zones z ON z.id=a.zone_id
  WHERE a.status IN ('open','acknowledged')
  ORDER BY a.created_at DESC
")->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Watch Officer</h1></div>
<p>Acknowledge or close alerts.</p>

<div class="list">
  <?php foreach($alerts as $a): ?>
    <div class="alert">
      <b><?= htmlspecialchars($a['title']) ?></b>
      <div class="badge"><?= htmlspecialchars($a['zone_name']) ?></div>
      <div class="badge">Status: <?= htmlspecialchars($a['status']) ?></div>
      <div><small class="muted"><?= htmlspecialchars($a['created_at']) ?></small></div>

      <form method="post" action="?page=alert_ack" class="row" style="gap:8px;margin-top:8px;">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="btn alt" type="submit">Acknowledge</button>
      </form>
      <form method="post" action="?page=alert_close" class="row" style="gap:8px;margin-top:8px;">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="btn" type="submit">Close</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if(!$alerts): ?><div class="alert"><em>No open alerts.</em></div><?php endif; ?>
</div>

<hr>
<div class="row"><a class="btn" href="?page=home">Go to Viewer</a></div>
