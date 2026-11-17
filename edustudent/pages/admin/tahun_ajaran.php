
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// SESSION DAN CSRF
// =====================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$pesan = '';
$tipe_pesan = '';

// =====================================
// PROSES TAMBAH DAN EDIT
// =====================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['tambah']) || isset($_POST['edit']))
) {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        die('Token keamanan tidak valid. Silakan muat ulang halaman.');
    }

    $is_edit = isset($_POST['edit']);

    $id = (int)($_POST['id'] ?? 0);
    $academic_year = trim($_POST['academic_year'] ?? '');
    $semester = $_POST['semester'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validasi format tahun ajaran
    $valid_year = preg_match(
        '/^\d{4}\/\d{4}$/',
        $academic_year
    );

    if ($valid_year) {
        [$year1, $year2] = explode('/', $academic_year);

        $valid_year = ((int)$year2 === (int)$year1 + 1);
    }

    // Validasi tanggal
    $valid_start = DateTime::createFromFormat('!Y-m-d', $start_date);
    $valid_end = DateTime::createFromFormat('!Y-m-d', $end_date);

    if (
        !$valid_year ||
        !in_array($semester, ['Ganjil', 'Genap'], true) ||
        !$valid_start ||
        $valid_start->format('Y-m-d') !== $start_date ||
        !$valid_end ||
        $valid_end->format('Y-m-d') !== $end_date ||
        $start_date >= $end_date
    ) {

        $pesan = 'Data tidak valid. Periksa format tahun ajaran dan tanggal.';
        $tipe_pesan = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // =====================================
            // CEK DATA DUPLIKAT
            // =====================================

            $sql_cek = "
                SELECT COUNT(*)
                FROM academic_years
                WHERE academic_year = ?
                AND semester = ?
            ";

            $params_cek = [$academic_year, $semester];

            if ($is_edit) {
                $sql_cek .= " AND id != ?";
                $params_cek[] = $id;
            }

            $cek = $pdo->prepare($sql_cek);
            $cek->execute($params_cek);

            if ((int)$cek->fetchColumn() > 0) {
                throw new Exception(
                    'Tahun ajaran dan semester tersebut sudah terdaftar.'
                );
            }

            // =====================================
            // TAMBAH DATA
            // =====================================

            if (!$is_edit) {

                if ($is_active === 1) {
                    $pdo->exec("
                        UPDATE academic_years
                        SET is_active = 0
                    ");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO academic_years (
                        academic_year,
                        semester,
                        start_date,
                        end_date,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $academic_year,
                    $semester,
                    $start_date,
                    $end_date,
                    $is_active
                ]);

                $pdo->commit();

                header('Location: tahun_ajaran.php?status=tambah');
                exit;

            } else {

                // Pastikan data yang diedit tersedia
                $cek = $pdo->prepare("
                    SELECT id
                    FROM academic_years
                    WHERE id = ?
                ");

                $cek->execute([$id]);

                if (!$cek->fetch()) {
                    throw new Exception('Data tahun ajaran tidak ditemukan.');
                }

                if ($is_active === 1) {
                    $pdo->exec("
                        UPDATE academic_years
                        SET is_active = 0
                    ");
                }

                $stmt = $pdo->prepare("
                    UPDATE academic_years
                    SET
                        academic_year = ?,
                        semester = ?,
                        start_date = ?,
                        end_date = ?,
                        is_active = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $academic_year,
                    $semester,
                    $start_date,
                    $end_date,
                    $is_active,
                    $id
                ]);

                $pdo->commit();

                header('Location: tahun_ajaran.php?status=edit');
                exit;
            }

        } catch (Exception $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = $ex->getMessage();
            $tipe_pesan = 'error';

        } catch (PDOException $ex) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = 'Gagal menyimpan data tahun ajaran.';
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

        $stmt = $pdo->prepare("
            SELECT is_active
            FROM academic_years
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new Exception('Tahun ajaran tidak ditemukan.');
        }

        if ((int)$data['is_active'] === 1) {
            throw new Exception(
                'Tahun ajaran yang sedang aktif tidak dapat dihapus. Aktifkan periode lain terlebih dahulu.'
            );
        }

        $stmt = $pdo->prepare("
            DELETE FROM academic_years
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        header('Location: tahun_ajaran.php?status=hapus');
        exit;

    } catch (Exception $ex) {

        $pesan = $ex->getMessage();
        $tipe_pesan = 'error';

    } catch (PDOException $ex) {

        $pesan = 'Gagal menghapus tahun ajaran.';
        $tipe_pesan = 'error';
    }
}

// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['status'])) {

    $status = $_GET['status'];

    if ($status === 'tambah') {
        $pesan = 'Tahun ajaran berhasil ditambahkan.';
        $tipe_pesan = 'success';

    } elseif ($status === 'edit') {
        $pesan = 'Tahun ajaran berhasil diperbarui.';

        $tipe_pesan = 'success';

    } elseif ($status === 'hapus') {
        $pesan = 'Tahun ajaran berhasil dihapus.';
        $tipe_pesan = 'success';
    }
}

