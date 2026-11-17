
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

$stmt->execute(['user_id' => $userId]);

$teacher = $stmt->fetch();

if (!$teacher) {
    exit("Profil guru tidak ditemukan.");
}

$teacherId = $teacher['id'];

// =====================================
// 2. PENCARIAN SISWA
// =====================================

$search = trim($_GET['search'] ?? '');

// =====================================
// 3. MENGAMBIL DATA SISWA
// =====================================

$sql = "
    SELECT DISTINCT
        s.id,
        s.nis,
        s.full_name,
        s.gender,
        s.birth_date,
        c.class_name,
        c.grade_level

    FROM students s

    INNER JOIN classes c
        ON c.id = s.class_id

    INNER JOIN schedules sc
        ON sc.class_id = s.class_id

    WHERE sc.teacher_id = :teacher_id
";

$params = [
    'teacher_id' => $teacherId
];

if ($search !== '') {

    $sql .= "
        AND (
            s.full_name LIKE :search_name
            OR s.nis LIKE :search_nis
        )
    ";

    $params['search_name'] = "%$search%";
    $params['search_nis'] = "%$search%";
}

$sql .= "
    ORDER BY s.full_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll();

$totalStudents = count($students);

$teacherName = htmlspecialchars(
    $teacher['full_name'],
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

    <title>Data Siswa - EduStudent</title>

    <link rel="stylesheet"
          href="../../assets/css/teacher.css">

    <style>

        .student-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .student-search {
            display: flex;
            gap: 8px;
        }

        .student-search input {
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            width: 260px;
            outline: none;
        }

        .student-search input:focus {
            border-color: #2563eb;
        }

        .student-search button {
            padding: 11px 18px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        .student-count {
            background: #eff6ff;
            color: #2563eb;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
        }

        .student-badge {
            background: #eff6ff;
            color: #2563eb;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .student-detail {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .student-detail:hover {
            text-decoration: underline;
        }

        @media(max-width: 550px) {

            .student-search {
                width: 100%;
            }

            .student-search input {
                width: 100%;
                min-width: 0;
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
                value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
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

            <h3>Data Siswa</h3>

            <div class="teacher-profile">

                <div class="teacher-profile-info">

                    <strong>
                        <?= $teacherName ?>
                    </strong>

                    <small>Guru</small>

                </div>

                <div class="teacher-avatar">
                    <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                </div>

            </div>

        </header>

        <!-- CONTENT -->

        <section class="teacher-content">

            <div class="teacher-welcome">

                <div>

                    <h1>Manajemen Data Siswa</h1>

                    <p>
                        Lihat dan cari data siswa dari kelas yang kamu ajar.
                    </p>

                </div>

                <div class="student-count">

                    <?= $totalStudents ?> Siswa

                </div>

            </div>

            <div class="teacher-panel">

                <div class="student-toolbar">

                    <div>

                        <h3>Daftar Siswa</h3>

                        <p style="color:#94a3b8; font-size:12px; margin-top:6px;">

                            Data siswa berdasarkan kelas mengajar.

                        </p>

                    </div>

                    <form method="GET" class="student-search">

                        <input
                            type="text"
                            name="search"
                            placeholder="Cari nama atau NIS..."
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        >

                        <button type="submit">
                            Cari
                        </button>

                    </form>

                </div>

                <div class="teacher-table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>No.</th>

                                <th>NIS</th>

                                <th>Nama Siswa</th>

                                <th>Jenis Kelamin</th>

                                <th>Kelas</th>

                                <th>Detail</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (!empty($students)): ?>

                                <?php foreach ($students as $index => $student): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($student['nis']) ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars($student['full_name']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($student['gender'] ?? '-') ?>
                                        </td>

                                        <td>

                                            <span class="student-badge">

                                                <?= htmlspecialchars($student['class_name']) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <a
                                                class="student-detail"
                                                href="detail_siswa.php?id=<?= (int)$student['id'] ?>"
                                            >
                                                Lihat Detail
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="6" class="teacher-empty">

                                        <?php if ($search !== ''): ?>

                                            Siswa yang kamu cari tidak ditemukan.

                                        <?php else: ?>

                                            Belum ada siswa pada kelas yang kamu ajar.

                                        <?php endif; ?>

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
