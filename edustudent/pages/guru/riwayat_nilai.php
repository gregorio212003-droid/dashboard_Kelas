
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/session.php';

/* =========================================
   1. CEK LOGIN GURU
========================================= */

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'guru'
) {
    header("Location: ../../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user']['id'];

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

/* =========================================
   2. CSRF TOKEN
========================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================================
   3. DATA GURU
========================================= */

$stmt = $pdo->prepare("
    SELECT id, full_name
    FROM teachers
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Data guru tidak ditemukan.");
}

$teacherId = (int) $teacher['id'];

/* =========================================
   4. PROSES EDIT DAN HAPUS
========================================= */

$allowedTypes = ['Tugas', 'UTS', 'UAS', 'Praktik'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {

        $error = "Permintaan tidak valid.";

    } else {

        $action = $_POST['action'] ?? '';
        $gradeId = (int) ($_POST['grade_id'] ?? 0);

        if ($gradeId <= 0) {

            $error = "Data nilai tidak valid.";

        } else {

            // Pastikan nilai milik guru yang sedang login

            $stmt = $pdo->prepare("
                SELECT id
                FROM grades
                WHERE id = :grade_id
                AND teacher_id = :teacher_id
                LIMIT 1
            ");

            $stmt->execute([
                'grade_id' => $gradeId,
                'teacher_id' => $teacherId
            ]);

            $gradeExists = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$gradeExists) {

                $error = "Nilai tidak ditemukan atau bukan milik Anda.";

            } elseif ($action === 'delete') {

                $stmt = $pdo->prepare("
                    DELETE FROM grades
                    WHERE id = :grade_id
                    AND teacher_id = :teacher_id
                ");

                $stmt->execute([
                    'grade_id' => $gradeId,
                    'teacher_id' => $teacherId
                ]);

                $success = "Nilai berhasil dihapus.";

            } elseif ($action === 'update') {

                $score = $_POST['score'] ?? '';
                $type = $_POST['assessment_type'] ?? '';
                $semester = (int) ($_POST['semester'] ?? 0);
                $academicYear = trim($_POST['academic_year'] ?? '');

                if (
                    $score === '' ||
                    !is_numeric($score) ||
                    (float) $score < 0 ||
                    (float) $score > 100
                ) {

                    $error = "Nilai harus antara 0 sampai 100.";

                } elseif (!in_array($type, $allowedTypes, true)) {

                    $error = "Jenis penilaian tidak valid.";

                } elseif (!in_array($semester, [1, 2], true)) {

                    $error = "Semester tidak valid.";

                } elseif (!preg_match('/^\d{4}\/\d{4}$/', $academicYear)) {

                    $error = "Format tahun ajaran tidak valid.";

                } else {

                    $stmt = $pdo->prepare("
                        UPDATE grades
                        SET
                            score = :score,
                            assessment_type = :assessment_type,
                            semester = :semester,
                            academic_year = :academic_year
                        WHERE id = :grade_id
                        AND teacher_id = :teacher_id
                    ");

                    $stmt->execute([
                        'score' => $score,
                        'assessment_type' => $type,
                        'semester' => $semester,
                        'academic_year' => $academicYear,
                        'grade_id' => $gradeId,
                        'teacher_id' => $teacherId
                    ]);

                    $success = "Nilai berhasil diperbarui.";
                }

            } else {

                $error = "Aksi tidak dikenali.";

            }
        }
    }

    if ($success !== '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/* =========================================
   5. DATA FILTER
========================================= */

$search = trim($_GET['search'] ?? '');
$classFilter = (int) ($_GET['class_id'] ?? 0);
$subjectFilter = (int) ($_GET['subject_id'] ?? 0);

/* =========================================
   6. DAFTAR KELAS GURU
========================================= */

$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.class_name
    FROM schedules sc
    INNER JOIN classes c ON c.id = sc.class_id
    WHERE sc.teacher_id = :teacher_id
    ORDER BY c.class_name
");

$stmt->execute(['teacher_id' => $teacherId]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   7. DAFTAR MATA PELAJARAN GURU
========================================= */

$stmt = $pdo->prepare("
    SELECT DISTINCT sub.id, sub.subject_name
    FROM schedules sc
    INNER JOIN subjects sub ON sub.id = sc.subject_id
    WHERE sc.teacher_id = :teacher_id
    ORDER BY sub.subject_name
");

$stmt->execute(['teacher_id' => $teacherId]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   8. AMBIL DATA NILAI UNTUK EDIT
========================================= */

$editId = (int) ($_GET['edit'] ?? 0);
$editGrade = null;

if ($editId > 0) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM grades
        WHERE id = :grade_id
        AND teacher_id = :teacher_id
        LIMIT 1
    ");

    $stmt->execute([
        'grade_id' => $editId,
        'teacher_id' => $teacherId
    ]);

    $editGrade = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editGrade) {
        $error = "Data nilai yang ingin diedit tidak ditemukan.";
    }
}

/* =========================================
   9. QUERY RIWAYAT NILAI
========================================= */

$sql = "
    SELECT
        g.id,
        g.score,
        g.assessment_type,
        g.semester,
        g.academic_year,
        g.created_at,

        st.full_name AS student_name,
        st.nis,

        c.id AS class_id,
        c.class_name,

        sub.id AS subject_id,
        sub.subject_name

    FROM grades g

    INNER JOIN students st ON st.id = g.student_id
    INNER JOIN classes c ON c.id = st.class_id
    INNER JOIN subjects sub ON sub.id = g.subject_id

    WHERE g.teacher_id = :teacher_id
";

$params = [
    'teacher_id' => $teacherId
];

if ($search !== '') {

    $sql .= "
        AND (
            st.full_name LIKE :search_name
            OR st.nis LIKE :search_nis
            OR sub.subject_name LIKE :search_subject
        )
    ";

    $params['search_name'] = "%$search%";
    $params['search_nis'] = "%$search%";
    $params['search_subject'] = "%$search%";
}

if ($classFilter > 0) {
    $sql .= " AND c.id = :class_id";
    $params['class_id'] = $classFilter;
}

if ($subjectFilter > 0) {
    $sql .= " AND sub.id = :subject_id";
    $params['subject_id'] = $subjectFilter;
}

$sql .= " ORDER BY g.created_at DESC, g.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   10. HITUNG TOTAL NILAI
========================================= */

$totalGrades = count($grades);

$average = 0;

if ($totalGrades > 0) {
    $average = array_sum(array_column($grades, 'score')) / $totalGrades;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Riwayat Nilai - EduStudent</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px;
    font-family: Arial, sans-serif;
    background: #f4f7fc;
    color: #243247;
}

.container {
    max-width: 1250px;
    margin: 20px auto;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    margin-bottom: 8px;
    font-size: 27px;
}

.header p {
    color: #718096;
}

.card {
    background: white;
    padding: 25px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 22px;
}

.card h2 {
    font-size: 19px;
    margin-top: 0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.stat-box {
    background: white;
    padding: 22px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.stat-box p {
    color: #718096;
    margin: 0 0 10px;
}

.stat-box h2 {
    color: #2864f0;
    font-size: 28px;
    margin: 0;
}

.message {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

input, select {
    padding: 11px 13px;
    border: 1px solid #dce3ed;
    border-radius: 7px;
    font-size: 14px;
}

.filter-form input {
    flex: 2;
    min-width: 200px;
}

.filter-form select {
    flex: 1;
    min-width: 150px;
}

.btn {
    padding: 11px 16px;
    border: none;
    border-radius: 7px;
    background: #2864f0;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn:hover {
    opacity: .85;
}

.btn-edit {
    background: #f59e0b;
}

.btn-delete {
    background: #ef4444;
}

.btn-back {
    background: #64748b;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th, td {
    padding: 14px 12px;
    border-bottom: 1px solid #e5eaf1;
    text-align: left;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.score {
    font-weight: bold;
    color: #2864f0;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 6px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
}

.actions {
    display: flex;
    gap: 7px;
}

.actions form {
    margin: 0;
}

.edit-form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.edit-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.edit-form .full {
    grid-column: 1 / -1;
}

.edit-form label {
    font-size: 13px;
    font-weight: bold;
}

.edit-form button {
    width: fit-content;
}

.empty {
    padding: 35px;
    text-align: center;
    color: #718096;
}

@media(max-width: 700px) {

    body {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .edit-form {
        grid-template-columns: 1fr;
    }

    .edit-form .full {
        grid-column: auto;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Riwayat Nilai Siswa</h1>

        <p>
            Selamat datang, <?= e($teacher['full_name']) ?>.
            Kelola seluruh nilai akademik siswa.
        </p>

    </div>

    <?php if ($success !== ''): ?>
        <div class="message success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->

    <div class="stats">

        <div class="stat-box">
            <p>Total Nilai Tersimpan</p>
            <h2><?= $totalGrades ?></h2>
        </div>

        <div class="stat-box">
            <p>Rata-rata Nilai</p>
            <h2><?= number_format($average, 2) ?></h2>
        </div>

    </div>

    <!-- FORM EDIT -->

    <?php if ($editGrade): ?>

        <div class="card" id="edit">

            <h2>Edit Nilai Siswa</h2>

            <form method="POST" class="edit-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="update"
                >

                <input
                    type="hidden"
                    name="grade_id"
                    value="<?= (int) $editGrade['id'] ?>"
                >

                <div class="form-group">

                    <label>Nilai</label>

                    <input
                        type="number"
                        name="score"
                        min="0"
                        max="100"
                        step="0.01"
                        value="<?= e($editGrade['score']) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Jenis Penilaian</label>

                    <select name="assessment_type" required>

                        <?php foreach ($allowedTypes as $type): ?>

                            <option
                                value="<?= e($type) ?>"
                                <?= $editGrade['assessment_type'] === $type ? 'selected' : '' ?>
                            >
                                <?= e($type) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Semester</label>

                    <select name="semester" required>

                        <option value="1"
                            <?= $editGrade['semester'] == 1 ? 'selected' : '' ?>>
                            Semester 1
                        </option>

                        <option value="2"
                            <?= $editGrade['semester'] == 2 ? 'selected' : '' ?>>
                            Semester 2
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Tahun Ajaran</label>

                    <input
                        type="text"
                        name="academic_year"
                        pattern="\d{4}/\d{4}"
                        value="<?= e($editGrade['academic_year']) ?>"
                        required
                    >

                </div>

                <div class="form-group full">

                    <div class="actions">

                        <button type="submit" class="btn">
                            Simpan Perubahan
                        </button>

                        <a href="riwayat_nilai.php" class="btn btn-back">
                            Batal
                        </a>

                    </div>

                </div>

            </form>

        </div>

    <?php endif; ?>

    <!-- TABEL RIWAYAT -->

    <div class="card">

        <h2>Daftar Riwayat Nilai</h2>

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                placeholder="Cari nama siswa, NIS, mata pelajaran..."
                value="<?= e($search) ?>"
            >

            <select name="class_id">

                <option value="0">Semua Kelas</option>

                <?php foreach ($classes as $class): ?>

                    <option
                        value="<?= (int) $class['id'] ?>"
                        <?= $classFilter === (int) $class['id'] ? 'selected' : '' ?>
                    >
                        <?= e($class['class_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <select name="subject_id">

                <option value="0">Semua Mata Pelajaran</option>

                <?php foreach ($subjects as $subject): ?>

                    <option
                        value="<?= (int) $subject['id'] ?>"
                        <?= $subjectFilter === (int) $subject['id'] ? 'selected' : '' ?>
                    >
                        <?= e($subject['subject_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn">
                Cari / Filter
            </button>

            <a href="riwayat_nilai.php" class="btn btn-back">
                Reset
            </a>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Jenis</th>
                        <th>Nilai</th>
                        <th>Semester</th>
                        <th>Tahun Ajaran</th>
                        <th>Aksi</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (empty($grades)): ?>

                    <tr>
                        <td colspan="10" class="empty">
                            Belum ada data nilai yang ditemukan.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php $no = 1; ?>

                    <?php foreach ($grades as $grade): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    <?= e($grade['student_name']) ?>
                                </strong>
                            </td>

                            <td><?= e($grade['nis']) ?></td>

                            <td><?= e($grade['class_name']) ?></td>

                            <td><?= e($grade['subject_name']) ?></td>

                            <td>
                                <span class="badge">
                                    <?= e($grade['assessment_type']) ?>
                                </span>
                            </td>

                            <td class="score">
                                <?= number_format((float) $grade['score'], 2) ?>
                            </td>

                            <td>
                                Semester <?= (int) $grade['semester'] ?>
                            </td>

                            <td><?= e($grade['academic_year']) ?></td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="?edit=<?= (int) $grade['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Apakah kamu yakin ingin menghapus nilai ini?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="grade_id"
                                            value="<?= (int) $grade['id'] ?>"
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

    <a href="dashboard.php" class="btn btn-back">
        Kembali ke Dashboard
    </a>

</div>

</body>
</html>