// =====================================
// PENCARIAN
// =====================================

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT *
    FROM academic_years
    WHERE 1=1
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            academic_year LIKE ?
            OR semester LIKE ?
        )
    ";

    $keyword = "%{$search}%";

    $params = [$keyword, $keyword];
}

$sql .= "
    ORDER BY start_date DESC, semester ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// STATISTIK
// =====================================

$totalYears = (int)$pdo->query("
    SELECT COUNT(DISTINCT academic_year)
    FROM academic_years
")->fetchColumn();

$totalSemesters = (int)$pdo->query("
    SELECT COUNT(*)
    FROM academic_years
")->fetchColumn();

$activeYear = $pdo->query("
    SELECT *
    FROM academic_years
    WHERE is_active = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// =====================================
// DATA EDIT
// =====================================

$data_edit = null;

if (isset($_GET['edit'])) {

    $id_edit = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM academic_years
        WHERE id = ?
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

<title>Manajemen Tahun Ajaran - EduStudent</title>

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
    font-size: 27px;
    color: #2563eb;
    margin-top: 12px;
}

.stat-card small {
    display: block;
    color: #94a3b8;
    margin-top: 7px;
    font-size: 12px;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-top: 12px;
    font-size: 13px;
}

.checkbox-group input {
    width: auto;
}

.form-actions {
    display: flex;
    gap: 9px;
    margin-top: 18px;
}

/* BUTTONS */

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
    max-width: 500px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 11px;
    border: 1px solid #dce3ed;
    border-radius: 8px;
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
    min-width: 800px;
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

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
}

.status-active {
    background: #dcfce7;
    color: #15803d;
}

.status-inactive {
    background: #f1f5f9;
    color: #64748b;
}

.semester-badge {
    display: inline-block;
    background: #eff6ff;
    color: #2563eb;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
}

.empty-data {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
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
            <a href="jadwal.php">Jadwal Pelajaran</a>

            <a href="tahun_ajaran.php" class="active">
                Tahun Ajaran
            </a>

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

            <h3>Manajemen Tahun Ajaran</h3>

            <div class="admin-user">
                Administrator
                <span>Admin EduStudent</span>
            </div>

        </header>

        <section class="admin-content">

            <div class="welcome">

                <h1>Tahun Ajaran</h1>

                <p>
                    Kelola periode tahun ajaran dan semester sekolah.
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
                    <span>Total Tahun Ajaran</span>
                    <h2><?= $totalYears ?></h2>
                </div>

                <div class="stat-card">
                    <span>Total Semester Terdaftar</span>
                    <h2><?= $totalSemesters ?></h2>
                </div>

                <div class="stat-card">

                    <span>Periode Aktif</span>

                    <?php if ($activeYear): ?>

                        <h2>
                            <?= e($activeYear['academic_year']) ?>
                        </h2>

                        <small>
                            Semester <?= e($activeYear['semester']) ?>
                        </small>

                    <?php else: ?>

                        <h2>-</h2>

                        <small>Belum ada periode aktif</small>

                    <?php endif; ?>

                </div>

            </div>

            <!-- FORM TAMBAH / EDIT -->

            <div class="admin-panel">

                <div class="panel-heading">

                    <h3>
                        <?= $data_edit ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran' ?>
                    </h3>

                    <p>
                        Masukkan periode akademik sekolah.
                    </p>

                </div>

                <form method="POST" action="tahun_ajaran.php">

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

                            <label>Tahun Ajaran</label>

                            <input
                                type="text"
                                name="academic_year"
                                placeholder="Contoh: 2026/2027"
                                pattern="\d{4}/\d{4}"
                                value="<?= e($data_edit['academic_year'] ?? '') ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>Semester</label>

                            <select name="semester" required>

                                <option value="">
                                    -- Pilih Semester --
                                </option>

                                <option
                                    value="Ganjil"
                                    <?= ($data_edit['semester'] ?? '') === 'Ganjil' ? 'selected' : '' ?>
                                >
                                    Ganjil
                                </option>

                                <option
                                    value="Genap"
                                    <?= ($data_edit['semester'] ?? '') === 'Genap' ? 'selected' : '' ?>
                                >
                                    Genap
                                </option>

                            </select>

                        </div>

                        <div class="form-group">

                            <label>Tanggal Mulai</label>

                            <input
                                type="date"
                                name="start_date"
                                value="<?= e($data_edit['start_date'] ?? '') ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>Tanggal Selesai</label>

                            <input
                                type="date"
                                name="end_date"
                                value="<?= e($data_edit['end_date'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>

                    <label class="checkbox-group">

                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= (int)($data_edit['is_active'] ?? 0) === 1 ? 'checked' : '' ?>
                        >

                        Jadikan tahun ajaran dan semester ini aktif

                    </label>

                    <div class="form-actions">

                        <?php if ($data_edit): ?>

                            <button
                                type="submit"
                                name="edit"
                                class="btn-primary"
                            >
                                Simpan Perubahan
                            </button>

                            <a
                                href="tahun_ajaran.php"
                                class="btn btn-gray"
                            >
                                Batal
                            </a>

                        <?php else: ?>

                            <button
                                type="submit"
                                name="tambah"
                                class="btn-primary"
                            >
                                + Tambahkan Tahun Ajaran
                            </button>

                        <?php endif; ?>

                    </div>

                </form>

            </div>

            <!-- DAFTAR TAHUN AJARAN -->

            <div class="admin-panel">

                <div class="panel-heading">

                    <h3>Daftar Tahun Ajaran</h3>

                    <p>
                        Seluruh periode akademik yang telah terdaftar.
                    </p>

                </div>

                <div class="toolbar">

                    <form method="GET" class="search-form">

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari tahun ajaran atau semester..."
                            value="<?= e($search) ?>"
                        >

                        <button type="submit">
                            Cari
                        </button>

                        <a
                            href="tahun_ajaran.php"
                            class="btn btn-gray"
                        >
                            Reset
                        </a>

                    </form>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>No.</th>
                                <th>Tahun Ajaran</th>
                                <th>Semester</th>
                                <th>Tanggal Mulai</th>
                                <th>Tanggal Selesai</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($academic_years)): ?>

                                <?php foreach ($academic_years as $index => $year): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($year['academic_year']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <span class="semester-badge">
                                                <?= e($year['semester']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= date('d-m-Y', strtotime($year['start_date'])) ?>
                                        </td>

                                        <td>
                                            <?= date('d-m-Y', strtotime($year['end_date'])) ?>
                                        </td>

                                        <td>

                                            <?php if ((int)$year['is_active'] === 1): ?>

                                                <span class="status status-active">
                                                    Aktif
                                                </span>

                                            <?php else: ?>

                                                <span class="status status-inactive">
                                                    Tidak Aktif
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <div class="actions">

                                                <a
                                                    href="tahun_ajaran.php?edit=<?= (int)$year['id'] ?>"
                                                    class="btn btn-edit"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menghapus periode ini?')"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$year['id'] ?>"
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

                                    <td colspan="7" class="empty-data">
                                        Belum ada data tahun ajaran.
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
