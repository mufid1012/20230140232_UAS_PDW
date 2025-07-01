<?php
session_start();
require_once '../config.php';

$pageTitle = 'Katalog Praktikum';
$activePage = 'courses';

// Ambil semua mata praktikum yang aktif
$sql = "SELECT c.*, u.nama as nama_asisten, 
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'aktif') as jumlah_mahasiswa
        FROM courses c 
        JOIN users u ON c.asisten_id = u.id 
        WHERE c.status = 'aktif' 
        ORDER BY c.created_at DESC";
$result = $conn->query($sql);

$courses = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Cek apakah mahasiswa sudah terdaftar di praktikum ini
        $is_enrolled = false;
        if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'mahasiswa') {
            $check_sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'aktif'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $_SESSION['user_id'], $row['id']);
            $check_stmt->execute();
            $check_stmt->store_result();
            $is_enrolled = $check_stmt->num_rows > 0;
            $check_stmt->close();
        }
        
        $row['is_enrolled'] = $is_enrolled;
        $courses[] = $row;
    }
}

// Handle pendaftaran praktikum
$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll_course'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
        $message = "Anda harus login sebagai mahasiswa untuk mendaftar praktikum.";
    } else {
        $course_id = $_POST['course_id'];
        $student_id = $_SESSION['user_id'];
        
        // Cek apakah sudah terdaftar
        $check_sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $student_id, $course_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $message = "Anda sudah terdaftar di praktikum ini.";
        } else {
            // Daftar ke praktikum
            $enroll_sql = "INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)";
            $enroll_stmt = $conn->prepare($enroll_sql);
            $enroll_stmt->bind_param("ii", $student_id, $course_id);
            
            if ($enroll_stmt->execute()) {
                $message = "Berhasil mendaftar ke praktikum!";
                // Refresh halaman untuk update data
                header("Location: courses.php?status=enrolled");
                exit();
            } else {
                $message = "Gagal mendaftar ke praktikum. Silakan coba lagi.";
            }
            $enroll_stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?> - SIMPRAK</title>
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
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="dashboard.php" class="text-gray-200 hover:bg-blue-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                                <a href="my_courses.php" class="text-gray-200 hover:bg-blue-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Praktikum Saya</a>
                            <?php endif; ?>
                            <a href="courses.php" class="bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium">Katalog Praktikum</a>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span class="text-white mr-4"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                            <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                                Logout
                            </a>
                        <?php else: ?>
                            <a href="../login.php" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md transition-colors duration-300">
                                Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6 lg:p-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Katalog Mata Praktikum</h1>
            <p class="text-gray-600">Temukan dan daftar ke mata praktikum yang tersedia</p>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['status']) && $_GET['status'] == 'enrolled'): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <strong>Berhasil!</strong> Anda telah mendaftar ke praktikum. Silakan cek di menu "Praktikum Saya".
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Course Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($courses)): ?>
                <div class="col-span-full text-center py-12">
                    <div class="text-gray-500 text-lg">Belum ada mata praktikum yang tersedia</div>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($course['nama']); ?></h3>
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                    <?php echo htmlspecialchars($course['kode']); ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-600 mb-4 line-clamp-3">
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($course['semester']); ?> - <?php echo htmlspecialchars($course['tahun_ajaran']); ?></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                    <span><?php echo $course['jumlah_mahasiswa']; ?> mahasiswa terdaftar</span>
                                </div>
                            </div>
                            
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'mahasiswa'): ?>
                                <?php if ($course['is_enrolled']): ?>
                                    <div class="mt-4">
                                        <div class="w-full bg-green-100 text-green-800 font-bold py-2 px-4 rounded text-center border border-green-300">
                                            ✅ Terdaftar
                                        </div>
                                        <a href="my_courses.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300 block text-center mt-2">
                                            Lihat Praktikum
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" name="enroll_course" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                            Daftar Praktikum
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <div class="mt-4">
                                    <a href="../login.php" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300 block text-center">
                                        Login untuk Daftar
                                    </a>
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