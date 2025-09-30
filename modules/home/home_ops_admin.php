<?php
ensure_acting_role($pdo, 'ops_admin');
?>
<div class="logo"><span class="dot"></span><h1>Ops Admin Home</h1></div>
<p>Day-to-day operations: create/update missions, inventory, survivors.</p>

<div class="list">
  <a class="item" href="#">Create or Update Survivors (future)</a>
  <a class="item" href="#">Manage Missions (future)</a>
  <a class="item" href="#">Inventory Overview (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
