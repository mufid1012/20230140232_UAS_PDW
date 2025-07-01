<?php
session_start();
require_once '../config.php';

// Cek login dan role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Detail Praktikum';
$activePage = 'my_courses';

// Ambil ID course dari parameter
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = $_SESSION['user_id'];

// Cek apakah mahasiswa terdaftar di course ini
$enrollment_check = "SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'aktif'";
$enrollment_stmt = $conn->prepare($enrollment_check);
$enrollment_stmt->bind_param("ii", $student_id, $course_id);
$enrollment_stmt->execute();
$enrollment_stmt->store_result();

if ($enrollment_stmt->num_rows == 0) {
    header("Location: my_courses.php");
    exit();
}
$enrollment_stmt->close();

// Ambil detail course
$course_sql = "SELECT c.*, u.nama as nama_asisten FROM courses c 
               JOIN users u ON c.asisten_id = u.id 
               WHERE c.id = ?";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_stmt->store_result();

// Bind result variables
$course_stmt->bind_result($course_id, $course_nama, $course_deskripsi, $course_kode, $course_semester, $course_tahun_ajaran, $course_asisten_id, $course_status, $course_created_at, $course_updated_at, $course_nama_asisten);

// Fetch course data
$course_stmt->fetch();
$course = [
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
    'nama_asisten' => $course_nama_asisten
];
$course_stmt->close();

// Ambil modul-modul dalam course
$modules_sql = "SELECT m.*, 
                (SELECT COUNT(*) FROM materials mat WHERE mat.module_id = m.id) as jumlah_materi,
                (SELECT COUNT(*) FROM submissions s WHERE s.module_id = m.id AND s.student_id = ?) as sudah_submit
                FROM modules m 
                WHERE m.course_id = ? 
                ORDER BY m.urutan ASC";
$modules_stmt = $conn->prepare($modules_sql);
$modules_stmt->bind_param("ii", $student_id, $course_id);
$modules_stmt->execute();
$modules_stmt->store_result();

// Bind result variables
$modules_stmt->bind_result($module_id, $module_course_id, $module_judul, $module_deskripsi, $module_urutan, $module_deadline, $module_created_at, $module_updated_at, $module_jumlah_materi, $module_sudah_submit);

$modules = [];
while ($modules_stmt->fetch()) {
    $modules[] = [
        'id' => $module_id,
        'course_id' => $module_course_id,
        'judul' => $module_judul,
        'deskripsi' => $module_deskripsi,
        'urutan' => $module_urutan,
        'deadline' => $module_deadline,
        'created_at' => $module_created_at,
        'updated_at' => $module_updated_at,
        'jumlah_materi' => $module_jumlah_materi,
        'sudah_submit' => $module_sudah_submit
    ];
}
$modules_stmt->close();

// Handle file upload untuk submission
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_report'])) {
    $module_id = $_POST['module_id'];
    $komentar = trim($_POST['komentar']);
    
    // Validasi file
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] == 0) {
        $file = $_FILES['report_file'];
        $allowed_types = ['pdf', 'doc', 'docx', 'zip', 'rar'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $message = "Tipe file tidak diizinkan. Gunakan PDF, DOC, DOCX, ZIP, atau RAR.";
        } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $message = "Ukuran file terlalu besar. Maksimal 10MB.";
        } else {
            // Cek apakah sudah submit sebelumnya
            $check_sql = "SELECT id FROM submissions WHERE student_id = ? AND module_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $student_id, $module_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $message = "Anda sudah mengumpulkan laporan untuk modul ini.";
            } else {
                // Upload file
                $upload_dir = dirname(__DIR__) . "/uploads/submissions/";
                
                // Pastikan direktori ada dan bisa ditulis
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $student_id . '_' . $module_id . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Simpan ke database dengan path relatif
                    $db_file_path = "uploads/submissions/" . $file_name;
                    $insert_sql = "INSERT INTO submissions (student_id, module_id, nama_file, nama_asli, tipe_file, ukuran_file, path_file, komentar) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iissssss", $student_id, $module_id, $file_name, $file['name'], $file_extension, $file['size'], $db_file_path, $komentar);
                    
                    if ($insert_stmt->execute()) {
                        $message = "Laporan berhasil dikumpulkan!";
                        // Refresh halaman
                        header("Location: course_detail.php?id=" . $course_id . "&status=submitted");
                        exit();
                    } else {
                        $message = "Gagal menyimpan laporan ke database: " . $conn->error;
                        // Hapus file yang sudah diupload jika gagal simpan ke database
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    $insert_stmt->close();
                } else {
                    $message = "Gagal mengunggah file. Silakan coba lagi. Error: " . error_get_last()['message'];
                }
            }
            $check_stmt->close();
        }
    } else {
        $message = "Pilih file laporan terlebih dahulu.";
    }
}

