
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

require_once __DIR__ . '/../../config/koneksi.php';

// =====================================
// 1. VALIDASI METODE POST
// =====================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: guru.php');
    exit;
}

// =====================================
// 2. VALIDASI CSRF
// =====================================

$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    header('Location: guru.php?error=invalid_request');
    exit;
}

// =====================================
// 3. AMBIL ID GURU
// =====================================

$teacherId = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$teacherId || $teacherId < 1) {
    header('Location: guru.php?error=invalid_id');
    exit;
}

// =====================================
// 4. PROSES PENGHAPUSAN
// =====================================

try {

    $pdo->beginTransaction();

    // Cari guru dan akun terkait

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.user_id,
            u.role

        FROM teachers t

        INNER JOIN users u
            ON t.user_id = u.id

        WHERE t.id = :id

        LIMIT 1
    ");

    $stmt->execute([
        'id' => $teacherId
    ]);

    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher || $teacher['role'] !== 'guru') {

        $pdo->rollBack();

        header('Location: guru.php?error=notfound');
        exit;
    }

    $userId = (int) $teacher['user_id'];

    // Hapus profil guru

    $stmt = $pdo->prepare("
        DELETE FROM teachers
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $teacherId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Gagal menghapus profil guru.');
    }

    // Hapus akun login guru

    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id = :id
        AND role = 'guru'
    ");

    $stmt->execute([
        'id' => $userId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Gagal menghapus akun guru.');
    }

    // Selesaikan transaksi

    $pdo->commit();

    // Perbarui token

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: guru.php?deleted=1');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    header('Location: guru.php?error=delete_failed');
    exit;
}
