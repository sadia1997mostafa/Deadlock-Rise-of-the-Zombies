<?php
ensure_acting_role($pdo, 'epidemiologist');
$zones = $pdo->query("SELECT id,name FROM zones WHERE active=1 ORDER BY name")->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Epidemiologist</h1></div>
<p>Log infection events. Viewer updates immediately.</p>

<div class="card-like">
  <h3>Log Infection Event</h3>
  <form method="post" action="?page=event_save" class="row" style="gap:8px;">
    <select name="zone_id" required>
      <?php foreach($zones as $z): ?>
        <option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="event_type" required>
      <option value="report">Report</option>
      <option value="cluster">Cluster</option>
      <option value="outbreak">Outbreak</option>
    </select>
    <input type="number" name="severity" min="1" max="5" value="1" required>
    <input name="notes" placeholder="Notes (optional)">
    <button class="btn" type="submit">Add</button>
  </form>
</div>

<hr>
<div class="row"><a class="btn" href="?page=home">Go to Viewer</a></div>
