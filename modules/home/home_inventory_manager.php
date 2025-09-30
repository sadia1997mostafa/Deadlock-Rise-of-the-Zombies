<?php
ensure_acting_role($pdo, 'inventory_manager');
?>
<div class="logo"><span class="dot"></span><h1>Inventory Manager Home</h1></div>
<p>Manage and allocate supplies and resources for all missions.</p>

<div class="list">
  <a class="item" href="#">View Stock Levels (future)</a>
  <a class="item" href="#">Reserve Items for Missions (future)</a>
  <a class="item" href="#">Inventory Reports (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
