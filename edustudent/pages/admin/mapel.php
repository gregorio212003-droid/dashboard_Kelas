
<?php
require_once '../../config/koneksi.php';
require_once '../../includes/auth.php';

require_role('admin');

$pesan = '';
$tipe_pesan = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// PROSES TAMBAH DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $kode = trim($_POST['subject_code'] ?? '');
    $nama = trim($_POST['subject_name'] ?? '');
    $deskripsi = trim($_POST['description'] ?? '');

    if ($kode === '' || $nama === '') {
        $pesan = 'Kode dan nama mata pelajaran wajib diisi.';
        $tipe_pesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO subjects
                (subject_code, subject_name, description)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$kode, $nama, $deskripsi ?: null]);

            header('Location: mapel.php?status=tambah');
            exit;
        } catch (PDOException $e) {
            $pesan = 'Gagal menambahkan data. Pastikan kode mata pelajaran belum digunakan.';
            $tipe_pesan = 'error';
        }
    }
}

// PROSES EDIT DATA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = (int)($_POST['id'] ?? 0);
    $kode = trim($_POST['subject_code'] ?? '');
    $nama = trim($_POST['subject_name'] ?? '');
    $deskripsi = trim($_POST['description'] ?? '');

    if ($id <= 0 || $kode === '' || $nama === '') {
        $pesan = 'Data edit tidak lengkap.';
        $tipe_pesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE subjects
                SET subject_code = ?,
                    subject_name = ?,
                    description = ?
                WHERE id = ?
            ");
            $stmt->execute([$kode, $nama, $deskripsi ?: null, $id]);

            header('Location: mapel.php?status=edit');
            exit;
        } catch (PDOException $e) {
            $pesan = 'Gagal mengubah data. Periksa kembali kode mata pelajaran.';
            $tipe_pesan = 'error';
        }
    }
}

// PROSES HAPUS DATA
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    if ($id > 0) {
        try {
            // Periksa apakah mapel digunakan oleh jadwal
            $cek = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE subject_id = ?");
            $cek->execute([$id]);
            $jadwal = (int)$cek->fetchColumn();

            // Periksa apakah mapel digunakan oleh nilai
            $cek = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE subject_id = ?");
            $cek->execute([$id]);
            $nilai = (int)$cek->fetchColumn();

            // Periksa apakah mapel digunakan oleh tugas
            $cek = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE subject_id = ?");
            $cek->execute([$id]);
            $tugas = (int)$cek->fetchColumn();

            if ($jadwal > 0 || $nilai > 0 || $tugas > 0) {
                $pesan = 'Mata pelajaran tidak dapat dihapus karena masih digunakan oleh jadwal, nilai, atau tugas.';
                $tipe_pesan = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->execute([$id]);

                header('Location: mapel.php?status=hapus');
                exit;
            }
        } catch (PDOException $e) {
            $pesan = 'Gagal menghapus data. Periksa relasi tabel database.';
            $tipe_pesan = 'error';
        }
    }
}

// NOTIFIKASI
if (isset($_GET['status'])) {
    $status = $_GET['status'];

    if ($status === 'tambah') {
        $pesan = 'Mata pelajaran berhasil ditambahkan.';
        $tipe_pesan = 'success';
    } elseif ($status === 'edit') {
        $pesan = 'Mata pelajaran berhasil diperbarui.';
        $tipe_pesan = 'success';
    } elseif ($status === 'hapus') {
        $pesan = 'Mata pelajaran berhasil dihapus.';
        $tipe_pesan = 'success';
    }
}

// PENCARIAN
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM subjects";
$params = [];

if ($cari !== '') {
    $sql .= " WHERE subject_code LIKE ? OR subject_name LIKE ?";
    $params = ["%$cari%", "%$cari%"];
}

$sql .= " ORDER BY subject_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mapel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STATISTIK
$total = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

// DATA UNTUK EDIT
$data_edit = null;

if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$id_edit]);
    $data_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mata Pelajaran - EduStudent</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6fa;
            color: #263238;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }

        .header p {
            color: #718096;
            font-size: 14px;
        }

        .back {
            display: inline-block;
            margin-bottom: 18px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }

        .stats {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .stats h3 {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stats strong {
            font-size: 30px;
            color: #2563eb;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce1e8;
            border-radius: 7px;
            outline: none;
            font-size: 14px;
        }

        input:focus, textarea:focus {
            border-color: #2563eb;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        button, .btn {
            border: none;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-cancel {
            background: #64748b;
            color: white;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
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

        .search {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
        }

        .search input {
            max-width: 350px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #718096;
        }

        @media(max-width: 650px) {
            body {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .search {
                flex-direction: column;
            }

            .search input {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="dashboard.php" class="back">← Kembali ke Dashboard Admin</a>

    <div class="header">
        <h1>Manajemen Mata Pelajaran</h1>
        <p>Kelola data mata pelajaran dalam sistem EduStudent.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= e($tipe_pesan) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <h3>Total Mata Pelajaran</h3>
        <strong><?= $total ?></strong>
    </div>

    <div class="card">
        <h2>
            <?= $data_edit ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran' ?>
        </h2>

        <form method="POST" action="mapel.php">

            <?php if ($data_edit): ?>
                <input type="hidden" name="id" value="<?= (int)$data_edit['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group">
                    <label>Kode Mata Pelajaran</label>
                    <input
                        type="text"
                        name="subject_code"
                        maxlength="20"
                        placeholder="Contoh: MTK001"
                        value="<?= e($data_edit['subject_code'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Nama Mata Pelajaran</label>
                    <input
                        type="text"
                        name="subject_name"
                        maxlength="100"
                        placeholder="Contoh: Matematika"
                        value="<?= e($data_edit['subject_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label>Deskripsi</label>
                    <textarea
                        name="description"
                        placeholder="Masukkan deskripsi mata pelajaran..."
                    ><?= e($data_edit['description'] ?? '') ?></textarea>
                </div>

            </div>

            <?php if ($data_edit): ?>
                <button type="submit" name="edit" class="btn-primary">
                    Simpan Perubahan
                </button>
                <a href="mapel.php" class="btn btn-cancel">Batal</a>
            <?php else: ?>
                <button type="submit" name="tambah" class="btn-primary">
                    + Tambahkan Mata Pelajaran
                </button>
            <?php endif; ?>

        </form>
    </div>

    <div class="card">

        <h2>Daftar Mata Pelajaran</h2>

        <form method="GET" class="search">
            <input
                type="text"
                name="cari"
                placeholder="Cari kode atau nama mata pelajaran..."
                value="<?= e($cari) ?>"
            >
            <button type="submit" class="btn-primary">Cari</button>
            <a href="mapel.php" class="btn btn-cancel">Reset</a>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($mapel) > 0): ?>

                    <?php $no = 1; ?>
                    <?php foreach ($mapel as $row): ?>

                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($row['subject_code']) ?></td>
                            <td><?= e($row['subject_name']) ?></td>
                            <td><?= e($row['description'] ?: '-') ?></td>

                            <td>
                                <div class="actions">
                                    <a
                                        href="mapel.php?edit=<?= (int)$row['id'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="mapel.php?hapus=<?= (int)$row['id'] ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm('Yakin ingin menghapus mata pelajaran ini?')"
                                    >
                                        Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            Belum ada data mata pelajaran.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

</div>

</body>
</html>
