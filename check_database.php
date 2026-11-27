<?php
session_start();
require_once 'db.php';

echo "<h2>Struktur Database</h2>";

// Cek tabel insiden
echo "<h3>Tabel insiden:</h3>";
$result = $mysqli->query("DESCRIBE insiden");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}

// Cek tabel penyebab_langsung
echo "<h3>Tabel penyebab_langsung:</h3>";
$result = $mysqli->query("DESCRIBE penyebab_langsung");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}

// Cek tabel penyebab_dasar
echo "<h3>Tabel penyebab_dasar:</h3>";
$result = $mysqli->query("DESCRIBE penyebab_dasar");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}
?>