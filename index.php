<?php
session_start();

// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'mahasiswa') {
        header('Location: mahasiswa/dashboard.php');
        exit();
    } elseif ($_SESSION['role'] === 'asisten') {
        header('Location: asisten/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang - Sistem Pengumpulan Tugas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-300 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-xl rounded-lg p-10 max-w-md w-full text-center">
        <h1 class="text-3xl font-bold mb-6 text-blue-800">Sistem Pengumpulan Tugas</h1>
        <p class="mb-8 text-gray-600">Silakan login atau daftar untuk melanjutkan.</p>
        <div class="flex flex-col gap-4">
            <a href="login.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg text-lg transition-colors duration-200">Login</a>
            <a href="register.php" class="w-full bg-gray-200 hover:bg-gray-300 text-blue-700 font-semibold py-3 rounded-lg text-lg transition-colors duration-200">Register</a>
        </div>
    </div>
</body>
</html> 