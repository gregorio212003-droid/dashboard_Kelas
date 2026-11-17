
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Membuat CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi keamanan
function escapeHTML($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Pencarian siswa
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.*,
        c.class_name,
        c.grade_level,
        c.major,
        u.username,
        u.email
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
";

$params = [];

if ($search !== '') {

    $sql .= "
        WHERE
            s.full_name LIKE :search1
            OR s.nis LIKE :search2
            OR s.nisn LIKE :search3
            OR u.username LIKE :search4
            OR u.email LIKE :search5
            OR c.class_name LIKE :search6
    ";

    $keyword = "%{$search}%";

    $params = [
        'search1' => $keyword,
        'search2' => $keyword,
        'search3' => $keyword,
        'search4' => $keyword,
        'search5' => $keyword,
        'search6' => $keyword
    ];
}

$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik
$totalStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM students
")->fetchColumn();

$activeStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM students
    WHERE status = 'aktif'
")->fetchColumn();

$inactiveStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM students
    WHERE status = 'nonaktif'
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Siswa - EduStudent</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #26364b;
}

.sidebar {
    width: 245px;
    height: 100vh;
    background: white;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 20px;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
}

.logo {
    font-size: 24px;
    font-weight: bold;
    color: #2864ed;
    margin-bottom: 45px;
}

.menu-title {
    font-size: 11px;
    color: #94a3b8;
    font-weight: bold;
    margin: 25px 0 15px;
}

.sidebar a {
    display: block;
    text-decoration: none;
    color: #52647c;
    padding: 13px 12px;
    border-radius: 9px;
    margin-bottom: 5px;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #edf4ff;
    color: #2864ed;
}

.main {
    margin-left: 245px;
}

.topbar {
    height: 75px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
}

.topbar h3 {
    font-size: 17px;
}

.admin-info {
    text-align: right;
    font-size: 13px;
    font-weight: bold;
}

.content {
    padding: 30px;
}

.page-title {
    font-size: 25px;
    margin-bottom: 8px;
}

.description {
    color: #718096;
    font-size: 14px;
    margin-bottom: 28px;
}

.statistics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 13px;
    border: 1px solid #e2e8f0;
}

.stat-card p {
    color: #718096;
    font-size: 13px;
    margin-bottom: 12px;
}

.stat-card h2 {
    color: #2864ed;
    font-size: 30px;
}

.table-card {
    background: white;
    padding: 25px;
    border-radius: 13px;
    border: 1px solid #e2e8f0;
}

.table-card h3 {
    margin-bottom: 8px;
}

.subtitle {
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 22px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.search-form {
    display: flex;
    gap: 8px;
}

input[type="text"] {
    width: 350px;
    padding: 12px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    outline: none;
}

button,
.btn {
    border: none;
    padding: 11px 14px;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 12px;
    display: inline-block;
}

.btn-blue {
    background: #2864ed;
    color: white;
}

.btn-red {
    background: #fee2e2;
    color: #dc2626;
}

.btn-gray {
    background: #e2e8f0;
    color: #334155;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    white-space: nowrap;
}

th {
    background: #f8fafc;
    color: #64748b;
    padding: 15px;
    text-align: left;
}

td {
    padding: 17px 15px;
    border-bottom: 1px solid #e2e8f0;
}

.status {
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
}

.active-status {
    background: #dcfce7;
    color: #15803d;
}

.inactive-status {
    background: #fee2e2;
    color: #dc2626;
}

.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.actions form {
    display: inline;
}

.empty {
    text-align: center;
    color: #94a3b8;
    padding: 35px;
}

.message {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

@media(max-width: 900px) {

    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
    }

    .statistics {
        grid-template-columns: 1fr;
    }

}

@media(max-width: 600px) {

    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
    }

    .content {
        padding: 15px;
    }

    input[type="text"] {
        width: 200px;
    }

}

</style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="logo">EduStudent</div>

    <div class="menu-title">MENU UTAMA</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="guru.php">Data Guru</a>
    <a href="siswa.php" class="active">Data Siswa</a>
    <a href="kelas.php">Data Kelas</a>
    <a href="mapel.php">Mata Pelajaran</a>
    <a href="jadwal.php">Jadwal Pelajaran</a>
    <a href="tahun_ajaran.php">Tahun Ajaran</a>

    <div class="menu-title">LAINNYA</div>

    <a href="pengumuman.php">Pengumuman</a>
    <a href="pengaturan.php">Pengaturan</a>

