<?php
// Admin can access everything. Accept legacy super_admin as well via is_super_admin check
require_login();
if (!is_super_admin($pdo, current_user_id())) {
  // admin-only page: redirect to home with an error instead of role select
  header('Location: ?page=home&err=role');
  exit;
}
// get zones for dropdown
 $zones = $pdo->query("SELECT id,name FROM zones ORDER BY name")->fetchAll();
// open alerts count for quick badge
$openAlerts = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE status='open'")->fetchColumn();
?>
<div class="logo"><span class="dot"></span><h1>Admin Pannel</h1></div>
<p>Manage admin features: campaigns, medical equipment and quick actions.</p>

<div class="list">
  <!-- Missions removed; use Campaigns instead -->
  <a class="item" href="?page=campaigns">Campaigns</a>
  <a class="item" href="?page=admin_zones">Manage Zones</a>
  <a class="item" href="?page=admin_vaccines">Manage Medical Equipment</a>
  <a class="item" href="?page=sql_examples">Admin queries</a>
  <a class="item" href="?page=admin_ambulance_requests">Ambulance Requests</a>
  <a class="item" href="?page=admin_icu_requests">ICU Requests</a>
  <a class="item" href="?page=admin_alerts">Alerts <?= $openAlerts ? '<small style="color:#c00">('.(int)$openAlerts.')</small>' : '' ?></a>
  <a class="item" href="?page=home&as=viewer">Viewer Home</a>
</div>

<hr>
<div class="card-like">
  <h3>Quick: Mark person infected</h3>
  <form method="post" action="?page=admin_mark_infected">
  <input name="survivor_id" placeholder="Person ID (leave blank to create new)">
  <input name="person_name" placeholder="Name (when creating new)" style="margin-top:6px;">
  <div style="margin-top:6px;display:flex;gap:8px;align-items:center;">
    <label style="font-size:90%;"><input type="checkbox" name="create_user_if_missing" value="1"> Create a user account if no match</label>
  </div>
  <select name="gender" style="margin-top:6px;">
    <option value="">Gender (optional)</option>
    <option value="male">Male</option>
    <option value="female">Female</option>
    <option value="other">Other</option>
  </select>
    <select name="zone_id">
      <option value="">(none)</option>
<?php foreach ($zones as $z): ?>
      <option value="<?php echo $z['id']; ?>"><?php echo htmlspecialchars($z['name']); ?></option>
<?php endforeach; ?>
    </select>
    <div class="row"><button class="btn" type="submit">Mark Infected</button></div>
  </form>
</div>

<hr>
<div class="card-like">
  <h3>Your status (quick)</h3>
  <form method="post" action="?page=self_status_action" style="display:flex;gap:8px;align-items:center;">
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="btn" type="submit" name="mark_safe">Mark myself safe</button>
      <button class="btn warn" type="submit" name="mark_infected">Mark myself infected</button>
    </div>
    <div style="margin-left:12px;display:flex;align-items:center;gap:8px;">
      <select name="zone_id">
        <option value="">-- Select zone --</option>
        <?php foreach ($zones as $z): ?>
          <option value="<?= (int)$z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn alt" type="submit" name="set_zone">Save zone</button>
    </div>
  </form>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=home">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>

<hr>
<div class="card-like">
  <h3>Update infected person (admin)</h3>
  <?php
    // fetch currently infected people (limit 200)
    $infected = $pdo->prepare("SELECT s.id, COALESCE(u.name,s.name) AS name, z.name AS zone_name, s.zone_id FROM `safe` s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN zones z ON s.zone_id = z.id WHERE s.outbreak_status = 'infected' LIMIT 200");
    $infected->execute();
    $infectedRows = $infected->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <?php if (!$infectedRows): ?>
    <div><em>No infected people found.</em></div>
  <?php else: ?>
    <form method="post" action="?page=admin_update_infected_status">
      <select name="safe_id" style="min-width:260px;">
        <?php foreach ($infectedRows as $ir): ?>
          <option value="<?= (int)$ir['id'] ?>">ID <?= (int)$ir['id'] ?> - <?= htmlspecialchars($ir['name']) ?> (<?= htmlspecialchars($ir['zone_name'] ?? 'No zone') ?>)</option>
        <?php endforeach; ?>
      </select>
      <select name="new_status" style="margin-left:8px;">
        <option value="safe">Mark Safe</option>
        <option value="deceased">Mark Deceased</option>
      </select>
      <button class="btn" type="submit" style="margin-left:8px;">Update</button>
    </form>
  <?php endif; ?>
</div>
