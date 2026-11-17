
<?php

require_once __DIR__ . '/config/koneksi.php';

$message = '';
$created = false;

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
);

$adminExists = (int) $stmt->fetchColumn() > 0;

if (!$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $username === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8
    ) {
        $message = "Isi semua data dengan benar. Password minimal 8 karakter.";
    } else {

        try {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, password, role)
                 VALUES (:username, :email, :password, 'admin')"
            );

            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hash
            ]);

            $created = true;

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $message = "Username atau email mungkin sudah digunakan.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setup Admin</title>
</head>
<body>

<h2>Membuat Admin EduStudent</h2>

<?php if ($created): ?>

    <h3>Akun admin berhasil dibuat!</h3>
    <p>Silakan hapus file setup_admin.php dari folder proyek.</p>
    <a href="auth/login.php">Menuju halaman login</a>

<?php elseif ($adminExists): ?>

    <p>Akun admin sudah tersedia. Setup tidak dapat digunakan lagi.</p>

<?php else: ?>

    <?php if ($message): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">

        <p>
            <label>Username</label><br>
            <input type="text" name="username" required>
        </p>

        <p>
            <label>Email</label><br>
            <input type="email" name="email" required>
        </p>

        <p>
            <label>Password (minimal 8 karakter)</label><br>
            <input type="password" name="password" minlength="8" required>
        </p>

        <button type="submit">Buat Akun Admin</button>

    </form>

<?php endif; ?>

</body>
</html>
