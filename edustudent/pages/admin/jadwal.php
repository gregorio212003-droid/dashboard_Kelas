
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// CSRF TOKEN
// =====================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================
// HELPER
// =====================================

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$pesan = '';
$tipe_pesan = '';

// Ambil pilihan hari langsung dari ENUM database
$column = $pdo->query(
    "SHOW COLUMNS FROM schedules LIKE 'day_of_week'"
)->fetch(PDO::FETCH_ASSOC);

$hari_list = [];

if ($column && preg_match('/^enum\((.*)\)$/i', $column['Type'], $matches)) {
    $hari_list = str_getcsv($matches[1], ',', "'");
}

// =====================================
// PROSES TAMBAH DAN EDIT
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['tambah']) || isset($_POST['edit']))) {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Token keamanan tidak valid. Silakan muat ulang halaman.');
    }

    $is_edit = isset($_POST['edit']);

    $id = (int)($_POST['id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $day = $_POST['day_of_week'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $room = trim($_POST['room'] ?? '');

    if (
        $class_id <= 0 ||
        $subject_id <= 0 ||
        $teacher_id <= 0 ||
        !in_array($day, $hari_list, true) ||
        !preg_match('/^\d{2}:\d{2}$/', $start) ||
        !preg_match('/^\d{2}:\d{2}$/', $end) ||
        $start >= $end
    ) {
        $pesan = 'Mohon periksa kembali data jadwal. Jam selesai harus lebih besar dari jam mulai.';
        $tipe_pesan = 'error';

    } else {

        try {

            // Pastikan kelas tersedia
            $cek = $pdo->prepare(
                "SELECT COUNT(*) FROM classes WHERE id = ?"
            );
            $cek->execute([$class_id]);

            if ((int)$cek->fetchColumn() === 0) {
                throw new Exception('Kelas tidak ditemukan.');
            }

            // Pastikan mata pelajaran tersedia
            $cek = $pdo->prepare(
                "SELECT COUNT(*) FROM subjects WHERE id = ?"
            );
            $cek->execute([$subject_id]);

            if ((int)$cek->fetchColumn() === 0) {
                throw new Exception('Mata pelajaran tidak ditemukan.');
            }

            // Pastikan guru tersedia
            $cek = $pdo->prepare(
                "SELECT COUNT(*) FROM teachers WHERE id = ?"
            );
            $cek->execute([$teacher_id]);

            if ((int)$cek->fetchColumn() === 0) {
                throw new Exception('Guru tidak ditemukan.');
            }

            // =====================================
            // CEK JADWAL BENTROK
            // =====================================

            $sql = "
                SELECT COUNT(*)
                FROM schedules
                WHERE day_of_week = ?
                AND start_time < ?
                AND end_time > ?
                AND (
                    class_id = ?
                    OR teacher_id = ?
                )
            ";

            $params = [
                $day,
                $end,
                $start,
                $class_id,
                $teacher_id
            ];

            if ($is_edit) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }

            $cek = $pdo->prepare($sql);
            $cek->execute($params);

            if ((int)$cek->fetchColumn() > 0) {
                throw new Exception(
                    'Jadwal bentrok! Kelas atau guru sudah memiliki jadwal pada waktu tersebut.'
                );
            }

            // =====================================
            // SIMPAN DATA
            // =====================================

            if ($is_edit) {

                $stmt = $pdo->prepare("
                    UPDATE schedules
                    SET
                        class_id = ?,
                        subject_id = ?,
                        teacher_id = ?,
                        day_of_week = ?,
                        start_time = ?,
                        end_time = ?,
                        room = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $class_id,
                    $subject_id,
                    $teacher_id,
                    $day,
                    $start,
                    $end,
                    $room !== '' ? $room : null,
                    $id
                ]);

                header('Location: jadwal.php?status=edit');
                exit;

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO schedules (
                        class_id,
                        subject_id,
                        teacher_id,
                        day_of_week,
                        start_time,
                        end_time,
                        room
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $class_id,
                    $subject_id,
                    $teacher_id,
                    $day,
                    $start,
                    $end,
                    $room !== '' ? $room : null
                ]);

                header('Location: jadwal.php?status=tambah');
                exit;
            }

        } catch (Exception $ex) {
            $pesan = $ex->getMessage();
            $tipe_pesan = 'error';

        } catch (PDOException $ex) {
            $pesan = 'Gagal menyimpan jadwal. Periksa kembali database.';
            $tipe_pesan = 'error';
        }
    }
}

