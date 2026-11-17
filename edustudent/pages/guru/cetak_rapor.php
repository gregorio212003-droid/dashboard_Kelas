
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/session.php';

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

// =====================================
// 1. FILTER
// =====================================

$studentId = (int)($_GET['student_id'] ?? 0);
$semester = (int)($_GET['semester'] ?? 1);
$academicYear = trim($_GET['academic_year'] ?? '');

if (!in_array($semester, [1, 2], true)) {
    $semester = 1;
}

// =====================================
// 2. DAFTAR SISWA
// =====================================

$stmt = $pdo->query("
    SELECT id, nis, full_name
    FROM students
    ORDER BY full_name ASC
");

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// 3. DAFTAR TAHUN AJARAN
// =====================================

$stmt = $pdo->query("
    SELECT DISTINCT academic_year
    FROM grades
    WHERE academic_year <> ''
    ORDER BY academic_year DESC
");

$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($academicYear === '' && !empty($years)) {
    $academicYear = $years[0];
}

// =====================================
// 4. DATA SISWA TERPILIH
// =====================================

$student = null;
$subjects = [];
$overallAverage = 0;

if ($studentId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            st.id,
            st.nis,
            st.nisn,
            st.full_name,
            st.gender,
            st.birth_date,
            st.address,
            c.class_name

        FROM students st

        INNER JOIN classes c
            ON c.id = st.class_id

        WHERE st.id = :id

        LIMIT 1
    ");

    $stmt->execute(['id' => $studentId]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

}

// =====================================
// 5. AMBIL NILAI PER MATA PELAJARAN
// =====================================

if ($student) {

    $stmt = $pdo->prepare("
        SELECT
            s.subject_code,
            s.subject_name,

            COUNT(g.id) AS total_assessments,

            AVG(g.score) AS average_score,

            MAX(g.score) AS highest_score,

            MIN(g.score) AS lowest_score

        FROM grades g

        INNER JOIN subjects s
            ON s.id = g.subject_id

        WHERE g.student_id = :student_id
        AND g.semester = :semester
        AND g.academic_year = :academic_year

        GROUP BY
            s.id,
            s.subject_code,
            s.subject_name

        ORDER BY s.subject_name ASC
    ");

    $stmt->execute([
        'student_id' => $studentId,
        'semester' => $semester,
        'academic_year' => $academicYear
    ]);

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rata-rata dari rata-rata setiap mata pelajaran

    if (!empty($subjects)) {

        $total = 0;

        foreach ($subjects as $subject) {
            $total += (float)$subject['average_score'];
        }

        $overallAverage = $total / count($subjects);
    }
}

// =====================================
// 6. PREDIKAT
// =====================================

function getPredicate($score) {

    if ($score >= 90) return 'A - Sangat Baik';
    if ($score >= 80) return 'B - Baik';
    if ($score >= 70) return 'C - Cukup';

    return 'D - Perlu Bimbingan';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Cetak Rapor - EduStudent</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.container {
    max-width: 950px;
    margin: auto;
}

.filter-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.filter-card h2 {
    margin-top: 0;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-form select {
    flex: 1;
    min-width: 180px;
    padding: 12px;
    border: 1px solid #dbe3ef;
    border-radius: 7px;
}

button, .btn {
    padding: 12px 18px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}

.report {
    background: white;
    padding: 40px;
    border-radius: 10px;
}

.report-header {
    text-align: center;
    border-bottom: 3px double #222;
    padding-bottom: 15px;
    margin-bottom: 25px;
}

.report-header h1 {
    font-size: 23px;
    margin: 0 0 8px;
}

.report-header p {
    margin: 4px;
    font-size: 13px;
}

.report-title {
    text-align: center;
    margin: 25px 0;
}

.report-title h2 {
    font-size: 20px;
    margin-bottom: 5px;
}

.identity {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 25px;
}

.identity p {
    margin: 5px 0;
    font-size: 13px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    border: 1px solid #333;
    padding: 10px;
    font-size: 12px;
    text-align: center;
}

th {
    background: #eaf1ff;
}

td.subject {
    text-align: left;
}

.summary {
    margin-top: 22px;
    border: 1px solid #333;
    padding: 15px;
}

.summary p {
    margin: 8px 0;
}

.signature {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 50px;
    text-align: center;
}

.signature div {
    min-height: 120px;
}

.signature .space {
    height: 65px;
}

.no-data {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

@media(max-width: 600px) {

    body {
        padding: 10px;
    }

    .report {
        padding: 15px;
        overflow-x: auto;
    }

    .identity {
        grid-template-columns: 1fr;
    }

}

@media print {

    @page {
        size: A4 portrait;
        margin: 15mm;
    }

    body {
        padding: 0;
        background: white;
    }

    .container {
        max-width: none;
    }

    .filter-card,
    .no-print {
        display: none !important;
    }

    .report {
        padding: 0;
        border-radius: 0;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- FILTER -->

    <div class="filter-card no-print">

        <h2>Cetak Rapor Siswa</h2>

        <p>Pilih siswa, semester, dan tahun ajaran.</p>

        <form method="GET" class="filter-form">

            <select name="student_id" required>

                <option value="">Pilih Siswa</option>

                <?php foreach ($students as $s): ?>

                    <option
                        value="<?= (int)$s['id'] ?>"
                        <?= $studentId === (int)$s['id']
                            ? 'selected' : '' ?>
                    >
                        <?= e($s['full_name']) ?>
                        (<?= e($s['nis']) ?>)
                    </option>

                <?php endforeach; ?>

            </select>

            <select name="semester">

                <option value="1"
                    <?= $semester === 1 ? 'selected' : '' ?>>
                    Semester 1
                </option>

                <option value="2"
                    <?= $semester === 2 ? 'selected' : '' ?>>
                    Semester 2
                </option>

            </select>

            <select name="academic_year" required>

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

            <button type="submit">
                Tampilkan Rapor
            </button>

        </form>

    </div>

    <?php if ($student): ?>

        <div class="report">

            <!-- KOP RAPOR -->

            <div class="report-header">

                <h1>EDUSTUDENT</h1>

                <p>SISTEM INFORMASI AKADEMIK SEKOLAH</p>

                <p>Laporan Hasil Belajar Peserta Didik</p>

            </div>

            <div class="report-title">

                <h2>LAPORAN HASIL BELAJAR</h2>

                <p>
                    Tahun Ajaran <?= e($academicYear) ?>
                </p>

                <strong>
                    Semester <?= $semester ?>
                </strong>

            </div>

            <!-- IDENTITAS -->

            <h3>Identitas Siswa</h3>

            <div class="identity">

                <div>

                    <p>
                        Nama:
                        <strong><?= e($student['full_name']) ?></strong>
                    </p>

                    <p>
                        NIS:
                        <?= e($student['nis']) ?>
                    </p>

                    <p>
                        NISN:
                        <?= e($student['nisn'] ?? '-') ?>
                    </p>

                </div>

                <div>

                    <p>
                        Kelas:
                        <strong><?= e($student['class_name']) ?></strong>
                    </p>

                    <p>
                        Jenis Kelamin:
                        <?= ($student['gender'] ?? '') === 'L'
                            ? 'Laki-laki' : 'Perempuan' ?>
                    </p>

                    <p>
                        Semester:
                        <?= $semester ?>
                    </p>

                </div>

            </div>

            <!-- NILAI -->

            <h3>Daftar Nilai Akademik</h3>

            <table>

                <thead>

                    <tr>

                        <th>No.</th>

                        <th>Kode</th>

                        <th>Mata Pelajaran</th>

                        <th>Jumlah Penilaian</th>

                        <th>Nilai Rata-rata</th>

                        <th>Predikat</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($subjects)): ?>

                    <tr>

                        <td colspan="6">
                            Belum ada nilai untuk periode ini.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($subjects as $index => $subject): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td><?= e($subject['subject_code']) ?></td>

                            <td class="subject">
                                <?= e($subject['subject_name']) ?>
                            </td>

                            <td>
                                <?= (int)$subject['total_assessments'] ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$subject['average_score'], 2
                                ) ?>
                            </td>

                            <td>
                                <?= e(getPredicate(
                                    (float)$subject['average_score']
                                )) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

            <!-- RINGKASAN -->

            <?php if (!empty($subjects)): ?>

                <div class="summary">

                    <h3>Ringkasan Akademik</h3>

                    <p>
                        Total Mata Pelajaran:
                        <strong><?= count($subjects) ?></strong>
                    </p>

                    <p>
                        Rata-rata Keseluruhan:
                        <strong>
                            <?= number_format($overallAverage, 2) ?>
                        </strong>
                    </p>

                    <p>
                        Predikat:
                        <strong>
                            <?= e(getPredicate($overallAverage)) ?>
                        </strong>
                    </p>

                </div>

            <?php endif; ?>

            <!-- TANDA TANGAN -->

            <div class="signature">

                <div>

                    <p>Mengetahui,</p>

                    <p>Kepala Sekolah</p>

                    <div class="space"></div>

                    <strong>(.............................)</strong>

                </div>

                <div>

                    <p>
                        Tanggal: <?= date('d-m-Y') ?>
                    </p>

                    <p>Guru Mata Pelajaran</p>

                    <div class="space"></div>

                    <strong>
                        <?= e($_SESSION['user']['username']) ?>
                    </strong>

                </div>

            </div>

        </div>

        <div class="no-print" style="margin-top:20px;">

            <button onclick="window.print()">
                Cetak / Simpan PDF
            </button>

            <a href="dashboard.php" class="btn"
               style="background:#64748b;">
                Kembali
            </a>

        </div>

    <?php elseif ($studentId > 0): ?>

        <div class="report no-data">
            Data siswa tidak ditemukan.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