// Jangan tutup koneksi di sini karena masih digunakan untuk query materials
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($course['nama']); ?> - SIMPRAK</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation -->
    <nav class="bg-blue-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-white text-2xl font-bold">SIMPRAK</span>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="dashboard.php" class="text-gray-200 hover:bg-blue-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <a href="my_courses.php" class="bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium">Praktikum Saya</a>
                            <a href="courses.php" class="text-gray-200 hover:bg-blue-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Cari Praktikum</a>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        <span class="text-white mr-4"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                        <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($course['nama']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($course['deskripsi']); ?></p>
                </div>
                <a href="my_courses.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                    ← Kembali
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['status']) && $_GET['status'] == 'submitted'): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <strong>Berhasil!</strong> Laporan Anda telah dikumpulkan.
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Course Info -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Praktikum</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Kode Praktikum</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($course['kode']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Asisten</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($course['nama_asisten']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Semester</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($course['semester']); ?> - <?php echo htmlspecialchars($course['tahun_ajaran']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Jumlah Modul</p>
                    <p class="font-semibold"><?php echo count($modules); ?> modul</p>
                </div>
            </div>
        </div>

        <!-- Modules -->
        <div class="space-y-6">
            <?php if (empty($modules)): ?>
                <div class="bg-white rounded-lg shadow-md p-6 text-center">
                    <p class="text-gray-500">Belum ada modul yang tersedia</p>
                </div>
            <?php else: ?>
                <?php foreach ($modules as $module): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($module['judul']); ?></h3>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                    Modul <?php echo $module['urutan']; ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($module['deskripsi']); ?></p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="text-sm text-gray-500">
                                    <span class="font-semibold">Deadline:</span><br>
                                    <?php echo $module['deadline'] ? date('d/m/Y H:i', strtotime($module['deadline'])) : 'Tidak ada deadline'; ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-semibold">Materi:</span><br>
                                    <?php echo $module['jumlah_materi']; ?> file tersedia
                                </div>
                                <div class="text-sm text-gray-500">
                                    <span class="font-semibold">Status:</span><br>
                                    <?php if ($module['sudah_submit'] > 0): ?>
                                        <span class="text-green-600">✓ Sudah dikumpulkan</span>
                                    <?php else: ?>
                                        <span class="text-red-600">✗ Belum dikumpulkan</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Materials -->
                            <?php
                            // Reconnect to database if needed
                            if (!isset($conn) || !$conn) {
                                require_once '../config.php';
                            }
                            
                            $materials_sql = "SELECT id, module_id, nama_file, nama_asli, tipe_file, ukuran_file, path_file, created_at FROM materials WHERE module_id = ? ORDER BY created_at ASC";
                            $materials_stmt = $conn->prepare($materials_sql);
                            $materials_stmt->bind_param("i", $module['id']);
                            $materials_stmt->execute();
                            $materials_stmt->store_result();

                            // Bind result variables
                            $materials_stmt->bind_result($material_id, $material_module_id, $material_nama_file, $material_nama_asli, $material_tipe_file, $material_ukuran_file, $material_path_file, $material_created_at);
                            ?>
                            
                            <?php if ($materials_stmt->num_rows > 0): ?>
                                <div class="mb-4">
                                    <h4 class="font-semibold text-gray-700 mb-2">Materi Praktikum:</h4>
                                    <div class="space-y-2">
                                        <?php while ($materials_stmt->fetch()): ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                                <div class="flex items-center">
                                                    <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    <span class="text-sm"><?php echo htmlspecialchars($material_nama_asli); ?></span>
                                                </div>
                                                <a href="../download.php?type=material&id=<?php echo $material_id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded transition-colors duration-300">
                                                    Unduh
                                                </a>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php $materials_stmt->close(); ?>

                            <!-- Submission Form -->
                            <?php if ($module['sudah_submit'] == 0): ?>
                                <div class="border-t pt-4">
                                    <h4 class="font-semibold text-gray-700 mb-3">Kumpulkan Laporan:</h4>
                                    <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                        <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">File Laporan (PDF, DOC, DOCX, ZIP, RAR)</label>
                                            <input type="file" name="report_file" accept=".pdf,.doc,.docx,.zip,.rar" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <p class="text-xs text-gray-500 mt-1">Maksimal 10MB</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Komentar (Opsional)</label>
                                            <textarea name="komentar" rows="3" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Tambahkan komentar atau catatan untuk asisten..."></textarea>
                                        </div>
                                        
                                        <button type="submit" name="submit_report" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                            Kumpulkan Laporan
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- Submission Status -->
                                <?php
                                // Reconnect to database if needed
                                if (!isset($conn) || !$conn) {
                                    require_once '../config.php';
                                }
                                
                                $submission_sql = "SELECT * FROM submissions WHERE student_id = ? AND module_id = ?";
                                $submission_stmt = $conn->prepare($submission_sql);
                                $submission_stmt->bind_param("ii", $student_id, $module['id']);
                                $submission_stmt->execute();
                                $submission_stmt->store_result();

                                // Bind result variables
                                $submission_stmt->bind_result($submission_id, $submission_student_id, $submission_module_id, $submission_nama_file, $submission_nama_asli, $submission_tipe_file, $submission_ukuran_file, $submission_path_file, $submission_submitted_at, $submission_nilai, $submission_feedback, $submission_status, $submission_graded_at, $submission_komentar);

                                // Fetch submission data
                                $submission_stmt->fetch();
                                $submission = [
                                    'id' => $submission_id,
                                    'student_id' => $submission_student_id,
                                    'module_id' => $submission_module_id,
                                    'nama_file' => $submission_nama_file,
                                    'nama_asli' => $submission_nama_asli,
                                    'tipe_file' => $submission_tipe_file,
                                    'ukuran_file' => $submission_ukuran_file,
                                    'path_file' => $submission_path_file,
                                    'submitted_at' => $submission_submitted_at,
                                    'nilai' => $submission_nilai,
                                    'feedback' => $submission_feedback,
                                    'status' => $submission_status,
                                    'graded_at' => $submission_graded_at,
                                    'komentar' => $submission_komentar
                                ];
                                $submission_stmt->close();
                                ?>
                                
                                <div class="border-t pt-4">
                                    <h4 class="font-semibold text-gray-700 mb-3">Status Pengumpulan:</h4>
                                    <div class="bg-green-50 border border-green-200 rounded p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-green-800">
                                                    <strong>Dikumpulkan:</strong> <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?>
                                                </p>
                                                <?php if ($submission['komentar']): ?>
                                                    <p class="text-sm text-green-700 mt-1">
                                                        <strong>Komentar:</strong> <?php echo htmlspecialchars($submission['komentar']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <a href="../download.php?type=submission&id=<?php echo $submission['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded transition-colors duration-300">
                                                Unduh File
                                            </a>
                                        </div>
                                        
                                        <?php if ($submission['nilai'] !== null): ?>
                                            <div class="mt-3 pt-3 border-t border-green-200">
                                                <p class="text-sm text-green-800">
                                                    <strong>Nilai:</strong> <?php echo $submission['nilai']; ?>/100
                                                </p>
                                                <?php if ($submission['feedback']): ?>
                                                    <p class="text-sm text-green-700 mt-1">
                                                        <strong>Feedback:</strong> <?php echo htmlspecialchars($submission['feedback']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-3 pt-3 border-t border-green-200">
                                                <p class="text-sm text-yellow-600">
                                                    <strong>Status:</strong> Menunggu penilaian asisten
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>
