<?php
// setup_database.php - JALANKAN SEKALI SAJA, HAPUS SETELAH BERHASIL
require_once 'db.php';

echo "<h2>Setup Database SCAT</h2>";
echo "<p>Membuat tabel dan data awal...</p>";

// 1. Buat tabel users dengan kolom role
$sql1 = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100),
    role VARCHAR(20) DEFAULT 'petugas',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($mysqli->query($sql1)) {
    echo "✓ Tabel 'users' berhasil dibuat<br>";
} else {
    echo "✗ Error users: " . $mysqli->error . "<br>";
}

// 2. Buat user admin jika belum ada
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$check = $mysqli->query("SELECT id FROM users WHERE username = '$username'");
if ($check->num_rows == 0) {
    $sql2 = "INSERT INTO users (username, password, fullname, role) 
             VALUES ('$username', '$hashed_password', 'Administrator', 'admin')";
    
    if ($mysqli->query($sql2)) {
        echo "✓ User admin berhasil dibuat<br>";
        echo "&nbsp;&nbsp;Username: <strong>$username</strong><br>";
        echo "&nbsp;&nbsp;Password: <strong>$password</strong><br>";
    } else {
        echo "✗ Error insert admin: " . $mysqli->error . "<br>";
    }
} else {
    echo "✓ User admin sudah ada<br>";
}

// 3. Buat tabel insiden
$sql3 = "CREATE TABLE IF NOT EXISTS insiden (
    id INT PRIMARY KEY AUTO_INCREMENT,
    judul_insiden VARCHAR(255) NOT NULL,
    tanggal DATE NOT NULL,
    lokasi VARCHAR(255),
    dibuat_oleh INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($mysqli->query($sql3)) {
    echo "✓ Tabel 'insiden' berhasil dibuat<br>";
} else {
    echo "✗ Error insiden: " . $mysqli->error . "<br>";
}

// 4. Buat tabel penyebab_langsung
$sql4 = "CREATE TABLE IF NOT EXISTS penyebab_langsung (
    id INT PRIMARY KEY AUTO_INCREMENT,
    insiden_id INT,
    kategori VARCHAR(100),
    deskripsi TEXT,
    FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
)";

if ($mysqli->query($sql4)) {
    echo "✓ Tabel 'penyebab_langsung' berhasil dibuat<br>";
} else {
    echo "✗ Error penyebab_langsung: " . $mysqli->error . "<br>";
}

// 5. Tambahkan data dummy untuk testing
$sql5 = "INSERT INTO insiden (judul_insiden, tanggal, lokasi, dibuat_oleh) VALUES
    ('Kecelakaan forklift', '2024-01-15', 'Gudang A', 1),
    ('Kebocoran bahan kimia', '2024-01-20', 'Lab Kimia', 1)
    ON DUPLICATE KEY UPDATE judul_insiden=judul_insiden";

if ($mysqli->query($sql5)) {
    echo "✓ Data dummy insiden ditambahkan<br>";
}

$sql6 = "INSERT INTO penyebab_langsung (insiden_id, kategori, deskripsi) VALUES
    (1, 'Human Error', 'Operator tidak hati-hati'),
    (1, 'Equipment Failure', 'Rem forklift blong'),
    (2, 'Prosedur', 'Tidak mengikuti SOP handling bahan kimia')
    ON DUPLICATE KEY UPDATE kategori=kategori";

if ($mysqli->query($sql6)) {
    echo "✓ Data dummy penyebab langsung ditambahkan<br>";
}

echo "<hr>";
echo "<h3 style='color: green'>Setup selesai!</h3>";
echo "<p>Silahkan:</p>";
echo "<ol>";
echo "<li><a href='login.php'>Login ke sistem</a> dengan username: <strong>admin</strong>, password: <strong>admin123</strong></li>";
echo "<li>HAPUS file ini (setup_database.php) untuk keamanan</li>";
echo "</ol>";
echo "<p><strong>Note:</strong> Ubah password setelah login pertama!</p>";
?>