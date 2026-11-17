
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('guru');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// 1. IDENTITAS GURU
// =====================================

$userId = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT id, nip, full_name
    FROM teachers
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$teacher = $stmt->fetch();

if (!$teacher) {
    exit("Profil guru belum terdaftar. Hubungi administrator.");
}

$teacherId = $teacher['id'];

$teacherName = htmlspecialchars(
    $teacher['full_name'],
    ENT_QUOTES,
    'UTF-8'
);

// =====================================
// 2. STATISTIK KELAS
// =====================================

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT class_id)
    FROM schedules
    WHERE teacher_id = :teacher_id
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$totalClasses = (int) $stmt->fetchColumn();

// =====================================
// 3. STATISTIK SISWA
// =====================================

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN schedules sc ON sc.class_id = s.class_id
    WHERE sc.teacher_id = :teacher_id
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$totalStudents = (int) $stmt->fetchColumn();

// =====================================
// 4. STATISTIK MATA PELAJARAN
// =====================================

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT subject_id)
    FROM schedules
    WHERE teacher_id = :teacher_id
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$totalSubjects = (int) $stmt->fetchColumn();

// =====================================
// 5. STATISTIK TUGAS
// =====================================

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignments
    WHERE teacher_id = :teacher_id
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$totalAssignments = (int) $stmt->fetchColumn();

// =====================================
// 6. JADWAL MENGAJAR HARI INI
// =====================================

$days = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$today = $days[date('l')] ?? '';

$stmt = $pdo->prepare("
    SELECT
        sc.start_time,
        sc.end_time,
        sc.room,
        sub.subject_name,
        c.class_name

    FROM schedules sc

    JOIN subjects sub
        ON sub.id = sc.subject_id

    JOIN classes c
        ON c.id = sc.class_id

    WHERE sc.teacher_id = :teacher_id
    AND sc.day_of_week = :day

    ORDER BY sc.start_time ASC
");

$stmt->execute([
    'teacher_id' => $teacherId,
    'day' => $today
]);

$todaySchedules = $stmt->fetchAll();

// =====================================
// 7. DAFTAR KELAS YANG DIAJAR
// =====================================

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.class_name,
        c.grade_level,
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT sc.subject_id) AS total_subjects

    FROM schedules sc

    JOIN classes c
        ON c.id = sc.class_id

    LEFT JOIN students s
        ON s.class_id = c.id

    WHERE sc.teacher_id = :teacher_id

    GROUP BY
        c.id,
        c.class_name,
        c.grade_level

    ORDER BY
        c.grade_level,
        c.class_name
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$classes = $stmt->fetchAll();

// =====================================
// 8. TUGAS TERBARU
// =====================================

