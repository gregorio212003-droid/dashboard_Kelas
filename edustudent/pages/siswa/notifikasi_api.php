
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('siswa');

require_once __DIR__ . '/../../config/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int) $_SESSION['user']['id'];

try {

    // Menghitung jumlah pengumuman belum dibaca

    $stmtCount = $pdo->prepare("
        SELECT COUNT(*)
        FROM announcements a

        LEFT JOIN announcement_reads ar
            ON ar.announcement_id = a.id
            AND ar.user_id = :user_id

        WHERE ar.id IS NULL
    ");

    $stmtCount->execute([
        'user_id' => $userId
    ]);

    $unreadCount = (int) $stmtCount->fetchColumn();


    // Mengambil 5 pengumuman terbaru yang belum dibaca

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        'user_id' => $userId
    ]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Menyiapkan hasil JSON

    $result = [];

    foreach ($notifications as $item) {

        $result[] = [
            'id' => (int) $item['id'],

            'title' => $item['title'],

            'content' => mb_strimwidth(
                strip_tags($item['content']),
                0,
                90,
                '...',
                'UTF-8'
            ),

            'created_at' => date(
                'd-m-Y H:i',
                strtotime($item['created_at'])
            )
        ];

    }


    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $result
    ], JSON_UNESCAPED_UNICODE);


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil notifikasi.'
    ]);

}
