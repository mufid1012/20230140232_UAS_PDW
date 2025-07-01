<?php
session_start();
require_once '../config.php';

// Cek login dan role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Tugas Belum Dikerjakan';
$activePage = 'pending_tasks';

$student_id = $_SESSION['user_id'];

// Query untuk mengambil tugas yang belum dikerjakan
$sql = "
    SELECT 
        m.id as module_id,
        m.judul as module_title,
        m.deskripsi as module_description,
        m.deadline,
        m.urutan,
        c.id as course_id,
        c.nama as course_name,
        c.kode as course_code,
        u.nama as asisten_name,
        (SELECT COUNT(*) FROM materials mat WHERE mat.module_id = m.id) as jumlah_material
    FROM modules m
    INNER JOIN courses c ON m.course_id = c.id
    INNER JOIN users u ON c.asisten_id = u.id
    INNER JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ? 
    AND e.status = 'aktif'
    AND m.id NOT IN (
        SELECT module_id FROM submissions WHERE student_id = ?
    )
    ORDER BY m.deadline ASC, c.nama ASC, m.urutan ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($task_course_id, $task_course_name, $task_course_code, $task_asisten_name, $task_module_id, $task_module_title, $task_module_description, $task_urutan, $task_deadline, $task_jumlah_material);

$pending_tasks = [];
while ($stmt->fetch()) {
    $pending_tasks[] = [
        'course_id' => $task_course_id,
        'course_name' => $task_course_name,
        'course_code' => $task_course_code,
        'asisten_name' => $task_asisten_name,
        'module_id' => $task_module_id,
        'module_title' => $task_module_title,
        'module_description' => $task_module_description,
        'urutan' => $task_urutan,
        'deadline' => $task_deadline,
        'jumlah_material' => $task_jumlah_material
    ];
}

// Hitung statistik
$total_pending = count($pending_tasks);
$urgent_tasks = 0; // Tugas dengan deadline dalam 3 hari
$overdue_tasks = 0; // Tugas yang sudah lewat deadline

foreach ($pending_tasks as $task) {
    if (!empty($task['deadline']) && $task['deadline'] != '0000-00-00 00:00:00' && strtotime($task['deadline']) !== false) {
        $deadline_timestamp = strtotime($task['deadline']);
        $now = time();
        $days_left = ceil(($deadline_timestamp - $now) / (60 * 60 * 24));
        
        if ($days_left < 0) {
            $overdue_tasks++;
        } elseif ($days_left <= 3) {
            $urgent_tasks++;
        }
    } else {
        // Deadline tidak valid
        $days_left = null;
    }
}

$stmt->close();
$conn->close();

require_once 'templates/header_mahasiswa.php';
?>

<!-- Header -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Tugas Belum Dikerjakan</h1>
    <p class="text-gray-600">Daftar tugas yang perlu Anda selesaikan</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Tugas</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_pending; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Mendesak (≤3 hari)</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $urgent_tasks; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Terlambat</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $overdue_tasks; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Tasks List -->
<?php if (empty($pending_tasks)): ?>
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <div class="text-6xl mb-4">🎉</div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Selamat!</h3>
        <p class="text-gray-600 mb-6">Anda telah menyelesaikan semua tugas yang tersedia.</p>
        <a href="my_courses.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
            Lihat Praktikum Saya
        </a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Daftar Tugas Belum Dikerjakan</h3>
        </div>
        
        <div class="divide-y divide-gray-200">
            <?php foreach ($pending_tasks as $task): ?>
                <?php
                $deadline_status = '';
                $deadline_class = '';
                $days_left = '';
                
                if (!empty($task['deadline']) && $task['deadline'] != '0000-00-00 00:00:00' && strtotime($task['deadline']) !== false) {
                    $deadline_timestamp = strtotime($task['deadline']);
                    $now = time();
                    $days_left = ceil(($deadline_timestamp - $now) / (60 * 60 * 24));
                    
                    if ($days_left < 0) {
                        $deadline_status = 'Terlambat';
                        $deadline_class = 'bg-red-100 text-red-800';
                        $days_left = abs($days_left) . ' hari';
                    } elseif ($days_left <= 3) {
                        $deadline_status = 'Mendesak';
                        $deadline_class = 'bg-yellow-100 text-yellow-800';
                        $days_left = $days_left . ' hari lagi';
                    } else {
                        $deadline_status = 'Tersisa';
                        $deadline_class = 'bg-green-100 text-green-800';
                        $days_left = $days_left . ' hari lagi';
                    }
                } else {
                    $deadline_status = 'Tidak ada deadline';
                    $deadline_class = 'bg-gray-100 text-gray-800';
                    $days_left = '-';
                }
                ?>
                
                <div class="p-6 hover:bg-gray-50 transition-colors duration-200">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h4 class="text-lg font-semibold text-gray-800 mr-3">
                                    <?php echo htmlspecialchars($task['module_title']); ?>
                                </h4>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                    Modul <?php echo $task['urutan']; ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-600 mb-3">
                                <?php echo htmlspecialchars($task['module_description']); ?>
                            </p>
                            
                            <div class="flex items-center space-x-6 text-sm text-gray-500 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($task['course_name']); ?> (<?php echo htmlspecialchars($task['course_code']); ?>)</span>
                                </div>
                                
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <span>Asisten: <?php echo htmlspecialchars($task['asisten_name']); ?></span>
                                </div>
                                
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span><?php echo $task['jumlah_material']; ?> materi tersedia</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($task['deadline']) && $task['deadline'] != '0000-00-00 00:00:00' && strtotime($task['deadline']) !== false): ?>
                                <div class="flex items-center mb-4">
                                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm text-gray-600 mr-3">
                                        Deadline: <?php echo date('d/m/Y H:i', strtotime($task['deadline'])); ?>
                                    </span>
                                    <span class="text-xs font-medium px-2.5 py-0.5 rounded <?php echo $deadline_class; ?>">
                                        <?php echo $deadline_status; ?>: <?php echo $days_left; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ml-6">
                            <a href="course_detail.php?id=<?php echo $task['course_id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                Kerjakan Tugas
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'templates/footer_mahasiswa.php';
?> 