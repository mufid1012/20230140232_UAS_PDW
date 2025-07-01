<?php
session_start();
require_once '../config.php';

// Cek login dan role asisten
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'asisten') {
    header("Location: ../login.php");
    exit();
}

$pageTitle = 'Manajemen Mata Praktikum';
$activePage = 'courses';

$message = '';

// Handle form submission untuk tambah/edit course
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_course'])) {
        $nama = trim($_POST['nama']);
        $deskripsi = trim($_POST['deskripsi']);
        $kode = trim($_POST['kode']);
        $semester = trim($_POST['semester']);
        $tahun_ajaran = trim($_POST['tahun_ajaran']);
        $asisten_id = $_SESSION['user_id'];
        
        // Validasi
        if (empty($nama) || empty($kode) || empty($semester) || empty($tahun_ajaran)) {
            $message = "Semua field wajib diisi!";
        } else {
            // Cek apakah kode sudah ada
            $check_sql = "SELECT id FROM courses WHERE kode = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $kode);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $message = "Kode praktikum sudah digunakan!";
            } else {
                $insert_sql = "INSERT INTO courses (nama, deskripsi, kode, semester, tahun_ajaran, asisten_id) VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssssi", $nama, $deskripsi, $kode, $semester, $tahun_ajaran, $asisten_id);
                
                if ($insert_stmt->execute()) {
                    $message = "Mata praktikum berhasil ditambahkan!";
                    header("Location: courses.php?status=added");
                    exit();
                } else {
                    $message = "Gagal menambahkan mata praktikum.";
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['edit_course'])) {
        $course_id = (int)$_POST['course_id'];
        $nama = trim($_POST['nama']);
        $deskripsi = trim($_POST['deskripsi']);
        $kode = trim($_POST['kode']);
        $semester = trim($_POST['semester']);
        $tahun_ajaran = trim($_POST['tahun_ajaran']);
        
        // Validasi
        if (empty($nama) || empty($kode) || empty($semester) || empty($tahun_ajaran)) {
            $message = "Semua field wajib diisi!";
        } else {
            // Cek apakah kode sudah ada (kecuali course yang sedang diedit)
            $check_sql = "SELECT id FROM courses WHERE kode = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $kode, $course_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $message = "Kode praktikum sudah digunakan!";
            } else {
                $update_sql = "UPDATE courses SET nama = ?, deskripsi = ?, kode = ?, semester = ?, tahun_ajaran = ? WHERE id = ? AND asisten_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssii", $nama, $deskripsi, $kode, $semester, $tahun_ajaran, $course_id, $_SESSION['user_id']);
                
                if ($update_stmt->execute()) {
                    $message = "Mata praktikum berhasil diperbarui!";
                    header("Location: courses.php?status=updated");
                    exit();
                } else {
                    $message = "Gagal memperbarui mata praktikum.";
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['delete_course'])) {
        $course_id = (int)$_POST['course_id'];
        
        // Cek apakah course milik asisten ini
        $check_sql = "SELECT id FROM courses WHERE id = ? AND asisten_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $delete_sql = "DELETE FROM courses WHERE id = ? AND asisten_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
            
            if ($delete_stmt->execute()) {
                $message = "Mata praktikum berhasil dihapus!";
                header("Location: courses.php?status=deleted");
                exit();
            } else {
                $message = "Gagal menghapus mata praktikum.";
            }
            $delete_stmt->close();
        } else {
            $message = "Mata praktikum tidak ditemukan atau Anda tidak memiliki akses.";
        }
        $check_stmt->close();
    }
}

// Ambil semua course yang dimiliki asisten
$courses_sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'aktif') as jumlah_mahasiswa,
                (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) as jumlah_modul
                FROM courses c 
                WHERE c.asisten_id = ? 
                ORDER BY c.created_at DESC";
$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $_SESSION['user_id']);
$courses_stmt->execute();
$courses_stmt->store_result();

// Bind result variables
$courses_stmt->bind_result($course_id, $course_nama, $course_deskripsi, $course_kode, $course_semester, $course_tahun_ajaran, $course_asisten_id, $course_status, $course_created_at, $course_updated_at, $course_jumlah_mahasiswa, $course_jumlah_modul);

// Fetch all courses into array
$courses = [];
while ($courses_stmt->fetch()) {
    $courses[] = [
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
        'jumlah_mahasiswa' => $course_jumlah_mahasiswa,
        'jumlah_modul' => $course_jumlah_modul
    ];
}
$courses_stmt->close();

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
                        <a href="courses.php" class="bg-gray-900 text-white flex items-center px-4 py-3 rounded-md transition-colors duration-200">
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
                        case 'added': echo '<strong>Berhasil!</strong> Mata praktikum berhasil ditambahkan.'; break;
                        case 'updated': echo '<strong>Berhasil!</strong> Mata praktikum berhasil diperbarui.'; break;
                        case 'deleted': echo '<strong>Berhasil!</strong> Mata praktikum berhasil dihapus.'; break;
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

            <!-- Add Course Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Tambah Mata Praktikum Baru</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mata Praktikum</label>
                        <input type="text" name="nama" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode Praktikum</label>
                        <input type="text" name="kode" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                        <select name="semester" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Pilih Semester</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" placeholder="2024/2025" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <textarea name="deskripsi" rows="3" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Deskripsi mata praktikum..."></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" name="add_course" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Tambah Mata Praktikum
                        </button>
                    </div>
                </form>
            </div>

            <!-- Courses List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Daftar Mata Praktikum</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">Belum ada mata praktikum</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                <?php echo htmlspecialchars($course['kode']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($course['nama']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($course['deskripsi']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($course['semester']); ?> - <?php echo htmlspecialchars($course['tahun_ajaran']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $course['jumlah_mahasiswa']; ?> mahasiswa
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $course['jumlah_modul']; ?> modul
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $course['status'] == 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)" class="text-blue-600 hover:text-blue-900">Edit</button>
                                                <a href="modules.php?course_id=<?php echo $course['id']; ?>" class="text-green-600 hover:text-green-900">Modul</a>
                                                <form method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata praktikum ini?')">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <button type="submit" name="delete_course" class="text-red-600 hover:text-red-900">Hapus</button>
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
        </main>
    </div>

    <!-- Edit Course Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Mata Praktikum</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Mata Praktikum</label>
                            <input type="text" name="nama" id="edit_nama" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kode Praktikum</label>
                            <input type="text" name="kode" id="edit_kode" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                            <select name="semester" id="edit_semester" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                        <button type="submit" name="edit_course" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors duration-300">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editCourse(course) {
            document.getElementById('edit_course_id').value = course.id;
            document.getElementById('edit_nama').value = course.nama;
            document.getElementById('edit_kode').value = course.kode;
            document.getElementById('edit_semester').value = course.semester;
            document.getElementById('edit_tahun_ajaran').value = course.tahun_ajaran;
            document.getElementById('edit_deskripsi').value = course.deskripsi;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>
