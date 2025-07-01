<?php
session_start();
require_once '../config.php';

// Cek login dan role asisten
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manajemen Modul';
$activePage = 'modules';

$message = '';
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

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

// Handle form submission untuk tambah/edit modul
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_module'])) {
        $course_id = (int)$_POST['course_id'];
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi']);
        $urutan = (int)$_POST['urutan'];
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        
        // Validasi
        if (empty($judul) || empty($course_id) || $urutan <= 0) {
            $message = "Judul, praktikum, dan urutan wajib diisi!";
        } else {
            // Cek apakah course milik asisten ini
            $check_course = "SELECT id FROM courses WHERE id = ? AND asisten_id = ?";
            $check_stmt = $conn->prepare($check_course);
            $check_stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows == 0) {
                $message = "Praktikum tidak ditemukan atau Anda tidak memiliki akses.";
            } else {
                $insert_sql = "INSERT INTO modules (course_id, judul, deskripsi, urutan, deadline) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("issis", $course_id, $judul, $deskripsi, $urutan, $deadline);
                
                if ($insert_stmt->execute()) {
                    $module_id = $conn->insert_id;
                    
                    // Handle file upload untuk materi
                    if (isset($_FILES['materials']) && !empty($_FILES['materials']['name'][0])) {
                        $upload_dir = "../uploads/materials/";
                        
                        // Pastikan direktori ada dan bisa ditulis
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        foreach ($_FILES['materials']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['materials']['error'][$key] == 0) {
                                $file = [
                                    'name' => $_FILES['materials']['name'][$key],
                                    'tmp_name' => $tmp_name,
                                    'size' => $_FILES['materials']['size'][$key],
                                    'type' => $_FILES['materials']['type'][$key]
                                ];
                                
                                $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'];
                                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                
                                if (in_array($file_extension, $allowed_types) && $file['size'] <= 50 * 1024 * 1024) { // 50MB limit
                                    $file_name = time() . '_' . $module_id . '_' . $key . '.' . $file_extension;
                                    $file_path = $upload_dir . $file_name;
                                    
                                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                        $material_sql = "INSERT INTO materials (module_id, nama_file, nama_asli, tipe_file, ukuran_file, path_file) VALUES (?, ?, ?, ?, ?, ?)";
                                        $material_stmt = $conn->prepare($material_sql);
                                        $material_stmt->bind_param("isssss", $module_id, $file_name, $file['name'], $file_extension, $file['size'], $file_path);
                                        if ($material_stmt->execute()) {
                                            // File berhasil disimpan
                                        } else {
                                            // Log error jika gagal menyimpan ke database
                                            error_log("Gagal menyimpan material ke database: " . $conn->error);
                                        }
                                        $material_stmt->close();
                                    } else {
                                        // Log error jika gagal upload file
                                        error_log("Gagal upload file: " . $file['name']);
                                    }
                                } else {
                                    // Log error jika file tidak valid
                                    error_log("File tidak valid: " . $file['name'] . " - Extension: " . $file_extension . " - Size: " . $file['size']);
                                }
                            } else {
                                // Log error upload
                                error_log("Error upload file: " . $_FILES['materials']['error'][$key]);
                            }
                        }
                    }
                    
                    $message = "Modul berhasil ditambahkan!";
                    header("Location: modules.php?course_id=" . $course_id . "&status=added");
                    exit();
                } else {
                    $message = "Gagal menambahkan modul.";
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['edit_module'])) {
        $module_id = (int)$_POST['module_id'];
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi']);
        $urutan = (int)$_POST['urutan'];
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        
        // Validasi
        if (empty($judul) || $urutan <= 0) {
            $message = "Judul dan urutan wajib diisi!";
        } else {
            // Cek apakah modul milik asisten ini
            $check_sql = "SELECT m.id FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ? AND c.asisten_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $module_id, $_SESSION['user_id']);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows == 0) {
                $message = "Modul tidak ditemukan atau Anda tidak memiliki akses.";
            } else {
                $update_sql = "UPDATE modules SET judul = ?, deskripsi = ?, urutan = ?, deadline = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssisi", $judul, $deskripsi, $urutan, $deadline, $module_id);
                
                if ($update_stmt->execute()) {
                    $message = "Modul berhasil diperbarui!";
                    header("Location: modules.php?course_id=" . $selected_course_id . "&status=updated");
                    exit();
                } else {
                    $message = "Gagal memperbarui modul.";
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['delete_module'])) {
        $module_id = (int)$_POST['module_id'];
        
        // Cek apakah modul milik asisten ini
        $check_sql = "SELECT m.id FROM modules m JOIN courses c ON m.course_id = c.id WHERE m.id = ? AND c.asisten_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $module_id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $delete_sql = "DELETE FROM modules WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $module_id);
            
            if ($delete_stmt->execute()) {
                $message = "Modul berhasil dihapus!";
                header("Location: modules.php?course_id=" . $selected_course_id . "&status=deleted");
                exit();
            } else {
                $message = "Gagal menghapus modul.";
            }
            $delete_stmt->close();
        } else {
            $message = "Modul tidak ditemukan atau Anda tidak memiliki akses.";
        }
        $check_stmt->close();
    }
}

