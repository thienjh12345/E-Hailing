<?php
/**
 * Passenger → emergency button on a live trip (JSON, used by assets/js/emergency.js).
 * Simulation only — see includes/emergency.php.
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/booking.php';
require __DIR__ . '/../includes/emergency.php';

$p = current_passenger();
emergency_endpoint('passenger', $p ? passenger_live_ride((int)$p['id']) : null, (bool)$p);
