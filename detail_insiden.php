<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$insiden_id = intval($_GET['id']);
require_once 'db.php';

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka) {
    if (empty($angka) || $angka == 0) return '-';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ================= AMBIL DATA =================
$insiden = null;
$stmt = $mysqli->prepare("SELECT i.*, u.username, u.fullname, u.email FROM insiden i LEFT JOIN users u ON i.dibuat_oleh = u.id WHERE i.id = ?");
if ($stmt) {
    $stmt->bind_param('i', $insiden_id);
    if ($stmt->execute()) {
        $insiden = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
}

if (!$insiden) {
    header("Location: dashboard.php");
    exit;
}

// Ambil data pendukung
$identitas = $mysqli->query("SELECT * FROM identitas WHERE insiden_id = $insiden_id LIMIT 1")->fetch_assoc();
$korban = $mysqli->query("SELECT * FROM korban WHERE insiden_id = $insiden_id LIMIT 1")->fetch_assoc();

$saksi_list = [];
$res_saksi = $mysqli->query("SELECT nama_saksi FROM saksi WHERE insiden_id = $insiden_id");
while ($row = $res_saksi->fetch_assoc()) { $saksi_list[] = $row['nama_saksi']; }

$jenis_kerugian = [];
$res_kerugian = $mysqli->query("SELECT jenis FROM jenis_kerugian WHERE insiden_id = $insiden_id");
while ($row = $res_kerugian->fetch_assoc()) { $jenis_kerugian[] = $row['jenis']; }

$jenis_kejadian_data = [];
$res_kejadian = $mysqli->query("SELECT deskripsi FROM jenis_kejadian WHERE insiden_id = $insiden_id");
while ($row = $res_kejadian->fetch_assoc()) { $jenis_kejadian_data[] = $row['deskripsi']; }

$penyebab_tindakan = [];
$res_penyebab = $mysqli->query("SELECT deskripsi FROM penyebab_langsung WHERE insiden_id = $insiden_id AND tipe = 'tindakan'");
while ($row = $res_penyebab->fetch_assoc()) { $penyebab_tindakan[] = $row['deskripsi']; }

$faktor_personil = [];
$faktor_pekerjaan = [];
$res_faktor = $mysqli->query("SELECT * FROM faktor_analisis WHERE insiden_id = $insiden_id ORDER BY id");
while ($row = $res_faktor->fetch_assoc()) {
    if (strtolower(trim($row['tipe'] ?? '')) == 'pekerjaan') {
        $faktor_pekerjaan[] = $row;
    } else {
        $faktor_personil[] = $row;
    }
}

$penyebab_dasar_can = [];
$res_can = $mysqli->query("SELECT * FROM penyebab_dasar WHERE insiden_id = $insiden_id ORDER BY id");
while ($row = $res_can->fetch_assoc()) { $penyebab_dasar_can[] = $row; }

$tindakan_perbaikan = [];
$res_tindakan = $mysqli->query("SELECT * FROM tindakan_perbaikan WHERE insiden_id = $insiden_id ORDER BY id");
while ($row = $res_tindakan->fetch_assoc()) { $tindakan_perbaikan[] = $row; }

$tindakan_files = [];
foreach ($tindakan_perbaikan as $tindakan) {
    $t_id = $tindakan['id'];
    $res_files = $mysqli->query("SELECT * FROM tindakan_files WHERE tindakan_id = $t_id");
    $files = [];
    while ($f = $res_files->fetch_assoc()) { $files[] = $f; }
    $tindakan_files[$t_id] = $files;
}

// Statistik CAN
$total_p = 0; $total_s = 0; $total_c = 0;
foreach ($penyebab_dasar_can as $item) {
    if (($item['program_p'] ?? '') == 'Y') $total_p++;
    if (($item['standar_s'] ?? '') == 'Y') $total_s++;
    if (($item['kepatuhan_c'] ?? '') == 'Y') $total_c++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Insiden - <?php echo e($insiden['judul_insiden']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #f7f9fc;
            --card-bg: #ffffff;
            --text-main: #2d3748;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --primary: #3182ce;
            --primary-light: #ebf8ff;
            --danger: #e53e3e;
            --danger-light: #fff5f5;
            --success: #38a169;
            --success-light: #f0fff4;
            --warning: #d69e2e;
            --warning-light: #fffff0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding: 40px 20px;
            font-size: 14px;
        }

        .container {
            max-width: 1050px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            padding: 45px;
        }

        /* HEADER */
        .header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 25px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 { 
            font-size: 26px; 
            color: var(--text-main); 
            margin-bottom: 8px; 
            font-weight: 700; 
            letter-spacing: -0.5px;
        }
        .header .subtitle { 
            font-size: 14px; 
            color: var(--text-muted); 
            background: var(--bg-color);
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .btn-outline { border-color: var(--border-color); background: white; color: var(--text-main); }
        .btn-outline:hover { background: var(--bg-color); border-color: #cbd5e0; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 2px 4px rgba(49, 130, 206, 0.3); }
        .btn-primary:hover { background: #2b6cb0; transform: translateY(-1px); }
        .btn-success { background: var(--success); color: white; box-shadow: 0 2px 4px rgba(56, 161, 105, 0.3); }
        .btn-success:hover { background: #2f855a; transform: translateY(-1px); }

        /* SECTION TITLE */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title i { 
            color: var(--primary); 
            background: var(--primary-light);
            padding: 8px;
            border-radius: 8px;
            font-size: 14px;
        }

        /* GRID LAYOUT */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .mb-4 { margin-bottom: 35px; }
        .card-section {
            background: #fafbfc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 25px;
        }

        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; gap: 20px; }
            .container { padding: 25px; }
        }

        /* DATA LIST */
        .data-row { display: flex; margin-bottom: 12px; align-items: flex-start; }
        .data-label { width: 140px; color: var(--text-muted); flex-shrink: 0; font-weight: 500; font-size: 13px; }
        .data-value { font-weight: 600; color: var(--text-main); flex-grow: 1; font-size: 14px; }
        
        .description-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            white-space: pre-line;
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.7;
        }

        /* TABLES */
        table { width: 100%; border-collapse: collapse; font-size: 13px; background: white; border-radius: 8px; overflow: hidden; }
        th { 
            background: #f8fafc; 
            font-weight: 600; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 0.5px; 
            padding: 14px 12px;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        tbody tr:nth-child(even) { background-color: #fcfcfd; }
        tbody tr:hover { background-color: var(--primary-light); transition: background 0.2s; }
        tr:last-child td { border-bottom: none; }

        /* BADGES */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-blue { background: var(--primary-light); color: var(--primary); }
        .badge-green { background: var(--success-light); color: var(--success); }
        .badge-yellow { background: var(--warning-light); color: var(--warning); }
        .badge-red { background: var(--danger-light); color: var(--danger); }
        
        /* FOOTER */
        .footer {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
            background: #fafbfc;
            padding: 20px;
            border-radius: 8px;
        }

        /* PRINT */
        @media print {
            body { background: white; padding: 0; }
            .container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .actions { display: none; }
            .card-section { border: none; padding: 0; background: white; }
            th, td { padding: 8px; }
            tbody tr:hover { background: transparent; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- HEADER -->
    <div class="header">
        <div>
            <h1><?php echo e($insiden['judul_insiden']); ?></h1>
            <div class="subtitle">
                <i class="fas fa-hashtag" style="font-size:12px;"></i> <?php echo e($insiden['insiden_number'] ?? 'N/A'); ?> &nbsp;|&nbsp; 
                <i class="fas fa-tag" style="font-size:12px;"></i> <?php echo e($insiden['insiden_type']); ?>
            </div>
        </div>
        <div class="actions">
            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
            <button onclick="exportToExcel()" class="btn btn-success"><i class="fas fa-file-excel"></i> Export Excel</button>
            <a href="generate_pdf.php?id=<?php echo $insiden_id; ?>" target="_blank" class="btn btn-outline"><i class="fas fa-file-pdf"></i> PDF</a>
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Cetak</button>
        </div>
    </div>

    <!-- INFORMASI DASAR -->
    <div class="mb-4 card-section">
        <h2 class="section-title"><i class="fas fa-info-circle"></i> Informasi Insiden</h2>
        <div class="grid-2">
            <div>
                <div class="data-row"><div class="data-label">Tanggal</div><div class="data-value"><?php echo date('d F Y', strtotime($insiden['tanggal'])); ?></div></div>
                <div class="data-row"><div class="data-label">Waktu</div><div class="data-value"><?php echo !empty($insiden['waktu_kejadian']) ? date('H:i', strtotime($insiden['waktu_kejadian'])) : '-'; ?></div></div>
                <div class="data-row"><div class="data-label">Lokasi</div><div class="data-value"><?php echo e($insiden['lokasi']); ?></div></div>
                <div class="data-row"><div class="data-label">Departemen</div><div class="data-value"><?php echo e($insiden['departemen_insiden']); ?></div></div>
            </div>
            <div>
                <div class="data-row"><div class="data-label">Manajer Dept.</div><div class="data-value"><?php echo e($insiden['nama_manajer_departemen'] ?? '-'); ?></div></div>
                <div class="data-row"><div class="data-label">Penyelidik</div><div class="data-value"><?php echo e($insiden['penyelidik']); ?></div></div>
                <div class="data-row"><div class="data-label">Tingkat Risiko</div><div class="data-value"><span class="badge badge-red"><?php echo e($insiden['tingkat_risiko']); ?></span></div></div>
                <div class="data-row"><div class="data-label">Kerugian</div><div class="data-value"><?php echo formatRupiah($insiden['perkiraan_kerugian']); ?></div></div>
            </div>
        </div>
    </div>

    <!-- DESKRIPSI -->
    <div class="mb-4">
        <h2 class="section-title"><i class="fas fa-align-left"></i> Deskripsi Kejadian</h2>
        <div class="description-box">
            <?php echo e($insiden['deskripsi_kejadian']); ?>
        </div>
    </div>

    <!-- KORBAN & SAKSI -->
    <div class="grid-2 mb-4">
        <div class="card-section">
            <h2 class="section-title" style="border-bottom:none; margin-bottom:10px; padding-bottom:0;"><i class="fas fa-user-injured"></i> Data Korban</h2>
            <?php if(!empty($korban)): ?>
                <div class="data-row"><div class="data-label">Nama</div><div class="data-value"><?php echo e($korban['nama']); ?></div></div>
                <div class="data-row"><div class="data-label">Jabatan</div><div class="data-value"><?php echo e($korban['jabatan']); ?></div></div>
                <div class="data-row"><div class="data-label">Bagian Tubuh</div><div class="data-value"><?php echo e($korban['bagian_tubuh']); ?></div></div>
            <?php else: ?>
                <p style="color: var(--text-muted); font-style: italic; padding-top:10px;">Tidak ada data korban.</p>
            <?php endif; ?>
        </div>
        <div class="card-section">
            <h2 class="section-title" style="border-bottom:none; margin-bottom:10px; padding-bottom:0;"><i class="fas fa-users"></i> Saksi</h2>
            <?php if(!empty($saksi_list)): ?>
                <ul style="list-style: disc; padding-left: 20px; color: var(--text-main); margin-top:10px;">
                    <?php foreach($saksi_list as $s): ?>
                        <li><?php echo e($s); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: var(--text-muted); font-style: italic; padding-top:10px;">Tidak ada saksi.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ANALISIS SCAT -->
    <div class="mb-4">
        <h2 class="section-title"><i class="fas fa-search"></i> Analisis Penyebab</h2>
        
        <h3 style="font-size: 14px; font-weight:600; margin-bottom: 12px; margin-top: 20px; color: var(--text-main);">1. Jenis Kejadian (Kontak dengan Energi)</h3>
        <?php if(count($jenis_kejadian_data) > 0): ?>
            <ul style="list-style: disc; padding-left: 20px; margin-bottom: 25px; color: var(--text-main); line-height: 1.8;">
                <?php foreach($jenis_kejadian_data as $jk): ?>
                    <li><?php echo e($jk); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 25px;">Tidak ada data.</p>
        <?php endif; ?>

        <h3 style="font-size: 14px; font-weight:600; margin-bottom: 12px; color: var(--text-main);">2. Penyebab Langsung (IC's)</h3>
        <?php if(count($penyebab_tindakan) > 0): ?>
            <ul style="list-style: disc; padding-left: 20px; margin-bottom: 25px; color: var(--text-main); line-height: 1.8;">
                <?php foreach($penyebab_tindakan as $pt): ?>
                    <li><?php echo e($pt); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 25px;">Tidak ada data.</p>
        <?php endif; ?>

        <h3 style="font-size: 14px; font-weight:600; margin-bottom: 12px; color: var(--text-main);">3. Penyebab Dasar (BC's)</h3>
        <?php if(count($faktor_personil) + count($faktor_pekerjaan) > 0): ?>
            <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th width="10%">Kode</th>
                            <th width="60%">Deskripsi</th>
                            <th width="30%">Tipe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($faktor_personil as $fp): ?>
                            <tr>
                                <td><strong><?php echo e($fp['kode_faktor']); ?></strong></td>
                                <td><?php echo e($fp['deskripsi']); ?></td>
                                <td><span class="badge badge-blue">Personil</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach($faktor_pekerjaan as $fp): ?>
                            <tr>
                                <td><strong><?php echo e($fp['kode_faktor']); ?></strong></td>
                                <td><?php echo e($fp['deskripsi']); ?></td>
                                <td><span class="badge badge-yellow">Pekerjaan</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 25px;">Tidak ada data.</p>
        <?php endif; ?>
    </div>

    <!-- CONTROL ACTION NEEDS (CAN) -->
    <div class="mb-4">
        <h2 class="section-title"><i class="fas fa-tasks"></i> Control Action Needs (CAN)</h2>
        <div style="margin-bottom: 15px; font-size: 13px; display:flex; gap:10px;">
            <span class="badge badge-red">P: <?php echo $total_p; ?></span> 
            <span class="badge badge-yellow">S: <?php echo $total_s; ?></span> 
            <span class="badge badge-blue">C: <?php echo $total_c; ?></span>
        </div>
        <?php if(count($penyebab_dasar_can) > 0): ?>
            <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th width="10%">Kode</th>
                            <th width="60%">Deskripsi Faktor</th>
                            <th width="10%" style="text-align:center;">P</th>
                            <th width="10%" style="text-align:center;">S</th>
                            <th width="10%" style="text-align:center;">C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($penyebab_dasar_can as $can): ?>
                            <tr>
                                <td><strong><?php echo e($can['kode']); ?></strong></td>
                                <td><?php echo e($can['deskripsi']); ?></td>
                                <td style="text-align:center; font-weight:bold; font-size:16px; color: <?php echo ($can['program_p'] == 'Y') ? 'var(--success)' : '#cbd5e0'; ?>"><?php echo ($can['program_p'] == 'Y') ? '✓' : '-'; ?></td>
                                <td style="text-align:center; font-weight:bold; font-size:16px; color: <?php echo ($can['standar_s'] == 'Y') ? 'var(--success)' : '#cbd5e0'; ?>"><?php echo ($can['standar_s'] == 'Y') ? '✓' : '-'; ?></td>
                                <td style="text-align:center; font-weight:bold; font-size:16px; color: <?php echo ($can['kepatuhan_c'] == 'Y') ? 'var(--success)' : '#cbd5e0'; ?>"><?php echo ($can['kepatuhan_c'] == 'Y') ? '✓' : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic;">Tidak ada data CAN.</p>
        <?php endif; ?>
    </div>

    <!-- TINDAKAN PERBAIKAN -->
    <div class="mb-4">
        <h2 class="section-title"><i class="fas fa-tools"></i> Tindakan Perbaikan</h2>
        <?php if(count($tindakan_perbaikan) > 0): ?>
            <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
                <table>
                    <thead>
                        <tr>
                            <th width="15%">Jenis</th>
                            <th width="20%">Rekomendasi</th>
                            <th width="20%">Tindakan</th>
                            <th width="15%">PIC</th>
                            <th width="15%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tindakan_perbaikan as $tp): 
                            $statusClass = 'badge-blue';
                            if($tp['status'] == 'Completed' || $tp['status'] == 'Closed') $statusClass = 'badge-green';
                            if($tp['status'] == 'Pending') $statusClass = 'badge-yellow';
                        ?>
                            <tr>
                                <td><strong><?php echo e($tp['jenis_tindakan']); ?></strong></td>
                                <td style="font-size: 13px;"><?php echo nl2br(e($tp['rekomendasi'])); ?></td>
                                <td style="font-size: 13px;"><?php echo nl2br(e($tp['deskripsi'])); ?></td>
                                <td><?php echo e($tp['penanggungjawab']); ?></td>
                                <td>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo e($tp['status']); ?></span>
                                    <?php if(!empty($tindakan_files[$tp['id']])): ?>
                                        <div style="margin-top:8px; font-size:11px; color:var(--text-muted);"><i class="fas fa-paperclip"></i> <?php echo count($tindakan_files[$tp['id']]); ?> File</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-style: italic;">Belum ada tindakan perbaikan.</p>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        Laporan dibuat oleh: <strong><?php echo e($insiden['fullname'] ?? 'User'); ?></strong> &nbsp;|&nbsp; 
        Terakhir diupdate: <?php echo date('d M Y H:i', strtotime($insiden['updated_at'] ?? $insiden['created_at'])); ?>
    </div>

</div>

<!-- JAVASCRIPT UNTUK EXPORT EXCEL -->
<script>
function exportToExcel() {
    const insidenData = {
        id: '<?php echo e($insiden["id"]); ?>',
        nomor: '<?php echo e($insiden["insiden_number"] ?? "N/A"); ?>',
        judul: '<?php echo e($insiden["judul_insiden"]); ?>',
        tanggal: '<?php echo date("d-m-Y", strtotime($insiden["tanggal"])); ?>',
        lokasi: '<?php echo e($insiden["lokasi"]); ?>',
        departemen: '<?php echo e($insiden["departemen_insiden"]); ?>',
        penyelidik: '<?php echo e($insiden["penyelidik"]); ?>',
        tingkat_risiko: '<?php echo e($insiden["tingkat_risiko"]); ?>'
    };
    
    const tindakanData = <?php echo json_encode($tindakan_perbaikan); ?>;
    const canData = <?php echo json_encode($penyebab_dasar_can); ?>;
    const bcData = <?php echo json_encode(array_merge($faktor_personil, $faktor_pekerjaan)); ?>;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_excel.php';
    form.style.display = 'none';
    
    const insidenInput = document.createElement('input');
    insidenInput.type = 'hidden';
    insidenInput.name = 'insiden_data';
    insidenInput.value = JSON.stringify(insidenData);
    form.appendChild(insidenInput);
    
    const tindakanInput = document.createElement('input');
    tindakanInput.type = 'hidden';
    tindakanInput.name = 'tindakan_data';
    tindakanInput.value = JSON.stringify(tindakanData);
    form.appendChild(tindakanInput);
    
    const canInput = document.createElement('input');
    canInput.type = 'hidden';
    canInput.name = 'can_data';
    canInput.value = JSON.stringify(canData);
    form.appendChild(canInput);
    
    const bcInput = document.createElement('input');
    bcInput.type = 'hidden';
    bcInput.name = 'bc_data';
    bcInput.value = JSON.stringify(bcData);
    form.appendChild(bcInput);
    
    document.body.appendChild(form);
    form.submit();
    
    setTimeout(() => {
        document.body.removeChild(form);
    }, 1000);
}
</script>

</body>
</html>