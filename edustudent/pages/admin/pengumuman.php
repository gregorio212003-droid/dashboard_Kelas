
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/session.php';

/* =====================================
   1. AUTENTIKASI ADMIN
===================================== */

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'admin'
) {
    header("Location: ../../auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

/* =====================================
   2. PROSES FORM
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $token)) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } else {

        $action = $_POST['action'] ?? '';

        /* =====================================
           TAMBAH PENGUMUMAN
        ===================================== */

        if ($action === 'save') {

            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'Umum');
            $targetClass = $_POST['target_class_id'] ?? '';
            $publishAtInput = trim($_POST['publish_at'] ?? '');
            $status = $_POST['status'] ?? 'draft';

            $allowedCategories = [
                'Umum',
                'Akademik',
                'Kegiatan',
                'Ujian',
                'Libur',
                'Penting'
            ];

            if (!in_array($category, $allowedCategories, true)) {
                $error = "Kategori pengumuman tidak valid.";
            } elseif (!in_array($status, ['draft', 'published'], true)) {
                $error = "Status pengumuman tidak valid.";
            } elseif ($title === '' || $content === '') {
                $error = "Judul dan isi pengumuman wajib diisi.";
            } elseif (mb_strlen($title) > 150) {
                $error = "Judul maksimal 150 karakter.";
            } else {

                $classId = null;

                if ($targetClass !== '') {

                    $classId = (int)$targetClass;

                    $checkClass = $pdo->prepare("
                        SELECT id FROM classes WHERE id = ?
                    ");

                    $checkClass->execute([$classId]);

                    if (!$checkClass->fetch()) {
                        $error = "Kelas yang dipilih tidak ditemukan.";
                    }
                }

                $publishAt = null;

                if ($publishAtInput !== '') {

                    $date = DateTime::createFromFormat(
                        'Y-m-d\TH:i',
                        $publishAtInput
                    );

                    if (!$date || $date->format('Y-m-d\TH:i') !== $publishAtInput) {
                        $error = "Format jadwal publikasi tidak valid.";
                    } else {
                        $publishAt = $date->format('Y-m-d H:i:s');
                    }
                }

                if ($error === '') {

                    if ($id > 0) {

                        // UPDATE PENGUMUMAN

                        $stmt = $pdo->prepare("
                            UPDATE announcements
                            SET
                                title = :title,
                                content = :content,
                                category = :category,
                                target_class_id = :class_id,
                                publish_at = :publish_at,
                                status = :status
                            WHERE id = :id
                        ");

                        $stmt->execute([
                            'title' => $title,
                            'content' => $content,
                            'category' => $category,
                            'class_id' => $classId,
                            'publish_at' => $publishAt,
                            'status' => $status,
                            'id' => $id
                        ]);

                        $_SESSION['flash_success'] =
                            "Pengumuman berhasil diperbarui.";

                    } else {

                        // INSERT PENGUMUMAN

                        $stmt = $pdo->prepare("
                            INSERT INTO announcements (
                                user_id,
                                title,
                                content,
                                category,
                                target_class_id,
                                publish_at,
                                status
                            ) VALUES (
                                :user_id,
                                :title,
                                :content,
                                :category,
                                :class_id,
                                :publish_at,
                                :status
                            )
                        ");

                        $stmt->execute([
                            'user_id' => $_SESSION['user']['id'],
                            'title' => $title,
                            'content' => $content,
                            'category' => $category,
                            'class_id' => $classId,
                            'publish_at' => $publishAt,
                            'status' => $status
                        ]);

                        $_SESSION['flash_success'] =
                            "Pengumuman berhasil ditambahkan.";
                    }

                    header("Location: pengumuman.php");
                    exit;
                }
            }
        }

        /* =====================================
           HAPUS PENGUMUMAN
        ===================================== */

        if ($action === 'delete') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    DELETE FROM announcements
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                $_SESSION['flash_success'] =
                    "Pengumuman berhasil dihapus.";

                header("Location: pengumuman.php");
                exit;
            }

            $error = "ID pengumuman tidak valid.";
        }
    }
}

