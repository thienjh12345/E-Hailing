<?php
require __DIR__ . '/../includes/passenger_auth.php';

// POST-only so a link or image on another site can't log the passenger out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_passenger();
}
redirect('index.php?as=passenger');
