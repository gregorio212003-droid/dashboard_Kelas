
<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/session.php';

/* =====================================
   1. CEK LOGIN DAN ROLE GURU
===================================== */

if (
    empty($_SESSION['user']) ||
    ($_SESSION['user']['role'] ?? '') !== 'guru'
) {
    header("Location: ../../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user']['id'];

/* =====================================
   2. FUNGSI BANTUAN
===================================== */

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================
   3. AMBIL DATA GURU
===================================== */

$stmt = $pdo->prepare("
    SELECT id, full_name, nip
    FROM teachers
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Data guru tidak ditemukan. Hubungi administrator.");
}

$teacherId = (int) $teacher['id'];

/* =====================================
   4. AMBIL JADWAL MENGAJAR GURU
===================================== */

$stmt = $pdo->prepare("
    SELECT
        sc.id AS schedule_id,
        sc.class_id,
        sc.subject_id,
        sc.day_of_week,
        sc.start_time,
        sc.end_time,
        c.class_name,
        sub.subject_name
    FROM schedules sc
    INNER JOIN classes c ON c.id = sc.class_id
    INNER JOIN subjects sub ON sub.id = sc.subject_id
    WHERE sc.teacher_id = :teacher_id
    ORDER BY sc.day_of_week, sc.start_time
");

$stmt->execute(['teacher_id' => $teacherId]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   5. PILIH JADWAL
===================================== */

$selectedScheduleId = (int) (
    $_POST['schedule_id'] ??
    $_GET['schedule_id'] ??
    0
);

$selectedSchedule = null;

foreach ($schedules as $schedule) {
    if ((int) $schedule['schedule_id'] === $selectedScheduleId) {
        $selectedSchedule = $schedule;
        break;
    }
}

/* =====================================
   6. SIMPAN NILAI
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif (!$selectedSchedule) {

        $error = "Jadwal mengajar tidak valid.";

    } else {

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $assessmentType = $_POST['assessment_type'] ?? '';
        $scoreInput = $_POST['score'] ?? '';
        $semester = (int) ($_POST['semester'] ?? 0);
        $academicYear = trim($_POST['academic_year'] ?? '');

        $allowedTypes = ['Tugas', 'UTS', 'UAS', 'Praktik'];

        if ($studentId <= 0) {

            $error = "Silakan pilih siswa.";

        } elseif (!in_array($assessmentType, $allowedTypes, true)) {

            $error = "Jenis penilaian tidak valid.";

        } elseif (
            $scoreInput === '' ||
            !is_numeric($scoreInput) ||
            (float) $scoreInput < 0 ||
            (float) $scoreInput > 100
        ) {

            $error = "Nilai harus berupa angka antara 0 sampai 100.";

        } elseif (!in_array($semester, [1, 2], true)) {

            $error = "Semester harus 1 atau 2.";

        } elseif (!preg_match('/^\d{4}\/\d{4}$/', $academicYear)) {

            $error = "Format tahun ajaran harus seperti 2026/2027.";

        } else {

            /* Pastikan siswa berada di kelas jadwal */

            $stmt = $pdo->prepare("
                SELECT id
                FROM students
                WHERE id = :student_id
                AND class_id = :class_id
                LIMIT 1
            ");

            $stmt->execute([
                'student_id' => $studentId,
                'class_id' => $selectedSchedule['class_id']
            ]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {

                $error = "Siswa tidak ditemukan dalam kelas tersebut.";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO grades (
                            student_id,
                            subject_id,
                            teacher_id,
                            assessment_type,
                            score,
                            semester,
                            academic_year
                        ) VALUES (
                            :student_id,
                            :subject_id,
                            :teacher_id,
                            :assessment_type,
                            :score,
                            :semester,
                            :academic_year
                        )
                    ");

                    $stmt->execute([
                        'student_id' => $studentId,
                        'subject_id' => $selectedSchedule['subject_id'],
                        'teacher_id' => $teacherId,
                        'assessment_type' => $assessmentType,
                        'score' => $scoreInput,
                        'semester' => $semester,
                        'academic_year' => $academicYear
                    ]);

                    $success = "Nilai berhasil disimpan!";

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                } catch (PDOException $ex) {

                    $error = "Gagal menyimpan nilai. Silakan periksa database.";

                }
            }
        }
    }
}

/* =====================================
   7. AMBIL DAFTAR SISWA
===================================== */

$students = [];

if ($selectedSchedule) {

    $stmt = $pdo->prepare("
        SELECT id, nis, full_name
        FROM students
        WHERE class_id = :class_id
        ORDER BY full_name ASC
    ");

    $stmt->execute([
        'class_id' => $selectedSchedule['class_id']
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Input Nilai - EduStudent</title>

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
            max-width: 850px;
            margin: 20px auto;
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

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 9px;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 13px;
            border: 1px solid #dce3ed;
            border-radius: 7px;
            font-size: 14px;
            background: white;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2864f0;
        }

        .btn {
            display: inline-block;
            padding: 13px 20px;
            background: #2864f0;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn:hover {
            background: #174fc9;
        }

        .btn-back {
            background: #64748b;
        }

        .message {
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .info {
            background: #eff6ff;
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            color: #1e40af;
            line-height: 1.8;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>Input Nilai Siswa</h1>

        <p>
            Selamat datang, <?= e($teacher['full_name']) ?>.
            Silakan masukkan nilai akademik siswa.
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

    <!-- PILIH JADWAL -->

    <div class="card">

        <h2>1. Pilih Kelas dan Mata Pelajaran</h2>

        <form method="GET">

            <div class="form-group">

                <label>Jadwal Mengajar</label>

                <select
                    name="schedule_id"
                    required
                    onchange="this.form.submit()"
                >

                    <option value="">-- Pilih Jadwal --</option>

                    <?php foreach ($schedules as $item): ?>

                        <option
                            value="<?= (int) $item['schedule_id'] ?>"
                            <?= $selectedScheduleId === (int) $item['schedule_id'] ? 'selected' : '' ?>
                        >

                            <?= e($item['class_name']) ?>
                            -
                            <?= e($item['subject_name']) ?>
                            (<?= e($item['day_of_week']) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <noscript>
                <button type="submit" class="btn">
                    Pilih Jadwal
                </button>
            </noscript>

        </form>

        <?php if (empty($schedules)): ?>

            <div class="message error">
                Belum ada jadwal mengajar yang ditugaskan kepada Anda.
            </div>

        <?php endif; ?>

    </div>

    <?php if ($selectedSchedule): ?>

        <!-- INFORMASI KELAS -->

        <div class="card">

            <h2>2. Informasi Pembelajaran</h2>

            <div class="info">

                <strong>Kelas:</strong>
                <?= e($selectedSchedule['class_name']) ?>
                <br>

                <strong>Mata Pelajaran:</strong>
                <?= e($selectedSchedule['subject_name']) ?>
                <br>

                <strong>Hari:</strong>
                <?= e($selectedSchedule['day_of_week']) ?>
                <br>

                <strong>Waktu:</strong>
                <?= e(substr($selectedSchedule['start_time'], 0, 5)) ?>
                -
                <?= e(substr($selectedSchedule['end_time'], 0, 5)) ?>

            </div>

            <!-- FORM INPUT NILAI -->

            <h2>3. Form Input Nilai</h2>

            <?php if (empty($students)): ?>

                <div class="message error">
                    Belum ada siswa terdaftar dalam kelas ini.
                </div>

            <?php else: ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="schedule_id"
                        value="<?= (int) $selectedScheduleId ?>"
                    >

                    <div class="form-group">

                        <label>Pilih Siswa</label>

                        <select name="student_id" required>

                            <option value="">
                                -- Pilih Siswa --
                            </option>

                            <?php foreach ($students as $student): ?>

                                <option
                                    value="<?= (int) $student['id'] ?>"
                                    <?= (int) ($_POST['student_id'] ?? 0) === (int) $student['id'] ? 'selected' : '' ?>
                                >

                                    <?= e($student['full_name']) ?>
                                    (NIS: <?= e($student['nis']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Jenis Penilaian</label>

                        <select name="assessment_type" required>

                            <option value="">
                                -- Pilih Jenis Penilaian --
                            </option>

                            <?php
                            $types = ['Tugas', 'UTS', 'UAS', 'Praktik'];
                            ?>

                            <?php foreach ($types as $type): ?>

                                <option
                                    value="<?= e($type) ?>"
                                    <?= ($_POST['assessment_type'] ?? '') === $type ? 'selected' : '' ?>
                                >

                                    <?= e($type) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Nilai Siswa (0 - 100)</label>

                        <input
                            type="number"
                            name="score"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="Contoh: 90"
                            value="<?= e($_POST['score'] ?? '') ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Semester</label>

                        <select name="semester" required>

                            <option value="">-- Pilih Semester --</option>

                            <option value="1"
                                <?= ($_POST['semester'] ?? '') == '1' ? 'selected' : '' ?>>
                                Semester 1
                            </option>

                            <option value="2"
                                <?= ($_POST['semester'] ?? '') == '2' ? 'selected' : '' ?>>
                                Semester 2
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>Tahun Ajaran</label>

                        <input
                            type="text"
                            name="academic_year"
                            placeholder="Contoh: 2026/2027"
                            value="<?= e($_POST['academic_year'] ?? '') ?>"
                            pattern="\d{4}/\d{4}"
                            required
                        >

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn">
                            Simpan Nilai
                        </button>

                        <a
                            href="dashboard.php"
                            class="btn btn-back"
                        >
                            Kembali
                        </a>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
