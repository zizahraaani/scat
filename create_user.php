<?php
// create_user.php - HAPUS FILE INI SETELAH DIGUNAKAN!
require_once 'db.php';

$username = 'admin';
$password = 'admin123'; // Ganti dengan password yang diinginkan
$fullname = 'Administrator';
$role = 'admin';

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $mysqli->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $username, $hashed_password, $fullname, $role);

if ($stmt->execute()) {
    echo "User berhasil dibuat!<br>";
    echo "Username: $username<br>";
    echo "Password: $password<br>";
    echo "<strong>HAPUS FILE INI SETELAH DIGUNAKAN!</strong>";
} else {
    echo "Error: " . $mysqli->error;
}
$stmt->close();
$mysqli->close();
?>