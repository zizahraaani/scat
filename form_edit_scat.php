<?php
// edit_insiden_scat_complete.php - FILE EDIT DENGAN PERBAIKAN
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['fullname']) && isset($_SESSION['username'])) {
    $_SESSION['fullname'] = $_SESSION['username'];
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$insiden_id = intval($_GET['id']);

// ================= AMBIL DATA LAMA DARI DATABASE =================
$insiden = null;
$jenis_kerugian = [];
$jenis_kejadian = [];
$penyebab_tindakan = [];
$faktor_personil = [];
$faktor_pekerjaan = [];
$penyebab_dasar_can = [];
$identitas = null;
$korban = null;
$saksi_list = [];
$tindakan_perbaikan = [];

// 1. Ambil data insiden utama (FIX: gunakan kolom 'departemen', bukan 'department')
$stmt = $mysqli->prepare("SELECT i.*, 
                         COALESCE(u.username, 'Unknown') as username, 
                         COALESCE(u.fullname, '-') as fullname,
                         COALESCE(u.departemen, '-') as departemen
                         FROM insiden i 
                         LEFT JOIN users u ON i.dibuat_oleh = u.id 
                         WHERE i.id = ?");
if ($stmt) {
    $stmt->bind_param('i', $insiden_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $insiden = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$insiden) {
    header("Location: dashboard.php");
    exit;
}

// 2. Identitas
$stmt = $mysqli->prepare("SELECT * FROM identitas WHERE insiden_id = ? LIMIT 1");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $identitas = $res->fetch_assoc();
}
$stmt->close();

// 3. Jenis kerugian
$stmt = $mysqli->prepare("SELECT jenis FROM jenis_kerugian WHERE insiden_id = ?");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $jenis_kerugian[] = $row['jenis'];
}
$stmt->close();

// 4. Jenis kejadian
$stmt = $mysqli->prepare("SELECT deskripsi FROM jenis_kejadian WHERE insiden_id = ?");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $jenis_kejadian[] = $row['deskripsi'];
}
$stmt->close();

// 5. Penyebab langsung - TINDAKAN
$stmt = $mysqli->prepare("SELECT deskripsi FROM penyebab_langsung WHERE insiden_id = ? AND tipe = 'tindakan'");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $penyebab_tindakan[] = $row['deskripsi'];
}
$stmt->close();

// 6. Faktor analisis
$stmt = $mysqli->prepare("SELECT * FROM faktor_analisis WHERE insiden_id = ?");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $tipe = isset($row['tipe']) ? strtolower(trim($row['tipe'])) : '';
    if ($tipe === 'pekerjaan') {
        $faktor_pekerjaan[] = $row;
    } else {
        $faktor_personil[] = $row;
    }
}
$stmt->close();

// 7. Penyebab dasar (CAN)
$stmt = $mysqli->prepare("SELECT * FROM penyebab_dasar WHERE insiden_id = ?");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $penyebab_dasar_can[] = $row;
}
$stmt->close();

// 8. Korban
$stmt = $mysqli->prepare("SELECT * FROM korban WHERE insiden_id = ? LIMIT 1");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $korban = $res->fetch_assoc();
}
$stmt->close();

// 9. Saksi
$stmt = $mysqli->prepare("SELECT nama_saksi FROM saksi WHERE insiden_id = ?");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $saksi_list[] = $row['nama_saksi'];
}
$stmt->close();

// 10. Tindakan perbaikan + file
$stmt = $mysqli->prepare("SELECT * FROM tindakan_perbaikan WHERE insiden_id = ? ORDER BY id");
$stmt->bind_param('i', $insiden_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $tid = intval($row['id']);
    $fstmt = $mysqli->prepare("SELECT * FROM tindakan_files WHERE tindakan_id = ? ORDER BY created_at");
    $fstmt->bind_param('i', $tid);
    $fstmt->execute();
    $fres = $fstmt->get_result();
    $row['files'] = [];
    while ($file = $fres->fetch_assoc()) {
        $row['files'][] = $file;
    }
    $fstmt->close();
    $tindakan_perbaikan[] = $row;
}
$stmt->close();

