<?php
// db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = getenv('DB_HOST') ?: "gateway01.ap-northeast-1.prod.aws.tidbcloud.com";
$user = getenv('DB_USER') ?: "36HKpCSA2LYYqwx.root";
$pass = getenv('DB_PASS'); 
$db   = getenv('DB_NAME') ?: "myapp";
$port = 4000;

// Path ke file CA certificate di dalam container Linux (Debian/Alpine)
$ca_cert = '/etc/ssl/certs/ca-certificates.crt';

$mysqli = mysqli_init();

// Beritahu PHP untuk menggunakan file CA ini
$mysqli->ssl_set(NULL, NULL, $ca_cert, NULL, NULL);

// Opsional: Matikan verifikasi peer name (kadang diperlukan di Docker)
$mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

// Lakukan koneksi
$mysqli->real_connect($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die("Gagal koneksi database: " . $mysqli->connect_error);
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>