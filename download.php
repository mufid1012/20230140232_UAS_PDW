<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (empty($type) || $id == 0) {
    die("Parameter tidak valid");
}

if ($type == 'material') {
    // Download materi - hanya untuk mahasiswa yang terdaftar
    if ($_SESSION['role'] != 'mahasiswa') {
        die("Akses ditolak");
    }
    
    $sql = "SELECT m.* FROM materials m 
            JOIN modules mod ON m.module_id = mod.id 
            JOIN courses c ON mod.course_id = c.id 
            JOIN enrollments e ON c.id = e.course_id 
            WHERE m.id = ? AND e.student_id = ? AND e.status = 'aktif'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        die("File tidak ditemukan atau Anda tidak memiliki akses");
    }
    
    // Bind result variables
    $stmt->bind_result($material_id, $material_module_id, $material_nama_file, $material_nama_asli, $material_tipe_file, $material_ukuran_file, $material_path_file, $material_created_at, $material_updated_at);
    $stmt->fetch();
    
    $file_path = $material_path_file;
    
    if (!file_exists($file_path)) {
        die("File tidak ditemukan di server");
    }
    
    // Set headers untuk download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $material_nama_asli . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    $stmt->close();
    
} elseif ($type == 'submission') {
    // Download submission - untuk mahasiswa (file sendiri) atau asisten
    if ($_SESSION['role'] == 'mahasiswa') {
        // Mahasiswa hanya bisa download file sendiri
        $sql = "SELECT * FROM submissions WHERE id = ? AND student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $_SESSION['user_id']);
    } elseif ($_SESSION['role'] == 'asisten') {
        // Asisten bisa download semua submission
        $sql = "SELECT * FROM submissions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
    } else {
        die("Akses ditolak");
    }
    
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        die("File tidak ditemukan atau Anda tidak memiliki akses");
    }
    
    // Bind result variables
    $stmt->bind_result($submission_id, $submission_student_id, $submission_module_id, $submission_nama_file, $submission_nama_asli, $submission_tipe_file, $submission_ukuran_file, $submission_path_file, $submission_submitted_at, $submission_nilai, $submission_feedback, $submission_status, $submission_graded_at, $submission_komentar);
    $stmt->fetch();
    
    $file_path = $submission_path_file;
    
    if (!file_exists($file_path)) {
        die("File tidak ditemukan di server");
    }
    
    // Set headers untuk download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $submission_nama_asli . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    $stmt->close();
    
} else {
    die("Tipe download tidak valid");
}

$conn->close();
exit();
?> 