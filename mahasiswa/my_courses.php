<?php
session_start();
require_once '../config.php';

// Cek login dan role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Praktikum Saya';
$activePage = 'my_courses';

// Ambil praktikum yang diikuti mahasiswa
$student_id = $_SESSION['user_id'];
$sql = "SELECT c.*, u.nama as nama_asisten, e.enrolled_at, e.status as enrollment_status,
        (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) as jumlah_modul,
        (SELECT COUNT(*) FROM submissions s 
         JOIN modules m ON s.module_id = m.id 
         WHERE m.course_id = c.id AND s.student_id = e.student_id) as jumlah_submission
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        JOIN users u ON c.asisten_id = u.id 
        WHERE e.student_id = ? 
        ORDER BY e.enrolled_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->store_result();

// Bind result variables
$stmt->bind_result($course_id, $course_nama, $course_deskripsi, $course_kode, $course_semester, $course_tahun_ajaran, $course_asisten_id, $course_status, $course_created_at, $course_updated_at, $course_nama_asisten, $course_enrolled_at, $course_enrollment_status, $course_jumlah_modul, $course_jumlah_submission);

$enrolled_courses = [];
while ($stmt->fetch()) {
    $enrolled_courses[] = [
        'id' => $course_id,
        'nama' => $course_nama,
        'deskripsi' => $course_deskripsi,
        'kode' => $course_kode,
        'semester' => $course_semester,
        'tahun_ajaran' => $course_tahun_ajaran,
        'asisten_id' => $course_asisten_id,
        'status' => $course_status,
        'created_at' => $course_created_at,
        'updated_at' => $course_updated_at,
        'nama_asisten' => $course_nama_asisten,
        'enrolled_at' => $course_enrolled_at,
        'enrollment_status' => $course_enrollment_status,
        'jumlah_modul' => $course_jumlah_modul,
        'jumlah_submission' => $course_jumlah_submission
    ];
}

$stmt->close();
$conn->close();

require_once 'templates/header_mahasiswa.php';
?>

<!-- Header -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Praktikum Saya</h1>
    <p class="text-gray-600">Kelola praktikum yang Anda ikuti</p>
</div>

<!-- Course Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($enrolled_courses)): ?>
        <div class="col-span-full text-center py-12">
            <div class="text-gray-500 text-lg mb-4">Anda belum mendaftar ke praktikum apapun</div>
            <a href="courses.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                Cari Praktikum
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($enrolled_courses as $course): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($course['nama']); ?></h3>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                            <?php echo htmlspecialchars($course['kode']); ?>
                        </span>
                    </div>
                    
                    <p class="text-gray-600 mb-4 line-clamp-2">
                        <?php echo htmlspecialchars($course['deskripsi']); ?>
                    </p>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Asisten: <?php echo htmlspecialchars($course['nama_asisten']); ?></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span><?php echo $course['jumlah_modul']; ?> modul tersedia</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo $course['jumlah_submission']; ?> tugas dikumpulkan</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Daftar: <?php echo date('d/m/Y', strtotime($course['enrolled_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300 text-center">
                            Lihat Detail
                        </a>
                        <?php if ($course['enrollment_status'] == 'aktif'): ?>
                            <form method="POST" action="unenroll.php" class="flex-1" onsubmit="return confirm('Apakah Anda yakin ingin keluar dari praktikum ini?')">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                    Keluar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer_mahasiswa.php';
?> 