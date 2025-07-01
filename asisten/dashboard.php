<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek jika pengguna belum login atau bukan asisten
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

$pageTitle = "Dashboard Asisten";
$activePage = "dashboard";

$asisten_id = $_SESSION['user_id'];

// Ambil statistik untuk asisten
// Total courses yang dimiliki
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE asisten_id = ?");
$stmt->bind_param("i", $asisten_id);
$stmt->execute();
$stmt->bind_result($total_courses);
$stmt->fetch();
$stmt->close();

// Total modules yang dibuat
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM modules m JOIN courses c ON m.course_id = c.id WHERE c.asisten_id = ?");
$stmt->bind_param("i", $asisten_id);
$stmt->execute();
$stmt->bind_result($total_modules);
$stmt->fetch();
$stmt->close();

// Total submissions yang diterima
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM submissions s JOIN modules m ON s.module_id = m.id JOIN courses c ON m.course_id = c.id WHERE c.asisten_id = ?");
$stmt->bind_param("i", $asisten_id);
$stmt->execute();
$stmt->bind_result($total_submissions);
$stmt->fetch();
$stmt->close();

// Total submissions yang belum dinilai
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM submissions s JOIN modules m ON s.module_id = m.id JOIN courses c ON m.course_id = c.id WHERE c.asisten_id = ? AND s.nilai IS NULL");
$stmt->bind_param("i", $asisten_id);
$stmt->execute();
$stmt->bind_result($ungraded_submissions);
$stmt->fetch();
$stmt->close();

// Recent submissions (5 terbaru)
$stmt = $conn->prepare("
    SELECT s.id, s.submitted_at, s.nama_asli, s.nilai, s.status,
           u.nama as student_name, m.judul as module_title, c.nama as course_name
    FROM submissions s 
    JOIN modules m ON s.module_id = m.id 
    JOIN courses c ON m.course_id = c.id 
    JOIN users u ON s.student_id = u.id
    WHERE c.asisten_id = ? 
    ORDER BY s.submitted_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $asisten_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($sub_id, $sub_submitted_at, $sub_nama_asli, $sub_nilai, $sub_status, $sub_student_name, $sub_module_title, $sub_course_name);

// Fetch all recent submissions into array
$recent_submissions = [];
while ($stmt->fetch()) {
    $recent_submissions[] = [
        'id' => $sub_id,
        'submitted_at' => $sub_submitted_at,
        'nama_asli' => $sub_nama_asli,
        'nilai' => $sub_nilai,
        'status' => $sub_status,
        'student_name' => $sub_student_name,
        'module_title' => $sub_module_title,
        'course_name' => $sub_course_name
    ];
}
$stmt->close();

include 'templates/header.php';
?>

<div class="bg-gradient-to-r from-blue-500 to-cyan-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <h1 class="text-3xl font-bold">Selamat Datang Kembali, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h1>
    <p class="mt-2 opacity-90">Kelola praktikum dan tugas mahasiswa Anda dari sini.</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Praktikum</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_courses; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Modul</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_modules; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Submission</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_submissions; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Belum Dinilai</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $ungraded_submissions; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Submissions -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800">Submission Terbaru</h3>
    </div>
    
    <?php if (!empty($recent_submissions)): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($recent_submissions as $submission): ?>
                <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h4 class="text-lg font-semibold text-gray-800 mr-3">
                                    <?php echo htmlspecialchars($submission['nama_asli']); ?>
                                </h4>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $submission['nilai'] !== null ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $submission['nilai'] !== null ? 'Sudah Dinilai' : 'Belum Dinilai'; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center space-x-6 text-sm text-gray-500 mb-2">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($submission['student_name']); ?></span>
                                </div>
                                
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($submission['course_name']); ?></span>
                                </div>
                                
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($submission['module_title']); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?></span>
                                <?php if ($submission['nilai'] !== null): ?>
                                    <span class="ml-4 font-semibold text-green-600">Nilai: <?php echo $submission['nilai']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="ml-6">
                            <a href="submissions.php?id=<?php echo $submission['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                Lihat Detail
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="p-12 text-center text-gray-500">
            <div class="text-4xl mb-4">📭</div>
            <p>Belum ada submission yang diterima</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>