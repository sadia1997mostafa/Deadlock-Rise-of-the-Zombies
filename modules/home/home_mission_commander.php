<?php
ensure_acting_role($pdo, 'mission_commander');
?>
<div class="logo"><span class="dot"></span><h1>Mission Commander Home</h1></div>
<p>Create, launch and complete missions; assign members and reserve supplies.</p>

<div class="list">
  <a class="item" href="#">New Mission (future)</a>
  <a class="item" href="#">Active Missions (future)</a>
  <a class="item" href="#">Completed Missions (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
