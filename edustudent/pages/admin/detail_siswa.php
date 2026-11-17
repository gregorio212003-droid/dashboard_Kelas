
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ========================================
// 1. VALIDASI ID SISWA
// ========================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: siswa.php');
    exit;
}

// ========================================
// 2. AMBIL DATA SISWA
// ========================================

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.user_id,
        s.nis,
        s.nisn,
        s.full_name,
        s.gender,
        s.birth_date,
        s.address,

        u.username,
        u.email,
        u.role,

        c.class_name,
        c.grade_level,
        c.major

    FROM students s

    LEFT JOIN users u
        ON s.user_id = u.id

    LEFT JOIN classes c
        ON s.class_id = c.id

    WHERE s.id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

// ========================================
// 3. JIKA SISWA TIDAK DITEMUKAN
// ========================================

if (!$student) {
    header('Location: siswa.php?error=not_found');
    exit;
}

// ========================================
// 4. HELPER
// ========================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function displayValue($value) {
    return ($value !== null && $value !== '')
        ? e($value)
        : '-';
}

// Format tanggal

$birthDateFormatted = '-';

if (!empty($student['birth_date'])) {

    $date = strtotime($student['birth_date']);

    if ($date !== false) {
        $birthDateFormatted = date('d-m-Y', $date);
    }
}

// Format jenis kelamin

$gender = '-';

if ($student['gender'] === 'L') {
    $gender = 'Laki-laki';
} elseif ($student['gender'] === 'P') {
    $gender = 'Perempuan';
}

// Format kelas

$className = '-';

if (!empty($student['class_name'])) {

    $className = $student['class_name'];

    if (!empty($student['grade_level'])) {
        $className .= ' - Kelas ' . $student['grade_level'];
    }

    if (!empty($student['major'])) {
        $className .= ' - ' . $student['major'];
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Detail Siswa - EduStudent</title>

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

.nav a:hover,
.nav a.active {
    background: #eff6ff;
    color: #2563eb;
}

/* MAIN */

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

/* CONTENT */

.content {
    padding: 30px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
}

.page-heading h1 {
    font-size: 24px;
    margin-bottom: 8px;
}

.page-heading p {
    color: #64748b;
    font-size: 14px;
}

/* BUTTON */

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    display: inline-block;
    border: none;
    cursor: pointer;
}

.btn-back {
    background: #e9eef6;
    color: #475569;
}

.btn-edit {
    background: #2563eb;
    color: white;
}

.btn:hover {
    opacity: 0.85;
}

/* PROFILE */

.profile-card {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 22px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 18px;
    padding-bottom: 25px;
    border-bottom: 1px solid #edf0f4;
}

.avatar {
    width: 75px;
    height: 75px;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 28px;
    font-weight: bold;
}

.profile-header h2 {
    font-size: 21px;
    margin-bottom: 8px;
}

.profile-header p {
    color: #94a3b8;
    font-size: 13px;
}

.badge {
    display: inline-block;
    background: #eff6ff;
    color: #2563eb;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    margin-top: 8px;
}

/* INFORMATION */

.section-title {
    font-size: 16px;
    margin-bottom: 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
}

.info-item {
    padding-bottom: 14px;
    border-bottom: 1px solid #edf0f4;
}

.info-item label {
    display: block;
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 8px;
}

.info-item strong {
    font-size: 14px;
    color: #263247;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.divider {
    border: none;
    border-top: 1px solid #edf0f4;
    margin: 25px 0;
}

/* RESPONSIVE */

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

    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .profile-header h2 {
        font-size: 17px;
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

    <!-- MAIN CONTENT -->

    <main class="main">

        <header class="navbar">

            <h3>Detail Siswa</h3>

            <div class="admin-user">

                Administrator

                <span>Admin EduStudent</span>

            </div>

        </header>

        <section class="content">

            <!-- PAGE HEADING -->

            <div class="page-heading">

                <div>

                    <h1>Detail Siswa</h1>

                    <p>
                        Informasi lengkap identitas dan akun siswa.
                    </p>

                </div>

                <div class="actions">

                    <a href="siswa.php" class="btn btn-back">
                        Kembali
                    </a>

                    <a
                        href="edit_siswa.php?id=<?= (int) $student['id'] ?>"
                        class="btn btn-edit"
                    >
                        Edit Siswa
                    </a>

                </div>

            </div>

            <!-- PROFILE CARD -->

            <div class="profile-card">

                <div class="profile-header">

                    <div class="avatar">

                        <?= strtoupper(
                            substr($student['full_name'], 0, 1)
                        ) ?>

                    </div>

                    <div>

                        <h2>
                            <?= e($student['full_name']) ?>
                        </h2>

                        <p>
                            NIS: <?= displayValue($student['nis']) ?>
                        </p>

                        <span class="badge">
                            <?= e($className) ?>
                        </span>

                    </div>

                </div>

                <!-- IDENTITAS -->

                <div style="padding-top:25px;">

                    <h3 class="section-title">
                        Informasi Identitas
                    </h3>

                    <div class="info-grid">

                        <div class="info-item">

                            <label>NIS</label>

                            <strong>
                                <?= displayValue($student['nis']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>NISN</label>

                            <strong>
                                <?= displayValue($student['nisn']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Nama Lengkap</label>

                            <strong>
                                <?= e($student['full_name']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Jenis Kelamin</label>

                            <strong>
                                <?= e($gender) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Tanggal Lahir</label>

                            <strong>
                                <?= e($birthDateFormatted) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Alamat</label>

                            <strong>
                                <?= displayValue($student['address']) ?>
                            </strong>

                        </div>

                    </div>

                </div>

                <hr class="divider">

                <!-- AKADEMIK -->

                <div>

                    <h3 class="section-title">
                        Informasi Akademik
                    </h3>

                    <div class="info-grid">

                        <div class="info-item">

                            <label>Kelas</label>

                            <strong>
                                <?= e($className) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>ID Siswa</label>

                            <strong>
                                <?= (int) $student['id'] ?>
                            </strong>

                        </div>

                    </div>

                </div>

                <hr class="divider">

                <!-- AKUN -->

                <div>

                    <h3 class="section-title">
                        Informasi Akun Login
                    </h3>

                    <div class="info-grid">

                        <div class="info-item">

                            <label>Username</label>

                            <strong>
                                <?= displayValue($student['username']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Email</label>

                            <strong>
                                <?= displayValue($student['email']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>Role</label>

                            <strong>
                                <?= displayValue($student['role']) ?>
                            </strong>

                        </div>

                        <div class="info-item">

                            <label>User ID</label>

                            <strong>
                                <?= displayValue($student['user_id']) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

</body>

</html>
