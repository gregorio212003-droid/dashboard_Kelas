
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('guru');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// 1. IDENTITAS GURU
// =====================================

$userId = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT id, full_name
    FROM teachers
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$teacher = $stmt->fetch();

if (!$teacher) {
    exit("Profil guru tidak ditemukan.");
}

$teacherId = $teacher['id'];

// =====================================
// 2. ID SISWA
// =====================================

$studentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {
    exit("ID siswa tidak valid.");
}

// =====================================
// 3. MENGAMBIL PROFIL SISWA
// =====================================

// Guru hanya boleh melihat siswa
// dari kelas yang diajarnya.

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.nis,
        s.nisn,
        s.full_name,
        s.gender,
        s.birth_date,
        s.address,
        c.class_name,
        c.grade_level

    FROM students s

    INNER JOIN classes c
        ON c.id = s.class_id

    WHERE s.id = :student_id

    AND EXISTS (
        SELECT 1
        FROM schedules sc
        WHERE sc.class_id = s.class_id
        AND sc.teacher_id = :teacher_id
    )

    LIMIT 1
");

$stmt->execute([
    'student_id' => $studentId,
    'teacher_id' => $teacherId
]);

$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit("Siswa tidak ditemukan atau bukan bagian dari kelas yang kamu ajar.");
}

// =====================================
// 4. MENGAMBIL DATA NILAI
// =====================================

