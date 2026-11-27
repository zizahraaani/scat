
<?php
// db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi database TiDB Cloud
$host = getenv('DB_HOST') ?: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: 'myapp';
$port = (int) (getenv('DB_PORT') ?: 4000);

// Lokasi sertifikat CA
$ca_cert_path = __DIR__ . '/isrgrootx1.pem';

// Inisialisasi MySQLi
$mysqli = mysqli_init();

if (!$mysqli) {
    die("Gagal menginisialisasi database.");
}

// Aktifkan SSL menggunakan sertifikat CA
$mysqli->ssl_set(
    NULL,
    NULL,
    $ca_cert_path,
    NULL,
    NULL
);

// Hubungkan ke TiDB Cloud
$connected = $mysqli->real_connect(
    $host,
    $user,
    $pass,
    $db,
    $port,
    NULL,
    MYSQLI_CLIENT_SSL
);

// Periksa koneksi
if (!$connected) {
    error_log("Database error: " . $mysqli->connect_error);
    die("Koneksi database gagal. Periksa konfigurasi database.");
}

// Atur karakter database
$mysqli->set_charset("utf8mb4");

// Helper output aman
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars(
            $str ?? '',
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
?>
