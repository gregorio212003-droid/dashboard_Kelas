
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ======================================
// 1. MENGAMBIL ID GURU
// ======================================

$teacherId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$teacherId || $teacherId < 1) {
    header('Location: guru.php');
    exit;
}

// ======================================
// 2. MENGAMBIL DATA GURU DARI DATABASE
// ======================================

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
        u.email,
        u.created_at

    FROM teachers t

    INNER JOIN users u
        ON t.user_id = u.id

    WHERE t.id = :id
    AND u.role = 'guru'

    LIMIT 1
");

$stmt->execute([
    'id' => $teacherId
]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// ======================================
// 3. JIKA DATA TIDAK DITEMUKAN
// ======================================

if (!$teacher) {
    header('Location: guru.php?error=notfound');
    exit;
}

// ======================================
// 4. HELPER OUTPUT
// ======================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ======================================
// 5. INISIAL NAMA GURU
// ======================================

$initial = strtoupper(
    substr($teacher['full_name'], 0, 1)
);

// ======================================
// 6. TANGGAL PENDAFTARAN
// ======================================

$registeredDate = '-';

if (!empty($teacher['created_at'])) {
    $registeredDate = date(
        'd F Y',
        strtotime($teacher['created_at'])
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Detail Guru - EduStudent</title>

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

/* LAYOUT */

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.admin-sidebar {
    width: 245px;
    background: white;
    padding: 25px 20px;
    border-right: 1px solid #e8ebf0;
    flex-shrink: 0;
}

.admin-logo {
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

.admin-nav a {
    display: block;
    padding: 12px;
    text-decoration: none;
    color: #64748b;
    font-size: 14px;
    border-radius: 8px;
    margin-bottom: 5px;
}

.admin-nav a:hover,
.admin-nav a.active {
    background: #eff6ff;
    color: #2563eb;
}

/* MAIN */

.admin-main {
    flex: 1;
    min-width: 0;
}

/* NAVBAR */

.admin-navbar {
    height: 75px;
    background: white;
    border-bottom: 1px solid #e8ebf0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.admin-navbar h3 {
    font-size: 17px;
}

.admin-user {
    text-align: right;
    font-size: 14px;
    font-weight: bold;
}

.admin-user span {
    display: block;
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}

/* CONTENT */

.admin-content {
    padding: 30px;
}

.back-link {
    display: inline-block;
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 25px;
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

/* NOTIFICATION */

.success-message {
    padding: 14px 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 22px;
}

/* PROFILE */

.profile-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 25px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 25px;
    border-bottom: 1px solid #edf0f4;
}

.profile-avatar {
    width: 75px;
    height: 75px;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: bold;
    flex-shrink: 0;
}

.profile-name h2 {
    font-size: 21px;
    margin-bottom: 8px;
}

.profile-name p {
    font-size: 13px;
    color: #64748b;
}

.role-badge {
    display: inline-block;
    padding: 5px 10px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 6px;
    font-size: 11px;
    margin-top: 9px;
}

/* SECTION */

.section-title {
    font-size: 16px;
    margin-bottom: 20px;
}

.information-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.information-item {
    padding-bottom: 15px;
    border-bottom: 1px solid #edf0f4;
}

.information-item span {
    display: block;
    font-size: 12px;
    color: #94a3b8;
    margin-bottom: 8px;
}

.information-item strong {
    font-size: 14px;
    color: #263247;
    overflow-wrap: anywhere;
}

.full-width {
    grid-column: 1 / -1;
}

/* BUTTON */

.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 22px;
}

.btn {
    display: inline-block;
    padding: 11px 17px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    border: none;
    cursor: pointer;
}

.btn-edit {
    background: #2563eb;
    color: white;
}

.btn-edit:hover {
    background: #1d4ed8;
}

.btn-back {
    background: #e2e8f0;
    color: #475569;
}

/* ACCOUNT */

.account-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 25px;
}

.account-panel h3 {
    font-size: 16px;
    margin-bottom: 20px;
}

.account-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* RESPONSIVE */

@media(max-width: 800px) {

    .admin-sidebar {
        display: none;
    }

    .admin-content {
        padding: 20px 15px;
    }

    .admin-navbar {
        padding: 0 15px;
    }

}

@media(max-width: 550px) {

    .information-grid,
    .account-info {
        grid-template-columns: 1fr;
    }

    .full-width {
        grid-column: auto;
    }

    .profile-header {
        align-items: flex-start;
    }

    .profile-avatar {
        width: 55px;
        height: 55px;
        font-size: 23px;
    }

    .profile-name h2 {
        font-size: 17px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .action-buttons a {
        text-align: center;
    }

}

</style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="admin-sidebar">

        <div class="admin-logo">
            EduStudent
        </div>

        <div class="menu-label">
            MENU UTAMA
        </div>

        <nav class="admin-nav">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="guru.php" class="active">
                Data Guru
            </a>

            <a href="siswa.php">
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

        <nav class="admin-nav">

            <a href="#">
                Pengumuman
            </a>

            <a href="#">
                Pengaturan
            </a>

        </nav>

    </aside>

    <!-- MAIN -->

    <main class="admin-main">

        <!-- NAVBAR -->

        <header class="admin-navbar">

            <h3>Detail Guru</h3>

            <div class="admin-user">

                Administrator

                <span>Admin EduStudent</span>

            </div>

        </header>

        <!-- CONTENT -->

        <section class="admin-content">

            <a href="guru.php" class="back-link">
                ← Kembali ke Manajemen Guru
            </a>

            <div class="page-heading">

                <h1>Profil Guru</h1>

                <p>
                    Informasi lengkap identitas dan akun guru EduStudent.
                </p>

            </div>

            <!-- NOTIFIKASI UPDATE -->

            <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>

                <div class="success-message">
                    Data guru berhasil diperbarui!
                </div>

            <?php endif; ?>

            <!-- TOMBOL AKSI -->

            <div class="action-buttons">

                <a href="guru.php" class="btn btn-back">
                    Kembali
                </a>

                <a
                    href="edit_guru.php?id=<?= (int) $teacher['id'] ?>"
                    class="btn btn-edit"
                >
                    ✎ Edit Profil Guru
                </a>

            </div>

            <!-- PROFILE PANEL -->

            <div class="profile-panel">

                <div class="profile-header">

                    <div class="profile-avatar">

                        <?= e($initial) ?>

                    </div>

                    <div class="profile-name">

                        <h2>
                            <?= e($teacher['full_name']) ?>
                        </h2>

                        <p>
                            NIP: <?= e($teacher['nip']) ?>
                        </p>

                        <span class="role-badge">
                            Guru
                        </span>

                    </div>

                </div>

                <br><br>

                <!-- IDENTITAS GURU -->

                <h3 class="section-title">
                    Informasi Pribadi
                </h3>

                <div class="information-grid">

                    <div class="information-item">

                        <span>Nama Lengkap</span>

                        <strong>
                            <?= e($teacher['full_name']) ?>
                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Nomor Induk Pegawai (NIP)</span>

                        <strong>
                            <?= e($teacher['nip']) ?>
                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Jenis Kelamin</span>

                        <strong>

                            <?php

                            if ($teacher['gender'] === 'L') {
                                echo 'Laki-laki';
                            } elseif ($teacher['gender'] === 'P') {
                                echo 'Perempuan';
                            } else {
                                echo '-';
                            }

                            ?>

                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Nomor Telepon</span>

                        <strong>
                            <?= e($teacher['phone']) ?>
                        </strong>

                    </div>

                    <div class="information-item full-width">

                        <span>Alamat</span>

                        <strong>
                            <?= nl2br(e($teacher['address'])) ?>
                        </strong>

                    </div>

                </div>

            </div>

            <!-- ACCOUNT PANEL -->

            <div class="account-panel">

                <h3>Informasi Akun</h3>

                <div class="account-info">

                    <div class="information-item">

                        <span>Username</span>

                        <strong>
                            <?= e($teacher['username']) ?>
                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Email</span>

                        <strong>
                            <?= e($teacher['email']) ?>
                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Role Akun</span>

                        <strong>
                            Guru
                        </strong>

                    </div>

                    <div class="information-item">

                        <span>Tanggal Terdaftar</span>

                        <strong>
                            <?= e($registeredDate) ?>
                        </strong>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

</body>

</html>