$stmt = $pdo->prepare("
    SELECT
        g.score,
        g.assessment_type,
        g.semester,
        g.academic_year,
        sub.subject_name

    FROM grades g

    INNER JOIN subjects sub
        ON sub.id = g.subject_id

    WHERE g.student_id = :student_id

    ORDER BY sub.subject_name ASC
");

$stmt->execute([
    'student_id' => $studentId
]);

$grades = $stmt->fetchAll();

// =====================================
// 5. MENGHITUNG RATA-RATA NILAI
// =====================================

$totalScore = 0;
$totalGrades = count($grades);

foreach ($grades as $grade) {
    $totalScore += (float) $grade['score'];
}

$averageScore = $totalGrades > 0
    ? round($totalScore / $totalGrades, 2)
    : 0;

// =====================================
// 6. MENGAMBIL RIWAYAT KEHADIRAN
// =====================================

$stmt = $pdo->prepare("
    SELECT
        attendance_date,
        status,
        notes

    FROM attendance

    WHERE student_id = :student_id

    ORDER BY attendance_date DESC

    LIMIT 10
");

$stmt->execute([
    'student_id' => $studentId
]);

$attendances = $stmt->fetchAll();

// =====================================
// 7. MENGHITUNG KEHADIRAN
// =====================================

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) AS alpha

    FROM attendance

    WHERE student_id = :student_id
");

$stmt->execute([
    'student_id' => $studentId
]);

$attendanceStats = $stmt->fetch();

$totalAttendance = (int) $attendanceStats['total'];

$attendancePercentage = $totalAttendance > 0
    ? round(
        ((int) $attendanceStats['hadir'] / $totalAttendance) * 100,
        1
    )
    : 0;

// =====================================
// 8. HELPER OUTPUT
// =====================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

$gender = '-';

if ($student['gender'] === 'L') {
    $gender = 'Laki-laki';
} elseif ($student['gender'] === 'P') {
    $gender = 'Perempuan';
}

$birthDate = !empty($student['birth_date'])
    ? date('d-m-Y', strtotime($student['birth_date']))
    : '-';

$teacherName = e($teacher['full_name']);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Detail Siswa - EduStudent</title>

    <link rel="stylesheet"
          href="../../assets/css/teacher.css">

    <style>

        .detail-back {
            display: inline-block;
            color: #2563eb;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .detail-profile {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-avatar {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
        }

        .detail-profile h2 {
            font-size: 22px;
            margin-bottom: 7px;
        }

        .detail-profile p {
            color: #64748b;
            font-size: 13px;
        }

        .detail-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .detail-info-item {
            padding: 14px 0;
            border-bottom: 1px solid #edf0f4;
        }

        .detail-info-item span {
            display: block;
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .detail-info-item strong {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .detail-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .detail-stat {
            background: white;
            border: 1px solid #e8ebf0;
            border-radius: 12px;
            padding: 20px;
        }

        .detail-stat span {
            color: #64748b;
            font-size: 12px;
        }

        .detail-stat h2 {
            color: #2563eb;
            font-size: 25px;
            margin-top: 12px;
        }

        .status-hadir {
            color: #16a34a;
            font-weight: bold;
        }

        .status-sakit {
            color: #d97706;
            font-weight: bold;
        }

        .status-izin {
            color: #2563eb;
            font-weight: bold;
        }

        .status-alpha {
            color: #dc2626;
            font-weight: bold;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        @media(max-width: 800px) {
            .detail-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .detail-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 500px) {
            .detail-info-grid {
                grid-template-columns: 1fr;
            }

            .detail-profile {
                align-items: flex-start;
            }

            .detail-stats {
                grid-template-columns: 1fr 1fr;
            }
        }

    </style>

</head>

<body>

<div class="teacher-layout">

    <!-- SIDEBAR -->

    <aside class="teacher-sidebar" id="sidebar">

        <div class="teacher-logo">
            EduStudent
        </div>

        <div class="teacher-menu-label">
            MENU UTAMA
        </div>

        <nav class="teacher-nav">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="#">
                Kelas Saya
            </a>

            <a href="siswa.php" class="active">
                Data Siswa
            </a>

            <a href="#">
                Jadwal Mengajar
            </a>

            <a href="#">
                Nilai Akademik
            </a>

            <a href="#">
                Tugas Sekolah
            </a>

            <a href="#">
                Kehadiran
            </a>

        </nav>

        <div class="teacher-menu-label">
            LAINNYA
        </div>

        <nav class="teacher-nav">

            <a href="#">Pengumuman</a>
            <a href="#">Profil Saya</a>
            <a href="#">Pengaturan</a>

        </nav>

        <form action="../../auth/logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
            >

            <button type="submit" class="teacher-logout">
                Keluar dari akun
            </button>

        </form>

    </aside>

    <!-- MAIN -->

    <main class="teacher-main">

        <header class="teacher-navbar">

            <button
                id="menu-toggle"
                class="menu-toggle"
                type="button"
            >
                ☰
            </button>

            <h3>Detail Siswa</h3>

            <div class="teacher-profile">

                <div class="teacher-profile-info">

                    <strong><?= $teacherName ?></strong>

                    <small>Guru</small>

                </div>

                <div class="teacher-avatar">
                    <?= e(strtoupper(substr($teacher['full_name'], 0, 1))) ?>
                </div>

            </div>

        </header>

        <section class="teacher-content">

            <a href="siswa.php" class="detail-back">
                ← Kembali ke Daftar Siswa
            </a>

            <!-- PROFIL SISWA -->

            <div class="teacher-panel detail-section">

                <div class="detail-profile">

                    <div class="detail-avatar">

                        <?= e(strtoupper(substr($student['full_name'], 0, 1))) ?>

                    </div>

                    <div>

                        <h2>
                            <?= e($student['full_name']) ?>
                        </h2>

                        <p>
                            <?= e($student['class_name']) ?>
                            · Kelas <?= e($student['grade_level']) ?>
                        </p>

                    </div>

                </div>

                <div class="detail-info-grid">

                    <div class="detail-info-item">

                        <span>NIS</span>

                        <strong><?= e($student['nis']) ?></strong>

                    </div>

                    <div class="detail-info-item">

                        <span>NISN</span>

                        <strong><?= e($student['nisn']) ?></strong>

                    </div>

                    <div class="detail-info-item">

                        <span>Jenis Kelamin</span>

                        <strong><?= e($gender) ?></strong>

                    </div>

                    <div class="detail-info-item">

                        <span>Tanggal Lahir</span>

                        <strong><?= e($birthDate) ?></strong>

                    </div>

                    <div class="detail-info-item">

                        <span>Kelas</span>

                        <strong><?= e($student['class_name']) ?></strong>

                    </div>

                    <div class="detail-info-item">

                        <span>Alamat</span>

                        <strong><?= e($student['address']) ?></strong>

                    </div>

                </div>

            </div>

            <!-- STATISTIK AKADEMIK -->

            <div class="detail-stats">

                <div class="detail-stat">

                    <span>Rata-rata Nilai</span>

                    <h2><?= $averageScore ?></h2>

                </div>

                <div class="detail-stat">

                    <span>Total Penilaian</span>

                    <h2><?= $totalGrades ?></h2>

                </div>

                <div class="detail-stat">

                    <span>Kehadiran</span>

                    <h2><?= $attendancePercentage ?>%</h2>

                </div>

                <div class="detail-stat">

                    <span>Total Kehadiran</span>

                    <h2><?= $totalAttendance ?></h2>

                </div>

            </div>

            <!-- RIWAYAT NILAI -->

            <div class="teacher-panel detail-section">

                <div class="teacher-panel-heading">

                    <div>

                        <h3>Riwayat Nilai Akademik</h3>

                        <p>Daftar hasil penilaian siswa</p>

                    </div>

                </div>

                <div class="teacher-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>No.</th>

                                <th>Mata Pelajaran</th>

                                <th>Jenis Penilaian</th>

                                <th>Nilai</th>

                                <th>Semester</th>

                                <th>Tahun Ajaran</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($grades)): ?>

                                <?php foreach ($grades as $index => $grade): ?>

                                    <tr>

                                        <td><?= $index + 1 ?></td>

                                        <td><?= e($grade['subject_name']) ?></td>

                                        <td><?= e($grade['assessment_type']) ?></td>

                                        <td>
                                            <strong><?= e($grade['score']) ?></strong>
                                        </td>

                                        <td><?= e($grade['semester']) ?></td>

                                        <td><?= e($grade['academic_year']) ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="6" class="teacher-empty">
                                        Belum ada data nilai siswa.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- RIWAYAT KEHADIRAN -->

            <div class="teacher-panel detail-section">

                <div class="teacher-panel-heading">

                    <div>

                        <h3>Riwayat Kehadiran</h3>

                        <p>10 catatan kehadiran terakhir</p>

                    </div>

                </div>

                <div class="teacher-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>No.</th>

                                <th>Tanggal</th>

                                <th>Status</th>

                                <th>Keterangan</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($attendances)): ?>

                                <?php foreach ($attendances as $index => $attendance): ?>

                                    <?php
                                    $statusClass = 'status-' .
                                        strtolower($attendance['status']);
                                    ?>

                                    <tr>

                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= date(
                                                'd-m-Y',
                                                strtotime($attendance['attendance_date'])
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="<?= e($statusClass) ?>">

                                                <?= e($attendance['status']) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= e($attendance['notes']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="4" class="teacher-empty">
                                        Belum ada riwayat kehadiran.
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

<script src="../../assets/js/teacher.js"></script>

</body>

</html>