</div>

<!-- MAIN CONTENT -->

<div class="main">

    <div class="topbar">

        <h3>Manajemen Siswa</h3>

        <div class="admin-info">
            Administrator
            <br>
            <span style="color:#94a3b8;font-weight:normal;">
                Admin EduStudent
            </span>
        </div>

    </div>

    <div class="content">

        <h2 class="page-title">Data Siswa</h2>

        <p class="description">
            Kelola dan lihat seluruh data siswa yang terdaftar.
        </p>

        <!-- NOTIFIKASI -->

        <?php if (isset($_GET['success'])): ?>

            <?php if ($_GET['success'] === 'nonaktif'): ?>

                <div class="message success">
                    Siswa berhasil dinonaktifkan.
                </div>

            <?php elseif ($_GET['success'] === 'aktif'): ?>

                <div class="message success">
                    Siswa berhasil diaktifkan kembali.
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>

            <div class="message error">
                Terjadi kesalahan. Silakan periksa kembali data siswa.
            </div>

        <?php endif; ?>


        <!-- STATISTIK -->

        <div class="statistics">

            <div class="stat-card">
                <p>Total Siswa Terdaftar</p>
                <h2><?= $totalStudents ?></h2>
            </div>

            <div class="stat-card">
                <p>Siswa Aktif</p>
                <h2><?= $activeStudents ?></h2>
            </div>

            <div class="stat-card">
                <p>Siswa Nonaktif</p>
                <h2><?= $inactiveStudents ?></h2>
            </div>

        </div>


        <!-- TABEL SISWA -->

        <div class="table-card">

            <h3>Daftar Siswa</h3>

            <p class="subtitle">
                Informasi seluruh siswa di EduStudent.
            </p>

            <!-- PENCARIAN DAN TAMBAH -->

            <div class="toolbar">

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari nama, NIS, username, email..."
                        value="<?= escapeHTML($search) ?>"
                    >

                    <button type="submit" class="btn-blue">
                        Cari
                    </button>

                </form>

                <a href="tambah_siswa.php" class="btn btn-blue">
                    + Tambah Siswa
                </a>

            </div>


            <!-- TABEL -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($students) > 0): ?>

                        <?php $no = 1; ?>

                        <?php foreach ($students as $student): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td>
                                    <?= escapeHTML($student['nis']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= escapeHTML($student['full_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= escapeHTML(
                                        $student['class_name'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapeHTML(
                                        $student['username'] ?? '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapeHTML(
                                        $student['email'] ?? '-'
                                    ) ?>
                                </td>

                                <!-- STATUS -->

                                <td>

                                    <?php if ($student['status'] === 'aktif'): ?>

                                        <span class="status active-status">
                                            Aktif
                                        </span>

                                    <?php else: ?>

                                        <span class="status inactive-status">
                                            Nonaktif
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- AKSI -->

                                <td>

                                    <div class="actions">

                                        <!-- DETAIL -->

                                        <a
                                            href="detail_siswa.php?id=<?= (int) $student['id'] ?>"
                                            class="btn btn-blue"
                                        >
                                            Detail
                                        </a>


                                        <!-- EDIT -->

                                        <a
                                            href="edit_siswa.php?id=<?= (int) $student['id'] ?>"
                                            class="btn btn-gray"
                                        >
                                            Edit
                                        </a>


                                        <?php if ($student['status'] === 'aktif'): ?>

                                            <!-- NONAKTIFKAN -->

                                            <form
                                                method="POST"
                                                action="nonaktif_siswa.php"
                                                onsubmit="return confirm('Yakin ingin menonaktifkan siswa ini?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escapeHTML(
                                                        $_SESSION['csrf_token']
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int) $student['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-red"
                                                >
                                                    Nonaktifkan
                                                </button>

                                            </form>


                                        <?php else: ?>

                                            <!-- STATUS NONAKTIF -->

                                            <span class="status inactive-status">
                                                Nonaktif
                                            </span>


                                            <!-- AKTIFKAN -->

                                            <form
                                                method="POST"
                                                action="aktifkan_siswa.php"
                                                onsubmit="return confirm('Yakin ingin mengaktifkan kembali siswa ini?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escapeHTML(
                                                        $_SESSION['csrf_token']
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="student_id"
                                                    value="<?= (int) $student['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-blue"
                                                >
                                                    Aktifkan
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="8" class="empty">
                                Data siswa tidak ditemukan.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>
