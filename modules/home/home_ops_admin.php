<?php
// Ops Admin home
ensure_acting_role($pdo, 'ops_admin');

// fetch regions (for zones and survivors dropdowns)
$regions = $pdo->query("SELECT id,name FROM regions ORDER BY name")->fetchAll();

// fetch zones (for survivor assignment)
$zones = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
?>
<div class="logo"><span class="dot"></span><h1>Ops Admin</h1></div>
<p>Manage the world: create Regions, Zones, and Survivors. After saving, you’ll be redirected to Viewer.</p>

<!-- Region -->
<div class="card-like" style="margin-bottom:12px;">
  <h3>Add / Update Region</h3>
  <form method="post" action="?page=region_save" class="row" style="gap:8px;">
    <input type="hidden" name="id" value="">
    <input name="name" placeholder="Region name" required>
    <input name="risk_level" type="number" min="1" max="5" value="1" required>
    <select name="active">
      <option value="1">Active</option>
      <option value="0">Inactive</option>
    </select>
    <button class="btn" type="submit">Save</button>
  </form>
</div>

<!-- Zone -->
<div class="card-like" style="margin-bottom:12px;">
  <h3>Add Zone</h3>
  <form method="post" action="?page=zone_save" class="row" style="gap:8px;">
    <select name="region_id" required>
      <?php foreach($regions as $r): ?>
        <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input name="name" placeholder="Zone name" required>
    <button class="btn" type="submit">Save</button>
  </form>
</div>

<!-- Survivor -->
<div class="card-like" style="margin-bottom:12px;">
  <h3>Add Survivor</h3>
  <form method="post" action="?page=survivor_save" class="row" style="flex-wrap:wrap;gap:8px;">
    <input name="name" placeholder="Name" required>
    <input name="age" type="number" min="0" placeholder="Age">
    <select name="gender">
      <option value="">Gender</option>
      <option value="male">Male</option>
      <option value="female">Female</option>
      <option value="other">Other</option>
    </select>
    <input name="profession" placeholder="Profession">
    <input name="skill" placeholder="Skill">
    <select name="zone_id">
      <option value="">No Zone</option>
      <?php foreach($zones as $z): ?>
        <option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Save</button>
  </form>
</div>

<hr>
<div class="row">
  <a class="btn" href="?page=home">Go to Viewer</a>
</div>
