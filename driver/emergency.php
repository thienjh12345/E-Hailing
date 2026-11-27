<?php
/**
 * Driver → emergency button on the current trip (JSON, used by assets/js/emergency.js).
 * Simulation only — see includes/emergency.php.
 */
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/booking.php';
require __DIR__ . '/../includes/emergency.php';

$d = current_driver();
emergency_endpoint('driver', $d ? driver_active_trip((int)$d['id']) : null, (bool)$d);
