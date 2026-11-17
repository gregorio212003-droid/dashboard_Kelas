
<?php

require_once __DIR__ . '/config/koneksi.php';

$message = '';
$success = false;

try {

    $pdo->beginTransaction();

    // =====================================
    // 1. MENCARI DATA SISWA
    // =====================================

    $studentNis = '123';

    $stmt = $pdo->prepare("
        SELECT id, user_id, class_id, full_name
        FROM students
        WHERE nis = :nis
        LIMIT 1
    ");

    $stmt->execute(['nis' => $studentNis]);

    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception("Data siswa dengan NIS 123 tidak ditemukan.");
    }

    $studentId = $student['id'];
    $classId = $student['class_id'];

    if (!$classId) {
        throw new Exception("Siswa belum memiliki kelas.");
    }

    // Mencegah data simulasi dimasukkan berulang kali
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM assignments
        WHERE class_id = :class_id
        AND title = 'Matematika - Persamaan'
    ");

    $stmt->execute(['class_id' => $classId]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception(
            "Data simulasi sudah pernah dimasukkan. Jangan jalankan ulang."
        );
    }

    // =====================================
    // 2. MEMBUAT AKUN GURU DEMO
    // =====================================

    $stmt = $pdo->prepare("
        SELECT id FROM users
        WHERE username = 'guru01'
        LIMIT 1
    ");

    $stmt->execute();

    $teacherUserId = $stmt->fetchColumn();

    if (!$teacherUserId) {

        $passwordHash = password_hash(
            'GuruEdu2026!',
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users
            (username, email, password, role)
            VALUES
            ('guru01', 'guru01@edustudent.local', :password, 'guru')
        ");

        $stmt->execute([
            'password' => $passwordHash
        ]);

        $teacherUserId = $pdo->lastInsertId();
    }

    // Mencari profil guru
    $stmt = $pdo->prepare("
        SELECT id FROM teachers
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute(['user_id' => $teacherUserId]);

    $teacherId = $stmt->fetchColumn();

    if (!$teacherId) {

        $stmt = $pdo->prepare("
            INSERT INTO teachers
            (user_id, nip, full_name, gender)
            VALUES
            (:user_id, :nip, :name, 'L')
        ");

        $stmt->execute([
            'user_id' => $teacherUserId,
            'nip' => 'GURU001',
            'name' => 'Budi Santoso'
        ]);

        $teacherId = $pdo->lastInsertId();
    }

    // =====================================
    // 3. DATA MATA PELAJARAN
    // =====================================

    $subjects = [
        ['MTK', 'Matematika'],
        ['BIN', 'Bahasa Indonesia'],
        ['BIG', 'Bahasa Inggris'],
        ['INF', 'Informatika'],
        ['BIO', 'Biologi'],
        ['FIS', 'Fisika'],
        ['KIM', 'Kimia'],
        ['SEJ', 'Sejarah']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO subjects
        (subject_code, subject_name)
        VALUES
        (:code, :name)
        ON DUPLICATE KEY UPDATE
        subject_name = VALUES(subject_name)
    ");

    foreach ($subjects as $subject) {

        $stmt->execute([
            'code' => $subject[0],
            'name' => $subject[1]
        ]);
    }

    // Mengambil ID mata pelajaran
    $subjectIds = [];

    foreach ($subjects as $subject) {

        $stmt = $pdo->prepare("
            SELECT id FROM subjects
            WHERE subject_code = :code
        ");

        $stmt->execute([
            'code' => $subject[0]
        ]);

        $subjectIds[$subject[0]] = $stmt->fetchColumn();
    }

    // =====================================
    // 4. DATA NILAI SISWA
    // =====================================

    $gradeData = [
        ['MTK', 'Tugas', 90],
        ['MTK', 'UTS', 85],

        ['BIN', 'Tugas', 88],
        ['BIN', 'UTS', 90],

        ['BIG', 'Tugas', 92],
        ['BIG', 'UTS', 89],

        ['INF', 'Tugas', 95],
        ['INF', 'UTS', 94],

        ['BIO', 'Tugas', 85],
        ['BIO', 'UTS', 88],

        ['FIS', 'Tugas', 87],
        ['FIS', 'UTS', 84],

        ['KIM', 'Tugas', 89],
        ['KIM', 'UTS', 91],

        ['SEJ', 'Tugas', 90],
        ['SEJ', 'UTS', 92]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO grades
        (
            student_id,
            subject_id,
            teacher_id,
            assessment_type,
            score,
            semester,
            academic_year
        )
        VALUES
        (
            :student_id,
            :subject_id,
            :teacher_id,
            :type,
            :score,
            1,
            '2026/2027'
        )
    ");

    foreach ($gradeData as $grade) {

        $stmt->execute([
            'student_id' => $studentId,
            'subject_id' => $subjectIds[$grade[0]],
            'teacher_id' => $teacherId,
            'type' => $grade[1],
            'score' => $grade[2]
        ]);
    }

    // =====================================
    // 5. DATA JADWAL PELAJARAN
    // =====================================

    $scheduleData = [
        ['Senin', 'MTK', '07:00:00', '08:30:00', 'A1'],
        ['Selasa', 'BIN', '07:00:00', '08:30:00', 'A1'],
        ['Rabu', 'BIG', '07:00:00', '08:30:00', 'A1'],
        ['Kamis', 'INF', '07:00:00', '08:30:00', 'Lab Komputer'],
        ['Jumat', 'BIO', '07:00:00', '08:30:00', 'A1'],
        ['Sabtu', 'SEJ', '07:00:00', '08:30:00', 'A1']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO schedules
        (
            class_id,
            subject_id,
            teacher_id,
            day_of_week,
            start_time,
            end_time,
            room
        )
        VALUES
        (
            :class_id,
            :subject_id,
            :teacher_id,
            :day,
            :start_time,
            :end_time,
            :room
        )
    ");

    foreach ($scheduleData as $schedule) {

        $stmt->execute([
            'class_id' => $classId,
            'subject_id' => $subjectIds[$schedule[1]],
            'teacher_id' => $teacherId,
            'day' => $schedule[0],
            'start_time' => $schedule[2],
            'end_time' => $schedule[3],
            'room' => $schedule[4]
        ]);
    }

    // =====================================
    // 6. DATA KEHADIRAN
    // =====================================

    $stmt = $pdo->prepare("
        INSERT INTO attendance
        (
            student_id,
            attendance_date,
            status,
            notes
        )
        VALUES
        (
            :student_id,
            :attendance_date,
            :status,
            :notes
        )
    ");

    for ($i = 9; $i >= 0; $i--) {

        $date = date('Y-m-d', strtotime("-$i days"));

        $status = ($i === 5) ? 'Sakit' : 'Hadir';

        $stmt->execute([
            'student_id' => $studentId,
            'attendance_date' => $date,
            'status' => $status,
            'notes' => $status === 'Sakit' ? 'Izin sakit' : null
        ]);
    }

    // =====================================
    // 7. DATA TUGAS SEKOLAH
    // =====================================

    $assignmentData = [
        ['Matematika - Persamaan', 'MTK', 3],
        ['Makalah Bahasa Indonesia', 'BIN', 5],
        ['Latihan Bahasa Inggris', 'BIG', 7],
        ['Praktik Pemrograman PHP', 'INF', 10]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO assignments
        (
            class_id,
            subject_id,
            teacher_id,
            title,
            description,
            due_date
        )
        VALUES
        (
            :class_id,
            :subject_id,
            :teacher_id,
            :title,
            :description,
            :due_date
        )
    ");

    foreach ($assignmentData as $assignment) {

        $stmt->execute([
            'class_id' => $classId,
            'subject_id' => $subjectIds[$assignment[1]],
            'teacher_id' => $teacherId,
            'title' => $assignment[0],
            'description' => 'Tugas simulasi EduStudent.',
            'due_date' => date(
                'Y-m-d H:i:s',
                strtotime("+{$assignment[2]} days")
            )
        ]);
    }

    // =====================================
    // 8. SELESAI
    // =====================================

    $pdo->commit();

    $success = true;

    $message = "Seluruh data akademik berhasil dimasukkan untuk siswa "
             . $student['full_name'] . "!";

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $message = "Terjadi kesalahan: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengisian Data EduStudent</title>
</head>

<body>

    <h2>Pengisian Data Akademik EduStudent</h2>

    <?php if ($success): ?>

        <h3 style="color:green;">
            Berhasil!
        </h3>

    <?php else: ?>

        <h3 style="color:red;">
            Perhatian
        </h3>

    <?php endif; ?>

    <p><?= htmlspecialchars($message) ?></p>

    <a href="pages/siswa/dashboard.php">
        Buka Dashboard Siswa
    </a>

</body>
</html>
