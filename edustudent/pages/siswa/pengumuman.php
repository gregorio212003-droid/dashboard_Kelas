
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('siswa');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// IDENTITAS USER
// =====================================

$userId = (int) $_SESSION['user']['id'];

$username = htmlspecialchars(
    $_SESSION['user']['username'] ?? 'Siswa',
    ENT_QUOTES,
    'UTF-8'
);

// =====================================
// PENCARIAN DAN FILTER
// =====================================

$search = trim($_GET['search'] ?? '');

$filter = $_GET['filter'] ?? 'all';

$allowedFilters = ['all', 'unread', 'read'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$announcement = null;
$announcements = [];

// =====================================
// DETAIL PENGUMUMAN
// =====================================

if ($id && $id > 0) {

    $stmt = $pdo->prepare("
        SELECT id, title, content, created_at
        FROM announcements
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $announcement = $stmt->fetch();

    // Tandai pengumuman sudah dibaca

    if ($announcement) {

        $stmtRead = $pdo->prepare("
            INSERT IGNORE INTO announcement_reads
                (user_id, announcement_id, read_at)
            VALUES
                (:user_id, :announcement_id, NOW())
        ");

        $stmtRead->execute([
            'user_id' => $userId,
            'announcement_id' => $announcement['id']
        ]);

    }
}

// =====================================
// JUMLAH PENGUMUMAN
// =====================================

$searchCondition = "";
$countParams = [
    'count_user_id' => $userId
];

if ($search !== '') {

    $searchCondition = "
        AND (
            a.title LIKE :count_title
            OR a.content LIKE :count_content
        )
    ";

    $countParams['count_title'] = '%' . $search . '%';
    $countParams['count_content'] = '%' . $search . '%';
}

$stmtCount = $pdo->prepare("
    SELECT
        COUNT(*) AS total,

        COALESCE(
            SUM(ar.id IS NULL), 0
        ) AS unread,

        COALESCE(
            SUM(ar.id IS NOT NULL), 0
        ) AS read_count

    FROM announcements a

    LEFT JOIN announcement_reads ar
        ON ar.announcement_id = a.id
        AND ar.user_id = :count_user_id

    WHERE 1=1
    $searchCondition
");

$stmtCount->execute($countParams);

$counts = $stmtCount->fetch();

$totalAnnouncements = (int) $counts['total'];
$unreadCount = (int) $counts['unread'];
$readCount = (int) $counts['read_count'];

// =====================================
// DAFTAR PENGUMUMAN
// =====================================

if (!$id) {

    $where = "";
    $params = [
        'user_id' => $userId
    ];

    // Filter pencarian

    if ($search !== '') {

        $where .= "
            AND (
                a.title LIKE :search_title
                OR a.content LIKE :search_content
            )
        ";

        $params['search_title'] = '%' . $search . '%';
        $params['search_content'] = '%' . $search . '%';
    }

    // Filter status baca

    if ($filter === 'unread') {

        $where .= " AND ar.id IS NULL ";

    } elseif ($filter === 'read') {

        $where .= " AND ar.id IS NOT NULL ";

    }

    $sql = "
        SELECT
            a.id,
            a.title,
            a.content,
            a.created_at,

            CASE
                WHEN ar.id IS NULL THEN 0
                ELSE 1
            END AS is_read

        FROM announcements a

        LEFT JOIN announcement_reads ar
            ON ar.announcement_id = a.id
            AND ar.user_id = :user_id

        WHERE 1=1
        $where

        ORDER BY a.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $announcements = $stmt->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Pengumuman - EduStudent</title>

<link rel="stylesheet"
      href="../../assets/css/student.css">

<style>

/* =====================================
   KONTEN
===================================== */

.announcement-content {
    padding: 30px;
}

.announcement-header {
    margin-bottom: 25px;
}

.announcement-header h1 {
    color: #1e293b;
    margin-bottom: 8px;
}

.announcement-header p {
    color: #64748b;
}

/* =====================================
   STATISTIK
===================================== */

.announcement-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.stat-card p {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 10px;
}

.stat-card h2 {
    color: #2563eb;
    margin: 0;
    font-size: 26px;
}

.stat-card.unread h2 {
    color: #2563eb;
}

.stat-card.read h2 {
    color: #16a34a;
}

/* =====================================
   PENCARIAN
===================================== */

.announcement-search {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.announcement-search input {
    flex: 1;
    padding: 13px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    outline: none;
    font-size: 14px;
}

.announcement-search input:focus {
    border-color: #2563eb;
}

.announcement-search button {
    padding: 12px 22px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.announcement-search button:hover {
    background: #1d4ed8;
}

/* =====================================
   FILTER
===================================== */

.filter-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.filter-btn {
    display: inline-block;
    padding: 10px 17px;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    text-decoration: none;
    color: #475569;
    background: white;
    font-size: 14px;
    transition: 0.2s;
}

.filter-btn:hover {
    background: #eff6ff;
    color: #2563eb;
}

.filter-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.filter-count {
    font-weight: bold;
    margin-left: 4px;
}

/* =====================================
   KARTU PENGUMUMAN
===================================== */

.announcement-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 18px;
    transition: 0.2s;
}

.announcement-card:hover {
    border-color: #bfdbfe;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.announcement-card h3 {
    color: #1e40af;
    margin-top: 0;
}

.announcement-date {
    color: #94a3b8;
    font-size: 13px;
    margin-bottom: 12px;
}

.announcement-description {
    color: #475569;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

/* =====================================
   STATUS BACA
===================================== */

.read-status {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 12px;
}

.status-unread {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-read {
    background: #dcfce7;
    color: #15803d;
}

/* =====================================
   BACA SELENGKAPNYA
===================================== */

.read-more {
    display: inline-block;
    margin-top: 15px;
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

.read-more:hover {
    text-decoration: underline;
}

/* =====================================
   DETAIL
===================================== */

.detail-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    border: 1px solid #e2e8f0;
}

.detail-card h2 {
    color: #1e40af;
}

.detail-text {
    color: #475569;
    line-height: 1.9;
    margin-top: 25px;
    overflow-wrap: anywhere;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #2563eb;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}

/* =====================================
   PESAN KOSONG
===================================== */

.empty-message {
    background: white;
    padding: 35px;
    border-radius: 12px;
    text-align: center;
    color: #64748b;
}

/* =====================================
   RESPONSIVE
===================================== */

@media(max-width: 700px) {

    .announcement-content {
        padding: 15px;
    }

    .announcement-stats {
        grid-template-columns: 1fr;
    }

    .announcement-search {
        flex-direction: column;
    }

    .announcement-search button {
        width: 100%;
    }

    .announcement-card {
        padding: 17px;
    }

    .detail-card {
        padding: 20px;
    }

}

</style>

</head>

<body>

<div class="student-layout">

    <!-- SIDEBAR -->

    <aside class="student-sidebar" id="sidebar">

        <div class="student-logo">
            EduStudent
        </div>

        <div class="sidebar-label">
            MENU UTAMA
        </div>

        <nav class="student-nav">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="jadwal.php">
                Jadwal Pelajaran
            </a>

            <a href="nilai.php">
                Nilai Akademik
            </a>

            <a href="tugas.php">
                Tugas Sekolah
            </a>

            <a href="kehadiran.php">
                Kehadiran
            </a>

        </nav>

        <div class="sidebar-label">
            LAINNYA
        </div>

        <nav class="student-nav">

            <a href="pengumuman.php" class="active">
                Pengumuman
            </a>

            <a href="profil.php">
                Profil Saya
            </a>

            <a href="pengaturan.php">
                Pengaturan
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="student-main">

        <header class="student-navbar">

            <button id="menu-toggle" class="menu-toggle">
                ☰
            </button>

            <h3>Pengumuman</h3>

            <div class="navbar-profile">

                <strong>
                    <?= $username ?>
                </strong>

            </div>

        </header>


        <section class="announcement-content">

        <?php if ($id): ?>

            <!-- =================================
                 DETAIL PENGUMUMAN
            ================================== -->

            <a href="pengumuman.php" class="back-link">
                ← Kembali ke Pengumuman
            </a>

            <?php if ($announcement): ?>

                <div class="detail-card">

                    <span class="read-status status-read">
                        ✓ Sudah Dibaca
                    </span>

                    <h2>
                        <?= htmlspecialchars(
                            $announcement['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>

                    <div class="announcement-date">

                        Dipublikasikan:

                        <?= date(
                            'd-m-Y H:i',
                            strtotime($announcement['created_at'])
                        ) ?>

                    </div>

                    <div class="detail-text">

                        <?= nl2br(
                            htmlspecialchars(
                                $announcement['content'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="empty-message">
                    Pengumuman tidak ditemukan.
                </div>

            <?php endif; ?>


        <?php else: ?>

            <!-- =================================
                 DAFTAR PENGUMUMAN
            ================================== -->

            <div class="announcement-header">

                <h1>Pengumuman Sekolah</h1>

                <p>
                    Informasi terbaru dari administrator EduStudent.
                </p>

            </div>


            <!-- STATISTIK -->

            <div class="announcement-stats">

                <div class="stat-card">

                    <p>Total Pengumuman</p>

                    <h2>
                        <?= $totalAnnouncements ?>
                    </h2>

                </div>

                <div class="stat-card unread">

                    <p>Belum Dibaca</p>

                    <h2>
                        <?= $unreadCount ?>
                    </h2>

                </div>

                <div class="stat-card read">

                    <p>Sudah Dibaca</p>

                    <h2>
                        <?= $readCount ?>
                    </h2>

                </div>

            </div>


            <!-- PENCARIAN -->

            <form method="GET"
                  action="pengumuman.php"
                  class="announcement-search">

                <input
                    type="text"
                    name="search"
                    placeholder="Cari pengumuman..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="filter"
                    value="<?= htmlspecialchars($filter) ?>"
                >

                <button type="submit">
                    Cari
                </button>

            </form>


            <!-- FILTER -->

            <div class="filter-container">

                <a
                    href="?filter=all&search=<?= urlencode($search) ?>"
                    class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>"
                >
                    Semua

                    <span class="filter-count">
                        <?= $totalAnnouncements ?>
                    </span>

                </a>


                <a
                    href="?filter=unread&search=<?= urlencode($search) ?>"
                    class="filter-btn <?= $filter === 'unread' ? 'active' : '' ?>"
                >
                    Belum Dibaca

                    <span class="filter-count">
                        <?= $unreadCount ?>
                    </span>

                </a>


                <a
                    href="?filter=read&search=<?= urlencode($search) ?>"
                    class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>"
                >
                    Sudah Dibaca

                    <span class="filter-count">
                        <?= $readCount ?>
                    </span>

                </a>

            </div>


            <!-- DAFTAR PENGUMUMAN -->

            <?php if ($announcements): ?>

                <?php foreach ($announcements as $item): ?>

                    <div class="announcement-card">

                        <!-- STATUS -->

                        <?php if ((int)$item['is_read'] === 1): ?>

                            <span class="read-status status-read">
                                ✓ Sudah Dibaca
                            </span>

                        <?php else: ?>

                            <span class="read-status status-unread">
                                ● Belum Dibaca
                            </span>

                        <?php endif; ?>


                        <!-- JUDUL -->

                        <h3>

                            <?= htmlspecialchars(
                                $item['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h3>


                        <!-- TANGGAL -->

                        <div class="announcement-date">

                            <?= date(
                                'd-m-Y H:i',
                                strtotime($item['created_at'])
                            ) ?>

                        </div>


                        <!-- ISI -->

                        <div class="announcement-description">

                            <?= nl2br(
                                htmlspecialchars(
                                    mb_strimwidth(
                                        $item['content'],
                                        0,
                                        180,
                                        '...',
                                        'UTF-8'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>

                        </div>


                        <!-- BACA -->

                        <a
                            href="pengumuman.php?id=<?= (int)$item['id'] ?>"
                            class="read-more"
                        >

                            Baca Selengkapnya →

                        </a>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-message">

                    <?php if ($filter === 'unread'): ?>

                        <h3>Semua Pengumuman Sudah Dibaca</h3>

                        <p>
                            Tidak ada pengumuman yang belum dibaca.
                        </p>

                    <?php elseif ($filter === 'read'): ?>

                        <h3>Belum Ada Riwayat</h3>

                        <p>
                            Kamu belum membaca pengumuman apa pun.
                        </p>

                    <?php else: ?>

                        <h3>Belum Ada Pengumuman</h3>

                        <p>
                            <?= $search !== ''
                                ? 'Pengumuman yang kamu cari tidak ditemukan.'
                                : 'Saat ini belum ada pengumuman dari Admin.' ?>
                        </p>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

        </section>

    </main>

</div>

<script src="../../assets/js/student.js"></script>

</body>
</html>
