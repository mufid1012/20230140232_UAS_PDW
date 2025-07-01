<?php
session_start();
require_once '../config.php';

// Cek login dan role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $student_id = $_SESSION['user_id'];
    
    // Update status enrollment menjadi 'keluar'
    $sql = "UPDATE enrollments SET status = 'keluar' WHERE student_id = ? AND course_id = ? AND status = 'aktif'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $course_id);
    
    if ($stmt->execute()) {
        header("Location: my_courses.php?status=unenrolled");
    } else {
        header("Location: my_courses.php?error=unenroll_failed");
    }
    $stmt->close();
} else {
    header("Location: my_courses.php");
}

$conn->close();
exit();
?> 