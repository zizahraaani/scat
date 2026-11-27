<?php
session_start();

/* =========================
   VALIDASI AKSES
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID insiden tidak valid");
}

$insiden_id = (int) $_GET['id'];

require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php'; // Pastikan path ke autoload mPDF benar

/* =========================
   DATA INSIDEN
========================= */
$q = $mysqli->prepare("
    SELECT i.*, u.fullname, u.departemen 
    FROM insiden i 
    LEFT JOIN users u ON i.dibuat_oleh = u.id 
    WHERE i.id = ?
");
$q->bind_param("i", $insiden_id);
$q->execute();
$insiden = $q->get_result()->fetch_assoc();

if (!$insiden) {
    die("Data insiden tidak ditemukan");
}

/* =========================
   DATA IDENTITAS
========================= */
$identitas = null;
$q = $mysqli->prepare("
    SELECT entitas, cabang, proect 
    FROM identitas 
    WHERE insiden_id = ? LIMIT 1
");
$q->bind_param("i", $insiden_id);
$q->execute();
$r = $q->get_result();
if ($r->num_rows > 0) {
    $identitas = $r->fetch_assoc();
}

/* =========================
   DATA PENDUKUNG
========================= */
function ambilList($mysqli, $table, $field, $id) {
    $data = [];
    $q = $mysqli->prepare("SELECT $field FROM $table WHERE insiden_id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $data[] = $row[$field];
    }
    return $data;
}

// Data dari database
$jenis_kerugian = ambilList($mysqli, 'jenis_kerugian', 'jenis', $insiden_id);
$penyebab_langsung = ambilList($mysqli, 'penyebab_langsung', 'deskripsi', $insiden_id);
$jenis_kejadian = ambilList($mysqli, 'jenis_kejadian', 'deskripsi', $insiden_id);

// Data faktor analisis
$faktor_personil = [];
$faktor_pekerjaan = [];
$q = $mysqli->prepare("
    SELECT kategori, kode_faktor, deskripsi, tipe 
    FROM faktor_analisis 
    WHERE insiden_id = ?
    ORDER BY tipe, kategori, kode_faktor
");
$q->bind_param("i", $insiden_id);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) {
    if ($row['tipe'] == 'personil') {
        $faktor_personil[] = $row;
    } else {
        $faktor_pekerjaan[] = $row;
    }
}

/* =========================
   DATA KORBAN
========================= */
$korban_data = null;
$q = $mysqli->prepare("
    SELECT nama, jabatan, bagian_tubuh 
    FROM korban 
    WHERE insiden_id = ? LIMIT 1
");
$q->bind_param("i", $insiden_id);
$q->execute();
$r = $q->get_result();
if ($r->num_rows > 0) {
    $korban_data = $r->fetch_assoc();
}

/* =========================
   DATA SAKSI
========================= */
$saksi_list = ambilList($mysqli, 'saksi', 'nama_saksi', $insiden_id);

/* =========================
   DATA PENYEBAB DASAR (CAN)
========================= */
$can_data = [];
$q = $mysqli->prepare("
    SELECT kode, deskripsi, program_p, standar_s, kepatuhan_c 
    FROM penyebab_dasar 
    WHERE insiden_id = ?
    ORDER BY kode
");
$q->bind_param("i", $insiden_id);
$q->execute();
$r = $q->get_result();
while ($row = $r->fetch_assoc()) {
    $can_data[] = $row;
}

/* =========================
   DATA TINDAKAN PERBAIKAN
========================= */
$tindakan_perbaikan = [];
try {
    $q = $mysqli->prepare("
        SELECT 
            jenis_tindakan, 
            deskripsi, 
            COALESCE(rekomendasi, '') as rekomendasi, 
            target_selesai, 
            penanggungjawab, 
            COALESCE(status, 'Pending') as status 
        FROM tindakan_perbaikan 
        WHERE insiden_id = ?
        ORDER BY id ASC
    ");
    
    if ($q === false) {
        die("Error preparing statement: " . $mysqli->error);
    }
    
    $q->bind_param("i", $insiden_id);
    $q->execute();
    $r = $q->get_result();
    
    while ($row = $r->fetch_assoc()) {
        $tindakan_perbaikan[] = $row;
    }
} catch (Exception $e) {
    error_log("Error query tindakan_perbaikan: " . $e->getMessage());
    $tindakan_perbaikan = [];
}

/* =========================
   FUNGSI HELPER
========================= */
function formatRupiah($angka) {
    if (empty($angka) || $angka == 0) return '-';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/* =========================
   LOGO DAN HEADER
========================= */
$logo_html = '';
$logo_path = __DIR__ . '/logo.png';
if (file_exists($logo_path)) {
    // Untuk mPDF, gunakan base64 encoded image
    $logo_data = base64_encode(file_get_contents($logo_path));
    $logo_html = '<img src="data:image/png;base64,'.$logo_data.'" style="height:40px;">';
}

// Nama perusahaan dari identitas atau default
$nama_perusahaan = $identitas ? htmlspecialchars($identitas['entitas']) : 'PT Radiant Utama Interinsco, Tbk';

/* =========================
   MAPPING DATA
========================= */
$kantor_cabang = '';
if ($identitas) {
    $kantor_cabang .= htmlspecialchars($identitas['entitas'] ?? '');
    if (!empty($identitas['cabang'])) {
        $kantor_cabang .= ' / ' . htmlspecialchars($identitas['cabang']);
    }
    if (!empty($identitas['proect'])) {
        $kantor_cabang .= ' / ' . htmlspecialchars($identitas['proect']);
    }
} else {
    $kantor_cabang = '-';
}

$departemen = htmlspecialchars($insiden['departemen_insiden'] ?? '-');
$lokasi = htmlspecialchars($insiden['lokasi'] ?? '-');
$tgl_kejadian = !empty($insiden['tanggal']) ? date('d/m/Y', strtotime($insiden['tanggal'])) : '-';

// Format waktu kejadian
$waktu = '-';
if (!empty($insiden['waktu_kejadian']) && $insiden['waktu_kejadian'] != '00:00:00') {
    $waktu = date('H:i', strtotime($insiden['waktu_kejadian']));
}

$tgl_laporan = !empty($insiden['created_at']) ? date('d/m/Y', strtotime($insiden['created_at'])) : '-';

// Data dari form baru
$insiden_number = htmlspecialchars($insiden['insiden_number'] ?? 'INC-' . $insiden_id);
$insiden_type = htmlspecialchars($insiden['insiden_type'] ?? '-');
$nama_penyelidik = htmlspecialchars($insiden['penyelidik'] ?? '-');
$nama_manajer_departemen = htmlspecialchars($insiden['nama_manajer_departemen'] ?? '-');

// Data korban
$nama_korban = $korban_data ? htmlspecialchars($korban_data['nama'] ?? '-') : '-';
$jabatan_korban = $korban_data ? htmlspecialchars($korban_data['jabatan'] ?? '-') : '-';
$bagian_tubuh = $korban_data ? htmlspecialchars($korban_data['bagian_tubuh'] ?? '-') : '-';

$perkiraan_kerugian = formatRupiah($insiden['perkiraan_kerugian'] ?? 0);
$potensi_kerugian = formatRupiah($insiden['potensi_kerugian'] ?? 0);
$obyek_alat = !empty($jenis_kejadian) ? htmlspecialchars(implode(', ', $jenis_kejadian)) : '-';
$saksi = !empty($saksi_list) ? htmlspecialchars(implode(', ', $saksi_list)) : '-';
$risiko = htmlspecialchars($insiden['tingkat_risiko'] ?? '-');

// Deskripsi kejadian
$deskripsi_kejadian = nl2br(htmlspecialchars($insiden['deskripsi_kejadian'] ?? '-'));

// User yang membuat laporan
$user_pembuat = htmlspecialchars($insiden['fullname'] ?? '-');

// Judul insiden untuk header
$judul_insiden = htmlspecialchars($insiden['judul_insiden'] ?? 'Laporan Investigasi Insiden');

/* =========================
   HTML CONTENT
========================= */
$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Laporan Investigasi Insiden SCAT - ' . $judul_insiden . '</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: Arial, sans-serif;
        font-size: 7pt;
        line-height: 1.4;
        color: #000000;
        width: 100%;
        padding: 0;
        margin: 0;
    }
    
    .page-wrapper {
        width: 100%;
        max-width: 170mm;
        margin: 0 auto;
    }
    /* === TINDAKAN PERBAIKAN (STABIL MPDF) === */
.tindakan-wrapper {
    margin-top: 10px;
}
    .tindakan-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 7pt;
}

.tindakan-grid th,
.tindakan-grid td {
    border: 0.5px solid #000;
    padding: 4px 5px;
    vertical-align: top;
    word-wrap: break-word;
}

.tindakan-grid th {
    font-weight: bold;
    text-align: center;
}

.tindakan-center {
    text-align: center;
}


.tindakan-item {
    border: 1px solid #000;
    padding: 10px;
    margin-bottom: 10px;
    font-size: 7pt;
    page-break-inside: avoid; /* PENTING: per item */
}

.tindakan-header {
    font-weight: bold;
    font-size: 7pt;
    margin-bottom: 6px;
}

.tindakan-meta {
    width: 100%;
    margin-bottom: 8px;
}

.tindakan-meta div {
    margin-bottom: 3px;
}

.tindakan-label {
    font-weight: bold;
}

.tindakan-section-title {
    font-weight: bold;
    margin-top: 8px;
    margin-bottom: 3px;
    border-bottom: 0.5px solid #aaa;
}

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 5px 0;
        font-size: 7pt;
        table-layout: fixed;
        word-wrap: break-word;
        line-height: 1.4;
    }
    
    td {
        border: 0.3px solid #000;
        padding: 4px 5px;
        vertical-align: top;
        word-break: break-word;
    }
    
    .table-header {
        background-color: #ffffff !important;
        font-weight: bold;
        text-align: center;
        padding: 5px 4px !important;
        font-size: 7pt !important;
        line-height: 1.2;
        border: 0.3px solid #000;
        color: #000000;
    }
    
    .table-header-left {
        background-color: #ffffff !important;
        font-weight: bold;
        text-align: left;
        padding: 5px 6px !important;
        font-size: 7pt !important;
        line-height: 1.2;
        border: 0.3px solid #000;
        color: #000000;
    }
    
    .table-content {
        font-size: 7pt !important;
        line-height: 1.4;
        font-weight: normal !important;
        color: #000000;
    }
    
    .table-content-bold {
        font-size: 7pt !important;
        line-height: 1.4;
        font-weight: bold !important;
        color: #000000;
    }
    
    .company-name {
        font-size: 12pt;
        font-weight: bold;
        margin: 0;
        line-height: 1.2;
        color: #000000;
    }
    
    .report-title {
        font-size: 8pt;
        font-weight: bold;
        margin: 0;
        line-height: 1.2;
        color: #000000;
    }
    
    .incident-title {
        font-size: 7pt;
        font-weight: bold;
        margin: 0;
        color: #000000;
        line-height: 1.2;
    }
    
    .text-cell {
        padding: 4px 5px !important;
        min-height: 22px;
    }
    
    .text-cell-large {
        padding: 5px 6px !important;
        min-height: 35px;
    }
    
    .text-cell-xlarge {
        padding: 6px 7px !important;
        min-height: 45px;
    }
    
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    
    .bold { font-weight: bold; }
    .normal { font-weight: normal; }
    .mt-0 { margin-top: 0px; }
    .mt-1 { margin-top: 5px; }
    .mt-2 { margin-top: 10px; }
    .mb-0 { margin-bottom: 0px; }
    .mb-1 { margin-bottom: 5px; }
    
    .footer {
        font-size: 7pt;
        color: #000000;
        text-align: left;
        margin-top: 10px;
        padding-top: 4px;
        border-top: 0.5px dashed #999;
        font-weight: normal;
        line-height: 1.4;
    }
    
    .simple-list {
        margin: 0;
        padding: 0;
        list-style-type: none;
        font-size: 7pt;
    }
    
    .simple-list li {
        margin-bottom: 2px;
        padding-left: 0;
        font-weight: normal;
        color: #000000;
    }
    
    .compact {
        margin: 0;
        padding: 0;
        font-size: 7pt;
        width: 100%;
        line-height: 1.4;
    }
    
    .compact td {
        padding: 3px 4px;
        font-weight: normal;
        color: #000000;
    }
    
    .tindakan-block {
        border: 1px solid #000;
        margin-top: 10px;
        margin-bottom: 15px;
        font-size: 7pt;
        page-break-inside: avoid;
    }
    
    .tindakan-header {
        background: #f0f0f0;
        text-align: center;
        font-weight: bold;
        padding: 6px;
        border-bottom: 1px solid #000;
    }
    
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 7pt;
    }
    
    .meta-table td {
        padding: 4px 6px;
        border: 1px solid #000;
    }
    /* === TABEL TINDAKAN PERBAIKAN – CORPORATE STYLE === */

.tindakan-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 7pt;
    margin-bottom: 8px;
}

.tindakan-table col.label {
    width: 40mm;
}

.tindakan-table col.value {
    width: auto;
}

.tindakan-table td {
    border: 0.4px solid #000;
    padding: 4px 6px;
    vertical-align: top;
    word-wrap: break-word;
}

.tindakan-title {
    background-color: #e6e6e6;
    font-weight: bold;
    text-align: left;
    padding: 6px;
    border: 0.6px solid #000;
}

.tindakan-label {
    font-weight: bold;
    background-color: #f7f7f7;
}

.tindakan-content {
    font-weight: normal;
    text-align: justify;
}

.tindakan-block {
    margin-top: 6px;
    page-break-inside: avoid;
}

    .tindakan-text-block {
        padding: 8px;
        line-height: 1.4;
    }
    
    .tindakan-text-block p {
        margin: 5px 0;
        text-align: justify;
    }
    
    .tindakan-text-block strong {
        display: block;
        margin-top: 8px;
        margin-bottom: 3px;
    }
        .table-container {
    width: 100%;
}

.tindakan-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 7pt;
}

    
    .page-break {
        page-break-before: always;
    }
    
    .keep-together {
        page-break-inside: avoid;
    }
    
    .allow-break {
        page-break-inside: auto;
    }
    
    .content-wrap {
        white-space: pre-wrap;
        word-wrap: break-word;
        font-weight: normal;
        color: #000000;
    }
        .tindakan-grid td {
    border: 0.5px solid #000;
    padding: 4px 5px;
    vertical-align: top;
    word-wrap: break-word;
    text-align: justify;        /* INI KUNCINYA */
}
    /* === TABEL TINDAKAN PERBAIKAN - FIXED WIDTH === */
