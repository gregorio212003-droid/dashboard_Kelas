
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// 1. AMBIL ID GURU
// =====================================

$teacherId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$teacherId || $teacherId < 1) {
    header('Location: guru.php');
    exit;
}

// =====================================
// 2. AMBIL DATA GURU
// =====================================

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.user_id,
        t.nip,
        t.full_name,
        t.gender,
        t.phone,
        t.address,
        u.username,
        u.email

    FROM teachers t

    INNER JOIN users u ON u.id = t.user_id

    WHERE t.id = :id
    AND u.role = 'guru'
    LIMIT 1
");

$stmt->execute(['id' => $teacherId]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header('Location: guru.php?error=notfound');
    exit;
}

// =====================================
// 3. CSRF TOKEN
// =====================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================
// 4. VARIABEL FORM
// =====================================

$nip = $teacher['nip'];
$fullName = $teacher['full_name'];
$gender = $teacher['gender'];
$phone = $teacher['phone'];
$address = $teacher['address'];
$username = $teacher['username'];
$email = $teacher['email'];

$errors = [];

// =====================================
// 5. PROSES UPDATE
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nip = trim($_POST['nip'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validasi CSRF

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        $errors[] = 'Permintaan tidak valid. Muat ulang halaman.';
    }

    // Validasi identitas

    if ($nip === '' || strlen($nip) > 30) {
        $errors[] = 'NIP wajib diisi dan maksimal 30 karakter.';
    }

    if ($fullName === '' || strlen($fullName) > 100) {
        $errors[] = 'Nama wajib diisi dan maksimal 100 karakter.';
    }

    if (!in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }

    if (strlen($phone) > 20) {
        $errors[] = 'Nomor telepon maksimal 20 karakter.';
    }

    if (strlen($address) > 10000) {
        $errors[] = 'Alamat terlalu panjang.';
    }

    // Validasi akun

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username harus 3-50 karakter dan hanya menggunakan huruf, angka, atau garis bawah.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (strlen($email) > 100) {
        $errors[] = 'Email maksimal 100 karakter.';
    }

    // =================================
    // 6. CEK DUPLIKASI
    // =================================

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM teachers
            WHERE nip = :nip
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            'nip' => $nip,
            'id' => $teacherId
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'NIP sudah digunakan guru lain.';
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = :username
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username,
            'id' => $teacher['user_id']
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Username sudah digunakan.';
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email,
            'id' => $teacher['user_id']
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Email sudah digunakan.';
        }
    }

    // =================================
    // 7. SIMPAN PERUBAHAN
    // =================================

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Update tabel teachers

            $stmt = $pdo->prepare("
                UPDATE teachers

                SET
                    nip = :nip,
                    full_name = :full_name,
                    gender = :gender,
                    phone = :phone,
                    address = :address

                WHERE id = :id
            ");

            $stmt->execute([
                'nip' => $nip,
                'full_name' => $fullName,
                'gender' => $gender,
                'phone' => $phone,
                'address' => $address,
                'id' => $teacherId
            ]);

            // Update tabel users

            $stmt = $pdo->prepare("
                UPDATE users

                SET
                    username = :username,
                    email = :email

                WHERE id = :id
                AND role = 'guru'
            ");

            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'id' => $teacher['user_id']
            ]);

            $pdo->commit();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header(
                'Location: detail_guru.php?id=' .
                $teacherId . '&updated=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = 'Gagal memperbarui data guru. Silakan coba kembali.';
        }
    }
}

// =====================================
// 8. HELPER OUTPUT
// =====================================

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

<title>Edit Guru - EduStudent</title>

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

.container {
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

.heading {
    margin-bottom: 25px;
}

.heading h1 {
    font-size: 25px;
    margin-bottom: 8px;
}

.heading p {
    color: #64748b;
    font-size: 14px;
}

.form-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 30px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    font-size: 17px;
    margin-bottom: 7px;
}

.form-section > p {
    color: #94a3b8;
    font-size: 12px;
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

input, select, textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #dbe2ed;
    border-radius: 8px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
}

input:focus, select:focus, textarea:focus {
    border-color: #2563eb;
}

textarea {
    min-height: 100px;
    resize: vertical;
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

<div class="container">

    <a href="detail_guru.php?id=<?= (int) $teacherId ?>"
       class="back-link">
        ← Kembali ke Detail Guru
    </a>

    <div class="heading">

        <h1>Edit Profil Guru</h1>

        <p>
            Perbarui informasi identitas dan akun guru.
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

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <!-- IDENTITAS GURU -->

            <div class="form-section">

                <h3>Informasi Pribadi</h3>

                <p>
                    Perbarui data identitas guru.
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

                            <option value="L"
                                <?= $gender === 'L' ? 'selected' : '' ?>>
                                Laki-laki
                            </option>

                            <option value="P"
                                <?= $gender === 'P' ? 'selected' : '' ?>>
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
                            value="<?= e($phone) ?>"
                        >

                    </div>

                    <div class="form-group full">

                        <label>Alamat</label>

                        <textarea
                            name="address"
                        ><?= e($address) ?></textarea>

                    </div>

                </div>

            </div>

            <!-- AKUN -->

            <div class="form-section">

                <h3>Informasi Akun</h3>

                <p>
                    Perbarui username dan email untuk login guru.
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
                            value="<?= e($username) ?>"
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
                            maxlength="100"
                            value="<?= e($email) ?>"
                            required
                        >

                    </div>

                </div>

            </div>

            <div class="form-actions">

                <a
                    href="detail_guru.php?id=<?= (int) $teacherId ?>"
                    class="btn btn-cancel"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="btn btn-submit"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
