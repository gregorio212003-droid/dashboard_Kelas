
<?php

require_once __DIR__ . '/config/koneksi.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nis = trim($_POST['nis'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $className = trim($_POST['class_name'] ?? '');

    if (
        $username === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8 ||
        $nis === '' ||
        $fullName === '' ||
        $className === ''
    ) {
        $message = "Semua data wajib diisi. Password minimal 8 karakter.";
    } else {

        try {

            $pdo->beginTransaction();

            // Cari kelas
            $stmt = $pdo->prepare("
                SELECT id FROM classes
                WHERE class_name = :class_name
                LIMIT 1
            ");

            $stmt->execute([
                'class_name' => $className
            ]);

            $class = $stmt->fetch();

            if (!$class) {
                throw new Exception("Kelas tidak ditemukan.");
            }

            // Buat akun pengguna
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users
                (username, email, password, role)
                VALUES (:username, :email, :password, 'siswa')
            ");

            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hash
            ]);

            $userId = $pdo->lastInsertId();

            // Buat profil siswa
            $stmt = $pdo->prepare("
                INSERT INTO students
                (user_id, class_id, nis, full_name)
                VALUES (:user_id, :class_id, :nis, :full_name)
            ");

            $stmt->execute([
                'user_id' => $userId,
                'class_id' => $class['id'],
                'nis' => $nis,
                'full_name' => $fullName
            ]);

            $pdo->commit();

            $success = true;
            $message = "Akun siswa berhasil dibuat!";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $message = $e instanceof PDOException
                ? "Username, email, atau NIS mungkin sudah digunakan."
                : $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Akun Siswa</title>
</head>
<body>

<h2>Registrasi Siswa EduStudent</h2>

<p><?= htmlspecialchars($message) ?></p>

<?php if (!$success): ?>

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
        <label>Password</label><br>
        <input type="password" name="password"
               minlength="8" required>
    </p>

    <p>
        <label>NIS</label><br>
        <input type="text" name="nis" required>
    </p>

    <p>
        <label>Nama Lengkap</label><br>
        <input type="text" name="full_name" required>
    </p>

    <p>
        <label>Kelas</label><br>
        <input type="text" name="class_name"
               value="XII Informatika" required>
    </p>

    <button type="submit">Buat Akun Siswa</button>

</form>

<?php else: ?>

    <h3>Akun siswa berhasil dibuat.</h3>
    <a href="auth/login.php">Pergi ke halaman login</a>

<?php endif; ?>

</body>
</html>