.tindakan-wrapper {
    margin-top: 10px;
    page-break-inside: avoid;
}

.tindakan-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 7pt;
    margin-bottom: 8px;
    table-layout: fixed;
}

/* DEFINE CLEAR COLUMN WIDTHS */
.tindakan-grid col:nth-child(1) { width: 15mm; }  /* No */
.tindakan-grid col:nth-child(2) { width: 85mm; }  /* Tindakan */
.tindakan-grid col:nth-child(3) { width: 35mm; }  /* PIC */
.tindakan-grid col:nth-child(4) { width: 35mm; }  /* Target & Status */

.tindakan-grid th {
    border: 0.5px solid #000;
    padding: 4px 5px;
    background-color: #f0f0f0;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    height: 25px;
}

.tindakan-grid td {
    border: 0.5px solid #000;
    padding: 4px 5px;
    vertical-align: top;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Specific column alignments */
.tindakan-grid td:nth-child(1) { text-align: center; }
.tindakan-grid td:nth-child(3) { text-align: left; }
.tindakan-grid td:nth-child(4) { text-align: left; }

/* Ensure content fits */
.tindakan-grid td div {
    margin-bottom: 2px;
    line-height: 1.3;
}

</style>
</head>
<body>
<div class="page-wrapper">';

// HEADER
$html .= '
<table style="margin-top: 1cm;" width="100%" cellspacing="0" cellpadding="0" style="border:none; margin-bottom:8px;">
    <tr>
        <td width="15%" style="border:none; vertical-align:middle; text-align:left;">
            ' . $logo_html . '
        </td>
        <td width="85%" style="border:none; vertical-align:middle; text-align:center;">
           <div class="company-name">LEMBAR LAPORAN PENYELIDIKAN ACCIDENT / INCIDENT </div>
            <div class="report-title">' . $nama_perusahaan . '</div>
            <div class="incident-title">' . $judul_insiden . '</div>
        </td>
    </tr>
</table>';

/* ==============================
   TABEL 1: IDENTIFIKASI
============================= */
$html .= '<div class="table-container">
    <table class="keep-together">
        <thead class="repeat-header">
            <tr>
                <td class="table-header" style="width: 20%;">NO INCIDEN</td>
                <td class="table-header" style="width: 20%;">KANTOR/CABANG/<br>PROJECT</td>
                <td class="table-header" style="width: 20%;">DEPARTEMEN<br>TERKAIT</td>
                <td class="table-header" style="width: 20%;">PENYELIDIK</td>
                <td class="table-header" style="width: 20%;">INCIDENT<br>TYPE</td>
            </tr>
        </thead>
        <tbody>
            <tr class="table-content">
                <td class="text-center table-content-bold">' . $insiden_number . '</td>
                <td class="text-cell">' . $kantor_cabang . '</td>
                <td class="text-cell">' . $departemen . '</td>
                <td class="text-cell">' . $nama_penyelidik . '</td>
                <td class="text-center">' . $insiden_type . '</td>
            </tr>
        </tbody>
    </table>
</div>';

/* ==============================
   TABEL 2: WAKTU & LOKASI
============================= */
$html .= '<div class="table-container mt-1">
    <table class="keep-together">
        <thead class="repeat-header">
            <tr>
                <td class="table-header" style="width: 30%;">LOKASI KEJADIAN</td>
                <td class="table-header" style="width: 20%;">TANGGAL KEJADIAN</td>
                <td class="table-header" style="width: 15%;">WAKTU KEJADIAN</td>
                <td class="table-header" style="width: 20%;">DIBUAT OLEH</td>
                <td class="table-header" style="width: 15%;">TANGGAL LAPORAN</td>
            </tr>
        </thead>
        <tbody>
            <tr class="table-content">
                <td class="text-cell">' . $lokasi . '</td>
                <td class="text-center">' . $tgl_kejadian . '</td>
                <td class="text-center">' . ($waktu ?: '-') . '</td>
                <td class="text-cell">' . $user_pembuat . '</td>
                <td class="text-center">' . $tgl_laporan . '</td>
            </tr>
        </tbody>
    </table>
</div>';

/* ==============================
   TABEL 3: STATUS INSIDEN
============================= */
$html .= '<div class="table-container mt-1">
    <table class="keep-together">
        <tr>
            <td class="table-header" style="width: 20%;">TINGKAT RISIKO</td>
        </tr>
        <tr class="table-content">
            <td class="text-center table-content-bold">' . $risiko . '</td>
        </tr>
    </table>
</div>';

/* ==============================
   TABEL 4: KORBAN & SAKSI
============================= */
$html .= '<div class="table-container mt-1">
    <table class="keep-together">
        <thead class="repeat-header">
            <tr>
                <td class="table-header" style="width: 20%;">NAMA KORBAN</td>
                <td class="table-header" style="width: 20%;">JABATAN</td>
                <td class="table-header" style="width: 20%;">BAGIAN TUBUH</td>
                <td class="table-header" style="width: 40%;">SAKSI YANG MELIHAT</td>
            </tr>
        </thead>
        <tbody>
            <tr class="table-content">
                <td class="text-cell">' . $nama_korban . '</td>
                <td class="text-cell">' . $jabatan_korban . '</td>
                <td class="text-cell">' . $bagian_tubuh . '</td>
                <td class="text-cell">' . $saksi . '</td>
            </tr>
        </tbody>
    </table>
</div>';

/* ==============================
   TABEL 5: KERUGIAN
============================= */
$html .= '<div class="table-container mt-1">
    <table class="keep-together">
        <thead class="repeat-header">
            <tr>
                <td class="table-header" style="width: 25%;">PERKIRAAN KERUGIAN</td>
                <td class="table-header" style="width: 25%;">POTENSI KERUGIAN</td>
                <td class="table-header" style="width: 50%;">JENIS KERUGIAN</td>
            </tr>
        </thead>
        <tbody>
            <tr class="table-content">
                <td class="text-center">' . $perkiraan_kerugian . '</td>
                <td class="text-center">' . $potensi_kerugian . '</td>
                <td class="text-cell">' . (!empty($jenis_kerugian) ? htmlspecialchars(implode(', ', $jenis_kerugian)) : '-') . '</td>
            </tr>
        </tbody>
    </table>
</div>';

/* ==============================
   TABEL 6: DESKRIPSI KEJADIAN
============================= */
$html .= '<div class="table-container mt-1 allow-break">
    <table class="allow-break" style="width: 100%;">
        <tr>
            <td class="table-header-left">DESKRIPSI KEJADIAN</td>
        </tr>
        <tr>
            <td class="text-cell-xlarge" style="text-align: justify;">
                ' . $deskripsi_kejadian . '
            </td>
        </tr>
    </table>
</div>';

/* ==============================
   TABEL 7: JENIS KEJADIAN
============================= */
if (!empty($jenis_kejadian)) {
    $html .= '<div class="table-container mt-1 allow-break">
        <table class="allow-break">
            <tr><td class="table-header-left">JENIS KONTAK/KEGIATAN</td></tr>
            <tr class="table-content"><td class="text-cell-large content-wrap">';
    $html .= implode(', ', array_map('htmlspecialchars', $jenis_kejadian));
    $html .= '</td></tr>
        </table>
    </div>';
}

/* ==============================
   TABEL 8: PENYEBAB LANGSUNG
============================= */
$html .= '<div class="table-container mt-1 allow-break">
    <table class="allow-break">
        <tr><td class="table-header-left">PENYEBAB LANGSUNG (IC\'s)</td></tr>
        <tr class="table-content"><td class="text-cell-large content-wrap">';
if (!empty($penyebab_langsung)) {
    $html .= implode(', ', array_map('htmlspecialchars', $penyebab_langsung));
} else {
    $html .= '-';
}
$html .= '</td></tr>
    </table>
</div>';

/* ==============================
   TABEL 9: FAKTOR DASAR
============================= */
$html .= '<div style="page-break-before: always; margin-top: 1cm;">
    <div class="table-container mt-1 allow-break">
        <table class="allow-break">
            <tr><td class="table-header" colspan="2">PENYEBAB DASAR / BASIC CAUSE (BC\'s)</td></tr>
            <tr class="table-content">
                <td class="text-cell-large" style="width: 50%;">
                    <div class="bold mb-0">FAKTOR PERSONIL:</div>';

if (!empty($faktor_personil)) {
    $items = [];
    $last_category = '';
    foreach ($faktor_personil as $faktor) {
        if ($last_category != $faktor['kategori']) {
            $items[] = '<div class="bold mt-1">' . htmlspecialchars($faktor['kategori']) . ':</div>';
            $last_category = $faktor['kategori'];
        }
        $items[] = '<div class="normal">' . htmlspecialchars($faktor['kode_faktor'] . ' - ' . $faktor['deskripsi']) . '</div>';
    }
    $html .= implode('', $items);
} else {
    $html .= '-';
}

$html .= '</td>
                <td class="text-cell-large" style="width: 50%;">
                    <div class="bold mb-0">FAKTOR PEKERJAAN:</div>';

if (!empty($faktor_pekerjaan)) {
    $items = [];
    $last_category = '';
    foreach ($faktor_pekerjaan as $faktor) {
        if ($last_category != $faktor['kategori']) {
            $items[] = '<div class="bold mt-1">' . htmlspecialchars($faktor['kategori']) . ':</div>';
            $last_category = $faktor['kategori'];
        }
        $items[] = '<div class="normal">' . htmlspecialchars($faktor['kode_faktor'] . ' - ' . $faktor['deskripsi']) . '</div>';
    }
    $html .= implode('', $items);
} else {
    $html .= '-';
}

$html .= '</td>
            </tr>
        </table>
    </div>
</div>';

/* ==============================
   TABEL 10: CONTROL ACTION NEEDS (CAN)
============================= */
$can_terfilter = [];
foreach ($can_data as $item) {
    if (!empty($item['program_p']) || !empty($item['standar_s']) || !empty($item['kepatuhan_c'])) {
        $can_terfilter[] = $item;
    }
}

if (!empty($can_terfilter)) {
    $html .= '<div class="table-container mt-1 allow-break">
        <table class="allow-break">
            <tr><td class="table-header-left">CONTROL ACTION NEEDS (CAN)</td></tr>
            <tr class="table-content"><td class="text-cell-xlarge">
                <table class="compact" border="1" width="100%">';
    
    foreach ($can_terfilter as $item) {
        $psc_display = [];
        if ($item['program_p'] == 'Y' || $item['program_p'] == 'N') {
            $psc_display[] = 'P:' . $item['program_p'];
        }
        if ($item['standar_s'] == 'Y' || $item['standar_s'] == 'N') {
            $psc_display[] = 'S:' . $item['standar_s'];
        }
        if ($item['kepatuhan_c'] == 'Y' || $item['kepatuhan_c'] == 'N') {
            $psc_display[] = 'C:' . $item['kepatuhan_c'];
        }
        
        $html .= '<tr>
            <td style="width: 10%;"><span class="bold">' . htmlspecialchars($item['kode']) . '</span></td>
            <td style="width: 70%;">' . htmlspecialchars($item['deskripsi']) . '</td>
            <td style="width: 20%; text-align: center;">' . (!empty($psc_display) ? implode(' | ', $psc_display) : '') . '</td>
        </tr>';
    }
    $html .= '</table>
            </td></tr>
        </table>
    </div>';
}

/* ==============================
   TABEL 11: TINDAKAN PERBAIKAN DAN PENCEGAHAN
============================= */

/* ==============================
   TABEL 11: TINDAKAN PERBAIKAN DAN PENCEGAHAN
============================= */

$html .= '
<div class="table-container">
<table class="tindakan-grid">
    <colgroup>
        <col style="width:15mm;">
        <col style="width:85mm;">
        <col style="width:35mm;">
        <col style="width:35mm;">
    </colgroup>

    <tr>
        <th>No</th>
        <th>Tindakan Perbaikan / Pencegahan</th>
        <th>PIC</th>
        <th>Target & Status</th>
    </tr>';

if (!empty($tindakan_perbaikan)) {
    foreach ($tindakan_perbaikan as $i => $t) {
        $target = !empty($t['target_selesai']) 
            ? date('d/m/Y', strtotime($t['target_selesai'])) 
            : '-';
        
        $jenis_tindakan = htmlspecialchars($t['jenis_tindakan'] ?? '-');
        $rekomendasi = !empty($t['rekomendasi']) 
            ? nl2br(htmlspecialchars($t['rekomendasi'])) 
            : '-';
        $deskripsi = !empty($t['deskripsi']) 
            ? nl2br(htmlspecialchars($t['deskripsi'])) 
            : '-';
        $pic = htmlspecialchars($t['penanggungjawab'] ?? '-');
        $status = htmlspecialchars($t['status'] ?? 'Pending');

        $html .= '
        <tr>
            <td style="text-align:center; vertical-align:middle;">' . ($i + 1) . '</td>
            <td>
                <div><strong>Jenis:</strong> ' . $jenis_tindakan . '</div>
                <div><strong>Rekomendasi:</strong> ' . $rekomendasi . '</div>
                <div><strong>Tindakan:</strong> ' . $deskripsi . '</div>
            </td>
            <td>' . $pic . '</td>
            <td>
                <div><strong>Target:</strong> ' . $target . '</div>
                <div><strong>Status:</strong> ' . $status . '</div>
            </td>
        </tr>';
    }
} else {
    $html .= '
    <tr>
        <td colspan="4" style="text-align:center; padding:20px;">
            Tidak ada data tindakan perbaikan
        </td>
    </tr>';
}

$html .= '</table></div>';


/* ==============================
   TABEL 12: TANDA TANGAN
============================= */
$html .= '
    <div class="table-container mt-2 keep-together">
        <table class="keep-together" style="width: 100%;" border="1">
            <thead>
                <tr>
                    <th class="table-header text-center" style="width: 50%;">PENYELIDIK</th>
                    <th class="table-header text-center" style="width: 50%;">MENGETAHUI<br>MANAJER DEPARTEMEN</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="height: 120px; vertical-align: top; padding: 10px; text-align: center;">
                        <div style="margin-bottom: 5px;">
                            <div class="bold" style="font-size: 8pt;">' . $nama_penyelidik . '</div>
                            <div style="font-size: 7pt;">Penyelidik</div>
                        </div>
                        <div style="height: 50px;"></div>
                        <div style="border-top: 0.5px solid #000; width: 80%; margin: 0 auto;"></div>
                        <div style="font-size: 6pt; margin-top: 3px;">
                            Tanggal: ' . $tgl_laporan . '
                        </div>
                    </td>
                    
                    <td style="height: 120px; vertical-align: top; padding: 10px; text-align: center;">
                        <div style="margin-bottom: 5px;">
                            <div class="bold" style="font-size: 8pt;">' . $nama_manajer_departemen . '</div>
                            <div style="font-size: 7pt;">Manajer Departemen</div>
                        </div>
                        <div style="height: 50px;"></div>
                        <div style="border-top: 0.5px solid #000; width: 80%; margin: 0 auto;"></div>
                        <div style="font-size: 6pt; margin-top: 3px;">
                            Tanggal: ' . $tgl_laporan . '
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    
</div>';

/* ==============================
   FOOTER
============================= */
$html .= '<div class="footer">
    P-QSHE-007-S3 Rev 01 / Tanggal Efektif : 29 Juli 2024
</div>';

$html .= '</div></body></html>';

/* =========================
   GENERATE PDF DENGAN MPDF
========================= */
try {
    // Buat instance mPDF dengan konfigurasi untuk A4
    
      $mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'margin_top' => 20,
    'margin_right' => 15,
    'margin_bottom' => 20,
    'margin_left' => 15,
    'margin_header' => 5,
    'margin_footer' => 10,
    'default_font' => 'arial',

        'tempDir' => __DIR__ . '/tmp' // Pastikan folder tmp ada dan writable
    ]);
    
    // Set metadata
    $mpdf->SetTitle('Laporan Investigasi Insiden SCAT - ' . $judul_insiden);
    $mpdf->SetAuthor($nama_perusahaan);
    
    // Write HTML content
    $mpdf->WriteHTML($html);
    
    // Output PDF
    $filename = 'LAPORAN_SCAT_' . $insiden_number . '_' . date('Ymd_His') . '.pdf';
    
    // Output ke browser
    $mpdf->Output($filename, 'D'); // 'D' untuk download, 'I' untuk inline
    
} catch (\Mpdf\MpdfException $e) {
    die('Error generating PDF: ' . $e->getMessage());
}

$mysqli->close();
?>