// Ambil modul-modul jika course dipilih
$modules = [];
if ($selected_course_id > 0) {
    $modules_sql = "SELECT m.*, 
                    (SELECT COUNT(*) FROM materials mat WHERE mat.module_id = m.id) as jumlah_materi,
                    (SELECT COUNT(*) FROM submissions s WHERE s.module_id = m.id) as jumlah_submission
                    FROM modules m 
                    JOIN courses c ON m.course_id = c.id 
                    WHERE m.course_id = ? AND c.asisten_id = ? 
                    ORDER BY m.urutan ASC";
    $modules_stmt = $conn->prepare($modules_sql);
    $modules_stmt->bind_param("ii", $selected_course_id, $_SESSION['user_id']);
    $modules_stmt->execute();
    $modules_stmt->store_result();

    // Bind result variables
    $modules_stmt->bind_result($module_id, $module_course_id, $module_judul, $module_deskripsi, $module_urutan, $module_deadline, $module_created_at, $module_updated_at, $module_jumlah_materi, $module_jumlah_submission);
    
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
            'jumlah_submission' => $module_jumlah_submission
        ];
    }
    $modules_stmt->close();
}

// Jangan tutup koneksi di sini karena masih digunakan untuk query materials
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
                        <a href="modules.php" class="bg-gray-900 text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            <span>Manajemen Modul</span>
                        </a>
                    </li>
                    <li>
                        <a href="submissions.php" class="text-gray-300 hover:bg-gray-700 hover:text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
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
                        case 'added': echo '<strong>Berhasil!</strong> Modul berhasil ditambahkan.'; break;
                        case 'updated': echo '<strong>Berhasil!</strong> Modul berhasil diperbarui.'; break;
                        case 'deleted': echo '<strong>Berhasil!</strong> Modul berhasil dihapus.'; break;
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

            <!-- Course Selection -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Pilih Praktikum</h2>
                <form method="GET" class="flex gap-4">
                    <select name="course_id" class="flex-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                        <option value="">Pilih Praktikum</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $selected_course_id == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['kode'] . ' - ' . $course['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_course_id > 0): ?>
                <!-- Add Module Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Modul Baru</h2>
                    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Judul Modul</label>
                            <input type="text" name="judul" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                            <input type="number" name="urutan" min="1" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deadline (Opsional)</label>
                            <input type="datetime-local" name="deadline" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">File Materi (Opsional)</label>
                            <input type="file" name="materials[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Maksimal 50MB per file. Format: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea name="deskripsi" rows="3" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Deskripsi modul..."></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" name="add_module" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                                Tambah Modul
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Modules List -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Daftar Modul</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urutan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($modules)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Belum ada modul</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($modules as $module): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                    <?php echo $module['urutan']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($module['judul']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($module['deskripsi']); ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $module['deadline'] ? date('d/m/Y H:i', strtotime($module['deadline'])) : '-'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $module['jumlah_materi']; ?> file
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $module['jumlah_submission']; ?> laporan
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button onclick="editModule(<?php echo htmlspecialchars(json_encode($module)); ?>)" class="text-blue-600 hover:text-blue-900">Edit</button>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus modul ini?')">
                                                        <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                        <button type="submit" name="delete_module" class="text-red-600 hover:text-red-900">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Edit Module Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Modul</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="module_id" id="edit_module_id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Judul Modul</label>
                            <input type="text" name="judul" id="edit_judul" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                            <input type="number" name="urutan" id="edit_urutan" min="1" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deadline (Opsional)</label>
                            <input type="datetime-local" name="deadline" id="edit_deadline" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                            <textarea name="deskripsi" id="edit_deskripsi" rows="3" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEditModal()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Batal
                        </button>
                        <button type="submit" name="edit_module" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editModule(module) {
            document.getElementById('edit_module_id').value = module.id;
            document.getElementById('edit_judul').value = module.judul;
            document.getElementById('edit_urutan').value = module.urutan;
            document.getElementById('edit_deadline').value = module.deadline ? module.deadline.replace(' ', 'T') : '';
            document.getElementById('edit_deskripsi').value = module.deskripsi;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
