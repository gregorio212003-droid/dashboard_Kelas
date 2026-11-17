
<?php

require_once __DIR__ . '/session.php';

function require_login()
{
    if (!isset($_SESSION['user'])) {
        header("Location: /edustudent/auth/login.php");
        exit;
    }
}

function require_role($role)
{
    require_login();

    if ($_SESSION['user']['role'] !== $role) {
        header("Location: /edustudent/index.php");
        exit;
    }
}
