<?php
ensure_acting_role($pdo, 'watch_officer');
?>
<div class="logo"><span class="dot"></span><h1>Watch Officer Home</h1></div>
<p>Monitor alerts in real time and acknowledge or respond to them.</p>

<div class="list">
  <a class="item" href="#">Incoming Alerts (future)</a>
  <a class="item" href="#">Acknowledged Alerts (future)</a>
  <a class="item" href="#">Alert History (future)</a>
</div>

<hr>
<div class="row">
  <a class="btn alt" href="?page=role_select">Switch Role</a>
  <a class="btn" href="?page=logout">Logout</a>
</div>
