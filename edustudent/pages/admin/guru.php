
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// 1. CSRF TOKEN
// =====================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================
// 2. PENCARIAN GURU
// =====================================

$search = trim($_GET['search'] ?? '');

// =====================================
// 3. QUERY DATA GURU
// =====================================

$sql = "
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

    WHERE u.role = 'guru'
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            t.full_name LIKE :name
            OR t.nip LIKE :nip
            OR u.username LIKE :username
            OR u.email LIKE :email
        )
    ";

    $keyword = "%{$search}%";

    $params = [
        'name' => $keyword,
        'nip' => $keyword,
        'username' => $keyword,
        'email' => $keyword
    ];
}

$sql .= " ORDER BY t.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// 4. TOTAL GURU
// =====================================

$totalTeachers = count($teachers);

// =====================================
// 5. HELPER OUTPUT
// =====================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Guru - EduStudent</title>

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

/* STATISTIC */

.stat-card {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 23px;
    margin-bottom: 25px;
    max-width: 300px;
}

.stat-card span {
    font-size: 13px;
    color: #64748b;
}

.stat-card h2 {
    font-size: 30px;
    color: #2563eb;
    margin-top: 12px;
}

/* PANEL */

.admin-panel {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 12px;
    padding: 23px;
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

/* NOTIFICATION */

.success-message {
    padding: 14px 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
}

.error-message {
    padding: 14px 16px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 20px;
}

/* TOOLBAR */

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.search-form {
    display: flex;
    gap: 8px;
    flex: 1;
    max-width: 550px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 11px 13px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
}

.search-form input:focus {
    border-color: #2563eb;
}

.search-form button {
    padding: 11px 18px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.btn-add {
    display: inline-block;
    padding: 12px 18px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    white-space: nowrap;
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

th, td {
    text-align: left;
    padding: 15px;
    border-bottom: 1px solid #edf0f4;
    font-size: 13px;
}

th {
    color: #64748b;
    font-size: 12px;
    background: #fafbfc;
}

td strong {
    color: #263247;
}

.badge-gender {
    display: inline-block;
    background: #eff6ff;
    color: #2563eb;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 11px;
}

/* ACTION BUTTONS */

.btn-detail {
    display: inline-block;
    padding: 7px 12px;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
}

.btn-delete {
    padding: 7px 12px;
    background: #fee2e2;
    color: #dc2626;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    margin-left: 5px;
}

.btn-delete:hover {
    background: #fecaca;
}

.delete-form {
    display: inline;
}

.empty-data {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
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

    .toolbar {
        align-items: stretch;
    }

    .search-form {
        width: 100%;
    }

    .btn-add {
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

        <header class="admin-navbar">

            <h3>Manajemen Guru</h3>

            <div class="admin-user">

                Administrator

                <span>Admin EduStudent</span>

            </div>

        </header>

        <section class="admin-content">

            <div class="welcome">

                <h1>Data Guru</h1>

                <p>
                    Kelola dan lihat seluruh data guru yang terdaftar.
                </p>

            </div>

            <!-- NOTIFIKASI BERHASIL -->

            <?php if (isset($_GET['created'])): ?>

                <div class="success-message">
                    Data guru berhasil ditambahkan!
                </div>

            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>

                <div class="success-message">
                    Data guru dan akun login berhasil dihapus!
                </div>

            <?php endif; ?>

            <!-- NOTIFIKASI GAGAL -->

            <?php if (isset($_GET['error'])): ?>

                <div class="error-message">

                    <?php

                    $error = $_GET['error'];

                    if ($error === 'delete_failed') {
                        echo 'Penghapusan gagal. Guru mungkin masih memiliki data terkait.';
                    } elseif ($error === 'notfound') {
                        echo 'Data guru tidak ditemukan.';
                    } elseif ($error === 'invalid_request') {
                        echo 'Permintaan tidak valid. Silakan muat ulang halaman.';
                    } else {
                        echo 'Permintaan tidak dapat diproses.';
                    }

                    ?>

                </div>

            <?php endif; ?>

            <!-- STATISTIK -->

            <div class="stat-card">

                <span>Total Guru Terdaftar</span>

                <h2><?= $totalTeachers ?></h2>

            </div>

            <!-- DAFTAR GURU -->

            <div class="admin-panel">

                <div class="panel-heading">

                    <h3>Daftar Guru</h3>

                    <p>
                        Informasi seluruh guru di EduStudent.
                    </p>

                </div>

                <!-- TOOLBAR -->

                <div class="toolbar">

                    <form method="GET" class="search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari nama, NIP, username, email..."
                            value="<?= e($search) ?>"
                        >

                        <button type="submit">
                            Cari
                        </button>

                        <?php if ($search !== ''): ?>

                            <a href="guru.php">
                                Reset
                            </a>

                        <?php endif; ?>

                    </form>

                    <a href="tambah_guru.php" class="btn-add">
                        + Tambah Guru
                    </a>

                </div>

                <!-- TABEL -->

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>No.</th>
                                <th>NIP</th>
                                <th>Nama Guru</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Jenis Kelamin</th>
                                <th>Telepon</th>
                                <th>Aksi</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($teachers)): ?>

                                <?php foreach ($teachers as $index => $teacher): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= e($teacher['nip']) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($teacher['full_name']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($teacher['username']) ?>
                                        </td>

                                        <td>
                                            <?= e($teacher['email']) ?>
                                        </td>

                                        <td>

                                            <span class="badge-gender">

                                                <?= $teacher['gender'] === 'L'
                                                    ? 'Laki-laki'
                                                    : ($teacher['gender'] === 'P'
                                                        ? 'Perempuan'
                                                        : '-') ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= e($teacher['phone']) ?>
                                        </td>

                                        <!-- AKSI -->

                                        <td style="white-space:nowrap;">

                                            <a
                                                href="detail_guru.php?id=<?= (int) $teacher['id'] ?>"
                                                class="btn-detail"
                                            >
                                                Detail
                                            </a>

                                            <form
                                                action="hapus_guru.php"
                                                method="POST"
                                                class="delete-form"
                                                onsubmit="return confirm('Apakah kamu yakin ingin menghapus guru ini beserta akun loginnya?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $teacher['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-delete"
                                                >
                                                    Hapus
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="8" class="empty-data">

                                        <?php if ($search !== ''): ?>

                                            Data guru tidak ditemukan.

                                        <?php else: ?>

                                            Belum ada data guru.

                                        <?php endif; ?>

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
