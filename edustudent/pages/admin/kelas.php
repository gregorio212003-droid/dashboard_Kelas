
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// ==========================================
// SESSION DAN CSRF
// ==========================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

// ==========================================
// PROSES TAMBAH, EDIT, DAN HAPUS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {

        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    } else {

        $action = $_POST['action'] ?? '';

        // ==================================
        // TAMBAH KELAS
        // ==================================

        if ($action === 'create') {

            $className = trim($_POST['class_name'] ?? '');
            $gradeLevel = (int)($_POST['grade_level'] ?? 0);
            $major = trim($_POST['major'] ?? '');

            if ($className === '') {

                $error = 'Nama kelas wajib diisi.';

            } elseif (strlen($className) > 30) {

                $error = 'Nama kelas maksimal 30 karakter.';

            } elseif (!in_array($gradeLevel, [10, 11, 12], true)) {

                $error = 'Tingkat kelas tidak valid.';

            } elseif (strlen($major) > 50) {

                $error = 'Jurusan maksimal 50 karakter.';

            } else {

                $check = $pdo->prepare("
                    SELECT id FROM classes
                    WHERE class_name = :class_name
                    LIMIT 1
                ");

                $check->execute([
                    'class_name' => $className
                ]);

                if ($check->fetch()) {

                    $error = 'Nama kelas tersebut sudah tersedia.';

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO classes
                        (class_name, grade_level, major)
                        VALUES
                        (:class_name, :grade_level, :major)
                    ");

                    $stmt->execute([
                        'class_name' => $className,
                        'grade_level' => $gradeLevel,
                        'major' => $major !== '' ? $major : null
                    ]);

                    header('Location: kelas.php?status=created');
                    exit;

                }

            }

        }

        // ==================================
        // EDIT KELAS
        // ==================================

        elseif ($action === 'update') {

            $id = (int)($_POST['id'] ?? 0);

            $className = trim($_POST['class_name'] ?? '');
            $gradeLevel = (int)($_POST['grade_level'] ?? 0);
            $major = trim($_POST['major'] ?? '');

            if ($id <= 0) {

                $error = 'ID kelas tidak valid.';

            } elseif ($className === '') {

                $error = 'Nama kelas wajib diisi.';

            } elseif (strlen($className) > 30) {

                $error = 'Nama kelas maksimal 30 karakter.';

            } elseif (!in_array($gradeLevel, [10, 11, 12], true)) {

                $error = 'Tingkat kelas tidak valid.';

            } elseif (strlen($major) > 50) {

                $error = 'Jurusan maksimal 50 karakter.';

            } else {

                $check = $pdo->prepare("
                    SELECT id FROM classes
                    WHERE class_name = :class_name
                    AND id != :id
                    LIMIT 1
                ");

                $check->execute([
                    'class_name' => $className,
                    'id' => $id
                ]);

                if ($check->fetch()) {

                    $error = 'Nama kelas sudah digunakan kelas lain.';

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE classes
                        SET
                            class_name = :class_name,
                            grade_level = :grade_level,
                            major = :major
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        'class_name' => $className,
                        'grade_level' => $gradeLevel,
                        'major' => $major !== '' ? $major : null,
                        'id' => $id
                    ]);

                    header('Location: kelas.php?status=updated');
                    exit;

                }

            }

        }

        // ==================================
        // HAPUS KELAS
        // ==================================

        elseif ($action === 'delete') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {

                $error = 'ID kelas tidak valid.';

            } else {

                // Periksa apakah kelas masih digunakan.

                $used = 0;

                $tables = [
                    'students' => 'class_id',
                    'schedules' => 'class_id',
                    'assignments' => 'class_id'
                ];

                foreach ($tables as $table => $column) {

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM `$table`
                        WHERE `$column` = :id
                    ");

                    $stmt->execute(['id' => $id]);

                    $used += (int)$stmt->fetchColumn();

                }

                if ($used > 0) {

                    $error = 'Kelas tidak dapat dihapus karena masih digunakan oleh data siswa, jadwal, atau tugas.';

                } else {

                    $stmt = $pdo->prepare("
                        DELETE FROM classes
                        WHERE id = :id
                    ");

                    $stmt->execute(['id' => $id]);

                    header('Location: kelas.php?status=deleted');
                    exit;

                }

            }

        }

    }

}

// ==========================================
// PESAN BERHASIL
// ==========================================

