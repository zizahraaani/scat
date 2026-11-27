<?php
// db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "gateway01.ap-northeast-1.prod.aws.tidbcloud.com"; 
$user = "36HKpCSA2LYYqwx.root"; 
$pass = "jyAp6bv95a2AhNWW"; 
$db   = "myapp";
$port = 4000; 

// Path ke file sertifikat CA yang sudah kamu unduh (isrgrootx1.pem)
$ca_cert_path = "C:/laragon/www/scat/isrgrootx1.pem"; 

$mysqli = mysqli_init();

// 1. Set CA path
$mysqli->ssl_set(NULL, NULL, $ca_cert_path, NULL, NULL); 

// 2. TAMBAHKAN BARIS INI: Matikan verifikasi peer name (khusus Windows)
$mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);

// 3. Lakukan koneksi
$mysqli->real_connect($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die("Gagal koneksi database: " . $mysqli->connect_error);
}

// Helper output aman
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>