// =====================================
// PROSES HAPUS
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Token keamanan tidak valid.');
    }

    $id = (int)($_POST['id'] ?? 0);

    try {
        $stmt = $pdo->prepare(
            "DELETE FROM schedules WHERE id = ?"
        );

        $stmt->execute([$id]);

        header('Location: jadwal.php?status=hapus');
        exit;

    } catch (PDOException $ex) {
        $pesan = 'Jadwal gagal dihapus.';
        $tipe_pesan = 'error';
    }
}

// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['status'])) {

    $status = $_GET['status'];

    if ($status === 'tambah') {
        $pesan = 'Jadwal berhasil ditambahkan.';
        $tipe_pesan = 'success';

    } elseif ($status === 'edit') {
        $pesan = 'Jadwal berhasil diperbarui.';
        $tipe_pesan = 'success';

    } elseif ($status === 'hapus') {
        $pesan = 'Jadwal berhasil dihapus.';
        $tipe_pesan = 'success';
    }
}

// =====================================
// DATA MASTER
// =====================================

$classes = $pdo->query("
    SELECT id, class_name, grade_level, major
    FROM classes
    ORDER BY grade_level, class_name
")->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query("
    SELECT id, subject_code, subject_name
    FROM subjects
    ORDER BY subject_name
")->fetchAll(PDO::FETCH_ASSOC);

$teachers = $pdo->query("
    SELECT id, nip, full_name
    FROM teachers
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// PENCARIAN DAN FILTER
// =====================================

$search = trim($_GET['search'] ?? '');
$filter_hari = $_GET['hari'] ?? '';

$sql = "
    SELECT
        s.*,
        c.class_name,
        c.grade_level,
        c.major,
        sub.subject_code,
        sub.subject_name,
        t.full_name AS teacher_name,
        t.nip
    FROM schedules s
    INNER JOIN classes c ON s.class_id = c.id
    INNER JOIN subjects sub ON s.subject_id = sub.id
    INNER JOIN teachers t ON s.teacher_id = t.id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            c.class_name LIKE ?
            OR sub.subject_name LIKE ?
            OR sub.subject_code LIKE ?
            OR t.full_name LIKE ?
            OR s.room LIKE ?
        )
    ";

    $keyword = "%$search%";

    $params = [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ];
}

if (in_array($filter_hari, $hari_list, true)) {
    $sql .= " AND s.day_of_week = ?";
    $params[] = $filter_hari;
}

$sql .= "
    ORDER BY
        FIELD(s.day_of_week, 'Senin', 'Selasa', 'Rabu',
        'Kamis', 'Jumat', 'Sabtu', 'Minggu'),
        s.start_time ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// STATISTIK
// =====================================

