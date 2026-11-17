
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ========================================
// 1. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========================================
// 2. HELPER
// ========================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ========================================
// 3. AMBIL DATA KELAS
// ========================================

$classStmt = $pdo->query("
    SELECT id, class_name, grade_level, major
    FROM classes
    ORDER BY grade_level ASC, class_name ASC
");

$classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// 4. INISIALISASI
// ========================================

$errors = [];

$nis = '';
$nisn = '';
$fullName = '';
$gender = '';
$birthDate = '';
$address = '';
$classId = '';
$username = '';
$email = '';

// ========================================
// 5. PROSES FORM
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil input

    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birthDate = $_POST['birth_date'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $classId = $_POST['class_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ====================================
    // VALIDASI CSRF
    // ====================================

    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    // ====================================
    // VALIDASI IDENTITAS
    // ====================================

    if ($nis === '') {
        $errors[] = 'NIS wajib diisi.';
    }

    if (strlen($nis) > 30) {
        $errors[] = 'NIS maksimal 30 karakter.';
    }

    if (strlen($nisn) > 30) {
        $errors[] = 'NISN maksimal 30 karakter.';
    }

    if ($fullName === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (strlen($fullName) > 100) {
        $errors[] = 'Nama maksimal 100 karakter.';
    }

    if (!in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Pilih jenis kelamin.';
    }

    // ====================================
    // VALIDASI TANGGAL LAHIR
    // ====================================

    if ($birthDate !== '') {

        $date = DateTime::createFromFormat('!Y-m-d', $birthDate);

        if (
            !$date ||
            $date->format('Y-m-d') !== $birthDate ||
            $birthDate > date('Y-m-d')
        ) {
            $errors[] = 'Tanggal lahir tidak valid.';
        }
    }

    // ====================================
    // VALIDASI KELAS
    // ====================================

    $classId = filter_var(
        $classId,
        FILTER_VALIDATE_INT
    );

    if (!$classId || $classId < 1) {

        $errors[] = 'Silakan pilih kelas.';

    } else {

        $checkClass = $pdo->prepare("
            SELECT id
            FROM classes
            WHERE id = :id
        ");

        $checkClass->execute([
            'id' => $classId
        ]);

        if (!$checkClass->fetch()) {
            $errors[] = 'Kelas tidak ditemukan.';
        }
    }

    // ====================================
    // VALIDASI AKUN
    // ====================================

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if (
        strlen($username) < 3 ||
        strlen($username) > 50
    ) {
        $errors[] = 'Username harus terdiri dari 3-50 karakter.';
    }

    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Username hanya boleh menggunakan huruf, angka, titik, garis bawah, dan tanda hubung.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (strlen($email) > 100) {
        $errors[] = 'Email maksimal 100 karakter.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }

    // ====================================
    // CEK NIS DUPLIKAT
    // ====================================

    if ($nis !== '') {

        $check = $pdo->prepare("
            SELECT id
            FROM students
            WHERE nis = :nis
            LIMIT 1
        ");

        $check->execute([
            'nis' => $nis
        ]);

        if ($check->fetch()) {
            $errors[] = 'NIS sudah digunakan siswa lain.';
        }
    }

    // ====================================
    // CEK NISN DUPLIKAT
    // ====================================

    if ($nisn !== '') {

        $check = $pdo->prepare("
            SELECT id
            FROM students
            WHERE nisn = :nisn
            LIMIT 1
        ");

        $check->execute([
            'nisn' => $nisn
        ]);

        if ($check->fetch()) {
            $errors[] = 'NISN sudah digunakan siswa lain.';
        }
    }

    // ====================================
    // CEK USERNAME DUPLIKAT
    // ====================================

    if ($username !== '') {

        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $check->execute([
            'username' => $username
        ]);

        if ($check->fetch()) {
            $errors[] = 'Username sudah digunakan.';
        }
    }

    // ====================================
    // CEK EMAIL DUPLIKAT
    // ====================================

    if ($email !== '') {

        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $check->execute([
            'email' => $email
        ]);

        if ($check->fetch()) {
            $errors[] = 'Email sudah digunakan.';
        }
    }

    // ====================================
    // 6. SIMPAN DATA
    // ====================================

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Hash password

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Buat akun login

            $userStmt = $pdo->prepare("
                INSERT INTO users (
                    username,
                    email,
                    password,
                    role
                )
                VALUES (
                    :username,
                    :email,
                    :password,
                    'siswa'
                )
            ");

            $userStmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword
            ]);

            // Ambil ID akun

            $userId = $pdo->lastInsertId();

            // Masukkan identitas siswa

            $studentStmt = $pdo->prepare("
                INSERT INTO students (
                    user_id,
                    class_id,
                    nis,
                    nisn,
                    full_name,
                    gender,
                    birth_date,
                    address
                )
                VALUES (
                    :user_id,
                    :class_id,
                    :nis,
                    :nisn,
                    :full_name,
                    :gender,
                    :birth_date,
                    :address
                )
            ");

            $studentStmt->execute([
                'user_id' => $userId,
                'class_id' => $classId,
                'nis' => $nis,
                'nisn' => $nisn !== '' ? $nisn : null,
                'full_name' => $fullName,
                'gender' => $gender,
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'address' => $address !== '' ? $address : null
            ]);

            // Selesaikan transaksi

            $pdo->commit();

            // Perbarui CSRF token

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Kembali ke daftar siswa

            header('Location: siswa.php?created=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = 'Gagal menyimpan data. Periksa kembali data dan struktur database.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Tambah Siswa - EduStudent</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    color: #263247;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 245px;
    background: white;
    padding: 25px 20px;
    border-right: 1px solid #e8ebf0;
    flex-shrink: 0;
}

.logo {
    font-size: 23px;
    font-weight: bold;
    color: #2563eb;
    margin-bottom: 40px;
}

.menu-label {
    font-size: 11px;
    color: #94a3b8;
    font-weight: bold;
    margin: 20px 0 12px;
}

.nav a {
    display: block;
    text-decoration: none;
    color: #64748b;
    padding: 12px;
    font-size: 14px;
    border-radius: 8px;
    margin-bottom: 5px;
}

.nav a.active,
.nav a:hover {
    background: #eff6ff;
    color: #2563eb;
}

.main {
    flex: 1;
    min-width: 0;
}

.navbar {
    height: 75px;
    background: white;
    border-bottom: 1px solid #e8ebf0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.navbar h3 {
    font-size: 17px;
}

.admin-user {
    font-size: 14px;
    font-weight: bold;
    text-align: right;
}

.admin-user span {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    margin-top: 4px;
}

.content {
    padding: 30px;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    font-size: 24px;
    margin-bottom: 8px;
}

.page-heading p {
    color: #64748b;
    font-size: 14px;
}

.form-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 25px;
    max-width: 950px;
}

.form-section {
    margin-bottom: 28px;
}

.form-section h3 {
    font-size: 16px;
    margin-bottom: 7px;
}

.form-section p {
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 13px;
    font-weight: bold;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dce3ed;
    border-radius: 8px;
    font-size: 13px;
    font-family: Arial, sans-serif;
    outline: none;
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #2563eb;
}

.form-group textarea {
    resize: vertical;
    min-height: 95px;
}

.required {
    color: #dc2626;
}

.hint {
    color: #94a3b8;
    font-size: 11px;
}

.divider {
    border: none;
    border-top: 1px solid #edf0f4;
    margin: 25px 0;
}

.error-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 22px;
    font-size: 13px;
}

.error-box ul {
    padding-left: 20px;
    margin-top: 8px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
}

.btn-cancel {
    display: inline-block;
    padding: 12px 20px;
    background: #f1f5f9;
    color: #475569;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
}

.btn-save {
    padding: 12px 22px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
}

.btn-save:hover {
    background: #1d4ed8;
}

@media(max-width: 800px) {

    .sidebar {
        display: none;
    }

    .content {
        padding: 20px 15px;
    }

    .navbar {
        padding: 0 15px;
    }

}

@media(max-width: 550px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn-save,
    .btn-cancel {
        text-align: center;
    }

}

</style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">
            EduStudent
        </div>

        <div class="menu-label">
            MENU UTAMA
        </div>

        <nav class="nav">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="guru.php">
                Data Guru
            </a>

            <a href="siswa.php" class="active">
                Data Siswa
            </a>

            <a href="kelas.php">
                Data Kelas
            </a>

            <a href="mapel.php">
                Mata Pelajaran
            </a>

            <a href="jadwal.php">
                Jadwal Pelajaran
            </a>

            <a href="tahun_ajaran.php">
                Tahun Ajaran
            </a>

        </nav>

        <div class="menu-label">
            LAINNYA
        </div>

        <nav class="nav">

            <a href="#">
                Pengumuman
            </a>

            <a href="#">
                Pengaturan
            </a>

        </nav>

    </aside>

    <!-- MAIN -->

    <main class="main">

        <header class="navbar">

            <h3>Tambah Siswa</h3>

            <div class="admin-user">

                Administrator

                <span>Admin EduStudent</span>

            </div>

        </header>

        <section class="content">

            <div class="page-heading">

                <h1>Tambah Siswa Baru</h1>

                <p>
                    Masukkan informasi identitas dan akun login siswa.
                </p>

            </div>

            <div class="form-panel">

                <!-- ERROR -->

                <?php if (!empty($errors)): ?>

                    <div class="error-box">

                        <strong>
                            Data belum dapat disimpan:
                        </strong>

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li><?= e($error) ?></li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <!-- IDENTITAS -->

                    <div class="form-section">

                        <h3>Informasi Identitas</h3>

                        <p>
                            Informasi pribadi dan identitas akademik siswa.
                        </p>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    NIS <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nis"
                                    value="<?= e($nis) ?>"
                                    maxlength="30"
                                    placeholder="Contoh: 2026001"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    NISN
                                </label>

                                <input
                                    type="text"
                                    name="nisn"
                                    value="<?= e($nisn) ?>"
                                    maxlength="30"
                                    placeholder="Masukkan NISN"
                                >

                            </div>

                            <div class="form-group full">

                                <label>
                                    Nama Lengkap <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="full_name"
                                    value="<?= e($fullName) ?>"
                                    maxlength="100"
                                    placeholder="Masukkan nama lengkap siswa"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Jenis Kelamin <span class="required">*</span>
                                </label>

                                <select name="gender" required>

                                    <option value="">
                                        Pilih jenis kelamin
                                    </option>

                                    <option
                                        value="L"
                                        <?= $gender === 'L' ? 'selected' : '' ?>
                                    >
                                        Laki-laki
                                    </option>

                                    <option
                                        value="P"
                                        <?= $gender === 'P' ? 'selected' : '' ?>
                                    >
                                        Perempuan
                                    </option>

                                </select>

                            </div>

                            <div class="form-group">

                                <label>
                                    Tanggal Lahir
                                </label>

                                <input
                                    type="date"
                                    name="birth_date"
                                    value="<?= e($birthDate) ?>"
                                >

                            </div>

                            <div class="form-group full">

                                <label>
                                    Alamat
                                </label>

                                <textarea
                                    name="address"
                                    placeholder="Masukkan alamat lengkap siswa"
                                ><?= e($address) ?></textarea>

                            </div>

                        </div>

                    </div>

                    <hr class="divider">

                    <!-- AKADEMIK -->

                    <div class="form-section">

                        <h3>Informasi Akademik</h3>

                        <p>
                            Tentukan kelas tempat siswa belajar.
                        </p>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    Kelas <span class="required">*</span>
                                </label>

                                <select name="class_id" required>

                                    <option value="">
                                        Pilih kelas
                                    </option>

                                    <?php foreach ($classes as $class): ?>

                                        <option
                                            value="<?= (int) $class['id'] ?>"
                                            <?= (string) $classId === (string) $class['id'] ? 'selected' : '' ?>
                                        >

                                            <?= e($class['class_name']) ?>
                                            - Kelas <?= e($class['grade_level']) ?>
                                            <?= $class['major'] ? '- ' . e($class['major']) : '' ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <?php if (empty($classes)): ?>

                                    <span class="hint">
                                        Belum ada kelas. Tambahkan data kelas terlebih dahulu.
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <hr class="divider">

                    <!-- AKUN LOGIN -->

                    <div class="form-section">

                        <h3>Akun Login Siswa</h3>

                        <p>
                            Akun ini akan digunakan siswa untuk masuk ke EduStudent.
                        </p>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    Username <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    value="<?= e($username) ?>"
                                    maxlength="50"
                                    placeholder="Contoh: siswa001"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Email <span class="required">*</span>
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    value="<?= e($email) ?>"
                                    maxlength="100"
                                    placeholder="Contoh: siswa@email.com"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Password <span class="required">*</span>
                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    minlength="8"
                                    placeholder="Minimal 8 karakter"
                                    required
                                >

                                <span class="hint">
                                    Password akan disimpan menggunakan password_hash().
                                </span>

                            </div>

                        </div>

                    </div>

                    <!-- TOMBOL -->

                    <div class="form-actions">

                        <a href="siswa.php" class="btn-cancel">
                            Batal
                        </a>

                        <button type="submit" class="btn-save">
                            Simpan Data Siswa
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

</body>

</html>