// ================= HANDLE FORM SUBMISSION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form (JANGAN pakai real_escape_string di sini — gunakan prepared statement)
    $judul_insiden      = trim($_POST['judul_insiden'] ?? '');
    $tanggal            = trim($_POST['tanggal'] ?? '');
    $waktu_kejadian     = trim($_POST['waktu_kejadian'] ?? '') ?: null;
    $lokasi             = trim($_POST['lokasi'] ?? '');
    $departemen_insiden = trim($_POST['departemen_insiden'] ?? '');
    $insiden_type       = trim($_POST['insiden_type'] ?? '');
    $penyelidik         = trim($_POST['penyelidik'] ?? '');
    $deskripsi_kejadian = trim($_POST['deskripsi_kejadian'] ?? '');
    $tingkat_risiko     = trim($_POST['tingkat_risiko'] ?? 'Tidak Dianalisis');

    $entitas = trim($_POST['Entitas'] ?? '');
    $cabang  = trim($_POST['Cabang'] ?? '');
    $proect  = trim($_POST['Proect'] ?? '');

    $nama_korban     = trim($_POST['nama_korban'] ?? '');
    $jabatan_korban  = trim($_POST['jabatan_korban'] ?? '');
    $bagian_tubuh    = trim($_POST['bagian_tubuh'] ?? '');

    $saksi_input = trim($_POST['saksi'] ?? '');
    $saksi_list_post = array_values(array_filter(array_map('trim', explode(',', $saksi_input)), function ($v) {
        return $v !== '';
    }));

    $perkiraan_kerugian = isset($_POST['perkiraan_kerugian'])
        ? floatval(preg_replace('/[^\d]/', '', $_POST['perkiraan_kerugian']))
        : 0;
    $potensi_kerugian = isset($_POST['potensi_kerugian'])
        ? floatval(preg_replace('/[^\d]/', '', $_POST['potensi_kerugian']))
        : 0;

    $jenis_kerugian_post     = isset($_POST['jenis_kerugian']) && is_array($_POST['jenis_kerugian']) ? $_POST['jenis_kerugian'] : [];
    $jenis_kejadian_post     = isset($_POST['jenis_kejadian']) && is_array($_POST['jenis_kejadian']) ? $_POST['jenis_kejadian'] : [];
    $penyebab_tindakan_post  = isset($_POST['penyebab_tindakan']) && is_array($_POST['penyebab_tindakan']) ? $_POST['penyebab_tindakan'] : [];
    $penyebab_dasar_post     = isset($_POST['penyebab_dasar']) && is_array($_POST['penyebab_dasar']) ? $_POST['penyebab_dasar'] : [];
    $faktor_personil_post    = isset($_POST['faktor_personil']) && is_array($_POST['faktor_personil']) ? $_POST['faktor_personil'] : [];
    $faktor_pekerjaan_post   = isset($_POST['faktor_pekerjaan']) && is_array($_POST['faktor_pekerjaan']) ? $_POST['faktor_pekerjaan'] : [];
    $tindakan_perbaikan_post = isset($_POST['tindakan_perbaikan']) && is_array($_POST['tindakan_perbaikan']) ? $_POST['tindakan_perbaikan'] : [];

    $mysqli->begin_transaction();
    try {
        // ============ 1. UPDATE insiden utama ============
        $sql = "UPDATE insiden SET 
                judul_insiden = ?, tanggal = ?, waktu_kejadian = ?, lokasi = ?,
                departemen_insiden = ?, insiden_type = ?, penyelidik = ?,
                deskripsi_kejadian = ?, tingkat_risiko = ?,
                perkiraan_kerugian = ?, potensi_kerugian = ?
                WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare UPDATE insiden gagal: " . $mysqli->error);
        }
        $stmt->bind_param(
            'sssssssssddi',
            $judul_insiden, $tanggal, $waktu_kejadian, $lokasi,
            $departemen_insiden, $insiden_type, $penyelidik,
            $deskripsi_kejadian, $tingkat_risiko,
            $perkiraan_kerugian, $potensi_kerugian, $insiden_id
        );
        // bind_param di atas ada typo spasi — perbaiki:
        $stmt->close();
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            'sssssssssddi',
            $judul_insiden, $tanggal, $waktu_kejadian, $lokasi,
            $departemen_insiden, $insiden_type, $penyelidik,
            $deskripsi_kejadian, $tingkat_risiko,
            $perkiraan_kerugian, $potensi_kerugian, $insiden_id
        );
        $stmt->close();

        // Karena bind_param dengan spasi bermasalah, tulis ulang dengan bersih:
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("Prepare gagal: " . $mysqli->error);
        $stmt->bind_param(
            'sssssssssddi',
            $judul_insiden,
            $tanggal,
            $waktu_kejadian,
            $lokasi,
            $departemen_insiden,
            $insiden_type,
            $penyelidik,
            $deskripsi_kejadian,
            $tingkat_risiko,
            $perkiraan_kerugian,
            $potensi_kerugian,
            $insiden_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Gagal update insiden: " . $stmt->error);
        }
        $stmt->close();

        // ============ 2. Hapus data lama anak (KECUALI tindakan & files) ============
        $tables = ['identitas','jenis_kerugian','jenis_kejadian','penyebab_langsung',
                   'faktor_analisis','penyebab_dasar','korban','saksi'];
        foreach ($tables as $table) {
            $d = $mysqli->prepare("DELETE FROM $table WHERE insiden_id = ?");
            if ($d) {
                $d->bind_param('i', $insiden_id);
                $d->execute();
                $d->close();
            }
        }

        // ============ 3. Insert identitas ============
        if ($entitas !== '' && $cabang !== '') {
            $stmt = $mysqli->prepare("INSERT INTO identitas (insiden_id, entitas, cabang, proect) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $insiden_id, $entitas, $cabang, $proect);
            if (!$stmt->execute()) throw new Exception("Gagal insert identitas: " . $stmt->error);
            $stmt->close();
        }

        // ============ 4. Jenis kerugian ============
        $stmt = $mysqli->prepare("INSERT INTO jenis_kerugian (insiden_id, jenis) VALUES (?, ?)");
        foreach ($jenis_kerugian_post as $jenis) {
            $jenis = trim($jenis);
            if ($jenis === '') continue;
            $stmt->bind_param('is', $insiden_id, $jenis);
            if (!$stmt->execute()) throw new Exception("Gagal insert jenis_kerugian: " . $stmt->error);
        }
        $stmt->close();

        // ============ 5. Jenis kejadian ============
        $stmt = $mysqli->prepare("INSERT INTO jenis_kejadian (insiden_id, deskripsi) VALUES (?, ?)");
        foreach ($jenis_kejadian_post as $kejadian) {
            $kejadian = trim($kejadian);
            if ($kejadian === '') continue;
            $stmt->bind_param('is', $insiden_id, $kejadian);
            if (!$stmt->execute()) throw new Exception("Gagal insert jenis_kejadian: " . $stmt->error);
        }
        $stmt->close();

        // ============ 6. Penyebab langsung (tindakan) ============
        $stmt = $mysqli->prepare("INSERT INTO penyebab_langsung (insiden_id, tipe, deskripsi) VALUES (?, 'tindakan', ?)");
        foreach ($penyebab_tindakan_post as $tindakan) {
            $tindakan = trim($tindakan);
            if ($tindakan === '') continue;
            $stmt->bind_param('is', $insiden_id, $tindakan);
            if (!$stmt->execute()) throw new Exception("Gagal insert penyebab_langsung: " . $stmt->error);
        }
        $stmt->close();

        // ============ 7. Korban ============
        if ($nama_korban !== '') {
            $stmt = $mysqli->prepare("INSERT INTO korban (insiden_id, nama, jabatan, bagian_tubuh) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('isss', $insiden_id, $nama_korban, $jabatan_korban, $bagian_tubuh);
            if (!$stmt->execute()) throw new Exception("Gagal insert korban: " . $stmt->error);
            $stmt->close();
        }

        // ============ 8. Saksi ============
        if (!empty($saksi_list_post)) {
            $stmt = $mysqli->prepare("INSERT INTO saksi (insiden_id, nama_saksi) VALUES (?, ?)");
            foreach ($saksi_list_post as $saksi_nama) {
                if ($saksi_nama === '') continue;
                $stmt->bind_param('is', $insiden_id, $saksi_nama);
                if (!$stmt->execute()) throw new Exception("Gagal insert saksi: " . $stmt->error);
            }
            $stmt->close();
        }

        // ============ 9. Hapus tindakan yang dipilih ============
        if (isset($_POST['deleted_tindakan']) && is_array($_POST['deleted_tindakan'])) {
            foreach ($_POST['deleted_tindakan'] as $deleted_id) {
                $deleted_id = intval($deleted_id);
                if ($deleted_id <= 0) continue;

                // Ambil file dulu untuk hapus fisik
                $fq = $mysqli->prepare("SELECT path_file FROM tindakan_files WHERE tindakan_id = ?");
                $fq->bind_param('i', $deleted_id);
                $fq->execute();
                $fr = $fq->get_result();
                while ($file = $fr->fetch_assoc()) {
                    if (!empty($file['path_file']) && file_exists($file['path_file'])) {
                        @unlink($file['path_file']);
                    }
                }
                $fq->close();

                $del = $mysqli->prepare("DELETE FROM tindakan_perbaikan WHERE id = ? AND insiden_id = ?");
                $del->bind_param('ii', $deleted_id, $insiden_id);
                if (!$del->execute()) throw new Exception("Gagal hapus tindakan: " . $del->error);
                $del->close();
            }
        }

        // ============ 10. Insert/Update tindakan perbaikan + file ============
        if (!empty($tindakan_perbaikan_post)) {
            foreach ($tindakan_perbaikan_post as $index => $tindakan) {
                $deskripsi     = trim($tindakan['deskripsi'] ?? '');
                $rekomendasi   = trim($tindakan['rekomendasi'] ?? '');
                if ($deskripsi === '' && $rekomendasi === '') continue;

                $jenis_tindakan   = trim($tindakan['jenis_tindakan'] ?? '');
                $penanggungjawab  = trim($tindakan['penanggungjawab'] ?? '');
                $target_selesai   = !empty($tindakan['target_selesai']) ? $tindakan['target_selesai'] : null;
                $status           = trim($tindakan['status'] ?? 'Pending');
                $existing_id      = isset($tindakan['id']) ? intval($tindakan['id']) : 0;

                if ($existing_id > 0) {
                    // ===== FIX UTAMA: HAPUS `updated_at = NOW()` dari query =====
                    // Tabel tindakan_perbaikan TIDAK punya kolom updated_at di dump Anda.
                    // Jika Anda sudah menjalankan ALTER TABLE di Bagian 1, MySQL akan
                    // mengisi updated_at otomatis via ON UPDATE CURRENT_TIMESTAMP.
                    $update_stmt = $mysqli->prepare("UPDATE tindakan_perbaikan SET 
                        jenis_tindakan = ?,
                        deskripsi = ?,
                        rekomendasi = ?,
                        target_selesai = ?,
                        penanggungjawab = ?,
                        status = ?
                        WHERE id = ? AND insiden_id = ?");
                    if (!$update_stmt) {
                        throw new Exception("Prepare UPDATE tindakan gagal: " . $mysqli->error);
                    }
                    $update_stmt->bind_param(
                        'sssssssi',
                        $jenis_tindakan,
                        $deskripsi,
                        $rekomendasi,
                        $target_selesai,
                        $penanggungjawab,
                        $status,
                        $existing_id,
                        $insiden_id
                    );
                    if (!$update_stmt->execute()) {
                        throw new Exception("Gagal update tindakan perbaikan: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                    $tindakan_id = $existing_id;
                } else {
                    $insert_stmt = $mysqli->prepare("INSERT INTO tindakan_perbaikan 
                        (insiden_id, jenis_tindakan, deskripsi, rekomendasi, target_selesai, penanggungjawab, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$insert_stmt) {
                        throw new Exception("Prepare INSERT tindakan gagal: " . $mysqli->error);
                    }
                    $insert_stmt->bind_param(
                        'issssss',
                        $insiden_id,
                        $jenis_tindakan,
                        $deskripsi,
                        $rekomendasi,
                        $target_selesai,
                        $penanggungjawab,
                        $status
                    );
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Gagal insert tindakan perbaikan: " . $insert_stmt->error);
                    }
                    $tindakan_id = $insert_stmt->insert_id;
                    $insert_stmt->close();
                }

                // ===== Handle upload file =====
                // Struktur: $_FILES['tindakan_perbaikan']['name'][$index]['lampiran'][$i]
                if (isset($_FILES['tindakan_perbaikan'])
                    && isset($_FILES['tindakan_perbaikan']['name'][$index]['lampiran'])
                    && is_array($_FILES['tindakan_perbaikan']['name'][$index]['lampiran'])) {

                    $files = $_FILES['tindakan_perbaikan'];

                    $uploadDir = __DIR__ . '/uploads/tindakan/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            throw new Exception("Gagal membuat folder upload: $uploadDir");
                        }
                    }

                    $allowedExtensions = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
                    $maxSize = 5 * 1024 * 1024;

                    foreach ($files['name'][$index]['lampiran'] as $i => $originalName) {
                        $err = $files['error'][$index]['lampiran'][$i];
                        if ($err === UPLOAD_ERR_NO_FILE) continue;
                        if ($err !== UPLOAD_ERR_OK) {
                            throw new Exception("Upload file '$originalName' gagal dengan kode error: $err");
                        }

                        $tmpName  = $files['tmp_name'][$index]['lampiran'][$i];
                        $fileSize = intval($files['size'][$index]['lampiran'][$i]);
                        $fileType = $files['type'][$index]['lampiran'][$i];

                        if ($fileSize > $maxSize) {
                            throw new Exception("File '$originalName' terlalu besar. Maks 5MB.");
                        }

                        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExtensions, true)) {
                            throw new Exception("File '$originalName' tidak diizinkan. Hanya PDF, DOC, XLS, JPG, PNG.");
                        }

                        $storedName = 'tindakan_' . $tindakan_id . '_' . uniqid() . '.' . $ext;
                        $relativePath = 'uploads/tindakan/' . $storedName;
                        $absolutePath = $uploadDir . $storedName;

                        if (!move_uploaded_file($tmpName, $absolutePath)) {
                            throw new Exception("Gagal memindahkan file '$originalName'.");
                        }

                        // Simpan path relatif (lebih portabel)
                        $fileStmt = $mysqli->prepare("INSERT INTO tindakan_files 
                            (tindakan_id, nama_file, path_file, ukuran, tipe) 
                            VALUES (?, ?, ?, ?, ?)");
                        if (!$fileStmt) {
                            throw new Exception("Prepare insert file gagal: " . $mysqli->error);
                        }
                        $fileStmt->bind_param('issis', $tindakan_id, $originalName, $relativePath, $fileSize, $fileType);
                        if (!$fileStmt->execute()) {
                            throw new Exception("Gagal simpan file DB: " . $fileStmt->error);
                        }
                        $fileStmt->close();
                    }
                }
            }
        }

        // ============ 11. Hapus file yang dicentang ============
        if (isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
            foreach ($_POST['delete_files'] as $file_id) {
                $file_id = intval($file_id);
                if ($file_id <= 0) continue;

                // FIX: verifikasi file milik insiden ini (cegah hapus lintas insiden)
                $q = $mysqli->prepare("SELECT tf.path_file 
                                       FROM tindakan_files tf
                                       JOIN tindakan_perbaikan tp ON tf.tindakan_id = tp.id
                                       WHERE tf.id = ? AND tp.insiden_id = ?");
                $q->bind_param('ii', $file_id, $insiden_id);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();

                if ($row) {
                    $path = $row['path_file'];
                    // Cek path absolut dan relatif
                    $candidates = [$path, __DIR__ . '/' . $path];
                    foreach ($candidates as $p) {
                        if ($p && file_exists($p)) { @unlink($p); break; }
                    }
                    $del = $mysqli->prepare("DELETE FROM tindakan_files WHERE id = ?");
                    $del->bind_param('i', $file_id);
                    $del->execute();
                    $del->close();
                }
            }
        }

        // ============ 12. Faktor analisis - PERSONIL ============
        $stmt = $mysqli->prepare("INSERT INTO faktor_analisis 
            (insiden_id, kategori, kode_faktor, deskripsi, tipe) 
            VALUES (?, ?, ?, ?, 'personil')");
        foreach ($faktor_personil_post as $faktor_str) {
            $parts = explode('|', $faktor_str, 2);
            if (count($parts) < 2) continue;
            $kode = trim($parts[0]);
            $desc = trim($parts[1]);
            if ($kode === '' || $desc === '') continue;

            $kategori = 'FAKTOR PERSONIL';
            $prefix = explode('.', $kode)[0];
            switch ($prefix) {
                case '1': $kategori = 'KETIDAKCAKAPAN FISIK/PSIKOLOGI'; break;
                case '2': $kategori = 'KEKURANGAN KEMAMPUAN MENTAL'; break;
                case '3': $kategori = 'TEKANAN FISIK'; break;
                case '4': $kategori = 'TEKANAN MENTAL'; break;
                case '5': $kategori = 'KURANGNYA PENGALAMAN'; break;
                case '6': $kategori = 'KEMAMPUAN KURANG'; break;
                case '7': $kategori = 'MOTIVASI TIDAK SESUAI'; break;
            }
            $stmt->bind_param('isss', $insiden_id, $kategori, $kode, $desc);
            if (!$stmt->execute()) throw new Exception("Gagal insert faktor personil: " . $stmt->error);
        }
        $stmt->close();

        // ============ 13. Faktor analisis - PEKERJAAN ============
        $stmt = $mysqli->prepare("INSERT INTO faktor_analisis 
            (insiden_id, kategori, kode_faktor, deskripsi, tipe) 
            VALUES (?, ?, ?, ?, 'pekerjaan')");
        foreach ($faktor_pekerjaan_post as $faktor_str) {
            $parts = explode('|', $faktor_str, 2);
            if (count($parts) < 2) continue;
            $kode = trim($parts[0]);
            $desc = trim($parts[1]);
            if ($kode === '' || $desc === '') continue;

            $kategori = 'FAKTOR PEKERJAAN';
            $prefix = explode('.', $kode)[0];
            switch ($prefix) {
                case '8':  $kategori = 'KEPEMIMPINAN TIDAK MEMADAI'; break;
                case '9':  $kategori = 'PENGETAHUAN TEKNIK TIDAK MEMADAI'; break;
                case '10': $kategori = 'PEMBELIAN TIDAK MEMADAI'; break;
                case '11': $kategori = 'PERAWATAN TIDAK MEMADAI'; break;
                case '12': $kategori = 'PERALATAN TIDAK MEMADAI'; break;
                case '13': $kategori = 'STANDAR PEKERJAAN KURANG'; break;
                case '14': $kategori = 'KEAUSAN DAN KERUSAKAN'; break;
                case '15': $kategori = 'PENYALAHGUNAAN'; break;
            }
            $stmt->bind_param('isss', $insiden_id, $kategori, $kode, $desc);
            if (!$stmt->execute()) throw new Exception("Gagal insert faktor pekerjaan: " . $stmt->error);
        }
        $stmt->close();

        // ============ 14. CAN (Penyebab Dasar) ============
        $stmt = $mysqli->prepare("INSERT INTO penyebab_dasar 
            (insiden_id, kode, deskripsi, program_p, standar_s, kepatuhan_c) 
            VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($penyebab_dasar_post as $item) {
            if (empty($item['kode']) || empty($item['deskripsi'])) continue;
            $kode = trim($item['kode']);
            $desc = trim($item['deskripsi']);
            $p = isset($item['program_p']) ? trim($item['program_p']) : '';
            $s = isset($item['standar_s']) ? trim($item['standar_s']) : '';
            $c = isset($item['kepatuhan_c']) ? trim($item['kepatuhan_c']) : '';
            // Pastikan hanya Y / N / ''
            $p = in_array($p, ['Y','N'], true) ? $p : '';
            $s = in_array($s, ['Y','N'], true) ? $s : '';
            $c = in_array($c, ['Y','N'], true) ? $c : '';
            $stmt->bind_param('isssss', $insiden_id, $kode, $desc, $p, $s, $c);
            if (!$stmt->execute()) throw new Exception("Gagal insert CAN: " . $stmt->error);
        }
        $stmt->close();

        $mysqli->commit();

        $_SESSION['message'] = "Insiden berhasil diperbarui!";
        $_SESSION['message_type'] = "success";

        if (isset($_POST['download_pdf']) && $_POST['download_pdf'] === '1') {
            header("Location: generate_pdf.php?id=$insiden_id");
            exit;
        }
        header("Location: detail_insiden.php?id=$insiden_id");
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        $error_message = "Gagal mengupdate insiden: " . $e->getMessage();
        error_log("Edit insiden error: " . $e->getMessage());
    }
}

// ================= DATA MASTER (tidak berubah) =================
$faktor_data = [
    "FAKTOR PERSONIL" => [
        "1" => ["title"=>"KETIDAKCAKAPAN KEMAMPUAN FISIK/PSIKOLOGI","sub"=>[
            "1.1"=>"Kekurangan Tinggi / Berat / Ukuran / Kekuatan Jangkauan, Dll.",
            "1.2"=>"Jangkauan tubuh yang tidak diperbolehkan",
            "1.3"=>"Keterbatasan dalam menjaga posisi badan",
            "1.4"=>"Alergi atau sensitif terhadap zat",
            "1.5"=>"Kepekaan panca indera (Suhu, Suara, Dll)",
            "1.6"=>"Pandangan berkurang","1.7"=>"Pendengaran berkurang",
            "1.8"=>"Panca indera berkurang (sentuhan, rasa, penciuman, keseimbangan)",
            "1.9"=>"Kemampuan pernapasan berkurang",
            "1.10"=>"Cacat fisik permanen lainnya","1.11"=>"Cacat fisik sementara"
        ]],
        "2" => ["title"=>"KEKURANGAN KEMAMPUAN MENTAL / PSIKOLOGI","sub"=>[
            "2.1"=>"Ketakutan dan fobia","2.2"=>"Gangguan emosional","2.3"=>"Penyakit kejiwaan",
            "2.4"=>"Tingkat kecerdasan","2.5"=>"Ketidakmampuan mengikuti","2.6"=>"Penilaian yang buruk",
            "2.7"=>"Koordinasi yang buruk","2.8"=>"Waktu reaksi lambat","2.9"=>"Bakat mekanikal rendah",
            "2.10"=>"Bakat belajar rendah","2.11"=>"Kegagalan memori"
        ]],
        "3" => ["title"=>"TEKANAN FISIK / PSIKOLOGI","sub"=>[
            "3.1"=>"Cedera penyakit","3.2"=>"Kelelahan karena Beban atau Durasi Tugas",
            "3.3"=>"Kelelahan karena Kurang Istirahat","3.4"=>"Kelelahan karena Overload sensorik",
            "3.5"=>"Paparan bahaya kesehatan","3.6"=>"Paparan suhu ekstrim","3.7"=>"Kekurangan oksigen",
            "3.8"=>"Variasi tekanan atmosfer","3.9"=>"Kekurangan gizi darah","3.10"=>"Narkoba"
        ]],
        "4" => ["title"=>"TEKANAN MENTAL / PSIKOLOGI","sub"=>[
            "4.1"=>"Emosi berlebihan","4.2"=>"Kelelahan karena beban mental",
            "4.3"=>"Permintaan penilaian keputusan yang ekstrim",
            "4.4"=>"Rutin, monoton, ketergantungan untuk tetap waspada",
            "4.5"=>"Kebutuhan konsentrasi/persepsi yang ekstrim",
            "4.6"=>"Kegiatan yang tidak berkesan atau menenangkan",
            "4.7"=>"Arahan/permintaan yang membingungkan",
            "4.8"=>"Arahan/permintaan yang berbenturan","4.9"=>"Keasyikan yang menggangu",
            "4.10"=>"Frustasi","4.11"=>"Penyakit mental"
        ]],
        "5" => ["title"=>"KURANGNYA PENGALAMAN","sub"=>[
            "5.1"=>"Kurang pengalaman","5.2"=>"Orientasi memadai",
            "5.3"=>"Pelatihan awal yang tidak memadai","5.4"=>"Update pelatihan yang tidak memadai",
            "5.5"=>"Arahan atau petunjuk yang salah dipahami"
        ]],
        "6" => ["title"=>"KEMAMPUAN KURANG","sub"=>[
            "6.1"=>"Kekurangan instruksi awal","6.2"=>"Kekurangan latihan",
            "6.3"=>"Tidak sering berlatih","6.4"=>"Kekurangan arahan","6.5"=>"Kekurangan telaah instruksi"
        ]],
        "7" => ["title"=>"MOTIVASI YANG TIDAK SESUAI","sub"=>[
            "7.1"=>"Kinerja yang tidak tepat diberi hadiah","7.2"=>"Kinerja yang tidak tepat dihukum",
            "7.3"=>"Kurangnya insentif","7.4"=>"Frustasi yang berlebihan","7.5"=>"Agresi yang berlebihan",
            "7.6"=>"Upaya yang tidak tepat untuk menghemat waktu dan usaha",
            "7.7"=>"Upaya yang tidak tepat untuk menghindari ketidaknyamanan",
            "7.8"=>"Upaya-upaya yang tidak layak untuk mendapatkan perhatian",
            "7.9"=>"Kedisiplinan yang tidak memadai","7.10"=>"Tekanan teman sebaya yang tidak pantas",
            "7.11"=>"Teladan yang salah","7.12"=>"Performa tidak cukup timbal balik",
            "7.13"=>"Tidak cukup dukungan atau sikap yang sesuai","7.14"=>"Tidak cukup insentif produksi"
        ]]
    ],
    "FAKTOR PEKERJAAN" => [
        "8" => ["title"=>"KEPEMIMPINAN DAN PENGAWASAN YANG TIDAK MEMADAI","sub"=>[
            "8.1"=>"Hubungan pelaporan yang tidak jelas atau bertentangan",
            "8.2"=>"Ketidakjelasan atau berbenturan dalam penunjukkan tanggung jawab",
            "8.3"=>"Delegasi yang tidak tepat atau tidak cukup",
            "8.4"=>"Memberikan kebijakan, prosedur, praktik atau pedoman yang tidak jelas",
            "8.5"=>"Memberikan tujuan sasaran atau standar yang bertentangan",
            "8.6"=>"Perencanaan dan pemrograman kerja yang tidak memadai",
            "8.7"=>"Instruksi, orientasi dan atau pelatihan yang tidak memadai",
            "8.8"=>"Tidak menyediakan dokumen referensi arahan dan petunjuk publikasi yang tidak memadai",
            "8.9"=>"Identifikasi dan evaluasi paparan kerugian yang tidak memadai",
            "8.10"=>"Kurangnya pengawasan/pengetahuan manajemen",
            "8.11"=>"Pencocokan kualifikasi seseorang dan kebutuhan pekerjaan tidak sesuai",
            "8.12"=>"Pengukuran kinerja dan evaluasi yang tidak memadai",
            "8.13"=>"Kurangnya/tidak sesuainya umpan balik dari kinerja"
        ]],
        "9" => ["title"=>"PENGETAHUAN TEKNIK TIDAK MEMADAI","sub"=>[
            "9.1"=>"Kurangnya perkiraan paparan kerugian",
            "9.2"=>"Kurangnya pertimbangan terhadap faktor ergonomi manusia",
            "9.3"=>"Kurangnya standar, spesifikasi dan atau kriteria standar",
            "9.4"=>"Kurangnya pengawasan saat pembangunan",
            "9.5"=>"Penilaian yang tidak memadai kesiapan operasional",
            "9.6"=>"Pengendalian yang kurang atau tidak sesuai",
            "9.7"=>"Kurangnya pengawasan saat mulainya pekerjaan",
            "9.8"=>"Kurangnya evaluasi perubahan"
        ]],
        "10" => ["title"=>"PEMBELIAN YANG TIDAK/KURANG MEMADAI","sub"=>[
            "10.1"=>"Spesifikasi yang tidak memadai sesuai permintaan resmi",
            "10.2"=>"Penelitian yang tidak memadai pada bahan atau peralatan",
            "10.3"=>"Spesifikasi kurang memadai untuk vendor",
            "10.4"=>"Rute pengiriman yang kurang memadai",
            "10.5"=>"Inspeksi penerimaan yang kurang memadai",
            "10.6"=>"Komunikasi keselamatan dan data kesehatan yang tidak memadai",
            "10.7"=>"Penanganan material yang tidak tepat",
            "10.8"=>"Penyimpanan material yang tidak tepat",
            "10.9"=>"Transportasi material yang tidak memadai",
            "10.10"=>"Identifikasi barang berbahaya yang tidak memadai",
            "10.11"=>"Penyelamatan yang tidak benar dan pembuangan limbah",
            "10.12"=>"Pemilihan kontraktor yang tidak memadai"
        ]],
        "11" => ["title"=>"PERAWATAN TIDAK MEMADAI","sub"=>[
            "11.1"=>"Kurangnya pencegahan","11.1.1"=>"Perkiraan kebutuhan",
            "11.1.2"=>"Pelumasan dan perawatan","11.1.3"=>"Penyetelan atau perakitan",
            "11.1.4"=>"Pembersihan atau pemolesan","11.2"=>"Kurangnya perbaikan",
            "11.2.1"=>"Komunikasi kebutuhan","11.2.2"=>"Jadwal pekerjaan",
            "11.2.3"=>"Pemeriksaan unit","11.2.4"=>"Penggantian komponen"
        ]],
        "12" => ["title"=>"PERALATAN DAN PERLENGKAPAN TIDAK MEMADAI","sub"=>[
            "12.1"=>"Kurangnya perkiraan dan kebutuhan berdasarkan risiko",
            "12.2"=>"Kurangnya pertimbangan faktor manusia dan ergonomi",
            "12.3"=>"Kurangnya standar atau spesifikasi",
            "12.4"=>"Kurangnya ketersediaan","12.5"=>"Kurangnya penyesuaian/perawatan",
            "12.6"=>"Kurangnya melepas dan memasang bagian yang sesuai",
            "12.7"=>"Kurangnya penggunaan kembali barang terpakai"
        ]],
        "13" => ["title"=>"STANDAR PEKERJAAN YANG KURANG MEMADAI","sub"=>[
            "13.1"=>"Kurangnya pengembangan standar untuk:",
            "13.1.1"=>"Evaluasi dan barang yang dibutuhkan",
            "13.1.2"=>"Perancangan proses koordinasi",
            "13.1.3"=>"Keterlibatan pekerja",
            "13.1.4"=>"Procedures/practices/regulations",
            "13.2"=>"Kurangnya komunikasi tentang standar untuk:",
            "13.2.1"=>"Publication","13.2.2"=>"Distribution",
            "13.2.3"=>"Translation into appropriate language",
            "13.2.4"=>"Training",
            "13.2.5"=>"Supported with signs, color codes and job aids",
            "13.3"=>"Kurangnya perawatan standar untuk:",
            "13.3.1"=>"Tracking work flow","13.3.2"=>"Updates",
            "13.3.3"=>"Supervision of procedure or regulation use",
            "13.3.4"=>"Kurangnya pengawasan atas kepuasan"
        ]],
        "14" => ["title"=>"KEAUSAN DAN KERUSAKAN SAAT MENJALANKAN","sub"=>[
            "14.1"=>"Kurangnya rencana penggunaan",
            "14.2"=>"Penambahan angka waktu yang tidak sesuai",
            "14.3"=>"Kurangnya pemeriksaan dan atau pengawasan",
            "14.4"=>"Ketidaksesuaian pembebanan atau penggunaan",
            "14.5"=>"Kurangnya perawatan",
            "14.6"=>"Digunakan oleh personel yang tidak berkualifikasi atau terlatih",
            "14.7"=>"Penggunaan yang tidak sesuai"
        ]],
        "15" => ["title"=>"PENYALAHGUNAAN","sub"=>[
            "15.1"=>"Perilaku tidak benar yang disetujui",
            "15.1.1"=>"Disengaja","15.1.2"=>"Tidak disengaja",
            "15.2"=>"Perilaku tidak benar yang tidak disetujui",
            "15.2.1"=>"Disengaja","15.2.2"=>"Tidak disengaja"
        ]]
    ]
];

$tabel1_jenis_kejadian = [
    "1"=>"Menabrak sesuatu (mengarah atau membentur sesuatu)",
    "2"=>"Ditabrak oleh (dihantam oleh benda bergerak)",
    "3"=>"Jatuh ke tingkat di bawah",
    "4"=>"Jatuh pada tingkat yang sama",
    "5"=>"Masuk ke dalam (terjepit dan tertusuk)",
    "6"=>"Berada di atas (tersangkut, tergantung)",
    "7"=>"Terkena di antara atau di bawah (terlindas atau terpotong)",
    "8"=>"Bersentuhan dengan (listrik, panas, beku, radiasi, bahan beracun, bising)",
    "9"=>"Stres berlebihan"
];

$tabel2a_tindakan = [
    "1"=>"Mengoperasikan peralatan tanpa wewenang",
    "2"=>"Kegagalan dalam memperingatkan",
    "3"=>"Kegagalan dalam mengamankan",
    "4"=>"Mengoperasikan dalam kecepatan yang tidak sesuai",
    "5"=>"Membuat alat keselamatan tidak bisa dioperasikan",
    "6"=>"Menggunakan peralatan yang cacat",
    "7"=>"Kegagalan dalam menggunakan PPE yang sesuai",
    "8"=>"Muatan tidak sesuai","9"=>"Penempatan tidak sesuai",
    "10"=>"Pengangkatan tidak sesuai","11"=>"Posisi bekerja tidak sesuai",
    "12"=>"Memperbaiki peralatan saat beroperasi","13"=>"Berkelakar",
    "14"=>"Di bawah pengaruh alkohol atau drugs lainnya",
    "15"=>"Menggunakan peralatan secara tidak sesuai",
    "16"=>"Tidak cukup perlindungan atau penghalang",
    "17"=>"Tidak cukup atau tidak sesuainya peralatan pelindung",
    "18"=>"Peralatan atau material rusak",
    "19"=>"Kemacetan atau kegiatan yang dibatasi",
    "20"=>"Sistem peringatan tidak cukup",
    "21"=>"Bahaya api dan ledakan",
    "22"=>"Kebersihan/penempatan yang buruk",
    "23"=>"Paparan suara bising","24"=>"Paparan radiasi","25"=>"Suhu ekstrim",
    "26"=>"Pencahayaan yang kurang atau berlebihan","27"=>"Ventilasi kurang",
    "28"=>"Kondisi lingkungan yang berbahaya"
];

// CAN data (tidak berubah)
$can_data = [
    ["kode"=>"1","deskripsi"=>"ADMINISTRASI DAN KEPEMIMPINAN","subitems"=>[
        ["kode"=>"1.1","deskripsi"=>"Kebijakan umum"],
        ["kode"=>"1.2","deskripsi"=>"Koordinator program"],
        ["kode"=>"1.3","deskripsi"=>"Partisipasi manajemen senior dan menengah"],
        ["kode"=>"1.4","deskripsi"=>"Standar kinerja manajemen"],
        ["kode"=>"1.5","deskripsi"=>"Keikutsertaan manajemen"],
        ["kode"=>"1.6","deskripsi"=>"Presentasi di rapat manajemen"],
        ["kode"=>"1.7","deskripsi"=>"Management reference manual"],
        ["kode"=>"1.8","deskripsi"=>"Audit manajemen dilakukan"],
        ["kode"=>"1.9","deskripsi"=>"Tanggung jawab individu untuk keselamatan dan kesehatan / kontrol pencegahan kerugian"],
        ["kode"=>"1.10","deskripsi"=>"Pembentukan keselamatan dan kesehatan tahunan / kontrol pencegahan kerugian"],
        ["kode"=>"1.11","deskripsi"=>"Bergabung dengan komite keselamatan & kesehatan dan atau perwakilannya"],
        ["kode"=>"1.12","deskripsi"=>"Menolak untuk bekerja dengan standar bahaya keselamatan dan kesehatan yang rendah"],
        ["kode"=>"1.13","deskripsi"=>"Daftar pustaka"]
    ]],
    ["kode"=>"2","deskripsi"=>"PELATIHAN MANAJEMEN","subitems"=>[
        ["kode"=>"2.1","deskripsi"=>"Program orientasi dan pengenalan manajemen"],
        ["kode"=>"2.2","deskripsi"=>"Pelatihan awal formal untuk personal senior manajemen"],
        ["kode"=>"2.3","deskripsi"=>"Ulasan dan pembaharuan formal tentang pelatihan senior manajemen"],
        ["kode"=>"2.4","deskripsi"=>"Pelatihan formal untuk personal supervisor dan manajemen menengah"],
        ["kode"=>"2.5","deskripsi"=>"Ulasan dan pembaharuan formal untuk personel supervisor dan manajemen menengah"],
        ["kode"=>"2.6","deskripsi"=>"Pelatihan formal untuk koordinator program"]
    ]],
    ["kode"=>"3","deskripsi"=>"PEMERIKSAAN TERENCANA","subitems"=>[
        ["kode"=>"3.1","deskripsi"=>"Pemeriksaan umum terencana"],
        ["kode"=>"3.2","deskripsi"=>"Tindak lanjut prosedur"],
        ["kode"=>"3.3","deskripsi"=>"Analisa laporan pemeriksaan"],
        ["kode"=>"3.4","deskripsi"=>"Pemeriksaan bagian penting dalam program inspeksi"],
        ["kode"=>"3.5","deskripsi"=>"Program pencegahan pemeliharaan"],
        ["kode"=>"3.6","deskripsi"=>"Pemeriksaan peralatan sebelum penanganan dan pemindahan"],
        ["kode"=>"3.7","deskripsi"=>"Kondisi alternatif sistem pelaporan"],
        ["kode"=>"3.8","deskripsi"=>"Laporan perencanaan program perawatan"],
        ["kode"=>"3.9","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"4","deskripsi"=>"ANALISIS TUGAS DAN PROSEDUR","subitems"=>[
        ["kode"=>"4.1","deskripsi"=>"Petunjuk dan pedoman penting manajemen"],
        ["kode"=>"4.2","deskripsi"=>"Inventarisasi tugas penting"],
        ["kode"=>"4.3","deskripsi"=>"Analisa tugas dan analisa sasaran prosedur"],
        ["kode"=>"4.4","deskripsi"=>"Analisis tugas dan prosedur untuk tugas penting selesai dan secara teratur diperbaharui"],
        ["kode"=>"4.5","deskripsi"=>"Analisa bahaya dan prosedur untuk analisa bahaya dan prosedur tugas penting"],
        ["kode"=>"4.6","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"5","deskripsi"=>"INVESTIGASI KECELAKAAN DAN KEJADIAN","subitems"=>[
        ["kode"=>"5.1","deskripsi"=>"Prosedur investigasi kecelakaan/kejadian"],
        ["kode"=>"5.2","deskripsi"=>"Lingkup investigasi yang dibuat"],
        ["kode"=>"5.3","deskripsi"=>"Perbaikan tindakan dan tindaklanjutan"],
        ["kode"=>"5.4","deskripsi"=>"Penyampaian kecelakaan besar yang digunakan"],
        ["kode"=>"5.5","deskripsi"=>"Informasi potensi kejadian besar yang digunakan"],
        ["kode"=>"5.6","deskripsi"=>"Partisipasi manajemen operasi"],
        ["kode"=>"5.7","deskripsi"=>"Pelaporan kejadian dan investigasi"],
        ["kode"=>"5.8","deskripsi"=>"Pemeliharaan laporan kecelakaan / kejadian"],
        ["kode"=>"5.9","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"6","deskripsi"=>"TUGAS OBSERVASI","subitems"=>[
        ["kode"=>"6.1","deskripsi"=>"Pentingnya arahan dari manajemen"],
        ["kode"=>"6.2","deskripsi"=>"Program dalam menyelesaikan tugas observasi"],
        ["kode"=>"6.3","deskripsi"=>"Tingkat pengamatan tugas yang sudah selesai"],
        ["kode"=>"6.4","deskripsi"=>"Pembagian tugas observasi"],
        ["kode"=>"6.5","deskripsi"=>"Analisa laporan pengamatan"],
        ["kode"=>"6.6","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"7","deskripsi"=>"KESIAPSIAGAAN DARURAT","subitems"=>[
        ["kode"=>"7.1","deskripsi"=>"Penunjukan koordinator"],
        ["kode"=>"7.2","deskripsi"=>"Perencanaan darurat tertulis"],
        ["kode"=>"7.3","deskripsi"=>"Pelatihan pengawas untuk first aid"],
        ["kode"=>"7.4","deskripsi"=>"Pelatihan karyawan untuk first aid (10%)"],
        ["kode"=>"7.5","deskripsi"=>"Lampu darurat dan listrik/genset memadai"],
        ["kode"=>"7.6","deskripsi"=>"Pusat penandaan label warna"],
        ["kode"=>"7.7","deskripsi"=>"Peralatan pelindung & peralatan penyelamatan"],
        ["kode"=>"7.8","deskripsi"=>"Pelatihan tanggap darurat"],
        ["kode"=>"7.9","deskripsi"=>"Petugas pertolongan pertama yang memenuhi syarat"],
        ["kode"=>"7.10","deskripsi"=>"Gotong royong atau sejenisnya"],
        ["kode"=>"7.11","deskripsi"=>"Perlindungan terhadap arsip penting"],
        ["kode"=>"7.12","deskripsi"=>"Perencanaan pasca acara"],
        ["kode"=>"7.13","deskripsi"=>"Komunikasi gawat darurat tersedia"],
        ["kode"=>"7.14","deskripsi"=>"Rencana komunikasi keselamatan publik"]
    ]],
    ["kode"=>"8","deskripsi"=>"ATURAN ORGANISASI","subitems"=>[
        ["kode"=>"8.1","deskripsi"=>"Keselamatan umum dan peraturan kesehatan"],
        ["kode"=>"8.2","deskripsi"=>"Khusus aturan kerja"],
        ["kode"=>"8.3","deskripsi"=>"Sistem izin kerja dan prosedur khusus"],
        ["kode"=>"8.4","deskripsi"=>"Pengetahuan tentang peraturan dan review"],
        ["kode"=>"8.5","deskripsi"=>"Usaha kepatuhan terhadap aturan"],
        ["kode"=>"8.6","deskripsi"=>"Penggunaan tanda/petunjuk dan kode warna sesuai"],
        ["kode"=>"8.7","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"9","deskripsi"=>"ACCIDENT/INCIDENT ANALYSIS","subitems"=>[
        ["kode"=>"9.1","deskripsi"=>"Statistik kinerja dihitung dan digunakan"],
        ["kode"=>"9.2","deskripsi"=>"Penyakit akibat kerja dan analisa penyakit"],
        ["kode"=>"9.3","deskripsi"=>"Analisa dan identifikasi terhadap kerusakan barang dan aset"],
        ["kode"=>"9.4","deskripsi"=>"Tim pemecah masalah"]
    ]],
    ["kode"=>"10","deskripsi"=>"PELATIHAN KARYAWAN","subitems"=>[
        ["kode"=>"10.1","deskripsi"=>"Training need analysis"],
        ["kode"=>"10.2","deskripsi"=>"Program pelatihan karyawan"],
        ["kode"=>"10.3","deskripsi"=>"Evaluasi program pelatihan"]
    ]],
    ["kode"=>"11","deskripsi"=>"ALAT PELINDUNG DIRI","subitems"=>[
        ["kode"=>"11.1","deskripsi"=>"Standar alat pelindung diri"],
        ["kode"=>"11.2","deskripsi"=>"Pencatatan alat pelindung diri"],
        ["kode"=>"11.3","deskripsi"=>"Penegakan standarisasi"],
        ["kode"=>"11.4","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"12","deskripsi"=>"PENGENDALIAN KESEHATAN","subitems"=>[
        ["kode"=>"12.1","deskripsi"=>"Identifikasi bahaya kesehatan"],
        ["kode"=>"12.2","deskripsi"=>"Kontrol bahaya kesehatan"],
        ["kode"=>"12.3","deskripsi"=>"Informasi / pelatihan / pendidikan"],
        ["kode"=>"12.4","deskripsi"=>"Pemantauan industrial hygiene"],
        ["kode"=>"12.5","deskripsi"=>"Program pemeliharaan kesehatan"],
        ["kode"=>"12.6","deskripsi"=>"Bantuan medis profesional"],
        ["kode"=>"12.7","deskripsi"=>"Komunikasi kesehatan untuk pekerja"],
        ["kode"=>"12.8","deskripsi"=>"Catatan perawatan/pemeliharaan"]
    ]],
    ["kode"=>"13","deskripsi"=>"PROGRAM SISTEM PENILAIAN","subitems"=>[
        ["kode"=>"13.1","deskripsi"=>"Kepatuhan dengan standar program audit secara menyeluruh"],
        ["kode"=>"13.2","deskripsi"=>"Kepatuhan dengan standar program audit dalam bentuk fisik"],
        ["kode"=>"13.3","deskripsi"=>"Kepatuhan dengan standar pencegahan dan pengendalian kebakaran"],
        ["kode"=>"13.4","deskripsi"=>"Kepatuhan dengan standar kesehatan kerja"],
        ["kode"=>"13.5","deskripsi"=>"Penyimpanan catatan tentang program sistem evaluasi"]
    ]],
    ["kode"=>"14","deskripsi"=>"ENGINEERING CONTROL","subitems"=>[
        ["kode"=>"14.1","deskripsi"=>"Desain keselamatan dan konsep pertimbangan teknik kesehatan"],
        ["kode"=>"14.2","deskripsi"=>"Proses rekayasa keselamatan dan desain pertimbangan kesehatan"],
        ["kode"=>"14.3","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"15","deskripsi"=>"KOMUNIKASI PERORANGAN","subitems"=>[
        ["kode"=>"15.1","deskripsi"=>"Pelatihan teknik komunikasi secara perorangan"],
        ["kode"=>"15.2","deskripsi"=>"Orientasi kerja perorangan / pelantikan baru / orang yang dipindahkan"],
        ["kode"=>"15.3","deskripsi"=>"Instruksi tugas pelatihan dan penggunaan yang benar"],
        ["kode"=>"15.4","deskripsi"=>"Rencana program kontak perorangan"],
        ["kode"=>"15.5","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"16","deskripsi"=>"RAPAT (GROUP MEETING)","subitems"=>[
        ["kode"=>"16.1","deskripsi"=>"Rapat/group meeting diadakan"],
        ["kode"=>"16.2","deskripsi"=>"Catatan tentang kejadian, bantuan visual, absensi, pembahasan masalah"],
        ["kode"=>"16.3","deskripsi"=>"Keterlibatan manajemen dari tingkat menengah sampai atas"],
        ["kode"=>"16.4","deskripsi"=>"Pemantauan program secara berkala"]
    ]],
    ["kode"=>"17","deskripsi"=>"PROMOSION UMUM","subitems"=>[
        ["kode"=>"17.1","deskripsi"=>"Program keselamatan bulletin board"],
        ["kode"=>"17.2","deskripsi"=>"Program penggunaan statistik dan fakta"],
        ["kode"=>"17.3","deskripsi"=>"Topik promosi yang penting"],
        ["kode"=>"17.4","deskripsi"=>"Penggunaan penghargaan atau pengenalan"],
        ["kode"=>"17.5","deskripsi"=>"Program informasi publik"],
        ["kode"=>"17.6","deskripsi"=>"Promosi performa grup"],
        ["kode"=>"17.7","deskripsi"=>"Promosi housekeeping"],
        ["kode"=>"17.8","deskripsi"=>"Catatan aktivitas untuk program promosi"]
    ]],
    ["kode"=>"18","deskripsi"=>"PEREKRUTAN DAN PENEMPATAN","subitems"=>[
        ["kode"=>"18.1","deskripsi"=>"Analisa kemampuan fisik"],
        ["kode"=>"18.2","deskripsi"=>"Pemeriksaan fisik sebelum bekerja"],
        ["kode"=>"18.3","deskripsi"=>"Orientasi umum / program pelantikan"],
        ["kode"=>"18.4","deskripsi"=>"Pengecekan sebelum pengangkatan dan penempatan karyawan"]
    ]],
    ["kode"=>"19","deskripsi"=>"KONTROL PEMBELIAN","subitems"=>[
        ["kode"=>"19.1","deskripsi"=>"Spesifikasi pengadaan dalam pembelian meliputi kesehatan dan keselamatan"],
        ["kode"=>"19.2","deskripsi"=>"Seleksi dan pengendalian kontraktor"]
    ]],
    ["kode"=>"20","deskripsi"=>"KESELAMATAN KERJA KETIKA OFF DUTY (Tidak Bertugas)","subitems"=>[
        ["kode"=>"20.1","deskripsi"=>"Pelaporan statistik dan analisa"],
        ["kode"=>"20.2","deskripsi"=>"Menginformasikan tentang berita keselamatan bagi yang tidak bertugas"]
    ]]
];

$jenis_kerugian_options = ["Manusia","Aset","Lingkungan","Keuangan","Reputasi"];

$insiden_type_options = [
    ""=>"Pilih Jenis Insiden",
    "FATALITY"=>"FATALITY",
    "DAYS AWAY FROM WORK (DAWF)"=>"DAYS AWAY FROM WORK (DAWF)",
    "TOTAL RECORDABLE INCIDENT (TRI)"=>"TOTAL RECORDABLE INCIDENT (TRI)",
    "MEDICAL TREATMENT CASE (MTC)"=>"MEDICAL TREATMENT CASE (MTC)",
    "FIRST AID CASE"=>"FIRST AID CASE",
    "MOTOR VEHICLE CRASH (MVC)"=>"MOTOR VEHICLE CRASH (MVC)",
    "OIL SPILL (TUMPAHAN > 200L)"=>"OIL SPILL (TUMPAHAN > 200L)",
    "FIRE EXPLOSION"=>"FIRE EXPLOSION",
    "PROPERTY DAMAGE"=>"PROPERTY DAMAGE",
    "NEAR MISS"=>"NEAR MISS"
];

$jenis_tindakan_options = [
    ""=>"Pilih Jenis Tindakan","Korektif"=>"Korektif","Pencegahan"=>"Pencegahan",
    "Perbaikan"=>"Perbaikan","Peningkatan"=>"Peningkatan","Lainnya"=>"Lainnya"
];

$status_tindakan_options = [
    "Pending"=>"Pending","On Progress"=>"On Progress",
    "Completed"=>"Completed","Closed"=>"Closed"
];

function e($string) { return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); }
function formatRupiah($angka) {
    if (empty($angka) || $angka == 0) return '';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
function checkFactorSelected($kode, $tipe, $personil_factors, $pekerjaan_factors) {
    $arr = ($tipe === 'personil') ? $personil_factors : $pekerjaan_factors;
    foreach ($arr as $factor) {
        if (isset($factor['kode_faktor']) && $factor['kode_faktor'] == $kode) return true;
    }
    return false;
}
function getFileIcon($fileType) {
    $ext = strtolower(pathinfo($fileType, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'fas fa-file-pdf';
        case 'doc': case 'docx': return 'fas fa-file-word';
        case 'xls': case 'xlsx': return 'fas fa-file-excel';
        case 'jpg': case 'jpeg': case 'png': case 'gif': return 'fas fa-file-image';
        default: return 'fas fa-file';
    }
}
function getFileIconClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'pdf';
        case 'doc': case 'docx': return 'word';
        case 'xls': case 'xlsx': return 'excel';
        case 'jpg': case 'jpeg': case 'png': case 'gif': return 'image';
        default: return 'generic';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Insiden SCAT - <?php echo e($insiden['judul_insiden']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --primary:#2c3e50;--secondary:#3498db;--success:#27ae60;--warning:#f39c12;
            --danger:#e74c3c;--edit-color:#ff9900;--light:#ecf0f1;--gray:#95a5a6;
            --dark:#2c3e50;--card-bg:#ffffff;
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Arial,sans-serif}
        body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);color:#333;min-height:100vh;display:flex}
        .sidebar{width:260px;background:var(--primary);color:#fff;height:100vh;position:fixed;padding:30px 0;box-shadow:3px 0 10px rgba(0,0,0,.1);z-index:1000}
        .sidebar-header{padding:0 25px 30px;text-align:center;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
        .sidebar-header h2{color:#fff;font-size:1.5rem;margin-bottom:5px}
        .user-profile{display:flex;align-items:center;padding:15px 25px;background:rgba(0,0,0,.1);margin:20px 15px;border-radius:10px}
        .user-avatar{width:45px;height:45px;background:var(--edit-color);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.2rem;margin-right:15px}
        .nav-menu{padding:0 15px}
        .nav-item{display:flex;align-items:center;color:#fff;padding:14px 20px;text-decoration:none;border-radius:8px;margin-bottom:8px;transition:all .3s}
        .nav-item i{margin-right:12px;font-size:1.1rem;width:25px}
        .nav-item:hover{background:rgba(255,255,255,.1);transform:translateX(5px)}
        .nav-item.active{background:var(--edit-color)}
        .logout-btn{background:var(--danger);margin-top:20px}
        .main-content{margin-left:260px;flex:1;padding:30px;overflow-y:auto;max-height:100vh}
        .container{max-width:1200px;margin:0 auto}
        .card{background:var(--card-bg);border-radius:12px;padding:25px;margin-bottom:25px;box-shadow:0 4px 15px rgba(0,0,0,.08);border-left:5px solid var(--edit-color)}
        .required:after{content:" *";color:var(--danger)}
        .input-with-icon{position:relative;display:flex;align-items:center}
        .input-with-icon i{position:absolute;left:15px;color:var(--gray)}
        .input-with-icon input{padding-left:45px}
        .checkbox-group-column{display:flex;flex-direction:column;gap:10px;margin-top:10px}
        .penyebab-item{display:flex;align-items:flex-start;gap:10px;padding:12px 15px;background:#f8f9fa;border-radius:8px;margin-bottom:8px;border:1px solid #e0e0e0;cursor:pointer;transition:all .2s}
        .penyebab-item:hover{background:#e8f4fc;border-color:var(--edit-color)}
        .penyebab-item .item-number{font-weight:bold;color:var(--edit-color);min-width:30px}
        .sub-tabel{background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;border-left:4px solid var(--edit-color)}
        .sub-tabel h4{color:var(--primary);margin-bottom:15px;display:flex;align-items:center;gap:10px}
        .faktor-analysis-section{background:var(--card-bg);border-radius:12px;padding:25px;margin-bottom:25px;box-shadow:0 4px 15px rgba(0,0,0,.08);border-left:5px solid var(--edit-color)}
        .faktor-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .faktor-header h3{color:var(--primary);font-size:20px;margin:0;display:flex;align-items:center;gap:10px}
        .faktor-controls{display:flex;gap:15px;align-items:center}
        .search-box-faktor{position:relative;display:flex;align-items:center}
        .search-box-faktor i{position:absolute;left:15px;color:var(--gray)}
        .search-box-faktor input{padding:10px 15px 10px 40px;border:2px solid #e0e0e0;border-radius:6px;width:250px}
        .faktor-table{width:100%;border-collapse:collapse;background:#fff}
        .faktor-table th{padding:15px 12px;text-align:left;background:linear-gradient(135deg,var(--edit-color) 0%,#ff7700 100%);color:#fff;font-weight:600;position:sticky;top:0;z-index:10}
        .faktor-table td{padding:12px 15px;border-bottom:1px solid #f0f0f0}
        .faktor-table tbody tr:hover{background-color:#f8f9fa}
        .category-header{background-color:#e8f4fc!important;font-weight:600;font-size:16px}
        .category-header td{padding:15px;color:var(--primary)}
        .kode-column{font-weight:bold;color:var(--edit-color)}
        .radio-group-can{display:flex;gap:15px;justify-content:center}
        .radio-group-can label{display:flex;align-items:center;gap:5px;cursor:pointer;padding:5px 10px;background:#f8f9fa;border-radius:4px;border:1px solid #e0e0e0}
        .radio-group-can label:hover{background:#e8f4fc}
        .not-selected{color:var(--gray);font-style:italic}
        .selected-item-type{padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold}
        .personil-type{background:#e3f2fd;color:#1565c0}
        .pekerjaan-type{background:#e8f5e9;color:#2e7d32}
        .remove-selected{background:none;border:none;color:var(--danger);cursor:pointer;padding:5px;border-radius:4px}
        .remove-selected:hover{background:rgba(231,76,60,.1)}
        .selection-stats{display:flex;justify-content:space-around;margin-top:20px;padding-top:20px;border-top:1px solid #e0e0e0}
        .stat-box{text-align:center;padding:10px}
        .stat-value{font-size:24px;font-weight:bold;color:var(--edit-color)}
        .stat-label{font-size:14px;color:var(--gray)}
        .file-icon{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:6px;margin-right:10px}
        .file-icon.pdf{background:#ffebee;color:#f44336}
        .file-icon.word{background:#e3f2fd;color:#2196f3}
        .file-icon.excel{background:#e8f5e9;color:#4caf50}
        .file-icon.image{background:#f3e5f5;color:#9c27b0}
        .file-icon.generic{background:#f5f5f5;color:#757575}
        .existing-file-info{display:flex;align-items:center;gap:10px}
        .existing-file-actions{display:flex;align-items:center;gap:10px}
        .btn-sm{padding:6px 12px;font-size:14px}
        .file-item{display:flex;align-items:center;gap:10px;padding:10px;background:#fff;border:1px solid #e0e0e0;border-radius:6px;margin-bottom:8px}
        .btn-remove-file{background:none;border:none;color:var(--danger);cursor:pointer;margin-left:auto}
        .tindakan-actions{display:flex;justify-content:flex-end;margin-top:15px}
        .error-message{color:var(--danger);font-size:14px;margin-top:5px}
        .card-header{display:flex;align-items:center;gap:15px;margin-bottom:25px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h3{color:var(--primary);font-size:20px;margin:0}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--primary)}
        .form-group input[type="text"],.form-group input[type="date"],.form-group input[type="time"],.form-group input[type="number"],.form-group textarea,.form-group select{width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;transition:border .3s}
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus{border-color:var(--edit-color);outline:none}
        .form-group textarea{min-height:120px;resize:vertical}
        .field-group{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .checkbox-group{display:flex;flex-wrap:wrap;gap:15px;margin-top:10px}
        .checkbox-group label{display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 15px;background:#f8f9fa;border-radius:6px;border:1px solid #e0e0e0;transition:all .2s}
        .checkbox-group label:hover{background:#e8f4fc;border-color:var(--edit-color)}
        .checkbox-group-large{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;margin-top:15px}
        .jenis-kejadian-item{display:flex;align-items:flex-start;gap:10px;padding:12px 15px;background:#f8f9fa;border-radius:8px;border:1px solid #e0e0e0;cursor:pointer;transition:all .2s}
        .jenis-kejadian-item:hover{background:#e8f4fc;border-color:var(--edit-color)}
        .faktor-table-container{overflow-x:auto;max-height:600px;border:1px solid #e0e0e0;border-radius:8px;margin-top:15px}
        .faktor-master-table{width:100%;border-collapse:collapse;background:#fff}
        .faktor-master-table thead{background:linear-gradient(135deg,var(--edit-color) 0%,#ff7700 100%);position:sticky;top:0;z-index:10}
        .faktor-master-table th{padding:15px 12px;text-align:left;color:#fff;font-weight:600}
        .faktor-master-table td{padding:12px 15px;border-bottom:1px solid #f0f0f0}
        .faktor-master-table tbody tr:hover{background-color:#f8f9fa}
        .category-row{background-color:#e8f4fc!important;font-weight:600;font-size:16px}
        .category-row td{padding:15px;color:var(--primary)}
        .selected-factors-display{background:#f0f7ff;padding:20px;border-radius:8px;margin-top:20px;border-left:4px solid var(--edit-color)}
        .selected-list{max-height:200px;overflow-y:auto;padding:10px;background:#fff;border-radius:6px;border:1px solid #e0e0e0}
        .selected-item{display:flex;justify-content:space-between;align-items:center;padding:10px 15px;margin-bottom:8px;background:#f8f9fa;border-radius:6px;border-left:3px solid var(--edit-color)}
        .tindakan-item{background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:15px;border:1px solid #e0e0e0}
        .tindakan-item:hover{background:#e8f4fc;border-color:var(--edit-color)}
        .file-upload-container{border:2px dashed #ddd;padding:15px;border-radius:5px;background-color:#f9f9f9}
        .existing-files-container{background:#f8f9fa;padding:15px;border-radius:8px;margin-top:10px;border:1px solid #e0e0e0}
        .existing-file-item{display:flex;align-items:center;justify-content:space-between;padding:10px;background:#fff;border-radius:6px;margin-bottom:8px;border-left:3px solid var(--edit-color)}
        .btn{padding:14px 28px;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:10px;transition:all .3s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.1)}
        .btn-primary{background:linear-gradient(135deg,var(--edit-color) 0%,#ff7700 100%);color:#fff}
        .btn-secondary{background:var(--gray);color:#fff}
        .btn-success{background:linear-gradient(135deg,var(--success) 0%,#219653 100%);color:#fff}
        .btn-danger{background:linear-gradient(135deg,var(--danger) 0%,#c0392b 100%);color:#fff}
        .btn-container{display:flex;justify-content:center;gap:20px;margin-top:30px;padding-top:30px;border-top:1px solid #e0e0e0}
        .alert-message{padding:15px 20px;border-radius:8px;margin-bottom:25px;display:flex;align-items:center;gap:15px;animation:slideIn .5s ease}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .mt-3{margin-top:15px}
        .text-muted{color:#6c757d;font-size:.875em}
        .empty-selection{text-align:center;color:var(--gray);font-style:italic;padding:20px}
        .info-box{background:#e8f4fc;border-left:4px solid var(--edit-color);padding:15px;border-radius:6px;margin-bottom:20px}
        .status-indicator{display:flex;align-items:center;gap:10px;padding:15px 20px;background:#e8f4fc;border-radius:8px;margin-bottom:25px;border-left:4px solid var(--edit-color)}
        .status-dot{width:12px;height:12px;background:var(--edit-color);border-radius:50%;animation:pulse 2s infinite}
        @keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
        @media(max-width:992px){.sidebar{width:80px}.sidebar-header h2,.user-info,.nav-item span{display:none}.nav-item{justify-content:center;padding:15px}.nav-item i{margin-right:0;font-size:1.3rem}.main-content{margin-left:80px;padding:20px}}
        @media(max-width:768px){body{flex-direction:column}.sidebar{width:100%;height:auto;position:relative;padding:15px 0}.main-content{margin-left:0}.field-group{grid-template-columns:1fr}.checkbox-group-large{grid-template-columns:1fr}.btn-container{flex-direction:column}.btn{justify-content:center}}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>SCAT</h2>
            <div class="version">Sistem Catatan Analisis Tindakan v1.0</div>
        </div>
        <div class="user-profile">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'User', 0, 2)); ?></div>
            <div class="user-info">
                <h4><?php echo e($_SESSION['fullname'] ?? 'User'); ?></h4>
                <div class="role"><?php echo e(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
            </div>
        </div>
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
            <a href="form_input_scat.php" class="nav-item"><i class="fas fa-plus-circle"></i><span>Input SCAT</span></a>
            <a href="logout.php" class="nav-item logout-btn"><i class="fas fa-sign-out-alt"></i><span>Keluar</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if (isset($error_message)): ?>
            <div class="alert-message alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo e($error_message); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert-message alert-success"><i class="fas fa-check-circle"></i> <?php echo e($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>

            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Edit semua data analisis SCAT. Semua perubahan akan disimpan saat Anda klik "Simpan Perubahan".</span>
            </div>

            <form method="POST" action="" id="editInsidenForm" novalidate enctype="multipart/form-data">
                <input type="hidden" name="download_pdf" id="downloadPDFFlag" value="0">
                <div id="hiddenFactorsContainer"></div>

                <!-- DATA DASAR -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-info-circle"></i><h3>Data Dasar Insiden</h3></div>
                    <div class="form-group">
                        <label class="required">Judul Insiden</label>
                        <input type="text" name="judul_insiden" required value="<?php echo e($insiden['judul_insiden']); ?>">
                    </div>
                    <div class="field-group">
                        <div class="form-group"><label class="required">Tanggal Kejadian</label><input type="date" name="tanggal" required value="<?php echo e($insiden['tanggal']); ?>"></div>
                        <div class="form-group"><label>Waktu Kejadian</label><input type="time" name="waktu_kejadian" value="<?php echo e($insiden['waktu_kejadian'] ?? ''); ?>"></div>
                    </div>
                    <div class="field-group">
                        <div class="form-group"><label class="required">Lokasi Kejadian</label><input type="text" name="lokasi" required value="<?php echo e($insiden['lokasi']); ?>"></div>
                        <div class="form-group"><label class="required">Departemen Terkait</label><input type="text" name="departemen_insiden" required value="<?php echo e($insiden['departemen_insiden'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="required">Deskripsi Kejadian</label>
                        <textarea id="input_deskripsi" name="deskripsi_kejadian" rows="6" required><?php echo e($insiden['deskripsi_kejadian']); ?></textarea>
                        <div class="mt-3"><button type="button" class="btn btn-secondary" id="clearDeskripsi"><i class="fas fa-trash"></i> Hapus Deskripsi</button></div>
                    </div>
                    <div class="field-group">
                        <div class="form-group">
                            <label class="required">Insiden Type</label>
                            <select name="insiden_type" class="insiden-type-select" required>
                                <?php foreach ($insiden_type_options as $value => $label): ?>
                                <option value="<?php echo e($value); ?>" <?php echo ($insiden['insiden_type'] == $value) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="required">Nama Penyelidik</label><input type="text" name="penyelidik" required value="<?php echo e($insiden['penyelidik'] ?? ''); ?>"></div>
                    </div>
                </div>

                <!-- IDENTITAS -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-building"></i><h3>Identitas Insiden (Opsional)</h3></div>
                    <div class="form-group"><label>Entitas</label><input type="text" name="Entitas" value="<?php echo e($identitas['entitas'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Cabang</label><textarea id="Cabang" name="Cabang" rows="3"><?php echo e($identitas['cabang'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label>Project</label><textarea id="Proect" name="Proect" rows="3"><?php echo e($identitas['proect'] ?? ''); ?></textarea></div>
                </div>

                <!-- KORBAN -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-user-injured"></i><h3>Data Korban (Opsional)</h3></div>
                    <div class="form-group"><label>Nama Korban</label><input type="text" name="nama_korban" value="<?php echo e($korban['nama'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Jabatan Korban</label><input type="text" name="jabatan_korban" value="<?php echo e($korban['jabatan'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Bagian Tubuh yang Terkena</label><input type="text" name="bagian_tubuh" value="<?php echo e($korban['bagian_tubuh'] ?? ''); ?>"></div>
                </div>

                <!-- SAKSI & KERUGIAN -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-users"></i><h3>Data Saksi dan Kerugian (Opsional)</h3></div>
                    <div class="form-group">
                        <label>Saksi yang Melihat Kejadian</label>
                        <textarea name="saksi" rows="3" placeholder="Pisahkan dengan koma..."><?php echo e(implode(', ', $saksi_list)); ?></textarea>
                        <small class="text-muted">Contoh: Budi Santoso, Siti Rahayu</small>
                    </div>
                    <div class="field-group">
                        <div class="form-group">
                            <label>Perkiraan Kerugian (Rp)</label>
                            <div class="input-with-icon"><i class="fas fa-money-bill-wave"></i>
                                <input type="text" name="perkiraan_kerugian" value="<?php echo formatRupiah($insiden['perkiraan_kerugian'] ?? 0); ?>" oninput="formatCurrency(this)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Potensi Kerugian (Rp)</label>
                            <div class="input-with-icon"><i class="fas fa-chart-line"></i>
                                <input type="text" name="potensi_kerugian" value="<?php echo formatRupiah($insiden['potensi_kerugian'] ?? 0); ?>" oninput="formatCurrency(this)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ANALISIS INSIDEN -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-search"></i><h3>Analisis Insiden (Opsional)</h3></div>
                    <div class="info-box"><i class="fas fa-info-circle"></i><span>Bagian ini opsional.</span></div>
                    <div class="form-group">
                        <label>Jenis Kerugian</label>
                        <div class="checkbox-group">
                            <?php foreach ($jenis_kerugian_options as $item): ?>
                            <label><input type="checkbox" name="jenis_kerugian[]" value="<?= $item ?>" <?= in_array($item, $jenis_kerugian) ? 'checked' : '' ?>><?= $item ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ANALISIS RISIKO -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-search"></i><h3>Analisis Risiko</h3></div>
                    <div class="form-group">
                        <label>Tingkat Risiko</label>
                        <select name="tingkat_risiko" class="insiden-type-select">
                            <option value="">Pilih Tingkat Risiko</option>
                            <?php foreach (['Low','Medium','High','Very High','Extreme','Tidak Dianalisis'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($insiden['tingkat_risiko'] ?? '') == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="margin-top:15px;padding:10px;background:#f8f9fa;border-radius:6px;">
                            <strong>Tingkat Risiko Saat Ini:</strong> <span style="font-weight:bold;"><?= e($insiden['tingkat_risiko'] ?? 'Belum Ditentukan') ?></span>
                        </div>
                    </div>
                </div>

                <!-- TABEL 1 -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-exclamation-triangle"></i><h3>TABEL 1: Jenis Kontak dengan Energi atau Zat</h3></div>
                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Pilih satu atau lebih:</label>
                        <div class="checkbox-group-large">
                            <?php foreach ($tabel1_jenis_kejadian as $num => $jenis): ?>
                            <label class="jenis-kejadian-item">
                                <input type="checkbox" name="jenis_kejadian[]" value="<?= htmlspecialchars($jenis) ?>" <?= in_array($jenis, $jenis_kejadian) ? 'checked' : '' ?>>
                                <span class="jenis-number"><?= $num ?>.</span>
                                <span class="jenis-desc"><?= htmlspecialchars($jenis) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- TABEL 2 -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-search"></i><h3>TABEL 2: Penyebab Langsung (IC's)</h3></div>
                    <div class="tabel2-container">
                        <div class="sub-tabel">
                            <h4><i class="fas fa-user-times"></i> TINDAKAN / PRAKTEK TIDAK STANDAR</h4>
                            <div class="checkbox-group-column">
                                <?php foreach ($tabel2a_tindakan as $num => $tindakan): ?>
                                <label class="penyebab-item">
                                    <input type="checkbox" name="penyebab_tindakan[]" value="<?= htmlspecialchars($tindakan) ?>" <?= in_array($tindakan, $penyebab_tindakan) ? 'checked' : '' ?>>
                                    <span class="item-number"><?= $num ?>.</span>
                                    <span class="item-desc"><?= htmlspecialchars($tindakan) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAKTOR ANALISIS -->
                <div class="faktor-analysis-section">
                    <div class="faktor-header">
                        <h3><i class="fas fa-users-cog"></i> Penyebab Dasar / Basic Cause (BC's)</h3>
                        <div class="faktor-controls">
                            <div class="search-box-faktor"><i class="fas fa-search"></i><input type="text" id="searchFaktor" placeholder="Cari faktor..."></div>
                            <button type="button" class="btn btn-secondary" id="clearFaktor"><i class="fas fa-trash"></i> Hapus Pilihan</button>
                        </div>
                    </div>
                    <div class="faktor-table-container">
                        <table class="faktor-master-table">
                            <thead><tr><th width="8%">Kode</th><th width="72%">Deskripsi Faktor</th><th width="10%">Kategori</th><th width="10%" class="checkbox-cell">Pilih</th></tr></thead>
                            <tbody>
                                <?php foreach ($faktor_data as $kategori => $items): $tipe = ($kategori == 'FAKTOR PERSONIL') ? 'personil' : 'pekerjaan'; ?>
                                <tr class="category-row"><td colspan="4"><i class="fas fa-folder"></i> <?= $kategori ?></td></tr>
                                <?php foreach ($items as $num => $data): ?>
                                <tr class="main-factor-row">
                                    <td><strong><?= $num ?></strong></td>
                                    <td><strong><?= htmlspecialchars($data['title']) ?></strong></td>
                                    <td><?= $kategori ?></td>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="factor-checkbox main-factor"
                                               data-kategori="<?= $kategori ?>" data-kode="<?= $num ?>"
                                               data-deskripsi="<?= htmlspecialchars($data['title']) ?>"
                                               data-tipe="<?= $tipe ?>"
                                               value="<?= $num ?>|<?= htmlspecialchars($data['title']) ?>"
                                               <?= checkFactorSelected($num, $tipe, $faktor_personil, $faktor_pekerjaan) ? 'checked' : '' ?>>
                                    </td>
                                </tr>
                                <?php foreach ($data['sub'] as $subCode => $subDesc): ?>
                                <tr class="sub-factor-row">
                                    <td class="sub-factor-code"><?= $subCode ?></td>
                                    <td class="sub-factor-desc"><?= htmlspecialchars($subDesc) ?></td>
                                    <td><?= $kategori ?></td>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="factor-checkbox sub-factor"
                                               data-kategori="<?= $kategori ?>" data-kode="<?= $subCode ?>"
                                               data-deskripsi="<?= htmlspecialchars($subDesc) ?>"
                                               data-tipe="<?= $tipe ?>" data-parent="<?= $num ?>"
                                               value="<?= $subCode ?>|<?= htmlspecialchars($subDesc) ?>"
                                               <?= checkFactorSelected($subCode, $tipe, $faktor_personil, $faktor_pekerjaan) ? 'checked' : '' ?>>
                                    </td>
                                </tr>
                                <?php endforeach; endforeach; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="selected-factors-display">
                        <h4><i class="fas fa-check-circle"></i> Faktor Terpilih</h4>
                        <div class="selected-list" id="selectedFaktorList"></div>
                        <div class="selection-stats">
                            <div class="stat-box"><div class="stat-value" id="personilCount">0</div><div class="stat-label">Personil</div></div>
                            <div class="stat-box"><div class="stat-value" id="pekerjaanCount">0</div><div class="stat-label">Pekerjaan</div></div>
                            <div class="stat-box"><div class="stat-value" id="totalCount">0</div><div class="stat-label">Total</div></div>
                        </div>
                    </div>
                </div>

                <!-- CAN -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-layer-group"></i><h3>Control Action Needs (CAN)</h3>
                        <small style="color:#7f8c8d;margin-left:10px;font-style:italic;">Pilih Y (Ya) atau N (Tidak)</small>
                    </div>
                    <div class="faktor-table-container">
                        <table class="faktor-table">
                            <thead><tr><th width="10%">Kode</th><th width="50%">Deskripsi Faktor</th><th width="13%">(P) Program</th><th width="13%">(S) Standar</th><th width="13%">(C) Kepatuhan</th></tr></thead>
                            <tbody>
                            <?php $index = 0;
                            foreach ($can_data as $category):
                                $category_can_data = array_filter($penyebab_dasar_can, function($item) use ($category) {
                                    return strpos($item['kode'], $category['kode'] . '.') === 0;
                                });
                            ?>
                                <tr class="category-header"><td colspan="5"><i class="fas fa-folder"></i> <?= e($category['kode']) ?>. <?= e($category['deskripsi']) ?></td></tr>
                                <?php foreach ($category['subitems'] as $subitem):
                                    $existing = null;
                                    foreach ($category_can_data as $can_item) {
                                        if ($can_item['kode'] == $subitem['kode']) { $existing = $can_item; break; }
                                    }
                                ?>
                                <tr>
                                    <td class="kode-column"><?= e($subitem['kode']) ?></td>
                                    <td class="deskripsi-column">
                                        <?= e($subitem['deskripsi']) ?>
                                        <input type="hidden" name="penyebab_dasar[<?= $index ?>][kode]" value="<?= $subitem['kode'] ?>">
                                        <input type="hidden" name="penyebab_dasar[<?= $index ?>][deskripsi]" value="<?= htmlspecialchars($subitem['deskripsi']) ?>">
                                    </td>
                                    <?php foreach (['program_p','standar_s','kepatuhan_c'] as $field): ?>
                                    <td class="radio-column">
                                        <div class="radio-group-can">
                                            <label title="Ya"><input type="radio" name="penyebab_dasar[<?= $index ?>][<?= $field ?>]" value="Y" <?= ($existing && $existing[$field] == 'Y') ? 'checked' : '' ?>> Y</label>
                                            <label title="Tidak"><input type="radio" name="penyebab_dasar[<?= $index ?>][<?= $field ?>]" value="N" <?= ($existing && $existing[$field] == 'N') ? 'checked' : '' ?>> N</label>
                                        </div>
                                        <?php if (!$existing || $existing[$field] == ''): ?><div class="not-selected" style="font-size:12px;margin-top:5px;">Belum dipilih</div><?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php $index++; endforeach; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TINDAKAN PERBAIKAN -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-tools"></i><h3>Tindakan Perbaikan dan Pencegahan</h3></div>
                    <div id="tindakanContainer">
                        <?php $tindakanCounter = 0;
                        if (!empty($tindakan_perbaikan)):
                            foreach ($tindakan_perbaikan as $index => $tindakan): $tindakanCounter++; ?>
                        <div class="tindakan-item" data-index="<?= $index ?>">
                            <input type="hidden" name="tindakan_perbaikan[<?= $index ?>][id]" value="<?= $tindakan['id'] ?>">
                            <div class="field-group">
                                <div class="form-group">
                                    <label>Jenis Tindakan</label>
                                    <select name="tindakan_perbaikan[<?= $index ?>][jenis_tindakan]" class="jenis-tindakan-select">
                                        <?php foreach ($jenis_tindakan_options as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (($tindakan['jenis_tindakan'] ?? '') == $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group"><label>Penanggung Jawab</label><input type="text" name="tindakan_perbaikan[<?= $index ?>][penanggungjawab]" value="<?= e($tindakan['penanggungjawab'] ?? '') ?>"></div>
                            </div>
                            <div class="form-group">
                                <label style="color:var(--edit-color);font-weight:bold;"><i class="fas fa-lightbulb"></i> Rekomendasi / Usulan Tindakan</label>
                                <textarea name="tindakan_perbaikan[<?= $index ?>][rekomendasi]" rows="3"><?= htmlspecialchars($tindakan['rekomendasi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label style="color:var(--secondary);font-weight:bold;"><i class="fas fa-clipboard-check"></i> Deskripsi Tindakan Nyata</label>
                                <textarea name="tindakan_perbaikan[<?= $index ?>][deskripsi]" rows="4"><?= htmlspecialchars($tindakan['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Lampiran/Dokumen Pendukung Baru</label>
                                <div class="file-upload-container">
                                    <input type="file" name="tindakan_perbaikan[<?= $index ?>][lampiran][]" class="file-upload-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple onchange="previewFiles(this, <?= $index ?>)">
                                    <div class="file-preview" id="filePreview<?= $index ?>"></div>
                                </div>
                                <small class="text-muted">Format: PDF, DOC, XLS, JPG, PNG (Maks. 5MB)</small>
                            </div>
                            <?php if (!empty($tindakan['files'])): ?>
                            <div class="form-group">
                                <label style="color:var(--success);font-weight:bold;"><i class="fas fa-paperclip"></i> Lampiran Terpasang</label>
                                <div class="existing-files-container">
                                    <?php foreach ($tindakan['files'] as $file): ?>
                                    <div class="existing-file-item">
                                        <div class="existing-file-info">
                                            <div class="file-icon <?= getFileIconClass($file['nama_file']) ?>"><i class="<?= getFileIcon($file['nama_file']) ?>"></i></div>
                                            <div><strong><?= htmlspecialchars($file['nama_file']) ?></strong><br><small>Ukuran: <?= round($file['ukuran']/1024, 1) ?> KB</small></div>
                                        </div>
                                        <div class="existing-file-actions">
                                            <a href="<?= e($file['path_file']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
                                            <label style="margin:0;cursor:pointer;">
                                                <input type="checkbox" name="delete_files[]" value="<?= $file['id'] ?>" style="margin-right:5px;">
                                                <span style="color:var(--danger);font-size:12px;">Hapus</span>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="field-group">
                                <div class="form-group"><label>Target Selesai</label><input type="date" name="tindakan_perbaikan[<?= $index ?>][target_selesai]" value="<?= e($tindakan['target_selesai'] ?? '') ?>"></div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="tindakan_perbaikan[<?= $index ?>][status]">
                                        <?php foreach ($status_tindakan_options as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= ($tindakan['status'] == $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="tindakan-actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="removeTindakan(this, <?= $tindakan['id'] ?? 0 ?>)"><i class="fas fa-trash"></i> Hapus Tindakan</button>
                            </div>
                            <hr style="margin:20px 0;">
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="form-group mt-3">
                        <button type="button" class="btn btn-primary" id="addTindakan"><i class="fas fa-plus"></i> Tambah Tindakan Perbaikan</button>
                    </div>
                </div>

                <!-- TOMBOL AKSI -->
                <div class="btn-container">
                    <button type="button" class="btn btn-secondary" id="resetForm"><i class="fas fa-redo"></i> Reset Form</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    <button type="button" class="btn btn-success" id="saveAndPDF"><i class="fas fa-file-download"></i> Simpan & Download PDF</button>
                    <a href="detail_insiden.php?id=<?= $insiden_id ?>" class="btn btn-danger"><i class="fas fa-times"></i> Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let tindakanCounter = <?php echo count($tindakan_perbaikan); ?>;
        let deletedTindakanIds = [];

        document.getElementById('addTindakan').addEventListener('click', function() {
            const container = document.getElementById('tindakanContainer');
            const newIndex = tindakanCounter++;
            const newTindakan = `
                <div class="tindakan-item" data-index="${newIndex}">
                    <input type="hidden" name="tindakan_perbaikan[${newIndex}][id]" value="">
                    <div class="field-group">
                        <div class="form-group">
                            <label>Jenis Tindakan</label>
                            <select name="tindakan_perbaikan[${newIndex}][jenis_tindakan]" class="jenis-tindakan-select">
                                <?php foreach ($jenis_tindakan_options as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Penanggung Jawab</label><input type="text" name="tindakan_perbaikan[${newIndex}][penanggungjawab]"></div>
                    </div>
                    <div class="form-group">
                        <label style="color:var(--edit-color);font-weight:bold;"><i class="fas fa-lightbulb"></i> Rekomendasi / Usulan Tindakan</label>
                        <textarea name="tindakan_perbaikan[${newIndex}][rekomendasi]" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="color:var(--secondary);font-weight:bold;"><i class="fas fa-clipboard-check"></i> Deskripsi Tindakan Nyata</label>
                        <textarea name="tindakan_perbaikan[${newIndex}][deskripsi]" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Lampiran/Dokumen Pendukung Baru</label>
                        <div class="file-upload-container">
                            <input type="file" name="tindakan_perbaikan[${newIndex}][lampiran][]" class="file-upload-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple onchange="previewFiles(this, ${newIndex})">
                            <div class="file-preview" id="filePreview${newIndex}"></div>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="form-group"><label>Target Selesai</label><input type="date" name="tindakan_perbaikan[${newIndex}][target_selesai]"></div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="tindakan_perbaikan[${newIndex}][status]">
                                <?php foreach ($status_tindakan_options as $key => $label): ?>
                                <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="tindakan-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeTindakan(this, 0)"><i class="fas fa-trash"></i> Hapus Tindakan</button>
                    </div>
                    <hr style="margin:20px 0;">
                </div>`;
            container.insertAdjacentHTML('beforeend', newTindakan);
        });

        window.removeTindakan = function(button, tindakanId) {
            if (!confirm('Apakah Anda yakin ingin menghapus tindakan ini?')) return;
            const item = button.closest('.tindakan-item');
            if (tindakanId > 0) {
                if (!deletedTindakanIds.includes(tindakanId)) {
                    deletedTindakanIds.push(tindakanId);
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'deleted_tindakan[]';
                    inp.value = tindakanId;
                    document.getElementById('editInsidenForm').appendChild(inp);
                }
            }
            item.remove();
        };

        // Faktor
        let selectedFactors = { personil: [], pekerjaan: [] };
        <?php foreach ($faktor_personil as $f) {
            echo "selectedFactors.personil.push({kode:'" . addslashes($f['kode_faktor']) . "',deskripsi:'" . addslashes($f['deskripsi']) . "'});\n";
        }
        foreach ($faktor_pekerjaan as $f) {
            echo "selectedFactors.pekerjaan.push({kode:'" . addslashes($f['kode_faktor']) . "',deskripsi:'" . addslashes($f['deskripsi']) . "'});\n";
        } ?>

        function updateSelectedFactorsDisplay() {
            const list = document.getElementById('selectedFaktorList');
            let html = '';
            selectedFactors.personil.forEach(f => {
                html += `<div class="selected-item"><div>${f.kode} — ${f.deskripsi}</div>
                    <span class="selected-item-type personil-type">Personil</span>
                    <button type="button" class="remove-selected" onclick="removeFactor('${f.kode}','personil')"><i class="fas fa-times"></i></button></div>`;
            });
            selectedFactors.pekerjaan.forEach(f => {
                html += `<div class="selected-item"><div>${f.kode} — ${f.deskripsi}</div>
                    <span class="selected-item-type pekerjaan-type">Pekerjaan</span>
                    <button type="button" class="remove-selected" onclick="removeFactor('${f.kode}','pekerjaan')"><i class="fas fa-times"></i></button></div>`;
            });
            if (!html) html = '<div class="empty-selection">Belum ada faktor yang dipilih</div>';
            list.innerHTML = html;
            document.getElementById('personilCount').textContent = selectedFactors.personil.length;
            document.getElementById('pekerjaanCount').textContent = selectedFactors.pekerjaan.length;
            document.getElementById('totalCount').textContent = selectedFactors.personil.length + selectedFactors.pekerjaan.length;
            updateCheckboxStates();
            updateHiddenFactorInputs();
        }
        window.removeFactor = function(kode, tipe) {
            selectedFactors[tipe] = selectedFactors[tipe].filter(f => f.kode !== kode);
            updateSelectedFactorsDisplay();
        };
        function updateCheckboxStates() {
            document.querySelectorAll('.factor-checkbox').forEach(cb => {
                const tipe = cb.dataset.tipe;
                const kode = cb.dataset.kode;
                cb.checked = selectedFactors[tipe].some(f => f.kode === kode);
            });
        }
        function updateHiddenFactorInputs() {
            const container = document.getElementById('hiddenFactorsContainer');
            container.innerHTML = '';
            selectedFactors.personil.forEach(f => {
                const inp = document.createElement('input');
                inp.type='hidden'; inp.name='faktor_personil[]';
                inp.value = f.kode + '|' + f.deskripsi;
                container.appendChild(inp);
            });
            selectedFactors.pekerjaan.forEach(f => {
                const inp = document.createElement('input');
                inp.type='hidden'; inp.name='faktor_pekerjaan[]';
                inp.value = f.kode + '|' + f.deskripsi;
                container.appendChild(inp);
            });
        }
        document.querySelectorAll('.factor-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const tipe = this.dataset.tipe;
                const kode = this.dataset.kode;
                const deskripsi = this.dataset.deskripsi;
                if (this.checked) {
                    if (!selectedFactors[tipe].some(f => f.kode === kode)) {
                        selectedFactors[tipe].push({kode, deskripsi});
                    }
                } else {
                    selectedFactors[tipe] = selectedFactors[tipe].filter(f => f.kode !== kode);
                }
                updateSelectedFactorsDisplay();
            });
        });
        document.getElementById('searchFaktor').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.faktor-master-table tbody tr').forEach(row => {
                if (row.classList.contains('category-row')) { row.style.display=''; return; }
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
        document.getElementById('clearFaktor').addEventListener('click', function() {
            if (confirm('Hapus semua pilihan faktor?')) {
                selectedFactors.personil = []; selectedFactors.pekerjaan = [];
                updateSelectedFactorsDisplay();
            }
        });
        document.getElementById('clearDeskripsi').addEventListener('click', function() {
            if (confirm('Hapus deskripsi kejadian?')) document.getElementById('input_deskripsi').value = '';
        });

        // Files
        window.previewFiles = function(input, index) {
            const previewContainer = document.getElementById('filePreview' + index);
            if (!previewContainer) return;
            previewContainer.innerHTML = '';
            const validExt = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'];
            const maxSize = 5 * 1024 * 1024;
            Array.from(input.files).forEach(file => {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!validExt.includes(ext)) { alert(`File ${file.name} tidak diizinkan.`); input.value=''; return; }
                if (file.size > maxSize) { alert(`File ${file.name} > 5MB.`); input.value=''; return; }
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `<i class="fas fa-file"></i><span>${file.name} (${(file.size/1024).toFixed(1)} KB)</span>`;
                previewContainer.appendChild(div);
            });
        };

        // Currency
        function formatCurrency(input) {
            let v = input.value.replace(/[^\d]/g, '');
            input.value = v ? 'Rp ' + parseInt(v,10).toLocaleString('id-ID') : '';
        }

        // Reset & PDF
        document.getElementById('resetForm').addEventListener('click', function() {
            if (confirm('Reset semua perubahan ke nilai asli?')) window.location.reload();
        });
        document.getElementById('saveAndPDF').addEventListener('click', function() {
            document.getElementById('downloadPDFFlag').value = '1';
            document.getElementById('editInsidenForm').submit();
        });

        // Submit validation
        document.getElementById('editInsidenForm').addEventListener('submit', function(e) {
            updateHiddenFactorInputs();
            const required = document.querySelectorAll('[required]');
            let valid = true;
            required.forEach(f => {
                if (!f.value.trim()) {
                    valid = false;
                    f.style.borderColor = '#e74c3c';
                } else {
                    f.style.borderColor = '#e0e0e0';
                }
            });
            if (!valid) { e.preventDefault(); alert('Harap lengkapi field wajib!'); return false; }
            return true;
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[name="perkiraan_kerugian"],input[name="potensi_kerugian"]').forEach(inp => {
                if (inp.value) formatCurrency(inp);
            });
            updateSelectedFactorsDisplay();
        });
    </script>
</body>
</html>