$totalSchedules = (int)$pdo->query("
    SELECT COUNT(*) FROM schedules
")->fetchColumn();

$totalClasses = (int)$pdo->query("
    SELECT COUNT(*) FROM classes
")->fetchColumn();

$totalSubjects = (int)$pdo->query("
    SELECT COUNT(*) FROM subjects
")->fetchColumn();

// =====================================
// DATA EDIT
// =====================================

$data_edit = null;

if (isset($_GET['edit'])) {

    $id_edit = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM schedules WHERE id = ?
    ");

    $stmt->execute([$id_edit]);

    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Jadwal - EduStudent</title>

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
    color: #2563eb;
    font-weight: bold;
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
    text-decoration: none;
    color: #64748b;
    padding: 12px;
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
    font-size: 14px;
    font-weight: bold;
    text-align: right;
}

.admin-user span {
    color: #94a3b8;
    font-size: 12px;
    display: block;
    margin-top: 4px;
}

/* CONTENT */

.admin-content {
    padding: 30px;
}

.welcome {
    margin-bottom: 25px;
}

.welcome h1 {
    font-size: 24px;
    margin-bottom: 8px;
}

.welcome p {
    font-size: 14px;
    color: #64748b;
}

/* STATISTICS */

.statistics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 22px;
}

.stat-card span {
    color: #64748b;
    font-size: 13px;
}

.stat-card h2 {
    font-size: 29px;
    color: #2563eb;
    margin-top: 12px;
}

/* PANEL */

.admin-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 23px;
    margin-bottom: 25px;
}

.panel-heading {
    margin-bottom: 22px;
}

.panel-heading h3 {
    font-size: 17px;
    margin-bottom: 7px;
}

.panel-heading p {
    font-size: 12px;
    color: #94a3b8;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    margin-bottom: 8px;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 8px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce3ed;
    border-radius: 8px;
    outline: none;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #2563eb;
}

.form-actions {
    display: flex;
    gap: 9px;
    margin-top: 18px;
}

button,
.btn {
    border: none;
    padding: 10px 15px;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 12px;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-gray {
    background: #e2e8f0;
    color: #334155;
}

.btn-edit {
    background: #eff6ff;
    color: #2563eb;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-add {
    display: inline-block;
    padding: 11px 16px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
}

/* MESSAGE */

.message {
    padding: 14px 16px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
}

.success {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* TOOLBAR */

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 8px;
    flex: 1;
    max-width: 550px;
}

.search-form input,
.search-form select {
    padding: 11px;
    border: 1px solid #dce3ed;
    border-radius: 8px;
    min-width: 0;
}

.search-form input {
    flex: 1;
}

.search-form button {
    background: #2563eb;
    color: white;
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

th,
td {
    text-align: left;
    padding: 14px;
    border-bottom: 1px solid #edf0f4;
    font-size: 13px;
}

th {
    color: #64748b;
    font-size: 12px;
    background: #fafbfc;
}

.actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.actions form {
    display: inline;
}

.empty-data {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
}

.day-badge {
    display: inline-block;
    background: #eff6ff;
    color: #2563eb;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
}

.time {
    white-space: nowrap;
    font-weight: bold;
}

@media(max-width: 900px) {
    .admin-sidebar {
        display: none;
    }

    .admin-content {
        padding: 20px 15px;
    }

    .statistics {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 600px) {
    .admin-navbar {
        padding: 0 15px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .search-form {
        max-width: 100%;
        flex-wrap: wrap;
    }

    .search-form input {
        min-width: 100%;
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

            <a href="dashboard.php">Dashboard</a>

            <a href="guru.php">Data Guru</a>

            <a href="siswa.php">Data Siswa</a>

            <a href="kelas.php">Data Kelas</a>

            <a href="mapel.php">Mata Pelajaran</a>

            <a href="jadwal.php" class="active">
                Jadwal Pelajaran
            </a>

            <a href="tahun_ajaran.php">Tahun Ajaran</a>

        </nav>

        <div class="menu-label">
            LAINNYA
        </div>

        <nav class="admin-nav">

            <a href="pengumuman.php">Pengumuman</a>

            <a href="pengaturan.php">Pengaturan</a>

        </nav>

    </aside>

    <!-- MAIN -->

    <main class="admin-main">

        <header class="admin-navbar">

            <h3>Manajemen Jadwal</h3>

            <div class="admin-user">

                Administrator

                <span>Admin EduStudent</span>

            </div>

        </header>

        <section class="admin-content">

            <div class="welcome">

                <h1>Jadwal Pelajaran</h1>

                <p>
                    Kelola jadwal pelajaran, kelas, guru, dan ruangan.
                </p>

            </div>

            <!-- NOTIFIKASI -->

            <?php if ($pesan !== ''): ?>

                <div class="message <?= e($tipe_pesan) ?>">
                    <?= e($pesan) ?>
                </div>

            <?php endif; ?>

            <!-- STATISTIK -->

            <div class="statistics">

                <div class="stat-card">
                    <span>Total Jadwal</span>
                    <h2><?= $totalSchedules ?></h2>
                </div>

                <div class="stat-card">
                    <span>Total Kelas</span>
                    <h2><?= $totalClasses ?></h2>
                </div>

                <div class="stat-card">
                    <span>Total Mata Pelajaran</span>
                    <h2><?= $totalSubjects ?></h2>
                </div>

            </div>

            <!-- FORM TAMBAH / EDIT -->

            <div class="admin-panel">

                <div class="panel-heading">

                    <h3>
                        <?= $data_edit ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran' ?>
                    </h3>

                    <p>
                        Isi informasi jadwal pelajaran dengan benar.
                    </p>

                </div>

                <form method="POST" action="jadwal.php">

                    <?php if ($data_edit): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$data_edit['id'] ?>"
                        >

                    <?php endif; ?>

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label>Kelas</label>

                            <select name="class_id" required>

                                <option value="">
                                    -- Pilih Kelas --
                                </option>

                                <?php foreach ($classes as $class): ?>

                                    <option
                                        value="<?= (int)$class['id'] ?>"
                                        <?= (int)($data_edit['class_id'] ?? 0) === (int)$class['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($class['class_name']) ?>
                                        (Kelas <?= e($class['grade_level']) ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>Mata Pelajaran</label>

                            <select name="subject_id" required>

                                <option value="">
                                    -- Pilih Mata Pelajaran --
                                </option>

                                <?php foreach ($subjects as $subject): ?>

                                    <option
                                        value="<?= (int)$subject['id'] ?>"
                                        <?= (int)($data_edit['subject_id'] ?? 0) === (int)$subject['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($subject['subject_code']) ?>
                                        - <?= e($subject['subject_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>Guru Pengajar</label>

                            <select name="teacher_id" required>

                                <option value="">
                                    -- Pilih Guru --
                                </option>

                                <?php foreach ($teachers as $teacher): ?>

                                    <option
                                        value="<?= (int)$teacher['id'] ?>"
                                        <?= (int)($data_edit['teacher_id'] ?? 0) === (int)$teacher['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($teacher['full_name']) ?>
                                        (<?= e($teacher['nip'] ?: 'Tanpa NIP') ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>Hari</label>

                            <select name="day_of_week" required>

                                <option value="">
                                    -- Pilih Hari --
                                </option>

                                <?php foreach ($hari_list as $hari): ?>

                                    <option
                                        value="<?= e($hari) ?>"
                                        <?= ($data_edit['day_of_week'] ?? '') === $hari ? 'selected' : '' ?>
                                    >
                                        <?= e($hari) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>Jam Mulai</label>

                            <input
                                type="time"
                                name="start_time"
                                value="<?= e(isset($data_edit['start_time']) ? substr($data_edit['start_time'], 0, 5) : '') ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>Jam Selesai</label>

                            <input
                                type="time"
                                name="end_time"
                                value="<?= e(isset($data_edit['end_time']) ? substr($data_edit['end_time'], 0, 5) : '') ?>"
                                required
                            >

                        </div>

                        <div class="form-group full">

                            <label>Ruangan</label>

                            <input
                                type="text"
                                name="room"
                                maxlength="30"
                                placeholder="Contoh: Ruang 101"
                                value="<?= e($data_edit['room'] ?? '') ?>"
                            >

                        </div>

                    </div>

                    <div class="form-actions">

                        <?php if ($data_edit): ?>

                            <button
                                type="submit"
                                name="edit"
                                class="btn-primary"
                            >
                                Simpan Perubahan
                            </button>

                            <a href="jadwal.php" class="btn btn-gray">
                                Batal
                            </a>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="tambah"
                                class="btn-primary"
                            >
                                + Tambahkan Jadwal
                            </button>

                        <?php endif; ?>

                    </div>

                </form>

            </div>

            <!-- DAFTAR JADWAL -->

            <div class="admin-panel">

                <div class="panel-heading">

                    <h3>Daftar Jadwal Pelajaran</h3>

                    <p>
                        Seluruh jadwal pelajaran yang telah terdaftar.
                    </p>

                </div>

                <!-- SEARCH DAN FILTER -->

                <div class="toolbar">

                    <form method="GET" class="search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari kelas, guru, mata pelajaran..."
                            value="<?= e($search) ?>"
                        >

                        <select name="hari">

                            <option value="">
                                Semua Hari
                            </option>

                            <?php foreach ($hari_list as $hari): ?>

                                <option
                                    value="<?= e($hari) ?>"
                                    <?= $filter_hari === $hari ? 'selected' : '' ?>
                                >
                                    <?= e($hari) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button type="submit">
                            Cari
                        </button>

                        <a href="jadwal.php" class="btn btn-gray">
                            Reset
                        </a>

                    </form>

                </div>

                <!-- TABEL -->

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>No.</th>
                                <th>Hari</th>
                                <th>Kelas</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Jam</th>
                                <th>Ruangan</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($schedules)): ?>

                                <?php foreach ($schedules as $index => $schedule): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <span class="day-badge">
                                                <?= e($schedule['day_of_week']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($schedule['class_name']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($schedule['subject_name']) ?>
                                            <br>
                                            <small>
                                                <?= e($schedule['subject_code']) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?= e($schedule['teacher_name']) ?>
                                        </td>

                                        <td class="time">
                                            <?= e(substr($schedule['start_time'], 0, 5)) ?>
                                            -
                                            <?= e(substr($schedule['end_time'], 0, 5)) ?>
                                        </td>

                                        <td>
                                            <?= e($schedule['room'] ?: '-') ?>
                                        </td>

                                        <td>

                                            <div class="actions">

                                                <a
                                                    href="jadwal.php?edit=<?= (int)$schedule['id'] ?>"
                                                    class="btn btn-edit"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus jadwal ini?')"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$schedule['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="hapus"
                                                        class="btn-delete"
                                                    >
                                                        Hapus
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8" class="empty-data">
                                        Belum ada jadwal pelajaran.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>
