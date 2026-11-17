
<?php

$host = "localhost";
$dbname = "edustudent_db";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Mengatur mode error PDO
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Mengatur hasil query sebagai associative array
    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    // Jangan tampilkan detail database kepada pengunjung
    error_log($e->getMessage());

    exit("Koneksi database gagal. Silakan coba kembali.");

}

?>
