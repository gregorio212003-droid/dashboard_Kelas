
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ==========================================
// FUNGSI KEAMANAN
// ==========================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==========================================
// VALIDASI ID
// ==========================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    header('Location: siswa.php');
    exit;
}

// ==========================================
// AMBIL DATA SISWA
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        s.*,
        u.username,
        u.email,
        c.class_name,
        c.grade_level,
        c.major

    FROM students s

    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id

    WHERE s.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: siswa.php');
    exit;
}

if (empty($student['user_id'])) {
    die('Akun pengguna siswa belum terhubung.');
}

// ==========================================
// AMBIL DAFTAR KELAS
// ==========================================

$classStmt = $pdo->query("
    SELECT id, class_name, grade_level, major
    FROM classes
    ORDER BY grade_level ASC, class_name ASC
");

$classes = $classStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// NILAI AWAL FORM
// ==========================================

$nis       = $student['nis'];
$nisn      = $student['nisn'];
$fullName  = $student['full_name'];
$gender    = $student['gender'];
$birthDate = $student['birth_date'];
$address   = $student['address'];
$classId   = $student['class_id'];
$username  = $student['username'];
$email     = $student['email'];

$errors = [];
$success = false;

// ==========================================
// PROSES UPDATE
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil input

    $nis       = trim($_POST['nis'] ?? '');
    $nisn      = trim($_POST['nisn'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $gender    = $_POST['gender'] ?? '';
    $birthDate = trim($_POST['birth_date'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $classId   = (int) ($_POST['class_id'] ?? 0);
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // --------------------------------------
    // VALIDASI FORM
    // --------------------------------------

    if ($nis === '') {
        $errors[] = 'NIS wajib diisi.';
    }

    if ($fullName === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if (!in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    if ($birthDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $birthDate);

        if (!$date || $date->format('Y-m-d') !== $birthDate) {
            $errors[] = 'Tanggal lahir tidak valid.';
        }
    }

    if ($classId < 1) {
        $errors[] = 'Silakan pilih kelas.';
    }

    // --------------------------------------
    // VALIDASI KELAS
    // --------------------------------------

    $checkClass = $pdo->prepare("
        SELECT COUNT(*)
        FROM classes
        WHERE id = :id
    ");

    $checkClass->execute(['id' => $classId]);

    if ((int) $checkClass->fetchColumn() === 0) {
        $errors[] = 'Kelas yang dipilih tidak ditemukan.';
    }

    // --------------------------------------
    // CEK DUPLIKAT NIS
    // --------------------------------------

    $checkNis = $pdo->prepare("
        SELECT COUNT(*)
        FROM students
        WHERE nis = :nis
        AND id != :id
    ");

    $checkNis->execute([
        'nis' => $nis,
        'id' => $id
    ]);

    if ((int) $checkNis->fetchColumn() > 0) {
        $errors[] = 'NIS sudah digunakan siswa lain.';
    }

    // --------------------------------------
    // CEK DUPLIKAT NISN
    // --------------------------------------

    if ($nisn !== '') {

        $checkNisn = $pdo->prepare("
            SELECT COUNT(*)
            FROM students
            WHERE nisn = :nisn
            AND id != :id
        ");

        $checkNisn->execute([
            'nisn' => $nisn,
            'id' => $id
        ]);

        if ((int) $checkNisn->fetchColumn() > 0) {
            $errors[] = 'NISN sudah digunakan siswa lain.';
        }
    }

    // --------------------------------------
    // CEK DUPLIKAT USERNAME
    // --------------------------------------

    $checkUsername = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE username = :username
        AND id != :id
    ");

    $checkUsername->execute([
        'username' => $username,
        'id' => $student['user_id']
    ]);

    if ((int) $checkUsername->fetchColumn() > 0) {
        $errors[] = 'Username sudah digunakan.';
    }

    // --------------------------------------
    // CEK DUPLIKAT EMAIL
    // --------------------------------------

    $checkEmail = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE email = :email
        AND id != :id
    ");

    $checkEmail->execute([
        'email' => $email,
        'id' => $student['user_id']
    ]);

    if ((int) $checkEmail->fetchColumn() > 0) {
        $errors[] = 'Email sudah digunakan.';
    }

    // --------------------------------------
    // SIMPAN PERUBAHAN
    // --------------------------------------

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            // Update tabel students

            $updateStudent = $pdo->prepare("
                UPDATE students SET

                    nis = :nis,
                    nisn = :nisn,
                    full_name = :full_name,
                    gender = :gender,
                    birth_date = :birth_date,
                    address = :address,
                    class_id = :class_id

                WHERE id = :id
            ");

            $updateStudent->execute([

                'nis' => $nis,

                'nisn' => $nisn !== ''
                    ? $nisn
                    : null,

                'full_name' => $fullName,

                'gender' => $gender,

                'birth_date' => $birthDate !== ''
                    ? $birthDate
                    : null,

                'address' => $address !== ''
                    ? $address
                    : null,

                'class_id' => $classId,

                'id' => $id

            ]);

            // Update tabel users

            $updateUser = $pdo->prepare("
                UPDATE users SET

                    username = :username,
                    email = :email

                WHERE id = :user_id
                AND role = 'siswa'
            ");

            $updateUser->execute([

                'username' => $username,

                'email' => $email,

                'user_id' => $student['user_id']

            ]);

            if ($updateUser->rowCount() === 0) {

                $checkUser = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE id = :id
                    AND role = 'siswa'
                ");

                $checkUser->execute([
                    'id' => $student['user_id']
                ]);

                if (!$checkUser->fetch()) {
                    throw new Exception(
                        'Akun siswa tidak ditemukan atau role tidak sesuai.'
                    );
                }
            }

            $pdo->commit();

            // Redirect setelah berhasil

            header(
                'Location: detail_siswa.php?id=' . $id . '&updated=1'
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Siswa - EduStudent</title>

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

/* SIDEBAR */

.sidebar {
    width: 245px;
    background: white;
    border-right: 1px solid #e5eaf1;
    padding: 25px 20px;
    flex-shrink: 0;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: #2563eb;
    margin-bottom: 40px;
}

.menu-title {
    font-size: 11px;
    color: #94a3b8;
    font-weight: bold;
    margin: 25px 0 12px;
}

.sidebar a {
    display: block;
    text-decoration: none;
    color: #64748b;
    padding: 13px 12px;
    font-size: 14px;
    border-radius: 8px;
    margin-bottom: 4px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #eff6ff;
    color: #2563eb;
}

/* MAIN */

.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    height: 75px;
    background: white;
    border-bottom: 1px solid #e5eaf1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.admin-info {
    font-size: 14px;
    font-weight: bold;
    text-align: right;
}

.admin-info small {
    display: block;
    color: #94a3b8;
    margin-top: 5px;
}

/* CONTENT */

.content {
    padding: 30px;
}

.heading {
    margin-bottom: 25px;
}

.heading h1 {
    font-size: 24px;
    margin-bottom: 8px;
}

.heading p {
    font-size: 14px;
    color: #64748b;
}

/* FORM CARD */

.form-card {
    background: white;
    border: 1px solid #e5eaf1;
    border-radius: 12px;
    padding: 28px;
    max-width: 950px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    font-size: 17px;
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
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 9px;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dbe3ee;
    border-radius: 8px;
    font-size: 14px;
    font-family: Arial, sans-serif;
    outline: none;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #2563eb;
}

textarea {
    resize: vertical;
    min-height: 100px;
}

.required {
    color: #ef4444;
}

.help {
    color: #94a3b8;
    font-size: 11px;
    margin-top: 7px;
}

.divider {
    border: none;
    border-top: 1px solid #edf0f4;
    margin: 25px 0;
}

/* BUTTON */

.buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    display: inline-block;
    padding: 12px 20px;
    border-radius: 8px;
    border: none;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
}

.btn-save {
    background: #2563eb;
    color: white;
}

.btn-cancel {
    background: #eef2f7;
    color: #475569;
}

.btn:hover {
    opacity: 0.85;
}

/* ALERT */

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}

.alert-error ul {
    padding-left: 20px;
    margin-top: 8px;
}

/* RESPONSIVE */

@media(max-width: 850px) {

    .sidebar {
        display: none;
    }

    .content {
        padding: 20px;
    }

    .topbar {
        padding: 0 20px;
    }

}

@media(max-width: 600px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .form-card {
        padding: 18px;
    }

    .buttons {
        flex-direction: column;
    }

    .buttons .btn {
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

        <div class="menu-title">
            MENU UTAMA
        </div>

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

        <div class="menu-title">
            LAINNYA
        </div>

        <a href="#">
            Pengumuman
        </a>

        <a href="#">
            Pengaturan
        </a>

    </aside>

    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <h3>Edit Data Siswa</h3>

            <div class="admin-info">

                Administrator

                <small>Admin EduStudent</small>

            </div>

        </header>

        <section class="content">

            <div class="heading">

                <h1>Edit Data Siswa</h1>

                <p>
                    Perbarui informasi identitas dan akun siswa.
                </p>

            </div>

            <div class="form-card">

                <!-- ALERT ERROR -->

                <?php if (!empty($errors)): ?>

                    <div class="alert alert-error">

                        <strong>
                            Terdapat kesalahan:
                        </strong>

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= e($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $id ?>"
                    >

                    <!-- IDENTITAS -->

                    <div class="form-section">

                        <h3>Informasi Identitas Siswa</h3>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    NIS <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="nis"
                                    value="<?= e($nis) ?>"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>NISN</label>

                                <input
                                    type="text"
                                    name="nisn"
                                    value="<?= e($nisn) ?>"
                                >

                            </div>

                            <div class="form-group full">

                                <label>
                                    Nama Lengkap
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="full_name"
                                    value="<?= e($fullName) ?>"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Jenis Kelamin
                                    <span class="required">*</span>
                                </label>

                                <select name="gender" required>

                                    <option value="">
                                        Pilih Jenis Kelamin
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

                                <label>Tanggal Lahir</label>

                                <input
                                    type="date"
                                    name="birth_date"
                                    value="<?= e($birthDate) ?>"
                                >

                            </div>

                            <div class="form-group full">

                                <label>Alamat</label>

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

                        <div class="form-grid">

                            <div class="form-group full">

                                <label>
                                    Kelas
                                    <span class="required">*</span>
                                </label>

                                <select name="class_id" required>

                                    <option value="">
                                        Pilih Kelas
                                    </option>

                                    <?php foreach ($classes as $class): ?>

                                        <option
                                            value="<?= (int) $class['id'] ?>"
                                            <?= (int) $classId === (int) $class['id'] ? 'selected' : '' ?>
                                        >

                                            <?= e($class['class_name']) ?>

                                            - Kelas <?= e($class['grade_level']) ?>

                                            <?= !empty($class['major'])
                                                ? ' - ' . e($class['major'])
                                                : '' ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <?php if (empty($classes)): ?>

                                    <small class="help">
                                        Belum ada kelas. Tambahkan kelas terlebih dahulu.
                                    </small>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <hr class="divider">

                    <!-- AKUN -->

                    <div class="form-section">

                        <h3>Informasi Akun Login</h3>

                        <div class="form-grid">

                            <div class="form-group">

                                <label>
                                    Username
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    value="<?= e($username) ?>"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Email
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    value="<?= e($email) ?>"
                                    required
                                >

                            </div>

                        </div>

                        <small class="help">
                            Password akun siswa tidak diubah melalui halaman ini.
                        </small>

                    </div>

                    <!-- BUTTON -->

                    <div class="buttons">

                        <a
                            href="detail_siswa.php?id=<?= (int) $id ?>"
                            class="btn btn-cancel"
                        >
                            Batal
                        </a>

                        <button
                            type="submit"
                            class="btn btn-save"
                            <?= empty($classes) ? 'disabled' : '' ?>
                        >
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>

</div>

</body>

</html>
