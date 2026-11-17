
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: siswa.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Token keamanan tidak valid.');
}

$id = filter_var(
    $_POST['student_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: siswa.php?error=invalid_id');
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE students
        SET status = 'nonaktif'
        WHERE id = :id
        AND status = 'aktif'
    ");

    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() > 0) {
        header('Location: siswa.php?success=nonaktif');
    } else {
        header('Location: siswa.php?error=not_found');
    }

    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());

    header('Location: siswa.php?error=database');
    exit;
}
