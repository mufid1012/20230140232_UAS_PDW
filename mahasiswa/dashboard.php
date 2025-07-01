<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek jika pengguna belum login atau bukan mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Ambil statistik mahasiswa
$student_id = $_SESSION['user_id'];

// Jumlah praktikum yang diikuti
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM enrollments WHERE student_id = ? AND status = 'aktif'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($enrolled_courses);
$stmt->fetch();
$stmt->close();

// Jumlah tugas yang sudah dikumpulkan
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM submissions WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($submitted_tasks);
$stmt->fetch();
$stmt->close();

// Jumlah tugas yang belum dikumpulkan (modul yang ada deadline tapi belum submit)
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT m.id) as total 
    FROM modules m 
    INNER JOIN enrollments e ON m.course_id = e.course_id 
    WHERE e.student_id = ? 
    AND e.status = 'aktif' 
    AND m.deadline IS NOT NULL 
    AND m.id NOT IN (
        SELECT module_id FROM submissions WHERE student_id = ?
    )
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$stmt->bind_result($pending_tasks);
$stmt->fetch();
$stmt->close();

// Notifikasi terbaru (nilai baru, deadline mendekat, dll)
$notifications = [];

// Nilai baru yang diberikan
$stmt = $conn->prepare("
    SELECT s.nilai, s.feedback, s.graded_at, m.judul as module_title, c.nama as course_name
    FROM submissions s
    INNER JOIN modules m ON s.module_id = m.id
    INNER JOIN courses c ON m.course_id = c.id
    WHERE s.student_id = ? AND s.nilai IS NOT NULL
    ORDER BY s.graded_at DESC
    LIMIT 3
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($grade_nilai, $grade_feedback, $grade_graded_at, $grade_module_title, $grade_course_name);

while ($stmt->fetch()) {
    $notifications[] = [
        'type' => 'grade',
        'icon' => '📊',
        'message' => "Nilai untuk <strong>{$grade_module_title}</strong> ({$grade_course_name}) telah diberikan: <strong>{$grade_nilai}</strong>",
        'time' => $grade_graded_at
    ];
}
$stmt->close();

// Deadline yang mendekat (dalam 3 hari)
$stmt = $conn->prepare("
    SELECT m.judul as module_title, m.deadline, c.nama as course_name
    FROM modules m
    INNER JOIN enrollments e ON m.course_id = e.course_id
    INNER JOIN courses c ON m.course_id = c.id
    WHERE e.student_id = ? 
    AND e.status = 'aktif'
    AND m.deadline IS NOT NULL
    AND m.deadline > NOW()
    AND m.deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)
    AND m.id NOT IN (
        SELECT module_id FROM submissions WHERE student_id = ?
    )
    ORDER BY m.deadline ASC
    LIMIT 3
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($deadline_module_title, $deadline_deadline, $deadline_course_name);

while ($stmt->fetch()) {
    $days_left = ceil((strtotime($deadline_deadline) - time()) / (60 * 60 * 24));
    $notifications[] = [
        'type' => 'deadline',
        'icon' => '⏰',
        'message' => "Batas waktu pengumpulan <strong>{$deadline_module_title}</strong> ({$deadline_course_name}) dalam <strong>{$days_left} hari</strong>!",
        'time' => $deadline_deadline
    ];
}
$stmt->close();

// Praktikum baru yang berhasil didaftar
$stmt = $conn->prepare("
    SELECT c.nama as course_name, e.enrolled_at
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? 
    AND e.status = 'aktif'
    ORDER BY e.enrolled_at DESC
    LIMIT 2
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($enrollment_course_name, $enrollment_enrolled_at);

while ($stmt->fetch()) {
    $notifications[] = [
        'type' => 'enrollment',
        'icon' => '✅',
        'message' => "Anda berhasil mendaftar pada mata praktikum <strong>{$enrollment_course_name}</strong>",
        'time' => $enrollment_enrolled_at
    ];
}
$stmt->close();

// Urutkan notifikasi berdasarkan waktu terbaru
usort($notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Ambil hanya 5 notifikasi terbaru
$notifications = array_slice($notifications, 0, 5);

require_once 'templates/header_mahasiswa.php'; 
?>

<div class="bg-gradient-to-r from-blue-500 to-cyan-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <h1 class="text-3xl font-bold">Selamat Datang Kembali, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h1>
    <p class="mt-2 opacity-90">Terus semangat dalam menyelesaikan semua modul praktikummu.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-blue-600"><?php echo $enrolled_courses; ?></div>
        <div class="mt-2 text-lg text-gray-600">Praktikum Diikuti</div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-green-500"><?php echo $submitted_tasks; ?></div>
        <div class="mt-2 text-lg text-gray-600">Tugas Dikumpulkan</div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-yellow-500"><?php echo $pending_tasks; ?></div>
        <div class="mt-2 text-lg text-gray-600">Tugas Menunggu</div>
    </div>
    
</div>

<div class="bg-white p-6 rounded-xl shadow-md">
    <h3 class="text-2xl font-bold text-gray-800 mb-4">Notifikasi Terbaru</h3>
    <?php if (!empty($notifications)): ?>
        <ul class="space-y-4">
            <?php foreach ($notifications as $notification): ?>
                <li class="flex items-start p-3 border-b border-gray-100 last:border-b-0">
                    <span class="text-xl mr-4"><?php echo $notification['icon']; ?></span>
                    <div class="flex-1">
                        <div class="text-gray-800"><?php echo $notification['message']; ?></div>
                        <div class="text-sm text-gray-500 mt-1">
                            <?php echo date('d/m/Y H:i', strtotime($notification['time'])); ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <div class="text-4xl mb-2">📭</div>
            <p>Tidak ada notifikasi terbaru</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Panggil Footer
require_once 'templates/footer_mahasiswa.php';
?>