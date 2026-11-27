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

$ca_cert = '/etc/ssl/certs/ca-certificates.crt';

$mysqli = mysqli_init();
$mysqli->ssl_set(NULL, NULL, $ca_cert, NULL, NULL);
$mysqli->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
$mysqli->real_connect($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die("Gagal koneksi database: " . $mysqli->connect_error);
}

?>