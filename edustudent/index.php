
<?php

require_once __DIR__ . '/includes/auth.php';

require_login();

$role = $_SESSION['user']['role'];

switch ($role) {

    case 'admin':
        header("Location: pages/admin/dashboard.php");
        break;

    case 'guru':
        header("Location: pages/guru/dashboard.php");
        break;

    case 'siswa':
        header("Location: pages/siswa/dashboard.php");
        break;

    default:
        session_destroy();
        header("Location: auth/login.php");
        break;
}

exit;
