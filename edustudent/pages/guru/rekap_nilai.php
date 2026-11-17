
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/session.php';

/* =====================================
   1. AUTENTIKASI
===================================== */

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'guru'
) {
    header("Location: ../../auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =====================================
   2. FILTER
===================================== */

$classId = (int)($_GET['class_id'] ?? 0);
$semester = (int)($_GET['semester'] ?? 0);
$academicYear = trim($_GET['academic_year'] ?? '');
$search = trim($_GET['search'] ?? '');

if (!in_array($semester, [0, 1, 2], true)) {
    $semester = 0;
}

/* =====================================
   3. DATA KELAS
===================================== */

$stmt = $pdo->query("
    SELECT id, class_name
    FROM classes
    ORDER BY class_name
");

$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   4. DATA TAHUN AJARAN
===================================== */

$stmt = $pdo->query("
    SELECT DISTINCT academic_year
    FROM grades
    WHERE academic_year <> ''
    ORDER BY academic_year DESC
");

$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =====================================
   5. QUERY REKAP NILAI
===================================== */

$sql = "
    SELECT
        st.id AS student_id,
        st.full_name,
        st.nis,
        c.class_name,

        COUNT(g.id) AS total_assessments,
        COUNT(DISTINCT g.subject_id) AS total_subjects,

        AVG(g.score) AS average_score,
        MAX(g.score) AS highest_score,
        MIN(g.score) AS lowest_score

    FROM students st

    INNER JOIN classes c
        ON c.id = st.class_id

    INNER JOIN grades g
        ON g.student_id = st.id

    WHERE 1=1
";

$params = [];

if ($classId > 0) {
    $sql .= " AND c.id = :class_id";
    $params['class_id'] = $classId;
}

if ($semester > 0) {
    $sql .= " AND g.semester = :semester";
    $params['semester'] = $semester;
}

if ($academicYear !== '') {
    $sql .= " AND g.academic_year = :academic_year";
    $params['academic_year'] = $academicYear;
}

if ($search !== '') {
    $sql .= "
        AND (
            st.full_name LIKE :student_name
            OR st.nis LIKE :nis
        )
    ";

    $params['student_name'] = "%$search%";
    $params['nis'] = "%$search%";
}

$sql .= "
    GROUP BY
        st.id,
        st.full_name,
        st.nis,
        c.class_name
    ORDER BY average_score DESC, st.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   6. HITUNG RANKING DAN PREDIKAT
===================================== */

foreach ($students as $index => &$student) {

    $score = (float)$student['average_score'];

    $student['rank'] = $index + 1;

    if ($score >= 90) {
        $student['grade'] = 'A';
        $student['description'] = 'Sangat Baik';
    } elseif ($score >= 80) {
        $student['grade'] = 'B';
        $student['description'] = 'Baik';
    } elseif ($score >= 70) {
        $student['grade'] = 'C';
        $student['description'] = 'Cukup';
    } else {
        $student['grade'] = 'D';
        $student['description'] = 'Perlu Bimbingan';
    }
}

unset($student);

/* =====================================
   7. STATISTIK
===================================== */

$totalStudents = count($students);

$overallAverage = 0;
$bestStudent = null;

if ($totalStudents > 0) {

    $overallAverage = array_sum(
        array_column($students, 'average_score')
    ) / $totalStudents;

    $bestStudent = $students[0];
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rekap Nilai Akademik - EduStudent</title>

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

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}

.stat-card {
    background: white;
    padding: 23px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.stat-card p {
    color: #718096;
    font-size: 14px;
}

.stat-card h2 {
    color: #2864f0;
    font-size: 29px;
    margin-bottom: 0;
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

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 22px;
}

input, select {
    padding: 12px;
    border: 1px solid #dce3ed;
    border-radius: 7px;
    font-size: 14px;
}

.filter-form input {
    flex: 2;
    min-width: 180px;
}

.filter-form select {
    flex: 1;
    min-width: 150px;
}

.btn {
    padding: 12px 17px;
    background: #2864f0;
    color: white;
    border: none;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
}

.btn-reset {
    background: #64748b;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

th, td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #e5eaf1;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.rank {
    font-weight: bold;
    color: #2864f0;
}

.average {
    font-weight: bold;
    color: #2563eb;
}

.predikat {
    display: inline-block;
    padding: 6px 12px;
    background: #eff6ff;
    color: #2563eb;
    border-radius: 6px;
    font-weight: bold;
}

.best {
    background: #eff6ff;
    border-left: 4px solid #2864f0;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 22px;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #718096;
}

@media(max-width: 700px) {

    body {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <h1>Rekap Nilai Akademik</h1>

        <p>
            Rekapitulasi nilai dan peringkat akademik seluruh siswa.
        </p>

    </div>

    <!-- STATISTIK -->

    <div class="stats">

        <div class="stat-card">

            <p>Total Siswa</p>

            <h2><?= $totalStudents ?></h2>

        </div>

        <div class="stat-card">

            <p>Rata-rata Akademik</p>

            <h2>
                <?= number_format($overallAverage, 2) ?>
            </h2>

        </div>

        <div class="stat-card">

            <p>Nilai Tertinggi</p>

            <h2>
                <?= $bestStudent
                    ? number_format((float)$bestStudent['average_score'], 2)
                    : '0.00' ?>
            </h2>

        </div>

    </div>

    <!-- SISWA TERBAIK -->

    <?php if ($bestStudent): ?>

        <div class="best">

            <strong>🏆 Siswa dengan Peringkat Pertama</strong>

            <p>
                <?= e($bestStudent['full_name']) ?>
                (<?= e($bestStudent['class_name']) ?>)
            </p>

            <strong>
                Rata-rata:
                <?= number_format(
                    (float)$bestStudent['average_score'], 2
                ) ?>
            </strong>

        </div>

    <?php endif; ?>

    <!-- TABEL REKAP -->

    <div class="card">

        <h2>Ranking Akademik Siswa</h2>

        <p style="color:#718096;font-size:13px;">
            Peringkat dihitung berdasarkan rata-rata seluruh nilai
            yang tercatat sesuai filter.
        </p>

        <!-- FILTER -->

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                placeholder="Cari nama atau NIS siswa..."
                value="<?= e($search) ?>"
            >

            <select name="class_id">

                <option value="0">Semua Kelas</option>

                <?php foreach ($classes as $class): ?>

                    <option
                        value="<?= (int)$class['id'] ?>"
                        <?= $classId === (int)$class['id']
                            ? 'selected' : '' ?>
                    >
                        <?= e($class['class_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <select name="semester">

                <option value="0">Semua Semester</option>

                <option value="1"
                    <?= $semester === 1 ? 'selected' : '' ?>>
                    Semester 1
                </option>

                <option value="2"
                    <?= $semester === 2 ? 'selected' : '' ?>>
                    Semester 2
                </option>

            </select>

            <select name="academic_year">

                <option value="">Semua Tahun Ajaran</option>

                <?php foreach ($years as $year): ?>

                    <option
                        value="<?= e($year) ?>"
                        <?= $academicYear === $year
                            ? 'selected' : '' ?>
                    >
                        <?= e($year) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn">
                Terapkan Filter
            </button>

            <a href="rekap_nilai.php" class="btn btn-reset">
                Reset
            </a>

        </form>

        <!-- TABEL -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Ranking</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Total Mapel</th>
                        <th>Jumlah Penilaian</th>
                        <th>Rata-rata</th>
                        <th>Tertinggi</th>
                        <th>Terendah</th>
                        <th>Predikat</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($students)): ?>

                    <tr>

                        <td colspan="10" class="empty">

                            Belum ada data nilai akademik.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($students as $student): ?>

                        <tr>

                            <td class="rank">

                                #<?= $student['rank'] ?>

                            </td>

                            <td>
                                <?= e($student['nis']) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e($student['full_name']) ?>
                                </strong>

                            </td>

                            <td>
                                <?= e($student['class_name']) ?>
                            </td>

                            <td>
                                <?= (int)$student['total_subjects'] ?>
                            </td>

                            <td>
                                <?= (int)$student['total_assessments'] ?>
                            </td>

                            <td class="average">

                                <?= number_format(
                                    (float)$student['average_score'], 2
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    (float)$student['highest_score'], 2
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    (float)$student['lowest_score'], 2
                                ) ?>

                            </td>

                            <td>

                                <span class="predikat">

                                    <?= e($student['grade']) ?>

                                </span>

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
