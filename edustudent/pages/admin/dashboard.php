
<?php

require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - EduStudent</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f3f6fc;
            color: #1e293b;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2563eb;
            margin-bottom: 10px;
        }

        .header p {
            color: #64748b;
        }

        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .welcome-card h2 {
            margin-top: 0;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .menu-card h3 {
            margin-top: 0;
            color: #2563eb;
        }

        .menu-card p {
            color: #64748b;
            line-height: 1.5;
        }

        .menu-card a {
            display: inline-block;
            padding: 10px 18px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            margin-top: 10px;
        }

        .menu-card a:hover {
            background: #1d4ed8;
        }

        .logout {
            margin-top: 30px;
        }

        .logout button {
            padding: 11px 22px;
            border: none;
            border-radius: 7px;
            background: #fee2e2;
            color: #dc2626;
            cursor: pointer;
            font-weight: bold;
        }

        .logout button:hover {
            background: #fecaca;
        }
    </style>

</head>

<body>

<div class="container">

    <div class="header">
        <h1>EduStudent</h1>
        <p>Academic Management System</p>
    </div>

    <div class="welcome-card">

        <h2>
            Selamat datang,
            <?= htmlspecialchars($_SESSION['user']['username']) ?>!
        </h2>

        <p>Anda login sebagai Administrator.</p>

        <p>
            Selamat bekerja dan mengelola sistem EduStudent.
        </p>

    </div>

    <h2>Menu Administrasi</h2>

    <div class="menu-grid">

        <!-- Menu Manajemen Pengumuman -->
        <div class="menu-card">

            <h3>Manajemen Pengumuman</h3>

            <p>
                Kelola pengumuman akademik,
                informasi sekolah, dan pemberitahuan
                untuk seluruh siswa.
            </p>

            <a href="pengumuman.php">
                Kelola Pengumuman
            </a>

        </div>

        <!-- Menu Manajemen Guru -->
        <div class="menu-card">

            <h3>Manajemen Guru</h3>

            <p>
                Kelola data guru yang terdaftar
                dalam sistem EduStudent.
            </p>

            <a href="guru.php">
                Kelola Guru
            </a>

        </div>

        <!-- Menu Manajemen Siswa -->
        <div class="menu-card">

            <h3>Manajemen Siswa</h3>

            <p>
                Kelola data siswa, informasi,
                serta status akun siswa.
            </p>

            <a href="siswa.php">
                Kelola Siswa
            </a>

        </div>

    </div>

    <!-- Logout -->
    <div class="logout">

        <form action="../../auth/logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <button type="submit">
                Keluar dari Akun
            </button>

        </form>

    </div>

</div>

</body>
</html>
