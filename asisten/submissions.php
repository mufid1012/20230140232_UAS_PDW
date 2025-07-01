<?php
session_start();
require_once '../config.php';

// Cek login dan role asisten
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Laporan Masuk';
$activePage = 'submissions';

$message = '';

// Filter parameters
$filter_module = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;
$filter_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Handle grading submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $nilai = (float)$_POST['nilai'];
    $feedback = trim($_POST['feedback']);
    
    // Validasi nilai
    if ($nilai < 0 || $nilai > 100) {
        $message = "Nilai harus antara 0-100!";
    } else {
        // Cek apakah submission milik course yang diajar asisten ini
        $check_sql = "SELECT s.id FROM submissions s 
                      JOIN modules m ON s.module_id = m.id 
                      JOIN courses c ON m.course_id = c.id 
                      WHERE s.id = ? AND c.asisten_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $submission_id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows == 0) {
            $message = "Submission tidak ditemukan atau Anda tidak memiliki akses.";
        } else {
            $update_sql = "UPDATE submissions SET nilai = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("dsi", $nilai, $feedback, $submission_id);
            
            if ($update_stmt->execute()) {
                $message = "Nilai berhasil disimpan!";
                header("Location: submissions.php?status=graded");
                exit();
            } else {
                $message = "Gagal menyimpan nilai.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

// Ambil daftar course yang dimiliki asisten
$courses_sql = "SELECT id, nama, kode FROM courses WHERE asisten_id = ? ORDER BY nama ASC";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $_SESSION['user_id']);
$courses_stmt->execute();
$courses_stmt->store_result();

// Bind result variables
$courses_stmt->bind_result($course_id, $course_nama, $course_kode);

// Fetch all courses into array
$courses = [];
while ($courses_stmt->fetch()) {
    $courses[] = [
        'id' => $course_id,
        'nama' => $course_nama,
        'kode' => $course_kode
    ];
}
$courses_stmt->close();

// Ambil daftar modul untuk filter
$modules_sql = "SELECT m.id, m.judul, c.nama as course_name, c.kode as course_code 
                FROM modules m 
                JOIN courses c ON m.course_id = c.id 
                WHERE c.asisten_id = ? 
                ORDER BY c.nama, m.urutan";
$modules_stmt = $conn->prepare($modules_sql);
$modules_stmt->bind_param("i", $_SESSION['user_id']);
$modules_stmt->execute();
$modules_stmt->store_result();

// Bind result variables
$modules_stmt->bind_result($module_id, $module_judul, $module_course_name, $module_course_code);

// Fetch all modules into array
$modules = [];
while ($modules_stmt->fetch()) {
    $modules[] = [
        'id' => $module_id,
        'judul' => $module_judul,
        'course_name' => $module_course_name,
        'course_code' => $module_course_code
    ];
}
$modules_stmt->close();

// Ambil daftar mahasiswa untuk filter
$students_sql = "SELECT DISTINCT u.id, u.nama, u.email 
                 FROM users u 
                 JOIN enrollments e ON u.id = e.student_id 
                 JOIN courses c ON e.course_id = c.id 
                 WHERE u.role = 'mahasiswa' AND c.asisten_id = ? 
                 ORDER BY u.nama";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $_SESSION['user_id']);
$students_stmt->execute();
$students_stmt->store_result();

// Bind result variables
$students_stmt->bind_result($student_id, $student_nama, $student_email);

// Fetch all students into array
$students = [];
while ($students_stmt->fetch()) {
    $students[] = [
        'id' => $student_id,
        'nama' => $student_nama,
        'email' => $student_email
    ];
}
$students_stmt->close();

// Ambil submissions dengan filter
$where_conditions = ["c.asisten_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = "i";

if ($filter_module > 0) {
    $where_conditions[] = "s.module_id = ?";
    $params[] = $filter_module;
    $param_types .= "i";
}

if ($filter_student > 0) {
    $where_conditions[] = "s.student_id = ?";
    $params[] = $filter_student;
    $param_types .= "i";
}

if (!empty($filter_status)) {
    $where_conditions[] = "s.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

$submissions_sql = "SELECT s.*, 
                    u.nama as student_name, u.email as student_email,
                    m.judul as module_title, m.urutan as module_order,
                    c.nama as course_name, c.kode as course_code
                    FROM submissions s 
                    JOIN users u ON s.student_id = u.id 
                    JOIN modules m ON s.module_id = m.id 
                    JOIN courses c ON m.course_id = c.id 
                    WHERE " . implode(" AND ", $where_conditions) . "
                    ORDER BY s.submitted_at DESC";

$submissions_stmt = $conn->prepare($submissions_sql);
$submissions_stmt->bind_param($param_types, ...$params);
$submissions_stmt->execute();
$submissions_stmt->store_result();

// Bind result variables - 14 columns from submissions + 6 from JOINs = 20 total
$submissions_stmt->bind_result($sub_id, $sub_student_id, $sub_module_id, $sub_nama_file, $sub_nama_asli, $sub_tipe_file, $sub_ukuran_file, $sub_path_file, $sub_komentar, $sub_nilai, $sub_feedback, $sub_status, $sub_submitted_at, $sub_graded_at, $sub_student_name, $sub_student_email, $sub_module_title, $sub_module_order, $sub_course_name, $sub_course_code);

// Fetch all submissions into array
$submissions = [];
while ($submissions_stmt->fetch()) {
    $submissions[] = [
        'id' => $sub_id,
        'student_id' => $sub_student_id,
        'module_id' => $sub_module_id,
        'nama_file' => $sub_nama_file,
        'nama_asli' => $sub_nama_asli,
        'tipe_file' => $sub_tipe_file,
        'ukuran_file' => $sub_ukuran_file,
        'path_file' => $sub_path_file,
        'komentar' => $sub_komentar,
        'submitted_at' => $sub_submitted_at,
        'nilai' => $sub_nilai,
        'feedback' => $sub_feedback,
        'status' => $sub_status,
        'graded_at' => $sub_graded_at,
        'student_name' => $sub_student_name,
        'student_email' => $sub_student_email,
        'module_title' => $sub_module_title,
        'module_order' => $sub_module_order,
        'course_name' => $sub_course_name,
        'course_code' => $sub_course_code
    ];
}
$submissions_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?> - Panel Asisten</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-800 text-white flex flex-col">
            <div class="p-6 text-center border-b border-gray-700">
                <h3 class="text-xl font-bold">Panel Asisten</h3>
                <p class="text-sm text-gray-400 mt-1"><?php echo htmlspecialchars($_SESSION['nama']); ?></p>
            </div>
            <nav class="flex-grow">
                <ul class="space-y-2 p-4">
                    <li>
                        <a href="dashboard.php" class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="courses.php" class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                            <span>Manajemen Mata Praktikum</span>
                        </a>
                    </li>
                    <li>
                        <a href="modules.php" class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            <span>Manajemen Modul</span>
                        </a>
                    </li>
                    <li>
                        <a href="submissions.php" class="bg-gray-900 text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75c0-.231-.035-.454-.1-.664M6.75 7.5h1.5M6.75 12h1.5m6.75 0h1.5m-1.5 3h1.5m-1.5 3h1.5M4.5 6.75h1.5v1.5H4.5v-1.5zM4.5 12h1.5v1.5H4.5v-1.5zM4.5 17.25h1.5v1.5H4.5v-1.5z" /></svg>
                            <span>Laporan Masuk</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                            <span>Manajemen Pengguna</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 lg:p-10">
            <header class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-300">
                    Logout
                </a>
            </header>

            <!-- Success Messages -->
            <?php if (isset($_GET['status'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php 
                    switch($_GET['status']) {
                        case 'graded': echo '<strong>Berhasil!</strong> Nilai berhasil disimpan.'; break;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Filter Laporan</h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                        <select name="module_id" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Modul</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module['id']; ?>" <?php echo $filter_module == $module['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['course_code'] . ' - ' . $module['judul']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa</label>
                        <select name="student_id" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Mahasiswa</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $filter_student == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Semua Status</option>
                            <option value="submitted" <?php echo $filter_status == 'submitted' ? 'selected' : ''; ?>>Belum Dinilai</option>
                            <option value="graded" <?php echo $filter_status == 'graded' ? 'selected' : ''; ?>>Sudah Dinilai</option>
                            <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>Terlambat</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Filter
                        </button>
                        <a href="submissions.php" class="ml-2 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Submissions List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Daftar Laporan (<?php echo count($submissions); ?> laporan)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Praktikum</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Submit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($submissions)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">Tidak ada laporan yang ditemukan</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($submission['student_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($submission['student_email']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($submission['course_code'] . ' - ' . $submission['course_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($submission['module_title']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <a href="../download.php?type=submission&id=<?php echo $submission['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                <?php echo htmlspecialchars($submission['nama_asli']); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                switch($submission['status']) {
                                                    case 'submitted': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'graded': echo 'bg-green-100 text-green-800'; break;
                                                    case 'late': echo 'bg-red-100 text-red-800'; break;
                                                }
                                                ?>">
                                                <?php 
                                                switch($submission['status']) {
                                                    case 'submitted': echo 'Belum Dinilai'; break;
                                                    case 'graded': echo 'Sudah Dinilai'; break;
                                                    case 'late': echo 'Terlambat'; break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if ($submission['nilai'] !== null): ?>
                                                <span class="font-semibold"><?php echo $submission['nilai']; ?>/100</span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="gradeSubmission(<?php echo htmlspecialchars(json_encode($submission)); ?>)" class="text-blue-600 hover:text-blue-900">
                                                <?php echo $submission['nilai'] !== null ? 'Edit Nilai' : 'Beri Nilai'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Grade Submission Modal -->
    <div id="gradeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Beri Nilai</h3>
                <form method="POST" id="gradeForm">
                    <input type="hidden" name="submission_id" id="grade_submission_id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa</label>
                            <p class="text-sm text-gray-900" id="grade_student_name"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                            <p class="text-sm text-gray-900" id="grade_module_title"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nilai (0-100)</label>
                            <input type="number" name="nilai" id="grade_nilai" min="0" max="100" step="0.01" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Feedback (Opsional)</label>
                            <textarea name="feedback" id="grade_feedback" rows="3" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Masukkan feedback untuk mahasiswa..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeGradeModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Batal
                        </button>
                        <button type="submit" name="grade_submission" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Simpan Nilai
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function gradeSubmission(submission) {
            document.getElementById('grade_submission_id').value = submission.id;
            document.getElementById('grade_student_name').textContent = submission.student_name;
            document.getElementById('grade_module_title').textContent = submission.module_title;
            document.getElementById('grade_nilai').value = submission.nilai || '';
            document.getElementById('grade_feedback').value = submission.feedback || '';
            document.getElementById('gradeModal').classList.remove('hidden');
        }

        function closeGradeModal() {
            document.getElementById('gradeModal').classList.add('hidden');
        }
    </script>
</body>
</html>