$stmt = $pdo->prepare("
    SELECT
        a.title,
        a.due_date,
        sub.subject_name,
        c.class_name

    FROM assignments a

    JOIN subjects sub
        ON sub.id = a.subject_id

    JOIN classes c
        ON c.id = a.class_id

    WHERE a.teacher_id = :teacher_id

    ORDER BY a.due_date ASC

    LIMIT 5
");

$stmt->execute([
    'teacher_id' => $teacherId
]);

$recentAssignments = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard Guru - EduStudent</title>

    <link rel="stylesheet"
          href="../../assets/css/teacher.css">

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

            <a href="dashboard.php" class="active">
                Dashboard
            </a>

            <a href="#">Kelas Saya</a>

            <a href="siswa.php">Data Siswa</a>

            <a href="#">Jadwal Mengajar</a>

            <a href="rekap_nilai.php">
                Rekap Nilai Akademik
            </a>

            <a href="riwayat_nilai.php">
                 Riwayat Nilai
            </a>

            <a href="#">Tugas Sekolah</a>

            <a href="#">Kehadiran</a>

            <a href="cetak_rapor.php">
                Cetak Rapor Siswa
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

        <!-- LOGOUT -->

        <form action="../../auth/logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit" class="teacher-logout">
                Keluar dari akun
            </button>

        </form>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="teacher-main">

        <!-- NAVBAR -->

        <header class="teacher-navbar">

            <button
                id="menu-toggle"
                class="menu-toggle"
                type="button"
                aria-label="Buka menu"
            >
                ☰
            </button>

            <h3>Dashboard Guru</h3>

            <div class="teacher-profile">

                <div class="teacher-profile-info">

                    <strong>
                        <?= $teacherName ?>
                    </strong>

                    <small>Guru</small>

                </div>

                <div class="teacher-avatar">
                    <?= htmlspecialchars(
                        strtoupper(substr($teacher['full_name'], 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            </div>

        </header>

        <!-- CONTENT -->

        <section class="teacher-content">

            <!-- WELCOME -->

            <div class="teacher-welcome">

                <div>

                    <h1>
                        Selamat datang, <?= $teacherName ?>!
                    </h1>

                    <p>
                        Berikut ringkasan aktivitas mengajar kamu.
                    </p>

                </div>

                <div class="teacher-date">
                    <?= date('d/m/Y') ?>
                </div>

            </div>

            <!-- STATISTIK -->

            <div class="teacher-stats">

                <div class="teacher-stat-card">

                    <span>Total Siswa</span>

                    <h2>
                        <?= $totalStudents ?>
                    </h2>

                    <small>
                        Siswa dari kelas yang diajar
                    </small>

                </div>

                <div class="teacher-stat-card">

                    <span>Total Kelas</span>

                    <h2>
                        <?= $totalClasses ?>
                    </h2>

                    <small>
                        Kelas yang diampu
                    </small>

                </div>

                <div class="teacher-stat-card">

                    <span>Mata Pelajaran</span>

                    <h2>
                        <?= $totalSubjects ?>
                    </h2>

                    <small>
                        Mata pelajaran yang diajar
                    </small>

                </div>

                <div class="teacher-stat-card">

                    <span>Total Tugas</span>

                    <h2>
                        <?= $totalAssignments ?>
                    </h2>

                    <small>
                        Tugas yang telah dibuat
                    </small>

                </div>

            </div>

            <!-- JADWAL DAN INFORMASI GURU -->

            <div class="teacher-grid">

                <!-- JADWAL -->

                <div class="teacher-panel">

                    <div class="teacher-panel-heading">

                        <div>

                            <h3>
                                Jadwal Mengajar Hari Ini
                            </h3>

                            <p>
                                <?= htmlspecialchars(
                                    $today ?: 'Hari libur',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        </div>

                    </div>

                    <?php if (!empty($todaySchedules)): ?>

                        <?php foreach ($todaySchedules as $schedule): ?>

                            <div class="teacher-schedule-item">

                                <div class="teacher-schedule-time">

                                    <?= htmlspecialchars(substr($schedule['start_time'], 0, 5)) ?>

                                    -

                                    <?= htmlspecialchars(substr($schedule['end_time'], 0, 5)) ?>

                                </div>

                                <div class="teacher-schedule-info">

                                    <strong>
                                        <?= htmlspecialchars($schedule['subject_name']) ?>
                                    </strong>

                                    <small>

                                        <?= htmlspecialchars($schedule['class_name']) ?>

                                        ·

                                        <?= htmlspecialchars($schedule['room'] ?? '-') ?>

                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="teacher-empty">

                            Tidak ada jadwal mengajar hari ini.

                        </div>

                    <?php endif; ?>

                </div>

                <!-- INFORMASI GURU -->

                <div class="teacher-panel">

                    <div class="teacher-panel-heading">

                        <div>

                            <h3>
                                Informasi Guru
                            </h3>

                            <p>
                                Identitas akun pengajar
                            </p>

                        </div>

                    </div>

                    <div class="teacher-information">

                        <div>

                            <span>Nama Guru</span>

                            <strong>
                                <?= $teacherName ?>
                            </strong>

                        </div>

                        <div>

                            <span>NIP</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $teacher['nip'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </div>

                        <div>

                            <span>Username</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $_SESSION['user']['username'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

            <!-- DAFTAR KELAS -->

            <div class="teacher-panel teacher-section">

                <div class="teacher-panel-heading">

                    <div>

                        <h3>
                            Kelas yang Saya Ajar
                        </h3>

                        <p>
                            Ringkasan kelas berdasarkan jadwal mengajar
                        </p>

                    </div>

                </div>

                <div class="teacher-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>Nama Kelas</th>

                                <th>Tingkat</th>

                                <th>Jumlah Siswa</th>

                                <th>Mata Pelajaran</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($classes)): ?>

                                <?php foreach ($classes as $class): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($class['class_name']) ?>
                                        </td>

                                        <td>
                                            Kelas <?= htmlspecialchars($class['grade_level']) ?>
                                        </td>

                                        <td>
                                            <?= (int) $class['total_students'] ?> siswa
                                        </td>

                                        <td>
                                            <?= (int) $class['total_subjects'] ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="4" class="teacher-empty">
                                        Belum ada kelas yang diampu.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- TUGAS TERBARU -->

            <div class="teacher-panel teacher-section">

                <div class="teacher-panel-heading">

                    <div>

                        <h3>
                            Tugas Terbaru
                        </h3>

                        <p>
                            Daftar tugas yang telah dibuat
                        </p>

                    </div>

                </div>

                <div class="teacher-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>Nama Tugas</th>

                                <th>Mata Pelajaran</th>

                                <th>Kelas</th>

                                <th>Deadline</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($recentAssignments)): ?>

                                <?php foreach ($recentAssignments as $assignment): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars($assignment['title']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($assignment['subject_name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($assignment['class_name']) ?>
                                        </td>

                                        <td>

                                            <?= !empty($assignment['due_date'])
                                                ? date(
                                                    'd-m-Y H:i',
                                                    strtotime($assignment['due_date'])
                                                )
                                                : '-' ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="4" class="teacher-empty">
                                        Belum ada tugas yang dibuat.
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

<!-- JAVASCRIPT -->

<script src="../../assets/js/teacher.js"></script>

</body>
</html>