if (isset($_GET['status'])) {

    $messages = [
        'created' => 'Kelas berhasil ditambahkan.',
        'updated' => 'Data kelas berhasil diperbarui.',
        'deleted' => 'Kelas berhasil dihapus.'
    ];

    $success = $messages[$_GET['status']] ?? '';

}

// ==========================================
// DATA KELAS UNTUK EDIT
// ==========================================

$editClass = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM classes
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $editId]);

    $editClass = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editClass) {
        $error = 'Data kelas tidak ditemukan.';
    }

}

// ==========================================
// PENCARIAN KELAS
// ==========================================

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT *
    FROM classes
    WHERE class_name LIKE :search
    OR major LIKE :search
    ORDER BY grade_level ASC, class_name ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'search' => '%' . $search . '%'
]);

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// STATISTIK KELAS
// ==========================================

$totalClasses = (int)$pdo->query("
    SELECT COUNT(*) FROM classes
")->fetchColumn();

$totalGrade10 = (int)$pdo->query("
    SELECT COUNT(*) FROM classes
    WHERE grade_level = 10
")->fetchColumn();

$totalGrade11 = (int)$pdo->query("
    SELECT COUNT(*) FROM classes
    WHERE grade_level = 11
")->fetchColumn();

$totalGrade12 = (int)$pdo->query("
    SELECT COUNT(*) FROM classes
    WHERE grade_level = 12
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Kelas - EduStudent</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    color: #1e293b;
}

.admin-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.admin-sidebar {
    width: 245px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    padding: 25px 20px;
    flex-shrink: 0;
}

.admin-logo {
    font-size: 24px;
    font-weight: bold;
    color: #2563eb;
    margin-bottom: 55px;
}

.sidebar-label {
    font-size: 11px;
    font-weight: bold;
    color: #94a3b8;
    margin-bottom: 14px;
}

.admin-nav {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 35px;
}

.admin-nav a {
    padding: 12px;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
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
    border-bottom: 1px solid #e2e8f0;
    padding: 0 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.admin-navbar h3 {
    font-size: 17px;
}

.admin-navbar span {
    font-size: 13px;
    color: #64748b;
}

.admin-content {
    padding: 30px;
    max-width: 1500px;
    margin: auto;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    margin: 0 0 8px;
    font-size: 27px;
}

.page-heading p {
    color: #64748b;
    margin: 0;
    font-size: 14px;
}

/* STATISTIK */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.stat-card span {
    color: #64748b;
    font-size: 13px;
}

.stat-card h2 {
    color: #2563eb;
    font-size: 29px;
    margin: 12px 0 0;
}

/* PANEL */

.admin-panel {
    background: white;
    padding: 24px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 22px;
}

.admin-panel h2 {
    font-size: 19px;
    margin: 0 0 22px;
}

/* FORM */

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 13px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #2563eb;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

/* BUTTON */

.btn {
    border: none;
    padding: 11px 17px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-secondary {
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

.btn:hover {
    opacity: .85;
}

/* SEARCH */

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
}

/* TABLE */

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th {
    background: #f8fafc;
    color: #64748b;
    text-align: left;
    font-size: 12px;
    padding: 15px;
}

td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

tr:hover td {
    background: #fafcff;
}

.grade-badge {
    background: #eff6ff;
    color: #2563eb;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 7px;
}

.action-buttons form {
    margin: 0;
}

/* ALERT */

.alert {
    padding: 14px 17px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
}

/* RESPONSIVE */

@media (max-width: 950px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 650px) {

    .admin-sidebar {
        display: none;
    }

    .admin-content {
        padding: 18px;
    }

    .admin-navbar {
        padding: 0 18px;
    }

    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h2 {
        font-size: 23px;
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

        <div class="sidebar-label">
            MENU ADMIN
        </div>

        <nav class="admin-nav">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="siswa.php">
                Manajemen Siswa
            </a>

            <a href="guru.php">
                Manajemen Guru
            </a>

            <a href="kelas.php" class="active">
                Manajemen Kelas
            </a>

            <a href="#">
                Mata Pelajaran
            </a>

            <a href="#">
                Jadwal Pelajaran
            </a>

            <a href="pengumuman.php">
                Pengumuman
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="admin-main">

        <header class="admin-navbar">

            <h3>Manajemen Kelas</h3>

            <span>
                Administrator EduStudent
            </span>

        </header>


        <section class="admin-content">

            <!-- JUDUL -->

            <div class="page-heading">

                <h1>Manajemen Kelas</h1>

                <p>
                    Kelola seluruh data kelas pada sistem EduStudent.
                </p>

            </div>


            <!-- PESAN -->

            <?php if ($success): ?>

                <div class="alert alert-success">
                    <?= h($success) ?>
                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-error">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>


            <!-- STATISTIK -->

            <div class="stats-grid">

                <div class="stat-card">

                    <span>Total Kelas</span>

                    <h2><?= $totalClasses ?></h2>

                </div>

                <div class="stat-card">

                    <span>Kelas X</span>

                    <h2><?= $totalGrade10 ?></h2>

                </div>

                <div class="stat-card">

                    <span>Kelas XI</span>

                    <h2><?= $totalGrade11 ?></h2>

                </div>

                <div class="stat-card">

                    <span>Kelas XII</span>

                    <h2><?= $totalGrade12 ?></h2>

                </div>

            </div>


            <!-- FORM TAMBAH / EDIT -->

            <div class="admin-panel">

                <h2>

                    <?= $editClass
                        ? 'Edit Data Kelas'
                        : 'Tambah Kelas Baru' ?>

                </h2>


                <form method="POST" action="kelas.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editClass ? 'update' : 'create' ?>"
                    >

                    <?php if ($editClass): ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$editClass['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">

                        <div class="form-group">

                            <label>Nama Kelas</label>

                            <input
                                type="text"
                                name="class_name"
                                maxlength="30"
                                placeholder="Contoh: XII Informatika"
                                value="<?= h(
                                    $editClass['class_name'] ?? ''
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>Tingkat Kelas</label>

                            <select name="grade_level" required>

                                <option value="">
                                    Pilih Tingkat
                                </option>

                                <?php foreach ([10, 11, 12] as $grade): ?>

                                    <option
                                        value="<?= $grade ?>"
                                        <?= isset($editClass['grade_level'])
                                            && (int)$editClass['grade_level'] === $grade
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        Kelas <?= $grade ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>Jurusan</label>

                            <input
                                type="text"
                                name="major"
                                maxlength="50"
                                placeholder="Contoh: Informatika"
                                value="<?= h(
                                    $editClass['major'] ?? ''
                                ) ?>"
                            >

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <?= $editClass
                                ? 'Simpan Perubahan'
                                : 'Tambah Kelas' ?>

                        </button>


                        <?php if ($editClass): ?>

                            <a
                                href="kelas.php"
                                class="btn btn-secondary"
                            >
                                Batal
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </div>


            <!-- DAFTAR KELAS -->

            <div class="admin-panel">

                <h2>Daftar Kelas</h2>


                <!-- PENCARIAN -->

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="search"
                        placeholder="Cari nama kelas atau jurusan..."
                        value="<?= h($search) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Cari
                    </button>

                    <?php if ($search !== ''): ?>

                        <a
                            href="kelas.php"
                            class="btn btn-secondary"
                        >
                            Reset
                        </a>

                    <?php endif; ?>

                </form>


                <!-- TABEL -->

                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Nama Kelas</th>

                                <th>Tingkat</th>

                                <th>Jurusan</th>

                                <th>Dibuat</th>

                                <th>Tindakan</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if ($classes): ?>

                                <?php $no = 1; ?>

                                <?php foreach ($classes as $class): ?>

                                    <tr>

                                        <td>
                                            <?= $no++ ?>
                                        </td>


                                        <td>
                                            <strong>
                                                <?= h($class['class_name']) ?>
                                            </strong>
                                        </td>


                                        <td>

                                            <span class="grade-badge">

                                                Kelas <?= (int)$class['grade_level'] ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= h(
                                                $class['major'] ?: '-'
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= $class['created_at']
                                                ? date(
                                                    'd-m-Y',
                                                    strtotime($class['created_at'])
                                                )
                                                : '-' ?>

                                        </td>


                                        <td>

                                            <div class="action-buttons">

                                                <a
                                                    href="kelas.php?edit=<?= (int)$class['id'] ?>"
                                                    class="btn btn-edit"
                                                >
                                                    Edit
                                                </a>


                                                <form
                                                    method="POST"
                                                    action="kelas.php"
                                                    onsubmit="return confirm('Yakin ingin menghapus kelas ini?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= h($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$class['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-delete"
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

                                    <td colspan="6" class="empty-state">

                                        Belum ada data kelas.

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
