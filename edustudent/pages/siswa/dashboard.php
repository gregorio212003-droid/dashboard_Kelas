
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('siswa');

require_once __DIR__ . '/../../config/koneksi.php';

$userId = (int) $_SESSION['user']['id'];

// ==========================================
// API NOTIFIKASI DASHBOARD (AJAX)
// ==========================================

if (isset($_GET['ajax']) && $_GET['ajax'] === 'notifications') {

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {

        $stmtAjaxCount = $pdo->prepare("
            SELECT COUNT(*)
            FROM announcements a
            LEFT JOIN announcement_reads ar
                ON ar.announcement_id = a.id
                AND ar.user_id = :user_id
            WHERE ar.id IS NULL
        ");

        $stmtAjaxCount->execute([
            'user_id' => $userId
        ]);

        $ajaxUnreadCount = (int) $stmtAjaxCount->fetchColumn();

        $stmtAjaxList = $pdo->prepare("
            SELECT
                a.id,
                a.title,
                a.content,
                a.created_at
            FROM announcements a
            LEFT JOIN announcement_reads ar
                ON ar.announcement_id = a.id
                AND ar.user_id = :user_id
            WHERE ar.id IS NULL
            ORDER BY a.created_at DESC
            LIMIT 5
        ");

        $stmtAjaxList->execute([
            'user_id' => $userId
        ]);

        $ajaxNotifications = [];

        foreach ($stmtAjaxList->fetchAll(PDO::FETCH_ASSOC) as $item) {

            $plainContent = trim(strip_tags($item['content'] ?? ''));

            if (function_exists('mb_strimwidth')) {
                $plainContent = mb_strimwidth(
                    $plainContent,
                    0,
                    90,
                    '...',
                    'UTF-8'
                );
            } else {
                $plainContent = substr($plainContent, 0, 90);
            }

            $ajaxNotifications[] = [
                'id' => (int) $item['id'],
                'title' => $item['title'],
                'content' => $plainContent,
                'created_at' => date(
                    'd-m-Y H:i',
                    strtotime($item['created_at'])
                )
            ];
        }

        echo json_encode([
            'success' => true,
            'unread_count' => $ajaxUnreadCount,
            'notifications' => $ajaxNotifications
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengambil notifikasi.'
        ]);

    }

    exit;
}

// ==========================================
// 1. IDENTITAS SISWA
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.nis,
        s.full_name,
        s.class_id,
        c.class_name,
        c.grade_level
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE s.user_id = :user_id
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    exit("Profil siswa belum terdaftar. Hubungi administrator.");
}

$studentId = $student['id'];
$classId = $student['class_id'] ?? null;

// ==========================================
// 2. STATISTIK NILAI
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        AVG(score) AS average_score,
        COUNT(DISTINCT subject_id) AS total_subjects
    FROM grades
    WHERE student_id = :student_id
");

$stmt->execute(['student_id' => $studentId]);
$gradeStats = $stmt->fetch();

$averageScore = round((float)($gradeStats['average_score'] ?? 0), 1);
$totalSubjects = (int)($gradeStats['total_subjects'] ?? 0);

// ==========================================
// 3. STATISTIK KEHADIRAN
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_attendance,
        SUM(
            CASE WHEN status = 'Hadir'
            THEN 1 ELSE 0 END
        ) AS total_present
    FROM attendance
    WHERE student_id = :student_id
");

$stmt->execute(['student_id' => $studentId]);
$attendanceStats = $stmt->fetch();

$totalAttendance = (int)($attendanceStats['total_attendance'] ?? 0);
$totalPresent = (int)($attendanceStats['total_present'] ?? 0);

$attendancePercentage = $totalAttendance > 0
    ? round(($totalPresent / $totalAttendance) * 100)
    : 0;

// ==========================================
// 4. TUGAS BELUM DIKERJAKAN
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.title,
        a.due_date,
        sub.subject_name,
        COALESCE(
            ass.status,
            'Belum Dikerjakan'
        ) AS submission_status
    FROM assignments a
    JOIN subjects sub ON sub.id = a.subject_id
    LEFT JOIN assignment_submissions ass
        ON ass.assignment_id = a.id
        AND ass.student_id = :student_id
    WHERE a.class_id = :class_id
    AND (
        ass.id IS NULL
        OR ass.status = 'Belum Dikerjakan'
    )
    ORDER BY a.due_date ASC
    LIMIT 5
");

$assignments = [];

if ($classId) {
    $stmt->execute([
        'student_id' => $studentId,
        'class_id' => $classId
    ]);

    $assignments = $stmt->fetchAll();
}

// ==========================================
// 5. JADWAL HARI INI
// ==========================================

$days = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$today = $days[date('l')] ?? '';

$schedules = [];

if ($classId && $today !== '') {

    $stmt = $pdo->prepare("
        SELECT
            sc.start_time,
            sc.end_time,
            sc.room,
            sub.subject_name,
            t.full_name AS teacher_name
        FROM schedules sc
        JOIN subjects sub ON sub.id = sc.subject_id
        JOIN teachers t ON t.id = sc.teacher_id
        WHERE sc.class_id = :class_id
        AND sc.day_of_week = :day
        ORDER BY sc.start_time ASC
    ");

    $stmt->execute([
        'class_id' => $classId,
        'day' => $today
    ]);

    $schedules = $stmt->fetchAll();
}

// ==========================================
// 6. GRAFIK PERKEMBANGAN NILAI
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        sub.subject_name,
        ROUND(AVG(g.score), 1) AS average_score
    FROM grades g
    JOIN subjects sub ON sub.id = g.subject_id
    WHERE g.student_id = :student_id
    GROUP BY g.subject_id, sub.subject_name
    ORDER BY sub.subject_name ASC
");

$stmt->execute(['student_id' => $studentId]);
$gradeChart = $stmt->fetchAll();

$chartLabels = array_column($gradeChart, 'subject_name');

$chartValues = array_map(
    'floatval',
    array_column($gradeChart, 'average_score')
);

// ==========================================
// 7. PENGUMUMAN TERBARU
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        content,
        created_at
    FROM announcements
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute();
$announcements = $stmt->fetchAll();

// ==========================================
// 8. JUMLAH PENGUMUMAN BELUM DIBACA
// ==========================================

$stmtNotification = $pdo->prepare("
    SELECT COUNT(*)
    FROM announcements a
    LEFT JOIN announcement_reads ar
        ON ar.announcement_id = a.id
        AND ar.user_id = :user_id
    WHERE ar.id IS NULL
");

$stmtNotification->execute(['user_id' => $userId]);
$unreadCount = (int) $stmtNotification->fetchColumn();

// ==========================================
// 9. DATA TAMPILAN
// ==========================================

$displayName = htmlspecialchars(
    $student['full_name'],
    ENT_QUOTES,
    'UTF-8'
);

$className = htmlspecialchars(
    $student['class_name'] ?? 'Belum ditentukan',
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard Siswa - EduStudent</title>

    <link rel="stylesheet"
          href="../../assets/css/student.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        /* =====================================
           NOTIFIKASI DASHBOARD
        ===================================== */

        .notification-wrapper {
            position: relative;
            display: inline-block;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .notification-bell {
            position: relative;
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: #eff6ff;
            color: #1e293b;
            font-size: 21px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-bell:hover {
            background: #dbeafe;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 19px;
            height: 19px;
            padding: 0 4px;
            border: 2px solid #fff;
            border-radius: 20px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .notification-dropdown {
            position: absolute;
            top: 56px;
            right: 0;
            width: 350px;
            max-width: calc(100vw - 30px);
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            z-index: 9999;
            display: none;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-dropdown-header {
            padding: 15px 17px;
            border-bottom: 1px solid #e2e8f0;
        }

        .notification-dropdown-header h3 {
            margin: 0 0 4px;
            color: #1e293b;
            font-size: 16px;
        }

        .notification-dropdown-header span {
            color: #64748b;
            font-size: 12px;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 14px 16px;
            color: #334155;
            text-decoration: none;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .notification-item:hover {
            background: #eff6ff;
        }

        .notification-item-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-item-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item-content strong {
            display: block;
            margin-bottom: 5px;
            color: #1e40af;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .notification-item-content p {
            margin: 0 0 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .notification-item-content small {
            color: #94a3b8;
            font-size: 11px;
        }

        .notification-empty {
            padding: 28px 15px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        .notification-dropdown-footer {
            padding: 13px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .notification-dropdown-footer a {
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .notification-dropdown-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {

            .notification-dropdown {
                position: fixed;
                top: 75px;
                right: 10px;
                width: calc(100vw - 20px);
            }

        }

    </style>

</head>

<body>

<div class="student-layout">

    <!-- ==================================
         SIDEBAR
    =================================== -->

    <aside class="student-sidebar" id="sidebar">

        <div class="student-logo">
            EduStudent
        </div>

        <div class="sidebar-label">
            MENU UTAMA
        </div>

        <nav class="student-nav">

            <a href="dashboard.php" class="active">
                Dashboard
            </a>

            <a href="#">
                Jadwal Pelajaran
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

            <a href="#">
                Kalender
            </a>

        </nav>

        <div class="sidebar-label">
            LAINNYA
        </div>

        <nav class="student-nav">

            <a href="pengumuman.php">
                Pengumuman
            </a>

            <a href="#">
                Profil Saya
            </a>

            <a href="#">
                Pengaturan
            </a>

        </nav>

        <!-- LOGOUT -->

        <form action="../../auth/logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <button class="logout-button" type="submit">
                Keluar dari akun
            </button>

        </form>

    </aside>


    <!-- ==================================
         MAIN CONTENT
    =================================== -->

    <main class="student-main">

        <!-- NAVBAR -->

        <header class="student-navbar">

            <button id="menu-toggle" class="menu-toggle">
                ☰
            </button>

            <h3>Dashboard</h3>

            <div class="navbar-profile">

                <!-- NOTIFIKASI -->
                <div class="notification-wrapper">

                    <button
                        type="button"
                        class="notification-bell"
                        id="notificationBell"
                        aria-label="Notifikasi"
                        aria-expanded="false"
                        title="Notifikasi pengumuman"
                    >

                        <span class="bell-icon">🔔</span>

                        <span
                            class="notification-badge"
                            id="notificationBadge"
                            <?= $unreadCount > 0 ? '' : 'style="display:none;"' ?>
                        >
                            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                        </span>

                    </button>

                    <div
                        class="notification-dropdown"
                        id="notificationDropdown"
                    >

                        <div class="notification-dropdown-header">

                            <h3>Notifikasi</h3>

                            <span>
                                Pengumuman terbaru yang belum dibaca
                            </span>

                        </div>

                        <div
                            class="notification-list"
                            id="notificationList"
                        >

                            <div
                                class="notification-empty"
                                id="notificationLoading"
                            >
                                Memuat notifikasi...
                            </div>

                        </div>

                        <div class="notification-dropdown-footer">

                            <a href="pengumuman.php">
                                Lihat Semua Pengumuman →
                            </a>

                        </div>

                    </div>

                </div>

                <div class="profile-info">

                    <strong><?= $displayName ?></strong>

                    <small>Siswa</small>

                </div>

                <div class="profile-avatar">

                    <?= htmlspecialchars(
                        strtoupper(substr($student['full_name'], 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>

        </header>


        <!-- ==================================
             CONTENT
        =================================== -->

        <section class="student-content">


            <!-- SELAMAT DATANG -->

            <div class="welcome-section">

                <div>

                    <h1>
                        Selamat datang, <?= $displayName ?>!
                    </h1>

                    <p>
                        Berikut ringkasan aktivitas akademik kamu.
                    </p>

                </div>

                <span class="class-badge">
                    <?= $className ?>
                </span>

            </div>


            <!-- ==================================
                 PENGUMUMAN TERBARU
            =================================== -->

            <div class="student-panel announcement-panel">

                <div class="panel-heading">

                    <h3>Pengumuman Terbaru</h3>

                    <span>Informasi dari administrator</span>

                </div>

                <?php if (!empty($announcements)): ?>

                    <?php foreach ($announcements as $announcement): ?>

                        <div style="
                            padding: 16px;
                            margin-top: 12px;
                            border: 1px solid #e2e8f0;
                            border-radius: 10px;
                            background: #ffffff;
                        ">

                            <h4 style="
                                margin: 0 0 8px;
                                color: #2563eb;
                            ">

                                <?= htmlspecialchars(
                                    $announcement['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h4>

                            <small style="color: #94a3b8;">

                                <?= date(
                                    'd-m-Y H:i',
                                    strtotime($announcement['created_at'])
                                ) ?>

                            </small>

                            <p style="
                                color: #475569;
                                line-height: 1.6;
                                margin-bottom: 0;
                            ">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $announcement['content'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>

                            </p>

                        </div>

                    <?php endforeach; ?>

                    <div style="margin-top: 18px;">

                        <a href="pengumuman.php"
                           style="
                               color: #2563eb;
                               text-decoration: none;
                               font-weight: bold;
                           ">

                            Lihat Semua Pengumuman →

                        </a>

                    </div>

                <?php else: ?>

                    <p class="empty-state">

                        Belum ada pengumuman dari administrator.

                    </p>

                <?php endif; ?>

            </div>


            <!-- ==================================
                 STATISTIK
            =================================== -->

            <div class="student-stats">

                <div class="student-card">

                    <span>Rata-rata Nilai</span>

                    <h2>
                        <?= number_format($averageScore, 1) ?>
                    </h2>

                    <small>Seluruh penilaian</small>

                </div>


                <div class="student-card">

                    <span>Mata Pelajaran</span>

                    <h2>
                        <?= $totalSubjects ?>
                    </h2>

                    <small>Mata pelajaran yang memiliki nilai</small>

                </div>


                <div class="student-card">

                    <span>Kehadiran</span>

                    <h2>
                        <?= $attendancePercentage ?>%
                    </h2>

                    <small>
                        <?= $totalPresent ?> kali hadir
                    </small>

                </div>


                <div class="student-card">

                    <span>Tugas Aktif</span>

                    <h2>
                        <?= count($assignments) ?>
                    </h2>

                    <small>Belum dikerjakan</small>

                </div>

            </div>


            <!-- ==================================
                 GRAFIK DAN JADWAL
            =================================== -->

            <div class="student-grid">


                <!-- GRAFIK NILAI -->

                <div class="student-panel">

                    <div class="panel-heading">

                        <h3>Perkembangan Nilai</h3>

                        <span>
                            Rata-rata per mata pelajaran
                        </span>

                    </div>

                    <div class="chart-container">

                        <canvas id="gradeChart"></canvas>

                    </div>

                </div>


                <!-- JADWAL HARI INI -->

                <div class="student-panel">

                    <div class="panel-heading">

                        <h3>Jadwal Hari Ini</h3>

                        <span>
                            <?= htmlspecialchars($today ?: 'Hari libur') ?>
                        </span>

                    </div>


                    <?php if ($schedules): ?>

                        <?php foreach ($schedules as $schedule): ?>

                            <div class="schedule-item">

                                <div class="schedule-time">

                                    <?= substr(
                                        $schedule['start_time'],
                                        0,
                                        5
                                    ) ?>

                                    -

                                    <?= substr(
                                        $schedule['end_time'],
                                        0,
                                        5
                                    ) ?>

                                </div>


                                <strong>

                                    <?= htmlspecialchars(
                                        $schedule['subject_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>


                                <small>

                                    <?= htmlspecialchars(
                                        $schedule['teacher_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                    ·

                                    <?= htmlspecialchars(
                                        $schedule['room'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="empty-state">

                            Belum ada jadwal untuk hari ini.

                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- ==================================
                 TUGAS BELUM DIKERJAKAN
            =================================== -->

            <div class="student-panel assignment-panel">

                <div class="panel-heading">

                    <h3>Tugas yang Belum Dikerjakan</h3>

                    <span>Daftar tugas terbaru</span>

                </div>


                <div class="table-responsive">

                    <table>

                        <thead>

                            <tr>

                                <th>Nama Tugas</th>

                                <th>Mata Pelajaran</th>

                                <th>Deadline</th>

                                <th>Status</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if ($assignments): ?>

                                <?php foreach ($assignments as $assignment): ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $assignment['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $assignment['subject_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= $assignment['due_date']
                                                ? date(
                                                    'd-m-Y H:i',
                                                    strtotime(
                                                        $assignment['due_date']
                                                    )
                                                )
                                                : '-' ?>

                                        </td>


                                        <td>

                                            <span class="status-pending">

                                                Belum Dikerjakan

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="4" class="empty-state">

                                        Tidak ada tugas yang belum dikerjakan.

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


        </section>



<!-- ==================================
     JAVASCRIPT GRAFIK
=================================== -->

<script>

const chartLabels = <?= json_encode(
    $chartLabels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;

const chartValues = <?= json_encode($chartValues) ?>;

const chartElement = document.getElementById('gradeChart');

if (chartElement) {

    new Chart(chartElement, {

        type: 'bar',

        data: {

            labels: chartLabels,

            datasets: [{

                label: 'Rata-rata Nilai',

                data: chartValues,

                backgroundColor: '#3b82f6',

                borderRadius: 7

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    max: 100

                }

            },

            plugins: {

                legend: {

                    display: false

                }

            }

        }

    });

}

</script>


<!-- JAVASCRIPT UTAMA -->

<script src="../../assets/js/student.js"></script>


<!-- =====================================
     JAVASCRIPT NOTIFIKASI REAL-TIME
===================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const bellButton = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');

    if (!bellButton || !dropdown || !badge || !list) {
        return;
    }

    // Endpoint AJAX menggunakan dashboard.php sendiri.
    const apiUrl = 'dashboard.php?ajax=notifications';

    // =================================
    // MENAMPILKAN DAFTAR NOTIFIKASI
    // =================================

    function renderNotifications(notifications) {

        list.innerHTML = '';

        if (!notifications || notifications.length === 0) {

            const empty = document.createElement('div');

            empty.className = 'notification-empty';

            empty.textContent = 'Tidak ada notifikasi baru.';

            list.appendChild(empty);

            return;
        }

        notifications.forEach(function (item) {

            const link = document.createElement('a');

            link.className = 'notification-item';

            link.href = 'pengumuman.php?id=' + encodeURIComponent(item.id);

            const icon = document.createElement('div');

            icon.className = 'notification-item-icon';

            icon.textContent = '🔔';

            const content = document.createElement('div');

            content.className = 'notification-item-content';

            const title = document.createElement('strong');

            title.textContent = item.title || 'Pengumuman baru';

            const description = document.createElement('p');

            description.textContent = item.content || '';

            const time = document.createElement('small');

            time.textContent = item.created_at || '';

            content.appendChild(title);

            content.appendChild(description);

            content.appendChild(time);

            link.appendChild(icon);

            link.appendChild(content);

            list.appendChild(link);

        });

    }


    // =================================
    // MENGAMBIL DATA DARI DATABASE
    // =================================

    async function loadNotifications() {

        try {

            const response = await fetch(apiUrl, {

                method: 'GET',

                credentials: 'same-origin',

                cache: 'no-store',

                headers: {

                    'Accept': 'application/json'

                }

            });

            if (!response.ok) {

                throw new Error('Gagal mengambil notifikasi.');

            }

            const data = await response.json();

            if (!data.success) {

                throw new Error(
                    data.message || 'Gagal mengambil notifikasi.'
                );

            }

            // =================================
            // UPDATE ANGKA NOTIFIKASI
            // =================================

            const count = Number(data.unread_count) || 0;

            if (count > 0) {

                badge.textContent = count > 99 ? '99+' : count;

                badge.style.display = 'flex';

                bellButton.title = count + ' pengumuman belum dibaca';

            } else {

                badge.textContent = '0';

                badge.style.display = 'none';

                bellButton.title = 'Tidak ada pengumuman belum dibaca';

            }

            // =================================
            // UPDATE DAFTAR NOTIFIKASI
            // =================================

            renderNotifications(data.notifications || []);

        } catch (error) {

            console.error('Notifikasi EduStudent:', error);

            if (list.children.length === 0) {

                const message = document.createElement('div');

                message.className = 'notification-empty';

                message.textContent = 'Notifikasi belum dapat dimuat.';

                list.appendChild(message);

            }

        }

    }


    // =================================
    // BUKA DAN TUTUP DROPDOWN
    // =================================

    bellButton.addEventListener('click', function (event) {

        event.stopPropagation();

        const isOpen = dropdown.classList.toggle('show');

        bellButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

        if (isOpen) {

            loadNotifications();

        }

    });


    // =================================
    // KLIK DI LUAR DROPDOWN
    // =================================

    document.addEventListener('click', function (event) {

        if (

            !dropdown.contains(event.target) &&

            !bellButton.contains(event.target)

        ) {

            dropdown.classList.remove('show');

            bellButton.setAttribute('aria-expanded', 'false');

        }

    });


    // =================================
    // KLIK DI DALAM DROPDOWN
    // =================================

    dropdown.addEventListener('click', function (event) {

        event.stopPropagation();

    });


    // =================================
    // MUAT NOTIFIKASI PERTAMA KALI
    // =================================

    loadNotifications();


    // =================================
    // UPDATE SETIAP 15 DETIK
    // =================================

    setInterval(function () {

        if (!document.hidden) {

            loadNotifications();

        }

    }, 15000);


    // =================================
    // UPDATE KETIKA TAB DIBUKA KEMBALI
    // =================================

    document.addEventListener('visibilitychange', function () {

        if (!document.hidden) {

            loadNotifications();

        }

    });

});

</script>

</body>

</html>

    </main>

</div>
 