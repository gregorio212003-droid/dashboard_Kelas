
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ======================================
// 1. CSRF TOKEN
// ======================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// ======================================
// 2. VARIABEL FORM
// ======================================

$nip = '';
$fullName = '';
$gender = '';
$phone = '';
$address = '';
$username = '';
$email = '';

$errors = [];
$success = false;

// ======================================
// 3. PROSES SIMPAN DATA
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nip = trim($_POST['nip'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // ----------------------------------
    // VALIDASI CSRF
    // ----------------------------------

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )
    ) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    // ----------------------------------
    // VALIDASI DATA
    // ----------------------------------

    if ($nip === '') {
        $errors[] = 'NIP wajib diisi.';
    }

    if (strlen($nip) > 30) {
        $errors[] = 'NIP maksimal 30 karakter.';
    }

    if ($fullName === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (strlen($fullName) > 100) {
        $errors[] = 'Nama lengkap maksimal 100 karakter.';
    }

    if (!in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Silakan pilih jenis kelamin.';
    }

    if (strlen($phone) > 20) {
        $errors[] = 'Nomor telepon maksimal 20 karakter.';
    }

    if (strlen($address) > 10000) {
        $errors[] = 'Alamat terlalu panjang.';
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username harus 3-50 karakter dan hanya boleh menggunakan huruf, angka, atau garis bawah.';
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

    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    // ----------------------------------
    // CEK DATA DUPLIKAT
    // ----------------------------------

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM teachers
            WHERE nip = :nip
            LIMIT 1
        ");

        $stmt->execute([
            'nip' => $nip
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'NIP sudah digunakan oleh guru lain.';
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Username sudah digunakan.';
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Email sudah terdaftar.';
        }
    }

    // ----------------------------------
    // SIMPAN KE DATABASE
    // ----------------------------------

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Password dienkripsi

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Membuat akun pengguna

            $stmt = $pdo->prepare("
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
                    'guru'
                )
            ");

            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword
            ]);

            // Mengambil ID akun yang baru dibuat

            $userId = $pdo->lastInsertId();

            // Membuat profil guru

            $stmt = $pdo->prepare("
                INSERT INTO teachers (
                    user_id,
                    nip,
                    full_name,
                    gender,
                    phone,
                    address
                )
                VALUES (
                    :user_id,
                    :nip,
                    :full_name,
                    :gender,
                    :phone,
                    :address
                )
            ");

            $stmt->execute([
                'user_id' => $userId,
                'nip' => $nip,
                'full_name' => $fullName,
                'gender' => $gender,
                'phone' => $phone,
                'address' => $address
            ]);

            // Jika semua berhasil, simpan transaksi

            $pdo->commit();

            // Menghindari pengiriman ulang formulir

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: guru.php?created=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = 'Data guru gagal disimpan. Periksa struktur database dan coba kembali.';
        }
    }
}

// ======================================
// 4. HELPER OUTPUT
// ======================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Tambah Guru - EduStudent</title>

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

        .page-container {
            max-width: 950px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .back-link {
            display: inline-block;
            color: #2563eb;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 25px;
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
            padding: 30px;
            margin-bottom: 25px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 17px;
            margin-bottom: 6px;
        }

        .form-section > p {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #dbe2ed;
            border-radius: 8px;
            outline: none;
            font-family: inherit;
            font-size: 13px;
            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #2563eb;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-help {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 7px;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: 15px;
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
            border-top: 1px solid #edf0f4;
            padding-top: 22px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-submit {
            background: #2563eb;
            color: white;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        @media(max-width: 600px) {

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-panel {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                text-align: center;
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <a href="guru.php" class="back-link">
        ← Kembali ke Daftar Guru
    </a>

    <div class="page-heading">

        <h1>Tambah Guru Baru</h1>

        <p>
            Masukkan identitas dan informasi akun guru.
        </p>

    </div>

    <div class="form-panel">

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <strong>Terjadi kesalahan:</strong>

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li><?= e($error) ?></li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <!-- IDENTITAS GURU -->

            <div class="form-section">

                <h3>Informasi Pribadi Guru</h3>

                <p>
                    Masukkan data identitas guru.
                </p>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            NIP <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="nip"
                            maxlength="30"
                            placeholder="Contoh: GURU002"
                            value="<?= e($nip) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Nama Lengkap <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            maxlength="100"
                            placeholder="Masukkan nama lengkap"
                            value="<?= e($fullName) ?>"
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

                        <label>Nomor Telepon</label>

                        <input
                            type="tel"
                            name="phone"
                            maxlength="20"
                            placeholder="Contoh: 081234567890"
                            value="<?= e($phone) ?>"
                        >

                    </div>

                    <div class="form-group full">

                        <label>Alamat</label>

                        <textarea
                            name="address"
                            placeholder="Masukkan alamat lengkap guru"
                        ><?= e($address) ?></textarea>

                    </div>

                </div>

            </div>

            <!-- AKUN LOGIN -->

            <div class="form-section">

                <h3>Informasi Akun Login</h3>

                <p>
                    Akun ini digunakan guru untuk masuk ke EduStudent.
                </p>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Username <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="username"
                            maxlength="50"
                            placeholder="Contoh: guru02"
                            value="<?= e($username) ?>"
                            required
                        >

                        <small class="form-help">
                            Gunakan 3-50 karakter berupa huruf,
                            angka, atau garis bawah.
                        </small>

                    </div>

                    <div class="form-group">

                        <label>
                            Email <span class="required">*</span>
                        </label>

                        <input
                            type="email"
                            name="email"
                            maxlength="100"
                            placeholder="guru@edustudent.local"
                            value="<?= e($email) ?>"
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

                        <small class="form-help">
                            Password akan disimpan dalam bentuk hash.
                        </small>

                    </div>

                    <div class="form-group">

                        <label>
                            Konfirmasi Password
                            <span class="required">*</span>
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            minlength="8"
                            placeholder="Masukkan ulang password"
                            required
                        >

                    </div>

                </div>

            </div>

            <!-- BUTTON -->

            <div class="form-actions">

                <a href="guru.php" class="btn btn-cancel">
                    Batal
                </a>

                <button
                    type="submit"
                    class="btn btn-submit"
                >
                    Simpan Data Guru
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
