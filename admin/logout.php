<?php
require __DIR__ . '/../includes/admin_auth.php';

// Logout is POST-only so a malicious link/image can't sign the admin out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_admin();
}
redirect('index.php?as=admin');
