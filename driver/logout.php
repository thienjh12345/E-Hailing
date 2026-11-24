<?php
require __DIR__ . '/../includes/driver_auth.php';

// POST-only so a link or image on another site can't log the driver out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_driver();
}
redirect('driver/login.php');
