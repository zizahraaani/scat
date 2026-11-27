<?php
// delete_insiden.php - Contoh implementasi
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Pastikan metode DELETE atau parameter ada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Mulai transaksi jika ada relasi foreign key
    $mysqli->begin_transaction();
    
    try {
        // Hapus data terkait terlebih dahulu (jika ada foreign key constraints)
        $mysqli->query("DELETE FROM penyebab_langsung WHERE insiden_id = $id");
        $mysqli->query("DELETE FROM penyebab_dasar WHERE insiden_id = $id");
        $mysqli->query("DELETE FROM tindakan_perbaikan WHERE insiden_id = $id");
        
        // Hapus insiden utama
        $result = $mysqli->query("DELETE FROM insiden WHERE id = $id");
        
        if ($result && $mysqli->affected_rows > 0) {
            $mysqli->commit();
            $_SESSION['success'] = "Insiden berhasil dihapus";
        } else {
            $mysqli->rollback();
            $_SESSION['error'] = "Gagal menghapus insiden";
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: data_scat.php");
    exit;
}
?>