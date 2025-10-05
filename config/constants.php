<?php
define('DANGER_ALERT_THRESHOLD', 70.00);  // when to auto-open an alert
define('DANGER_LOOKBACK_DAYS', 7);        // how many days to compute danger from

const DANGER_WEIGHTS = [
  'report'   => 5,
  'cluster'  => 12,
  'outbreak' => 25,
];
