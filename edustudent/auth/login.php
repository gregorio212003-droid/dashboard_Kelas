
<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/session.php';

// Jika sudah login, arahkan ke dashboard
if (isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$error = "";

// Membuat CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {

        $error = "Permintaan tidak valid.";

    } else {

        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {

            $error = "Username/email dan password wajib diisi.";

        } else {

            // Mengambil data user sekaligus status siswa
            $sql = "SELECT
                        u.id,
                        u.username,
                        u.email,
                        u.password,
                        u.role,
                        s.status AS student_status
                    FROM users u
                    LEFT JOIN students s ON s.user_id = u.id
                    WHERE u.username = :username
                    OR u.email = :email
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':username' => $identity,
                ':email' => $identity
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Memeriksa username dan password
            if ($user && password_verify($password, $user['password'])) {

                // Pemeriksaan khusus akun siswa
                if ($user['role'] === 'siswa') {

                    if ($user['student_status'] === null) {

                        $error = "Data siswa tidak ditemukan. Silakan hubungi administrator.";

                    } elseif ($user['student_status'] !== 'aktif') {

                        $error = "Akun Anda telah dinonaktifkan. Silakan hubungi administrator.";

                    }
                }

                // Jika tidak ada error, login berhasil
                if ($error === '') {

                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];

                    // Membuat CSRF Token baru
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: ../index.php");
                    exit;
                }

            } else {

                $error = "Username/email atau password salah.";

            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - EduStudent</title>

    <link rel="stylesheet" href="../assets/css/login.css">

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <div class="brand">

            <h1>EduStudent</h1>

            <p>Academic Management System</p>

        </div>

        <div class="login-heading">

            <h2>Selamat Datang!</h2>

            <p>Silakan login untuk melanjutkan.</p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="error-message">

                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

            </div>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="form-group">

                <label>Username atau Email</label>

                <input
                    type="text"
                    name="identity"
                    placeholder="Masukkan username atau email"
                    value="<?= htmlspecialchars($_POST['identity'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >

            </div>

            <button type="submit">
                Login
            </button>

        </form>

        <div class="login-footer">

            EduStudent Dashboard &copy; 2026

        </div>

    </div>

</div>

</body>

</html>