/* =====================================
   3. DATA KELAS
===================================== */

$stmt = $pdo->query("
    SELECT id, class_name
    FROM classes
    ORDER BY class_name ASC
");

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   4. DATA EDIT
===================================== */

$editData = null;

$editId = (int)($_GET['edit'] ?? 0);

if ($editId > 0) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM announcements
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$editId]);

    $editData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editData) {
        $error = "Pengumuman yang ingin diedit tidak ditemukan.";
    }
}

/* =====================================
   5. PENCARIAN DAN DAFTAR PENGUMUMAN
===================================== */

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        a.*,
        u.username,
        c.class_name

    FROM announcements a

    LEFT JOIN users u
        ON u.id = a.user_id

    LEFT JOIN classes c
        ON c.id = a.target_class_id

    WHERE 1=1
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            a.title LIKE :title
            OR a.content LIKE :content
            OR a.category LIKE :category
        )
    ";

    $keyword = "%$search%";

    $params = [
        'title' => $keyword,
        'content' => $keyword,
        'category' => $keyword
    ];
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   6. STATISTIK
===================================== */

$total = count($announcements);

$published = 0;
$draft = 0;

foreach ($announcements as $announcement) {

    if ($announcement['status'] === 'published') {
        $published++;
    } else {
        $draft++;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manajemen Pengumuman - EduStudent</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f4f7fc;
    color: #243247;
}

.container {
    max-width: 1250px;
    margin: auto;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    font-size: 27px;
    margin-bottom: 8px;
}

.header p {
    color: #718096;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.stat-card p {
    color: #718096;
    font-size: 14px;
}

.stat-card h2 {
    color: #2864f0;
    font-size: 28px;
    margin-bottom: 0;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 25px;
}

.card h2 {
    margin-top: 0;
    font-size: 19px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: bold;
}

input, textarea, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce3ed;
    border-radius: 7px;
    font-size: 14px;
    font-family: inherit;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.btn {
    padding: 11px 17px;
    border: none;
    border-radius: 7px;
    background: #2864f0;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    display: inline-block;
}

.btn-edit {
    background: #e8f0ff;
    color: #2563eb;
}

.btn-delete {
    background: #fff0f0;
    color: #dc2626;
}

.btn-reset {
    background: #64748b;
}

.btn-cancel {
    background: #64748b;
    margin-left: 5px;
}

.alert {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-form input {
    flex: 1;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #e5eaf1;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
}

.status-published {
    background: #dcfce7;
    color: #166534;
}

.status-draft {
    background: #f1f5f9;
    color: #475569;
}

.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.actions form {
    margin: 0;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #718096;
}

@media(max-width: 700px) {

    body {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .search-form {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <h1>Manajemen Pengumuman</h1>

        <p>
            Kelola informasi dan pengumuman sekolah EduStudent.
        </p>

    </div>

    <!-- PESAN -->

    <?php if ($success): ?>

        <div class="alert alert-success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- STATISTIK -->

    <div class="stats">

        <div class="stat-card">

            <p>Total Pengumuman</p>

            <h2><?= $total ?></h2>

        </div>

        <div class="stat-card">

            <p>Published</p>

            <h2><?= $published ?></h2>

        </div>

        <div class="stat-card">

            <p>Draft</p>

            <h2><?= $draft ?></h2>

        </div>

    </div>

    <!-- FORM -->

    <div class="card">

        <h2>
            <?= $editData
                ? 'Edit Pengumuman'
                : 'Buat Pengumuman Baru' ?>
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int)($editData['id'] ?? 0) ?>"
            >

            <div class="form-group">

                <label>Judul Pengumuman</label>

                <input
                    type="text"
                    name="title"
                    maxlength="150"
                    placeholder="Masukkan judul pengumuman"
                    value="<?= e($editData['title'] ?? '') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Isi Pengumuman</label>

                <textarea
                    name="content"
                    placeholder="Tuliskan isi pengumuman..."
                    required
                ><?= e($editData['content'] ?? '') ?></textarea>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Kategori</label>

                    <select name="category">

                        <?php

                        $categories = [
                            'Umum',
                            'Akademik',
                            'Kegiatan',
                            'Ujian',
                            'Libur',
                            'Penting'
                        ];

                        foreach ($categories as $category):

                        ?>

                            <option
                                value="<?= e($category) ?>"
                                <?= ($editData['category'] ?? 'Umum')
                                    === $category ? 'selected' : '' ?>
                            >
                                <?= e($category) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Target Penerima</label>

                    <select name="target_class_id">

                        <option value="">
                            Semua Siswa
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= (int)$class['id'] ?>"
                                <?= (int)($editData['target_class_id'] ?? 0)
                                    === (int)$class['id']
                                    ? 'selected' : '' ?>
                            >
                                <?= e($class['class_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Jadwal Publikasi</label>

                    <input
                        type="datetime-local"
                        name="publish_at"
                        value="<?= !empty($editData['publish_at'])
                            ? e(date(
                                'Y-m-d\TH:i',
                                strtotime($editData['publish_at'])
                            ))
                            : '' ?>"
                    >

                    <small style="color:#718096;">
                        Kosongkan untuk menggunakan tanggal publikasi
                        saat ini.
                    </small>

                </div>

                <div class="form-group">

                    <label>Status Pengumuman</label>

                    <select name="status">

                        <option
                            value="draft"
                            <?= ($editData['status'] ?? 'draft') === 'draft'
                                ? 'selected' : '' ?>
                        >
                            Draft
                        </option>

                        <option
                            value="published"
                            <?= ($editData['status'] ?? '') === 'published'
                                ? 'selected' : '' ?>
                        >
                            Published
                        </option>

                    </select>

                </div>

            </div>

            <button type="submit" class="btn">

                <?= $editData
                    ? 'Simpan Perubahan'
                    : '+ Tambahkan Pengumuman' ?>

            </button>

            <?php if ($editData): ?>

                <a href="pengumuman.php" class="btn btn-cancel">
                    Batal Edit
                </a>

            <?php endif; ?>

        </form>

    </div>

    <!-- DAFTAR PENGUMUMAN -->

    <div class="card">

        <h2>Daftar Pengumuman</h2>

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Cari judul atau kategori..."
                value="<?= e($search) ?>"
            >

            <button type="submit" class="btn">
                Cari
            </button>

            <a href="pengumuman.php" class="btn btn-reset">
                Reset
            </a>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>No.</th>
                        <th>Judul</th>
                        <th>Kategori</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Jadwal Publikasi</th>
                        <th>Pembuat</th>
                        <th>Aksi</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($announcements)): ?>

                    <tr>

                        <td colspan="8" class="empty">

                            Belum ada pengumuman.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($announcements as $index => $item): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>

                                <strong>
                                    <?= e($item['title']) ?>
                                </strong>

                            </td>

                            <td>
                                <?= e($item['category']) ?>
                            </td>

                            <td>

                                <?= $item['target_class_id'] === null
                                    ? 'Semua Siswa'
                                    : e($item['class_name'] ?? '-') ?>

                            </td>

                            <td>

                                <span class="status status-<?= e($item['status']) ?>">

                                    <?= e(ucfirst($item['status'])) ?>

                                </span>

                            </td>

                            <td>

                                <?= $item['publish_at']
                                    ? e(date(
                                        'd-m-Y H:i',
                                        strtotime($item['publish_at'])
                                    ))
                                    : 'Langsung' ?>

                            </td>

                            <td>
                                <?= e($item['username'] ?? '-') ?>
                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="pengumuman.php?edit=<?= (int)$item['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus pengumuman ini?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$item['id'] ?>"
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

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <a href="dashboard.php" class="btn btn-reset">
        Kembali ke Dashboard
    </a>

</div>

</body>
</html>
