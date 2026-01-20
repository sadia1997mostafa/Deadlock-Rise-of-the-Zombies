<?php
define('DANGER_ALERT_THRESHOLD', 50.00);  
define('DANGER_LOOKBACK_DAYS', 7);        

const DANGER_WEIGHTS = [
  'report'   => 5,
  'cluster'  => 12,
  'outbreak' => 25,
];


if (! defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@example.local');
