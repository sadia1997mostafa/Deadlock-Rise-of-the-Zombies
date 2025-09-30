<?php
// Super Admin can access everything (ensure guard for non-SA users)
ensure_acting_role($pdo, 'super_admin');
?>
<div class="logo"><span class="dot"></span><h1>Super Admin Home</h1></div>
<p>Manage everything: approve roles, oversee missions and resources.</p>

<div class="list">
  <a class="item" href="?page=sa_requests">Manage Role Requests</a>
  <a class="item" href="#">System Settings (future)</a>
</div>

<div class="badge" style="margin-top:12px">Preview other role homes</div>
<div class="list">
  <a class="item" href="?page=home&as=ops_admin">Ops Admin Home</a>
  <a class="item" href="?page=home&as=mission_commander">Mission Commander Home</a>
  <a class="item" href="?page=home&as=inventory_manager">Inventory Manager Home</a>
  <a class="item" href="?page=home&as=epidemiologist">Epidemiologist Home</a>
  <a class="item" href="?page=home&as=watch_officer">Watch Officer Home</a>
  <a class="item" href="?page=home&as=data_clerk">Data Clerk Home</a>
  <a class="item" href="?page=home&as=viewer">Viewer Home (public)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
