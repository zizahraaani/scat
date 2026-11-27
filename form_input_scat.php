<?php
// form_input_scat_complete.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === CEK STRUKTUR TABEL ===
function checkAndCreateTables($mysqli) {
    // Array tabel yang akan dibuat
    $tables = [
        // Update CREATE TABLE insiden untuk menambahkan kolom baru
        'insiden' => "
CREATE TABLE IF NOT EXISTS insiden (
    id INT PRIMARY KEY AUTO_INCREMENT,
    insiden_number VARCHAR(50) UNIQUE,  -- KOLOM BARU UNTUK NOMOR INSIDEN MANUAL
    judul_insiden VARCHAR(255) NOT NULL,
    tanggal DATE NOT NULL,
    waktu_kejadian DATETIME,
    lokasi VARCHAR(255) NOT NULL,
    departemen_insiden VARCHAR(100) DEFAULT NULL,
    nama_manajer_departemen VARCHAR(100) DEFAULT NULL,
    insiden_type VARCHAR(50) DEFAULT NULL,
    penyelidik VARCHAR(255) DEFAULT NULL,
    deskripsi_kejadian TEXT NOT NULL,
    tingkat_risiko VARCHAR(50),
    perkiraan_kerugian DECIMAL(15,2) DEFAULT 0,
    potensi_kerugian DECIMAL(15,2) DEFAULT 0,
    dibuat_oleh INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
",

       // Ganti definisi tabel tindakan_perbaikan dengan yang ini:
'tindakan_perbaikan' => "CREATE TABLE IF NOT EXISTS tindakan_perbaikan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    insiden_id INT NOT NULL,
    jenis_tindakan VARCHAR(100) NOT NULL,
    deskripsi TEXT NOT NULL,
    rekomendasi TEXT, -- KOLOM BARU
    target_selesai DATE,
    penanggungjawab VARCHAR(100),
    status VARCHAR(20) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
)",

        'jenis_kejadian' => "CREATE TABLE IF NOT EXISTS jenis_kejadian (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            deskripsi TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'faktor_analisis' => "CREATE TABLE IF NOT EXISTS faktor_analisis (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            kategori VARCHAR(100) NOT NULL,
            kode_faktor VARCHAR(20) NOT NULL,
            deskripsi TEXT NOT NULL,
            tipe ENUM('personil', 'pekerjaan') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'jenis_kerugian' => "CREATE TABLE IF NOT EXISTS jenis_kerugian (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            jenis VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'identitas' => "CREATE TABLE IF NOT EXISTS identitas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            entitas VARCHAR(100) NOT NULL,
            cabang TEXT NOT NULL,
            proect VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'penyebab_langsung' => "CREATE TABLE IF NOT EXISTS penyebab_langsung (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            tipe VARCHAR(20) NOT NULL,
            deskripsi TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'penyebab_dasar' => "CREATE TABLE IF NOT EXISTS penyebab_dasar (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            kode VARCHAR(10) NOT NULL,
            deskripsi TEXT NOT NULL,
            program_p VARCHAR(1) DEFAULT '',
            standar_s VARCHAR(1) DEFAULT '',
            kepatuhan_c VARCHAR(1) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'korban' => "CREATE TABLE IF NOT EXISTS korban (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            nama VARCHAR(100),
            jabatan VARCHAR(100),
            bagian_tubuh VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'saksi' => "CREATE TABLE IF NOT EXISTS saksi (
            id INT PRIMARY KEY AUTO_INCREMENT,
            insiden_id INT NOT NULL,
            nama_saksi VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (insiden_id) REFERENCES insiden(id) ON DELETE CASCADE
        )",
        
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama_lengkap VARCHAR(100) NOT NULL,
            departemen VARCHAR(100) DEFAULT NULL,
            email VARCHAR(100),
            role VARCHAR(20) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Tabel baru untuk file lampiran
        'tindakan_files' => "CREATE TABLE IF NOT EXISTS tindakan_files (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tindakan_id INT NOT NULL,
            nama_file VARCHAR(255) NOT NULL,
            path_file VARCHAR(500) NOT NULL,
            ukuran INT NOT NULL,
            tipe VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tindakan_id) REFERENCES tindakan_perbaikan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    // Cek dan buat setiap tabel
    foreach ($tables as $tableName => $createQuery) {
        $check = $mysqli->query("SHOW TABLES LIKE '$tableName'");
        if ($check->num_rows == 0) {
            if (!$mysqli->query($createQuery)) {
                error_log("Gagal membuat tabel $tableName: " . $mysqli->error);
            } else {
                error_log("Tabel $tableName berhasil dibuat");
                
                // Insert default data untuk users jika tabel users baru dibuat
                if ($tableName == 'users') {
                    $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
                    $mysqli->query("INSERT INTO users (username, password, nama_lengkap, email, departemen, role) 
                                   VALUES ('admin', '$default_pass', 'Administrator', 'admin@scat.com', 'HRD', 'admin')");
                }
            }
        }
        if (isset($check)) $check->free();
    }
    
    // Cek dan tambah kolom nama_manajer_departemen jika belum ada
    $checkColumn = $mysqli->query("SHOW COLUMNS FROM insiden LIKE 'nama_manajer_departemen'");
    if ($checkColumn && $checkColumn->num_rows == 0) {
        $alterQuery = "ALTER TABLE insiden ADD COLUMN nama_manajer_departemen VARCHAR(100) DEFAULT NULL AFTER departemen_insiden";
        if ($mysqli->query($alterQuery)) {
            error_log("Kolom nama_manajer_departemen berhasil ditambahkan");
        } else {
            error_log("Gagal menambahkan kolom: " . $mysqli->error);
        }
    }
    if (isset($checkColumn)) $checkColumn->free();
    
    // Cek dan tambah kolom insiden_number jika belum ada
    $checkColumn = $mysqli->query("SHOW COLUMNS FROM insiden LIKE 'insiden_number'");
    if ($checkColumn && $checkColumn->num_rows == 0) {
        $alterQuery = "ALTER TABLE insiden ADD COLUMN insiden_number VARCHAR(50) UNIQUE AFTER id";
        if ($mysqli->query($alterQuery)) {
            error_log("Kolom insiden_number berhasil ditambahkan");
        } else {
            error_log("Gagal menambahkan kolom insiden_number: " . $mysqli->error);
        }
    }
    if (isset($checkColumn)) $checkColumn->free();
    
    // Cek dan tambah kolom rekomendasi jika belum ada
    $checkColumn = $mysqli->query("SHOW COLUMNS FROM tindakan_perbaikan LIKE 'rekomendasi'");
    if ($checkColumn && $checkColumn->num_rows == 0) {
        $alterQuery = "ALTER TABLE tindakan_perbaikan ADD COLUMN rekomendasi TEXT AFTER deskripsi";
        if ($mysqli->query($alterQuery)) {
            error_log("Kolom rekomendasi berhasil ditambahkan");
        } else {
            error_log("Gagal menambahkan kolom rekomendasi: " . $mysqli->error);
        }
    }
    if (isset($checkColumn)) $checkColumn->free();
}

// Panggil fungsi untuk membuat tabel
checkAndCreateTables($mysqli);

$message = '';
$success = false;
$insiden_id = null;

// Fungsi untuk generate nomor insiden otomatis
function generateInsidenNumber($mysqli) {
    $year = date('Y');
    $month = date('m');
    
    // Cari nomor terakhir untuk tahun dan bulan ini
    $query = "SELECT insiden_number FROM insiden WHERE insiden_number LIKE 'INS-{$year}{$month}-%' ORDER BY insiden_number DESC LIMIT 1";
    $result = $mysqli->query($query);
    
    if ($result && $result->num_rows > 0) {
        $last_number = $result->fetch_assoc()['insiden_number'];
        // Ekstrak angka terakhir
        $last_num = intval(substr($last_number, -3));
        $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_num = '001';
    }
    
    return "INS-{$year}{$month}-{$next_num}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Tampilkan semua data POST
    error_log("POST Data: " . print_r($_POST, true));
    
    // Sanitize input data
    $insiden_number = trim($mysqli->real_escape_string($_POST['insiden_number'] ?? ''));
    $judul_insiden = trim($mysqli->real_escape_string($_POST['judul_insiden'] ?? ''));
    $tanggal = $_POST['tanggal'] ?? '';
    $waktu_kejadian = $_POST['waktu_kejadian'] ?? '';
    $lokasi = trim($mysqli->real_escape_string($_POST['lokasi'] ?? ''));
    $departemen_insiden = trim($mysqli->real_escape_string($_POST['departemen_insiden'] ?? ''));
    $nama_manajer_departemen = trim($mysqli->real_escape_string($_POST['nama_manajer_departemen'] ?? ''));
    $deskripsi_kejadian = trim($mysqli->real_escape_string($_POST['deskripsi_kejadian'] ?? ''));
    $jenis_kerugian = $_POST['jenis_kerugian'] ?? [];
    $tingkat_risiko = $_POST['tingkat_risiko'] ?? 'Tidak Dianalisis';
    
    // Data untuk identitas
    $entitas = trim($mysqli->real_escape_string($_POST['Entitas'] ?? ''));
    $cabang = trim($mysqli->real_escape_string($_POST['Cabang'] ?? ''));
    $proect = trim($mysqli->real_escape_string($_POST['Proect'] ?? ''));
    
    // Data korban
    $nama_korban = trim($mysqli->real_escape_string($_POST['nama_korban'] ?? ''));
    $jabatan_korban = trim($mysqli->real_escape_string($_POST['jabatan_korban'] ?? ''));
    $bagian_tubuh = trim($mysqli->real_escape_string($_POST['bagian_tubuh'] ?? ''));
    
    // Data saksi
    $saksi_input = trim($mysqli->real_escape_string($_POST['saksi'] ?? ''));
    $saksi_list = array_filter(array_map('trim', explode(',', $saksi_input)));
    
    // Data kerugian - DIPERBAIKI: Gunakan raw values dari hidden inputs
    $perkiraan_kerugian = isset($_POST['perkiraan_kerugian_raw']) ? 
        floatval($_POST['perkiraan_kerugian_raw']) : 0;
    
    // Handle potensi kerugian - SEDERHANA (tanpa operator)
    $potensi_kerugian = isset($_POST['potensi_kerugian_raw']) ? 
        floatval($_POST['potensi_kerugian_raw']) : 0;
    
    // Debug logging untuk troubleshooting
    error_log("DEBUG - Perkiraan Kerugian: " . $perkiraan_kerugian);
    error_log("DEBUG - Potensi Kerugian: " . $potensi_kerugian);
    
    // Data untuk tabel lainnya
    $jenis_kejadian = $_POST['jenis_kejadian'] ?? [];
    $penyebab_tindakan = $_POST['penyebab_tindakan'] ?? [];
    $penyebab_dasar = $_POST['penyebab_dasar'] ?? [];
    $jenis_kerugian = isset($_POST['jenis_kerugian']) && is_array($_POST['jenis_kerugian']) ? $_POST['jenis_kerugian'] : [];
    $faktor_personil = isset($_POST['faktor_personil']) && is_array($_POST['faktor_personil']) ? $_POST['faktor_personil'] : [];
    $faktor_pekerjaan = isset($_POST['faktor_pekerjaan']) && is_array($_POST['faktor_pekerjaan']) ? $_POST['faktor_pekerjaan'] : [];
    $insiden_type = trim($mysqli->real_escape_string($_POST['insiden_type'] ?? ''));
    $penyelidik = trim($mysqli->real_escape_string($_POST['penyelidik'] ?? ''));
    $tindakan_perbaikan = $_POST['tindakan_perbaikan'] ?? [];
    
    // AMBIL TINGKAT RISIKO DARI FORM
    $tingkat_risiko = $_POST['tingkat_risiko'] ?? 'Tidak Dianalisis';

    // Debug log untuk memastikan data diterima
    error_log("Tingkat risiko dari form: " . $tingkat_risiko);

    // Tambahkan validasi
    $errors = [];
    if (empty($insiden_type)) $errors[] = 'Insiden Type wajib diisi';
    if (empty($penyelidik)) $errors[] = 'Nama penyelidik wajib diisi';
    
    // Validasi input wajib
    if (empty($judul_insiden)) $errors[] = 'Judul insiden wajib diisi';
    if (empty($tanggal)) $errors[] = 'Tanggal kejadian wajib diisi';
    if (empty($lokasi)) $errors[] = 'Lokasi kejadian wajib diisi';
    if (empty($departemen_insiden)) $errors[] = 'Departemen terkait wajib diisi';
    if (empty($nama_manajer_departemen)) $errors[] = 'Nama manajer departemen wajib diisi';
    
    // Validasi insiden number
    if (!empty($insiden_number)) {
        // Cek apakah insiden_number sudah ada
        $check_stmt = $mysqli->prepare("SELECT id FROM insiden WHERE insiden_number = ?");
        $check_stmt->bind_param('s', $insiden_number);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $errors[] = 'Nomor insiden sudah digunakan. Silakan gunakan nomor lain.';
        }
        $check_stmt->close();
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
    } else {
        // Jika insiden_number kosong, generate otomatis
        if (empty($insiden_number)) {
            $insiden_number = generateInsidenNumber($mysqli);
        }
        
        // Mulai transaction
        $mysqli->begin_transaction();
        
        try {
            // INSERT ke tabel insiden
            $stmt = $mysqli->prepare("
                INSERT INTO insiden (
                    insiden_number,
                    judul_insiden,         
                    tanggal,               
                    waktu_kejadian,          
                    lokasi,                
                    departemen_insiden,     
                    nama_manajer_departemen,
                    insiden_type,         
                    penyelidik,           
                    deskripsi_kejadian,      
                    tingkat_risiko,          
                    perkiraan_kerugian,      
                    potensi_kerugian,        
                    dibuat_oleh              
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $mysqli->error);
            }

            // Parameter: 14 total
            $stmt->bind_param('sssssssssssddi',
                $insiden_number,
                $judul_insiden,              
                $tanggal,
                $waktu_kejadian,            
                $lokasi,                     
                $departemen_insiden,          
                $nama_manajer_departemen,     
                $insiden_type,                
                $penyelidik,                  
                $deskripsi_kejadian,          
                $tingkat_risiko,              
                $perkiraan_kerugian,       
                $potensi_kerugian,         
                $_SESSION['user_id']       
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $insiden_id = $stmt->insert_id;
            $stmt->close();
            
            error_log("Insiden berhasil disimpan. ID: $insiden_id, Nomor: $insiden_number");
            error_log("Perkiraan kerugian: $perkiraan_kerugian, Potensi kerugian: $potensi_kerugian");
            
            // INSERT jenis kerugian (jika ada)
            if (!empty($jenis_kerugian)) {
                $stmt = $mysqli->prepare("INSERT INTO jenis_kerugian (insiden_id, jenis) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare jenis_kerugian failed: " . $mysqli->error);
                }
                
                foreach ($jenis_kerugian as $jenis) {
                    $jenis_clean = trim($mysqli->real_escape_string($jenis));
                    $stmt->bind_param('is', $insiden_id, $jenis_clean);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute jenis_kerugian failed: " . $stmt->error);
                    }
                }
                $stmt->close();
            }
            
            // INSERT identitas (jika ada)
            if (!empty($entitas) && !empty($cabang)) {
                $stmt = $mysqli->prepare("INSERT INTO identitas (insiden_id, entitas, cabang, proect) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare identitas failed: " . $mysqli->error);
                }
                
                $stmt->bind_param('isss', $insiden_id, $entitas, $cabang, $proect);
                if (!$stmt->execute()) {
                    throw new Exception("Execute identitas failed: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // INSERT data korban (jika ada)
            if (!empty($nama_korban)) {
                $stmt = $mysqli->prepare("INSERT INTO korban (insiden_id, nama, jabatan, bagian_tubuh) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare korban failed: " . $mysqli->error);
                }
                
                $stmt->bind_param('isss', $insiden_id, $nama_korban, $jabatan_korban, $bagian_tubuh);
                if (!$stmt->execute()) {
                    throw new Exception("Execute korban failed: " . $stmt->error);
                }
                $stmt->close();
            }
            
            // INSERT data saksi (jika ada)
            if (!empty($saksi_list)) {
                $stmt = $mysqli->prepare("INSERT INTO saksi (insiden_id, nama_saksi) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare saksi failed: " . $mysqli->error);
                }
                
                foreach ($saksi_list as $saksi_nama) {
                    if (!empty(trim($saksi_nama))) {
                        $stmt->bind_param('is', $insiden_id, $saksi_nama);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute saksi failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // INSERT Tindakan Perbaikan dengan FILE HANDLING
            if (!empty($tindakan_perbaikan) && is_array($tindakan_perbaikan)) {
                error_log("DEBUG - Memulai penyimpanan " . count($tindakan_perbaikan) . " tindakan perbaikan");
                
                foreach ($tindakan_perbaikan as $index => $tindakan) {
                    // Pastikan data minimal ada
                    if (!empty($tindakan['jenis_tindakan']) && !empty($tindakan['deskripsi'])) {
                        $jenis_tindakan = $mysqli->real_escape_string($tindakan['jenis_tindakan'] ?? '');
                        $deskripsi = $mysqli->real_escape_string($tindakan['deskripsi'] ?? '');
                        $rekomendasi = $mysqli->real_escape_string($tindakan['rekomendasi'] ?? '');
                        $penanggungjawab = $mysqli->real_escape_string($tindakan['penanggungjawab'] ?? '');
                        $target_selesai = !empty($tindakan['target_selesai']) ? $tindakan['target_selesai'] : NULL;
                        $status = $mysqli->real_escape_string($tindakan['status'] ?? 'Pending');
                        
                        error_log("DEBUG - Tindakan $index: Jenis=$jenis_tindakan, Penanggungjawab=$penanggungjawab");
                        
                        $sql = "INSERT INTO tindakan_perbaikan 
                               (insiden_id, jenis_tindakan, deskripsi, rekomendasi, target_selesai, penanggungjawab, status) 
                               VALUES ('$insiden_id', '$jenis_tindakan', '$deskripsi', '$rekomendasi', '$target_selesai', '$penanggungjawab', '$status')";
                        
                        if ($mysqli->query($sql)) {
                            $tindakan_id = $mysqli->insert_id;
                            error_log("DEBUG - Tindakan $index berhasil disimpan dengan ID: $tindakan_id");
                            
                            // HANDLE FILE UPLOAD jika ada
                            if (isset($_FILES['tindakan_perbaikan']['name'][$index]['lampiran'])) {
                                $files = $_FILES['tindakan_perbaikan']['name'][$index]['lampiran'];
                                
                                // Buat folder uploads jika belum ada
                                $uploadDir = 'uploads/tindakan/';
                                if (!is_dir($uploadDir)) {
                                    mkdir($uploadDir, 0755, true);
                                }
                                
                                for ($i = 0; $i < count($files); $i++) {
                                    if ($_FILES['tindakan_perbaikan']['error'][$index]['lampiran'][$i] === 0) {
                                        $originalName = $_FILES['tindakan_perbaikan']['name'][$index]['lampiran'][$i];
                                        $tempName = $_FILES['tindakan_perbaikan']['tmp_name'][$index]['lampiran'][$i];
                                        $fileSize = $_FILES['tindakan_perbaikan']['size'][$index]['lampiran'][$i];
                                        $fileType = $_FILES['tindakan_perbaikan']['type'][$index]['lampiran'][$i];
                                        
                                        // Generate unique filename
                                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                                        $storedName = 'tindakan_' . $tindakan_id . '_' . uniqid() . '.' . $extension;
                                        $uploadPath = $uploadDir . $storedName;
                                        
                                        // Pindahkan file
                                        if (move_uploaded_file($tempName, $uploadPath)) {
                                            // Simpan info file ke database
                                            $fileSql = "INSERT INTO tindakan_files 
                                                       (tindakan_id, nama_file, path_file, ukuran, tipe) 
                                                       VALUES ('$tindakan_id', '$originalName', '$uploadPath', '$fileSize', '$fileType')";
                                            if (!$mysqli->query($fileSql)) {
                                                error_log("ERROR - Gagal menyimpan file: " . $mysqli->error);
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            error_log("ERROR - Gagal menyimpan tindakan $index: " . $mysqli->error);
                            throw new Exception("Gagal menyimpan tindakan perbaikan: " . $mysqli->error);
                        }
                    } else {
                        error_log("WARNING - Tindakan $index tidak valid atau kosong: " . print_r($tindakan, true));
                    }
                }
            } else {
                error_log("WARNING - Tidak ada data tindakan perbaikan atau format salah");
            }
            
            // INSERT FAKTOR PERSONIL
            if (!empty($faktor_personil)) {
                $stmt = $mysqli->prepare("INSERT INTO faktor_analisis (insiden_id, kategori, kode_faktor, deskripsi, tipe) VALUES (?, ?, ?, ?, 'personil')");
                if (!$stmt) {
                    throw new Exception("Prepare faktor_personil failed: " . $mysqli->error);
                }
                
                foreach ($faktor_personil as $faktor_str) {
                    $parts = explode('|', $faktor_str, 2);
                    if (count($parts) >= 2) {
                        $kode = trim($parts[0]);
                        $deskripsi = trim($parts[1]);
                        
                        // Tentukan kategori berdasarkan kode
                        $kategori = 'FAKTOR PERSONIL';
                        if (strpos($kode, '1') === 0 || strpos($kode, '1.') === 0) {
                            $kategori = 'KETIDAKCAKAPAN FISIK/PSIKOLOGI';
                        } elseif (strpos($kode, '2') === 0 || strpos($kode, '2.') === 0) {
                            $kategori = 'KEKURANGAN KEMAMPUAN MENTAL';
                        } elseif (strpos($kode, '3') === 0 || strpos($kode, '3.') === 0) {
                            $kategori = 'TEKANAN FISIK';
                        } elseif (strpos($kode, '4') === 0 || strpos($kode, '4.') === 0) {
                            $kategori = 'TEKANAN MENTAL';
                        } elseif (strpos($kode, '5') === 0 || strpos($kode, '5.') === 0) {
                            $kategori = 'KURANGNYA PENGALAMAN';
                        } elseif (strpos($kode, '6') === 0 || strpos($kode, '6.') === 0) {
                            $kategori = 'KEMAMPUAN KURANG';
                        } elseif (strpos($kode, '7') === 0 || strpos($kode, '7.') === 0) {
                            $kategori = 'MOTIVASI TIDAK SESUAI';
                        }
                        
                        $stmt->bind_param('isss', $insiden_id, $kategori, $kode, $deskripsi);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute faktor_personil failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // INSERT FAKTOR PEKERJAAN
            if (!empty($faktor_pekerjaan)) {
                $stmt = $mysqli->prepare("INSERT INTO faktor_analisis (insiden_id, kategori, kode_faktor, deskripsi, tipe) VALUES (?, ?, ?, ?, 'pekerjaan')");
                if (!$stmt) {
                    throw new Exception("Prepare faktor_pekerjaan failed: " . $mysqli->error);
                }
                
                foreach ($faktor_pekerjaan as $faktor_str) {
                    $parts = explode('|', $faktor_str, 2);
                    if (count($parts) >= 2) {
                        $kode = trim($parts[0]);
                        $deskripsi = trim($parts[1]);
                        
                        // Tentukan kategori berdasarkan kode
                        $kategori = 'FAKTOR PEKERJAAN';
                        if (strpos($kode, '8') === 0 || strpos($kode, '8.') === 0) {
                            $kategori = 'KEPEMIMPINAN TIDAK MEMADAI';
                        } elseif (strpos($kode, '9') === 0 || strpos($kode, '9.') === 0) {
                            $kategori = 'PENGETAHUAN TEKNIK TIDAK MEMADAI';
                        } elseif (strpos($kode, '10') === 0 || strpos($kode, '10.') === 0) {
                            $kategori = 'PEMBELIAN TIDAK MEMADAI';
                        } elseif (strpos($kode, '11') === 0 || strpos($kode, '11.') === 0) {
                            $kategori = 'PERAWATAN TIDAK MEMADAI';
                        } elseif (strpos($kode, '12') === 0 || strpos($kode, '12.') === 0) {
                            $kategori = 'PERALATAN TIDAK MEMADAI';
                        } elseif (strpos($kode, '13') === 0 || strpos($kode, '13.') === 0) {
                            $kategori = 'STANDAR PEKERJAAN KURANG';
                        } elseif (strpos($kode, '14') === 0 || strpos($kode, '14.') === 0) {
                            $kategori = 'KEAUSAN DAN KERUSAKAN';
                        } elseif (strpos($kode, '15') === 0 || strpos($kode, '15.') === 0) {
                            $kategori = 'PENYALAHGUNAAN';
                        }
                        
                        $stmt->bind_param('isss', $insiden_id, $kategori, $kode, $deskripsi);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute faktor_pekerjaan failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // INSERT Jenis Kejadian (Tabel 1)
            if (!empty($jenis_kejadian)) {
                $stmt = $mysqli->prepare("INSERT INTO jenis_kejadian (insiden_id, deskripsi) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare jenis_kejadian failed: " . $mysqli->error);
                }
                
                foreach ($jenis_kejadian as $jenis) {
                    if (!empty(trim($jenis))) {
                        $jenis_clean = trim($mysqli->real_escape_string($jenis));
                        $stmt->bind_param('is', $insiden_id, $jenis_clean);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute jenis_kejadian failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // INSERT Penyebab Langsung - TINDAKAN
            if (!empty($penyebab_tindakan)) {
                $stmt = $mysqli->prepare("INSERT INTO penyebab_langsung (insiden_id, tipe, deskripsi) VALUES (?, 'tindakan', ?)");
                if (!$stmt) {
                    throw new Exception("Prepare penyebab_tindakan failed: " . $mysqli->error);
                }
                
                foreach ($penyebab_tindakan as $tindakan) {
                    if (!empty(trim($tindakan))) {
                        $tindakan_clean = trim($mysqli->real_escape_string($tindakan));
                        $stmt->bind_param('is', $insiden_id, $tindakan_clean);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute penyebab_tindakan failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // INSERT Penyebab Dasar - CONTROL ACTION NEEDS (CAN)
            if (!empty($penyebab_dasar)) {
                $stmt = $mysqli->prepare("INSERT INTO penyebab_dasar (insiden_id, kode, deskripsi, program_p, standar_s, kepatuhan_c) 
                                        VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare penyebab_dasar failed: " . $mysqli->error);
                }
                
                foreach ($penyebab_dasar as $item) {
                    if (!empty($item['kode']) && !empty($item['deskripsi'])) {
                        $kode = trim($mysqli->real_escape_string($item['kode']));
                        $deskripsi = trim($mysqli->real_escape_string($item['deskripsi']));
                        $program_p = isset($item['program_p']) && in_array($item['program_p'], ['Y', 'N']) ? $item['program_p'] : '';
                        $standar_s = isset($item['standar_s']) && in_array($item['standar_s'], ['Y', 'N']) ? $item['standar_s'] : '';
                        $kepatuhan_c = isset($item['kepatuhan_c']) && in_array($item['kepatuhan_c'], ['Y', 'N']) ? $item['kepatuhan_c'] : '';

                        $stmt->bind_param('isssss', $insiden_id, $kode, $deskripsi, $program_p, $standar_s, $kepatuhan_c);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute penyebab_dasar failed: " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            
            // Commit transaction
            $mysqli->commit();
            
            $success = true;
            $message = "Data insiden berhasil disimpan! ID: {$insiden_id}, Nomor: {$insiden_number}";
            error_log("SUCCESS: Insiden berhasil disimpan dengan potensi kerugian: $potensi_kerugian");
            
            // Redirect untuk download PDF jika diperlukan
            if (isset($_POST['download_pdf']) && $_POST['download_pdf'] == '1') {
                header("Location: generate_pdf.php?id=" . $insiden_id);
                exit;
            }
            
        } catch (Exception $e) {
            // Rollback transaction jika ada error
            $mysqli->rollback();
            $message = "Gagal menyimpan data: " . $e->getMessage();
            error_log("Error: " . $e->getMessage());
        }
    }
}

// ================= DATA FAKTOR PERSONIL DAN PEKERJAAN =================
$faktor_data = [
    "FAKTOR PERSONIL" => [
        "1" => [
            "title" => "KETIDAKCAKAPAN KEMAMPUAN FISIK/PSIKOLOGI",
            "sub" => [
                "1.1" => "Kekurangan Tinggi / Berat / Ukuran / Kekuatan Jangkauan, Dll.",
                "1.2" => "Jangkauan tubuh yang tidak diperbolehkan",
                "1.3" => "Keterbatasan dalam menjaga posisi badan",
                "1.4" => "Alergi atau sensitif terhadap zat",
                "1.5" => "Kepekaan panca indera (Suhu, Suara, Dll)",
                "1.6" => "Pandangan berkurang",
                "1.7" => "Pendengaran berkurang",
                "1.8" => "Panca indera berkurang (sentuhan, rasa, penciuman, keseimbangan)",
                "1.9" => "Kemampuan pernapasan berkurang",
                "1.10" => "Cacat fisik permanen lainnya",
                "1.11" => "Cacat fisik sementara",
                "1.12" => "Hasil Mcu ",
                "1.13" => "Sedang mengkonsumsi obat"
            ]
        ],
        "2" => [
            "title" => "KEKURANGAN KEMAMPUAN MENTAL / PSIKOLOGI",
            "sub" => [
                "2.1" => "Ketakutan dan fobia",
                "2.2" => "Gangguan emosional",
                "2.3" => "Penyakit kejiwaan",
                "2.4" => "Tingkat kecerdasan",
                "2.5" => "Ketidakmampuan mengikuti",
                "2.6" => "Penilaian yang buruk",
                "2.7" => "Koordinasi yang buruk",
                "2.8" => "Waktu reaksi lambat",
                "2.9" => "Bakat mekanikal rendah",
                "2.10" => "Bakat belajar rendah",
                "2.11" => "Kegagalan memori"
            ]
        ],
        "3" => [
            "title" => "TEKANAN FISIK / PSIKOLOGI",
            "sub" => [
                "3.1" => "Cedera penyakit",
                "3.2" => "Kelelahan karena Beban atau Durasi Tugas",
                "3.3" => "Kelelahan karena Kurang Istirahat",
                "3.4" => "Kelelahan karena Overload sensorik",
                "3.5" => "Paparan bahaya kesehatan",
                "3.6" => "Paparan suhu ekstrim",
                "3.7" => "Kekurangan oksigen",
                "3.8" => "Variasi tekanan atmosfer",
                "3.9" => "Kekurangan gizi darah",
                "3.10" => "Narkoba"
            ]
        ],
        "4" => [
            "title" => "TEKANAN MENTAL / PSIKOLOGI",
            "sub" => [
                "4.1" => "Emosi berlebihan",
                "4.2" => "Kelelahan karena beban mental",
                "4.3" => "Permintaan penilaian keputusan yang ekstrim",
                "4.4" => "Rutin, monoton, ketergantungan untuk tetap waspada",
                "4.5" => "Kebutuhan konsentrasi/persepsi yang ekstrim",
                "4.6" => "Kegiatan yang tidak berkesan atau menenangkan",
                "4.7" => "Arahan/permintaan yang membingungkan",
                "4.8" => "Arahan/permintaan yang berbenturan",
                "4.9" => "Keasyikan yang menggangu",
                "4.10" => "Frustasi",
                "4.11" => "Penyakit mental"
            ]
        ],
        "5" => [
            "title" => "KURANGNYA PENGALAMAN",
            "sub" => [
                "5.1" => "Kurang pengalaman",
                "5.2" => "Orientasi memadai",
                "5.3" => "Pelatihan awal yang tidak memadai",
                "5.4" => "Update pelatihan yang tidak memadai",
                "5.5" => "Arahan atau petunjuk yang salah dipahami"
            ]
        ],
        "6" => [
            "title" => "KEMAMPUAN KURANG",
            "sub" => [
                "6.1" => "Kekurangan instruksi awal",
                "6.2" => "Kekurangan latihan",
                "6.3" => "Tidak sering berlatih",
                "6.4" => "Kekurangan arahan",
                "6.5" => "Kekurangan telaah instruksi"
            ]
        ],
        "7" => [
            "title" => "MOTIVASI YANG TIDAK SESUAI",
            "sub" => [
                "7.1" => "Kinerja yang tidak tepat diberi hadiah",
                "7.2" => "Kinerja yang tidak tepat dihukum",
                "7.3" => "Kurangnya insentif",
                "7.4" => "Frustasi yang berlebihan",
                "7.5" => "Agresi yang berlebihan",
                "7.6" => "Upaya yang tidak tepat untuk menghemat waktu dan usaha",
                "7.7" => "Upaya yang tidak tepat untuk menghindari ketidaknyamanan",
                "7.8" => "Upaya-upaya yang tidak layak untuk mendapatkan perhatian",
                "7.9" => "Kedisiplinan yang tidak memadai",
                "7.10" => "Tekanan teman sebaya yang tidak pantas",
                "7.11" => "Teladan yang salah",
                "7.12" => "Performa tidak cukup timbal balik",
                "7.13" => "Tidak cukup dukungan atau sikap yang sesuai",
                "7.14" => "Tidak cukup insentif produksi"
            ]
        ]
    ],
    "FAKTOR PEKERJAAN" => [
        "8" => [
            "title" => "KEPEMIMPINAN DAN PENGAWASAN YANG TIDAK MEMADAI",
            "sub" => [
                "8.1" => "Hubungan pelaporan yang tidak jelas atau bertentangan",
                "8.2" => "Ketidakjelasan atau berbenturan dalam penunjukkan tanggung jawab",
                "8.3" => "Delegasi yang tidak tepat atau tidak cukup",
                "8.4" => "Memberikan kebijakan, prosedur, praktik atau pedoman yang tidak jelas",
                "8.5" => "Memberikan tujuan sasaran atau standar yang bertentangan",
                "8.6" => "Perencanaan dan pemrograman kerja yang tidak memadai",
                "8.7" => "Instruksi, orientasi dan atau pelatihan yang tidak memadai",
                "8.8" => "Tidak menyediakan dokumen referensi arahan dan petunjuk publikasi yang tidak memadai",
                "8.9" => "Identifikasi dan evaluasi paparan kerugian yang tidak memadai",
                "8.10" => "Kurangnya pengawasan/pengetahuan manajemen",
                "8.11" => "Pencocokan kualifikasi seseorang dan kebutuhan pekerjaan tidak sesuai",
                "8.12" => "Pengukuran kinerja dan evaluasi yang tidak memadai",
                "8.13" => "Kurangnya/tidak sesuainya umpan balik dari kinerja"
            ]
        ],
        "9" => [
            "title" => "PENGETAHUAN TEKNIK TIDAK MEMADAI",
            "sub" => [
                "9.1" => "Kurangnya perkiraan paparan kerugian",
                "9.2" => "Kurangnya pertimbangan terhadap faktor ergonomi manusia",
                "9.3" => "Kurangnya standar, spesifikasi dan atau kriteria standar",
                "9.4" => "Kurangnya pengawasan saat pembangunan",
                "9.5" => "Penilaian yang tidak memadai kesiapan operasional",
                "9.6" => "Pengendalian yang kurang atau tidak sesuai",
                "9.7" => "Kurangnya pengawasan saat mulainya pekerjaan",
                "9.8" => "Kurangnya evaluasi perubahan"
            ]
        ],
        "10" => [
            "title" => "PEMBELIAN YANG TIDAK/KURANG MEMADAI",
            "sub" => [
                "10.1" => "Spesifikasi yang tidak memadai sesuai permintaan resmi",
                "10.2" => "Penelitian yang tidak memadai pada bahan atau peralatan",
                "10.3" => "Spesifikasi kurang memadai untuk vendor",
                "10.4" => "Rute pengiriman yang kurang memadai",
                "10.5" => "Inspeksi penerimaan yang kurang memadai",
                "10.6" => "Komunikasi keselamatan dan data kesehatan yang tidak memadai",
                "10.7" => "Penanganan material yang tidak tepat",
                "10.8" => "Penyimpanan material yang tidak tepat",
                "10.9" => "Transportasi material yang tidak memadai",
                "10.10" => "Identifikasi barang berbahaya yang tidak memadai",
                "10.11" => "Penyelamatan yang tidak benar dan pembuangan limbah",
                "10.12" => "Pemilihan kontraktor yang tidak memadai"
            ]
        ],
        "11" => [
            "title" => "PERAWATAN TIDAK MEMADAI",
            "sub" => [
                "11.1" => "Kurangnya pencegahan",
                "11.1.1" => "Perkiraan kebutuhan",
                "11.1.2" => "Pelumasan dan perawatan",
                "11.1.3" => "Penyetelan atau perakitan",
                "11.1.4" => "Pembersihan atau pemolesan",
                "11.2" => "Kurangnya perbaikan",
                "11.2.1" => "Komunikasi kebutuhan",
                "11.2.2" => "Jadwal pekerjaan",
                "11.2.3" => "Pemeriksaan unit",
                "11.2.4" => "Penggantian komponen"
            ]
        ],
        "12" => [
            "title" => "PERALATAN DAN PERLENGKAPAN TIDAK MEMADAI",
            "sub" => [
                "12.1" => "Kurangnya perkiraan dan kebutuhan berdasarkan risiko",
                "12.2" => "Kurangnya pertimbangan faktor manusia dan ergonomi",
                "12.3" => "Kurangnya standar atau spesifikasi",
                "12.4" => "Kurangnya ketersediaan",
                "12.5" => "Kurangnya penyesuaian/perawatan",
                "12.6" => "Kurangnya melepas dan memasang bagian yang sesuai",
                "12.7" => "Kurangnya penggunaan kembali barang terpakai"
            ]
        ],
        "13" => [
            "title" => "STANDAR PEKERJAAN YANG KURANG MEMADAI",
            "sub" => [
                "13.1" => "Kurangnya pengembangan standar untuk:",
                "13.1.1" => "Evaluasi dan barang yang dibutuhkan",
                "13.1.2" => "Perancangan proses koordinasi",
                "13.1.3" => "Keterlibatan pekerja",
                "13.1.4" => "Prosedur/praktik/peraturan",
                "13.2" => "Kurangnya komunikasi tentang standar untuk:",
                "13.2.1" => "Publikasi",
                "13.2.2" => "Distribusi",
                "13.2.3" => "Penerjemahan ke dalam bahasa yang sesuai",
                "13.2.4" => "Pelatihan",
                "13.2.5" => "Didukung dengan tanda, warna kode dan bantuan pekerjaan",
                "13.3" => "Kurangnya perawatan standar untuk:",
                "13.3.1" => "Penelusuran alur pekerjaan",
                "13.3.2" => "Pembaruan",
                "13.3.3" => "Pengawasan penggunaan prosedur atau peraturan",
                "13.3.4" => "Kurangnya pengawasan atas kepuasan"
            ]
        ],
        "14" => [
            "title" => "KEAUSAN DAN KERUSAKAN SAAT MENJALANKAN",
            "sub" => [
                "14.1" => "Kurangnya rencana penggunaan",
                "14.2" => "Penambahan angka waktu yang tidak sesuai",
                "14.3" => "Kurangnya pemeriksaan dan atau pengawasan",
                "14.4" => "Ketidaksesuaian pembebanan atau penggunaan",
                "14.5" => "Kurangnya perawatan",
                "14.6" => "Digunakan oleh personel yang tidak berkualifikasi atau terlatih",
                "14.7" => "Penggunaan yang tidak sesuai"
            ]
        ],
        "15" => [
            "title" => "PENYALAHGUNAAN",
            "sub" => [
                "15.1" => "Perilaku tidak benar yang disetujui",
                "15.1.1" => "Disengaja",
                "15.1.2" => "Tidak disengaja",
                "15.2" => "Perilaku tidak benar yang tidak disetujui",
                "15.2.1" => "Disengaja",
                "15.2.2" => "Tidak disengaja"
            ]
        ]
    ]
];

// Data lainnya...
$tabel1_jenis_kejadian = [
    "1" => "Menabrak sesuatu (mengarah atau membentur sesuatu)",
    "2" => "Ditabrak oleh (dihantam oleh benda bergerak)",
    "3" => "Jatuh ke tingkat di bawah",
    "4" => "Jatuh pada tingkat yang sama",
    "5" => "Masuk ke dalam (terjepit dan tertusuk)",
    "6" => "Berada di atas (tersangkut, tergantung)",
    "7" => "Terkena di antara atau di bawah (terlindas atau terpotong)",
    "8" => "Bersentuhan dengan (listrik, panas, beku, radiasi, bahan beracun, bising)",
    "9" => "Stres berlebihan",
    "10" => "Kejadian Hampir celaka ",
    "11" => "Mengalami penyakit akibat kerja "
];

$tabel2a_tindakan = [
    "1" => "Mengoperasikan peralatan tanpa wewenang",
    "2" => "Kegagalan dalam memperingatkan",
    "3" => "Kegagalan dalam mengamankan",
    "4" => "Mengoperasikan dalam kecepatan yang tidak sesuai",
    "5" => "Membuat alat keselamatan tidak bisa dioperasikan",
    "6" => "Menggunakan peralatan yang cacat",
    "7" => "Kegagalan dalam menggunakan PPE yang sesuai",
    "8" => "Muatan tidak sesuai",
    "9" => "Penempatan tidak sesuai",
    "10" => "Pengangkatan tidak sesuai",
    "11" => "Posisi bekerja tidak sesuai",
    "12" => "Memperbaiki peralatan saat beroperasi",
    "13" => "Berkelakar",
    "14" => "Di bawah pengaruh alkohol atau drugs lainnya",
    "15" => "Menggunakan peralatan secara tidak sesuai",
    "16" => "Tidak cukup perlindungan atau penghalang",
    "17" => "Tidak cukup atau tidak sesuainya peralatan pelindung",
    "18" => "Peralatan atau material rusak",
    "19" => "Kemacetan atau kegiatan yang dibatasi",
    "20" => "Sistem peringatan tidak cukup",
    "21" => "Bahaya api dan ledakan",
    "22" => "Kebersihan/penempatan yang buruk",
    "23" => "Paparan suara bising",
    "24" => "Paparan radiasi",
    "25" => "Suhu ekstrim",
    "26" => "Pencahayaan yang kurang atau berlebihan",
    "27" => "Ventilasi kurang",
    "28" => "Kondisi lingkungan yang berbahaya"
];

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka) {
    if (empty($angka) || !is_numeric($angka)) return '';
    return 'Rp ' . number_format(floatval($angka), 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Input SCAT dengan Analisis Faktor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS styles - sama seperti sebelumnya */
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --gray: #95a5a6;
            --sidebar-bg: #2c3e50;
            --hover-bg: #34495e;
            --dark: #2c3e50;
            --card-bg: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 30px;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 25px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .sidebar-header .version {
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            background: rgba(0,0,0,0.1);
            margin: 20px 15px;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .user-info h4 {
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .user-info .role {
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .nav-menu {
            padding: 0 15px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            color: white;
            padding: 14px 20px;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-item i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 25px;
            text-align: center;
        }
        
        .nav-item:hover {
            background: var(--hover-bg);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: var(--secondary);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .logout-btn {
            background: var(--danger);
            margin-top: 20px;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        /* ================= MAIN CONTENT ================= */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            transition: all 0.3s ease;
            overflow-y: auto;
            max-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ================= CARDS ================= */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 5px solid var(--secondary);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header i {
            font-size: 24px;
            color: #3498db;
        }

        .card-header h3 {
            color: #2c3e50;
            font-size: 20px;
            margin: 0;
        }

        /* ================= FORM STYLES ================= */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group label.required::after {
            content: " *";
            color: #e74c3c;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* ================= FILE UPLOAD STYLES ================= */
        .file-upload-container {
            border: 2px dashed #ddd;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .file-upload-input {
            width: 100%;
            margin-bottom: 10px;
        }

        .file-preview {
            margin-top: 10px;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background: white;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .file-item i {
            margin-right: 10px;
            color: #666;
        }

        .file-item span {
            flex-grow: 1;
            font-size: 14px;
        }

        .btn-remove-file {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
        }

        .btn-remove-file:hover {
            color: #bd2130;
        }

        /* ================= CHECKBOX GROUPS ================= */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            transition: all 0.2s;
        }

        .checkbox-group label:hover {
            background: #e8f4fc;
            border-color: #3498db;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3498db;
        }

        /* ================= TABEL 1: JENIS KEJADIAN ================= */
        .checkbox-group-large {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .jenis-kejadian-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 60px;
        }

        .jenis-kejadian-item:hover {
            background: #e8f4fc;
            border-color: #3498db;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .jenis-kejadian-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3498db;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .jenis-number {
            font-weight: bold;
            color: #3498db;
            min-width: 25px;
            flex-shrink: 0;
        }

        .jenis-desc {
            flex-grow: 1;
            color: #333;
            line-height: 1.4;
        }

        /* ================= TABEL 2: PENYEBAB LANGSUNG ================= */
        .tabel2-container {
            margin-top: 20px;
        }

        .sub-tabel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }

        .sub-tabel h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sub-tabel h4 i {
            color: #3498db;
        }

        .checkbox-group-column {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 12px;
        }

        .penyebab-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 60px;
        }

        .penyebab-item:hover {
            background: #e8f4fc;
            border-color: #3498db;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        .penyebab-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3498db;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .item-number {
            font-weight: bold;
            color: #e74c3c;
            min-width: 25px;
            flex-shrink: 0;
        }

        .item-desc {
            flex-grow: 1;
            color: #333;
            line-height: 1.4;
        }

        /* ================= ANALISIS RISIKO ================= */
        .radio-group-simple {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .radio-item:hover {
            background: #e8f4fc;
            border-color: #3498db;
        }

        .radio-item input[type="radio"] {
            display: none;
        }

        .radio-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #95a5a6;
            display: inline-block;
            position: relative;
            transition: all 0.3s ease;
        }

        .radio-item input[type="radio"]:checked + .radio-dot {
            border-color: #3498db;
            background: #3498db;
        }

        .radio-item input[type="radio"]:checked + .radio-dot::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .radio-item input[type="radio"]:checked ~ span:last-child {
            color: #3498db;
            font-weight: 600;
        }

        /* ================= FAKTOR ANALYSIS ================= */
        .faktor-analysis-section {
            margin-top: 30px;
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .faktor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .faktor-header h3 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .faktor-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-box-faktor {
            position: relative;
        }

        .search-box-faktor input {
            padding: 10px 15px 10px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            width: 250px;
            font-size: 14px;
        }

        .search-box-faktor i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }

        .faktor-table-container {
            overflow-x: auto;
            max-height: 600px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-top: 15px;
        }

        .faktor-master-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .faktor-master-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .faktor-master-table th {
            padding: 15px 12px;
            text-align: left;
            color: white;
            font-weight: 600;
            border: none;
        }

        .faktor-master-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .faktor-master-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .faktor-master-table td {
            padding: 12px 15px;
            vertical-align: top;
        }

        .category-row {
            background-color: #e8f4fc !important;
            font-weight: 600;
            font-size: 16px;
        }

        .category-row td {
            padding: 15px;
            color: #2c3e50;
        }

        .main-factor-row {
            background-color: #f8f9fa;
            font-weight: 500;
        }

        .sub-factor-row {
            background-color: white;
        }

        .sub-factor-code {
            padding-left: 40px !important;
            color: #555;
            font-weight: 500;
        }

        .sub-factor-desc {
            color: #666;
            padding-left: 10px !important;
        }

        .checkbox-cell {
            text-align: center;
            width: 60px;
        }

        .factor-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #3498db;
        }

        /* ================= SELECTED FACTORS DISPLAY ================= */
        .selected-factors-display {
            background: #f0f7ff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #3498db;
        }

        .selected-factors-display h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .selected-list {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .selected-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #3498db;
        }

        .selected-item-code {
            font-weight: bold;
            color: #2c3e50;
            min-width: 70px;
        }

        .selected-item-desc {
            flex-grow: 1;
            padding: 0 15px;
        }

        .selected-item-type {
            font-size: 12px;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }

        .personil-type {
            background: #3498db;
        }

        .pekerjaan-type {
            background: #27ae60;
        }

        .remove-selected {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
        }

        .selection-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .stat-box {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-align: center;
            min-width: 100px;
            border: 1px solid #e0e0e0;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ================= CAN TABLE ================= */
        .faktor-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .faktor-table th {
            background: #3498db;
            color: white;
            padding: 15px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .faktor-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .faktor-table tr:hover {
            background-color: #f8f9fa;
        }

        .category-header {
            background-color: #e8f4fc !important;
            font-weight: bold;
            font-size: 16px;
        }

        .category-header td {
            padding: 15px;
            color: #2c3e50;
        }

        .kode-column {
            font-weight: bold;
            color: #2c3e50;
            width: 80px;
        }

        .deskripsi-column {
            width: 50%;
        }

        .radio-column {
            text-align: center;
            width: 100px;
        }

        .radio-group-can {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .radio-group-can label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
            min-width: 35px;
            justify-content: center;
        }

        .radio-group-can label:hover {
            background-color: #e8f4fc;
        }

        .radio-group-can input[type="radio"] {
            width: 16px;
            height: 16px;
        }

        .radio-group-can input[value="Y"] {
            accent-color: #27ae60;
        }

        .radio-group-can input[value="N"] {
            accent-color: #e74c3c;
        }

        .not-selected {
            color: #95a5a6;
            font-style: italic;
            font-size: 12px;
            margin-top: 5px;
        }

        /* ================= SUMMARY ================= */
        .summary-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .summary-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            margin: 0;
        }

        .summary-content {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .pdf-preview {
            background: white;
            color: #333;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
        }

        .pdf-preview i {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .pdf-preview h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .pdf-preview p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        /* ================= BUTTONS ================= */
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
        }

        /* ================= ALERTS ================= */
        .alert-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            background: #e8f4fc;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            background: #27ae60;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        /* ================= ANIMATIONS ================= */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2,
            .user-info,
            .nav-item span {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-top: 15px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .radio-group-simple {
                flex-direction: column;
                gap: 10px;
            }
            
            .radio-item {
                min-width: 100%;
            }
            
            .faktor-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .faktor-controls {
                width: 100%;
                flex-wrap: wrap;
            }
            
            .search-box-faktor input {
                width: 100%;
            }
            
            .checkbox-group-large,
            .checkbox-group-column {
                grid-template-columns: 1fr;
            }
            
            .btn-container {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }

        /* ================= UTILITY ================= */
        .mt-3 {
            margin-top: 15px;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        .empty-selection {
            text-align: center;
            color: #95a5a6;
            font-style: italic;
            padding: 20px;
        }
        
        /* Legend untuk CAN */
        .legend-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-top: 20px;
        }
        
        .legend-title {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .legend-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .legend-item strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 8px;
        }
        
        .legend-item p {
            margin: 0;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Highlight untuk kategori CAN */
        .category-header {
            background-color: #e8f4fc !important;
            font-weight: bold;
            font-size: 16px;
        }
        
        .category-header td {
            padding: 15px;
            color: #2c3e50;
        }
        
        .category-header i {
            color: #3498db;
            margin-right: 10px;
        }
        
        /* Indikator untuk radio yang belum dipilih */
        .not-selected {
            color: #95a5a6;
            font-style: italic;
        }

        /* Risk display styles */
        .risk-level-display {
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .risk-extreme {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 2px solid #e74c3c;
        }
        
        .risk-high {
            background: rgba(230, 126, 34, 0.2);
            color: #e67e22;
            border: 2px solid #e67e22;
        }
        
        .risk-medium {
            background: rgba(241, 196, 15, 0.2);
            color: #f39c12;
            border: 2px solid #f39c12;
        }
        
        .risk-low {
            background: rgba(46, 204, 113, 0.2);
            color: #27ae60;
            border: 2px solid #27ae60;
        }
        
        .risk-explanation {
            font-size: 0.9rem;
            margin-top: 8px;
            font-weight: normal;
        }
        
        /* Error message */
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* Field Group */
        .field-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .field-group {
                grid-template-columns: 1fr;
            }
        }
        
        /* Input dengan icon */
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }
        
        .input-with-icon input {
            padding-left: 45px;
        }
        
        /* Info Box */
        .info-box {
            background: #e8f4fc;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .info-box i {
            color: #3498db;
            margin-right: 10px;
        }
        
        /* Style untuk insiden type select */
        .insiden-type-select,
        .jenis-tindakan-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }

        .insiden-type-select:focus,
        .jenis-tindakan-select:focus {
            border-color: #3498db;
            outline: none;
        }

        /* Style untuk container tindakan */
        .tindakan-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
        }

        .tindakan-item:hover {
            background: #e8f4fc;
            border-color: #3498db;
        }

        .tindakan-actions {
            text-align: right;
            margin-top: 15px;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        /* Style untuk input nomor insiden */
        .insiden-number-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }
        
        .insiden-number-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .auto-generate-info {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #27ae60;
            margin-top: 10px;
        }
        
        .auto-generate-info i {
            color: #27ae60;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- ================= SIDEBAR ================= -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>SCAT</h2>
            <div class="version">Sistem Catatan Analisis Tindakan v1.0</div>
        </div>
        
        <div class="user-profile">
            <div class="user-avatar">
                <?php 
                $username = $_SESSION['username'] ?? 'User';
                echo strtoupper(substr($username, 0, 2)); 
                ?>
            </div>
            <div class="user-info">
                <h4><?php echo e($_SESSION['fullname'] ?? 'User'); ?></h4>
                <div class="role"><?php echo e(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="form_input_scat.php" class="nav-item active">
                <i class="fas fa-plus-circle"></i>
                <span>Input SCAT</span>
            </a>
            <a href="logout.php" class="nav-item logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
    
    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">
        <div class="container">
            <?php if ($message): ?>
            <div class="alert-message <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert-message alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Berhasil!</strong> Data insiden telah disimpan dengan ID: <?php echo e($insiden_id); ?><br>
                    <a href="generate_pdf.php?id=<?php echo e($insiden_id); ?>" class="btn btn-success mt-2" target="_blank">
                        <i class="fas fa-download"></i> Download Laporan PDF
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Lengkapi semua bagian analisis sesuai dengan insiden yang terjadi</span>
            </div>
            
            <form method="POST" action="" id="scatForm" novalidate enctype="multipart/form-data">
                <input type="hidden" name="download_pdf" id="downloadPDFFlag" value="0">
                
                <!-- Hidden container untuk menyimpan faktor terpilih -->
                <div id="hiddenFactorsContainer"></div>
                
                <div class="form-container">
                    <!-- ================= NOMOR INSIDEN ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-hashtag"></i>
                            <h3>Nomor Insiden</h3>
                        </div>
                        
                        <div class="insiden-number-group">
                            <div class="form-group">
                                <label>Nomor Insiden</label>
                                <div class="insiden-number-container">
                                    <input type="text" name="insiden_number" 
                                           value="<?php echo e($_POST['insiden_number'] ?? ''); ?>"
                                           placeholder="Contoh: INS-202412-001"
                                           style="flex: 1;">
                                    <button type="button" class="btn btn-secondary" id="generateNumberBtn">
                                        <i class="fas fa-sync-alt"></i> Generate Otomatis
                                    </button>
                                </div>
                                <small class="text-muted">Biarkan kosong untuk generate otomatis atau isi dengan nomor custom</small>
                            </div>
                            
                            <div class="auto-generate-info">
                                <i class="fas fa-info-circle"></i>
                                <span>
                                    <strong>Format otomatis:</strong> INS-YYYYMM-XXX (Contoh: INS-202412-001)<br>
                                    <strong>Catatan:</strong> Pastikan nomor insiden unik dan tidak duplikat dengan data lain.
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ================= DATA DASAR ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i>
                            <h3>Data Dasar Insiden</h3>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Judul Insiden</label>
                            <input type="text" name="judul_insiden" required 
                                   value="<?php echo e($_POST['judul_insiden'] ?? ''); ?>"
                                   placeholder="Masukkan judul insiden...">
                        </div>
                        
                        <div class="field-group">
                            <div class="form-group">
                                <label class="required">Tanggal Kejadian</label>
                                <input type="date" name="tanggal" required 
                                       value="<?php echo e($_POST['tanggal'] ?? date('Y-m-d')); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Waktu Kejadian</label>
                                <input type="time" name="waktu_kejadian" 
                                       value="<?php echo e($_POST['waktu_kejadian'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="field-group">
                            <div class="form-group">
                                <label class="required">Lokasi Kejadian</label>
                                <input type="text" name="lokasi" required
                                       value="<?php echo e($_POST['lokasi'] ?? ''); ?>"
                                       placeholder="Masukkan lokasi kejadian...">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Departemen Terkait</label>
                                <input type="text" name="departemen_insiden" required
                                       value="<?php echo e($_POST['departemen_insiden'] ?? ''); ?>"
                                       placeholder="Contoh: HSE, Operations, Maintenance, dll...">
                            </div>
                        </div>
                        
                        <div class="field-group">
                            <div class="form-group">
                                <label class="required">Nama Manajer Departemen</label>
                                <input type="text" name="nama_manajer_departemen" required
                                       value="<?php echo e($_POST['nama_manajer_departemen'] ?? ''); ?>"
                                       placeholder="Masukkan nama manajer departemen...">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Insiden Type</label>
                                <select name="insiden_type" class="insiden-type-select" required>
                                    <option value="">Pilih Jenis Insiden</option>
                                    <option value="FATALITY" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'FATALITY') ? 'selected' : '' ?>>FATALITY</option>
                                    <option value="DAYS AWAY FROM WORK (DAWF)" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'DAYS AWAY FROM WORK (DAWF)') ? 'selected' : '' ?>>DAYS AWAY FROM WORK (DAWF)</option>
                                    <option value="TOTAL RECORDABLE INCIDENT (TRI)" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'TOTAL RECORDABLE INCIDENT (TRI)') ? 'selected' : '' ?>>TOTAL RECORDABLE INCIDENT (TRI)</option>
                                    <option value="MEDICAL TREATMENT CASE (MTC)" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'MEDICAL TREATMENT CASE (MTC)') ? 'selected' : '' ?>>MEDICAL TREATMENT CASE (MTC)</option>
                                    <option value="FIRST AID CASE" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'FIRST AID CASE') ? 'selected' : '' ?>>FIRST AID CASE</option>
                                    <option value="MOTOR VEHICLE CRASH (MVC)" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'MOTOR VEHICLE CRASH (MVC)') ? 'selected' : '' ?>>MOTOR VEHICLE CRASH (MVC)</option>
                                    <option value="OIL SPILL (TUMPAHAN > 200L)" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'OIL SPILL (TUMPAHAN > 200L)') ? 'selected' : '' ?>>OIL SPILL (TUMPAHAN > 200L)</option>
                                    <option value="FIRE EXPLOSION" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'FIRE EXPLOSION') ? 'selected' : '' ?>>FIRE EXPLOSION</option>
                                    <option value="PROPERTY DAMAGE" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'PROPERTY DAMAGE') ? 'selected' : '' ?>>PROPERTY DAMAGE</option>
                                    <option value="NEAR MISS" <?= (isset($_POST['insiden_type']) && $_POST['insiden_type'] == 'NEAR MISS') ? 'selected' : '' ?>>NEAR MISS</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Deskripsi Kejadian</label>
                            <textarea id="input_deskripsi" name="deskripsi_kejadian" rows="6" required
                                      placeholder="Deskripsikan kejadian secara lengkap..."><?php 
                                echo e($_POST['deskripsi_kejadian'] ?? ''); 
                            ?></textarea>
                            <div class="mt-3">
                                <button type="button" class="btn btn-secondary" id="clearDeskripsi">
                                    <i class="fas fa-trash"></i> Hapus Deskripsi
                                </button>
                            </div>
                        </div>
                        
                        <div class="field-group">
                            <div class="form-group">
                                <label class="required">Nama Penyelidik</label>
                                <input type="text" name="penyelidik" required
                                       value="<?php echo e($_POST['penyelidik'] ?? ''); ?>"
                                       placeholder="Masukkan nama penyelidik insiden...">
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- ================= IDENTITAS ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-building"></i>
                            <h3>Identitas Insiden (Opsional)</h3>
                        </div>
                        
                        <div class="form-group">
                            <label>Entitas</label>
                            <input type="text" name="Entitas" 
                                   value="<?php echo e($_POST['Entitas'] ?? ''); ?>"
                                   placeholder="Masukkan entitas...">
                        </div>
                        
                        <div class="form-group">
                            <label>Cabang</label>
                            <textarea id="Cabang" name="Cabang" rows="3"
                                      placeholder="Masukkan cabang..."><?php 
                                echo e($_POST['Cabang'] ?? ''); 
                            ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Project</label>
                            <textarea id="Proect" name="Proect" rows="3"
                                      placeholder="Masukkan proect..."><?php 
                                echo e($_POST['Proect'] ?? ''); 
                            ?></textarea>
                        </div>
                    </div>
                    
                    <!-- ================= DATA KORBAN ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-injured"></i>
                            <h3>Data Korban (Opsional)</h3>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Korban</label>
                            <input type="text" name="nama_korban" 
                                   value="<?php echo e($_POST['nama_korban'] ?? ''); ?>"
                                   placeholder="Masukkan nama korban...">
                        </div>
                        
                        <div class="form-group">
                            <label>Jabatan Korban</label>
                            <input type="text" name="jabatan_korban" 
                                   value="<?php echo e($_POST['jabatan_korban'] ?? ''); ?>"
                                   placeholder="Masukkan jabatan korban...">
                        </div>
                        
                        <div class="form-group">
                            <label>Bagian Tubuh yang Terkena</label>
                            <input type="text" name="bagian_tubuh" 
                                   value="<?php echo e($_POST['bagian_tubuh'] ?? ''); ?>"
                                   placeholder="Contoh: Tangan kanan, Kaki, Kepala...">
                        </div>
                    </div>
                    
                    <!-- ================= DATA SAKSI DAN KERUGIAN ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users"></i>
                            <h3>Data Saksi dan Kerugian (Opsional)</h3>
                        </div>
                        
                        <div class="form-group">
                            <label>Saksi yang Melihat Kejadian</label>
                            <textarea name="saksi" rows="3"
                                      placeholder="Masukkan nama saksi, pisahkan dengan koma jika lebih dari satu..."><?php 
                                echo e($_POST['saksi'] ?? ''); 
                            ?></textarea>
                            <small class="text-muted">Contoh: Budi Santoso, Siti Rahayu, Ahmad Fauzi</small>
                        </div>
                        
                        <div class="field-group">
                            <div class="form-group">
                                <label>Perkiraan Kerugian (Rp)</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <input type="text" name="perkiraan_kerugian" 
                                           id="perkiraan_kerugian"
                                           value="<?php echo formatRupiah($_POST['perkiraan_kerugian'] ?? ''); ?>"
                                           placeholder="0"
                                           oninput="formatCurrency(this)">
                                </div>
                                <!-- Input hidden untuk nilai asli (tanpa format) -->
                                <input type="hidden" name="perkiraan_kerugian_raw" id="perkiraan_kerugian_raw">
                            </div>
                            
                            <div class="form-group">
                                <label>Potensi Kerugian (Rp)</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-chart-line"></i>
                                    <input type="text" name="potensi_kerugian" 
                                           id="potensi_kerugian"
                                           value="<?php echo isset($_POST['potensi_kerugian']) ? number_format(floatval($_POST['potensi_kerugian']), 0, ',', '.') : ''; ?>"
                                           placeholder="0"
                                           oninput="formatCurrency(this)">
                                </div>
                                <!-- Input hidden untuk nilai asli (tanpa format) -->
                                <input type="hidden" name="potensi_kerugian_raw" id="potensi_kerugian_raw">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ================= ANALISIS RISIKO SEDERHANA (OPSIONAL) ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-search"></i>
                            <h3>Analisis Insiden (Opsional)</h3>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <span>Bagian ini opsional. Isi jika ingin melakukan analisis risiko tambahan.</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Kerugian</label>
                            <div class="checkbox-group">
                                <?php
                                $kerugian_items = ["Manusia", "Aset", "Lingkungan", "Keuangan", "Reputasi"];
                                foreach ($kerugian_items as $item):
                                ?>
                                <label>
                                    <input type="checkbox" name="jenis_kerugian[]" value="<?= $item ?>"
                                    <?= isset($_POST['jenis_kerugian']) && in_array($item, $_POST['jenis_kerugian']) ? 'checked' : '' ?>>
                                    <?= $item ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Kemungkinan Keparahan Kerugian</label>
                            <div class="radio-group-simple">
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_keparahan" value="Major (A)" 
                                        <?= (isset($_POST['kemungkinan_keparahan']) && $_POST['kemungkinan_keparahan'] == 'Major (A)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Major (A)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_keparahan" value="Serious (B)"
                                        <?= (isset($_POST['kemungkinan_keparahan']) && $_POST['kemungkinan_keparahan'] == 'Serious (B)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Serious (B)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_keparahan" value="Minor (C)"
                                        <?= (isset($_POST['kemungkinan_keparahan']) && $_POST['kemungkinan_keparahan'] == 'Minor (C)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Minor (C)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Kemungkinan Kejadian</label>
                            <div class="radio-group-simple">
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_kejadian" value="High (A)"
                                        <?= (isset($_POST['kemungkinan_kejadian']) && $_POST['kemungkinan_kejadian'] == 'High (A)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>High (A)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_kejadian" value="Moderate (B)"
                                        <?= (isset($_POST['kemungkinan_kejadian']) && $_POST['kemungkinan_kejadian'] == 'Moderate (B)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Moderate (B)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="kemungkinan_kejadian" value="Low (C)"
                                        <?= (isset($_POST['kemungkinan_kejadian']) && $_POST['kemungkinan_kejadian'] == 'Low (C)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Low (C)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Frekuensi Terpapar</label>
                            <div class="radio-group-simple">
                                <label class="radio-item">
                                    <input type="radio" name="frekuensi_terpapar" value="Extensive (A)"
                                        <?= (isset($_POST['frekuensi_terpapar']) && $_POST['frekuensi_terpapar'] == 'Extensive (A)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Extensive (A)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="frekuensi_terpapar" value="Moderate (B)"
                                        <?= (isset($_POST['frekuensi_terpapar']) && $_POST['frekuensi_terpapar'] == 'Moderate (B)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Moderate (B)</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="frekuensi_terpapar" value="Low (C)"
                                        <?= (isset($_POST['frekuensi_terpapar']) && $_POST['frekuensi_terpapar'] == 'Low (C)') ? 'checked' : '' ?>>
                                    <span class="radio-dot"></span>
                                    <span>Low (C)</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Tingkat Risiko yang dihitung otomatis -->
                        <div class="form-group">
                            <label>Tingkat Risiko</label>
                            <div class="risk-result" id="riskResult">
                                <p>Bagian ini opsional. Isi parameter di atas untuk melihat analisis risiko.</p>
                            </div>
                            <input type="hidden" name="tingkat_risiko" id="tingkat_risiko_input" 
                                   value="<?php echo e($_POST['tingkat_risiko'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <!-- ================= TABEL 1: JENIS KEJADIAN ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>TABEL 1: Jenis Kontak dengan Energi atau Zat</h3>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> Pilih satu atau lebih jenis kontak yang terjadi:</label>
                            <div class="checkbox-group-large">
                                <?php foreach ($tabel1_jenis_kejadian as $num => $jenis): ?>
                                <label class="jenis-kejadian-item">
                                    <input type="checkbox" name="jenis_kejadian[]" value="<?= htmlspecialchars($jenis) ?>"
                                           <?= isset($_POST['jenis_kejadian']) && in_array($jenis, $_POST['jenis_kejadian']) ? 'checked' : '' ?>>
                                    <span class="jenis-number"><?= $num ?>.</span>
                                    <span class="jenis-desc"><?= htmlspecialchars($jenis) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ================= TABEL 2: PENYEBAB LANGSUNG ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-search"></i>
                            <h3>TABEL 2: Penyebab Langsung (IC's)</h3>
                        </div>
                        
                        <div class="tabel2-container">
                            <!-- Sub-tabel 2A: TINDAKAN TIDAK AMAN -->
                            <div class="sub-tabel">
                                <h4><i class="fas fa-user-times"></i> TINDAKAN / PRAKTEK TIDAK STANDAR</h4>
                                <div class="checkbox-group-column">
                                    <?php foreach ($tabel2a_tindakan as $num => $tindakan): ?>
                                    <label class="penyebab-item">
                                        <input type="checkbox" name="penyebab_tindakan[]" value="<?= htmlspecialchars($tindakan) ?>"
                                               <?= isset($_POST['penyebab_tindakan']) && in_array($tindakan, $_POST['penyebab_tindakan']) ? 'checked' : '' ?>>
                                        <span class="item-number"><?= $num ?>.</span>
                                        <span class="item-desc"><?= htmlspecialchars($tindakan) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ================= Penyebab Dasar / Basic Cause (BC's) ================= -->
                    <div class="faktor-analysis-section">
                        <div class="faktor-header">
                            <h3><i class="fas fa-users-cog"></i> Penyebab Dasar / Basic Cause (BC's)</h3>
                            <div class="faktor-controls">
                                <div class="search-box-faktor">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchFaktor" placeholder="Cari faktor...">
                                </div>
                                <button type="button" class="btn btn-secondary" id="clearFaktor">
                                    <i class="fas fa-trash"></i> Hapus Pilihan
                                </button>
                            </div>
                        </div>
                        
                        <div class="faktor-table-container">
                            <table class="faktor-master-table">
                                <thead>
                                    <tr>
                                        <th width="8%">Kode</th>
                                        <th width="72%">Deskripsi Faktor</th>
                                        <th width="10%">Kategori</th>
                                        <th width="10%" class="checkbox-cell">Pilih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $faktor_index = 0;
                                    foreach ($faktor_data as $kategori => $items):
                                        $tipe = ($kategori == 'FAKTOR PERSONIL') ? 'personil' : 'pekerjaan';
                                    ?>
                                    <tr class="category-row">
                                        <td colspan="4">
                                            <i class="fas fa-folder"></i> <?= $kategori ?>
                                        </td>
                                    </tr>
                                    
                                    <?php foreach ($items as $num => $data): ?>
                                    <tr class="main-factor-row">
                                        <td><strong><?= $num ?></strong></td>
                                        <td><strong><?= htmlspecialchars($data['title']) ?></strong></td>
                                        <td><?= $kategori ?></td>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" class="factor-checkbox main-factor" 
                                                   data-kategori="<?= $kategori ?>"
                                                   data-kode="<?= $num ?>"
                                                   data-deskripsi="<?= htmlspecialchars($data['title']) ?>"
                                                   data-tipe="<?= $tipe ?>"
                                                   value="<?= $num ?>|<?= htmlspecialchars($data['title']) ?>">
                                        </td>
                                    </tr>
                                    
                                    <?php foreach ($data['sub'] as $subCode => $subDesc): 
                                    $faktor_index++;
                                    ?>
                                    <tr class="sub-factor-row">
                                        <td class="sub-factor-code"><?= $subCode ?></td>
                                        <td class="sub-factor-desc"><?= htmlspecialchars($subDesc) ?></td>
                                        <td><?= $kategori ?></td>
                                        <td class="checkbox-cell">
                                            <input type="checkbox" class="factor-checkbox sub-factor" 
                                                   data-kategori="<?= $kategori ?>"
                                                   data-kode="<?= $subCode ?>"
                                                   data-deskripsi="<?= htmlspecialchars($subDesc) ?>"
                                                   data-tipe="<?= $tipe ?>"
                                                   data-parent="<?= $num ?>"
                                                   value="<?= $subCode ?>|<?= htmlspecialchars($subDesc) ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="selected-factors-display">
                            <h4><i class="fas fa-check-circle"></i> Faktor Terpilih</h4>
                            <div class="selected-list" id="selectedFaktorList">
                                <div class="empty-selection">Belum ada faktor yang dipilih</div>
                            </div>
                            
                            <div class="selection-stats">
                                <div class="stat-box">
                                    <div class="stat-value" id="personilCount">0</div>
                                    <div class="stat-label">Personil</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="pekerjaanCount">0</div>
                                    <div class="stat-label">Pekerjaan</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value" id="totalCount">0</div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ================= Control Action Needs (CAN) ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-layer-group"></i>
                            <h3>Control Action Needs (CAN)</h3>
                            <small style="color: #7f8c8d; margin-left: 10px;">Pilih Y (Ya) atau N (Tidak) untuk setiap item - Default: Belum dipilih</small>
                        </div>
                        
                        <div class="faktor-table-container">
                            <table class="faktor-table">
                                <thead>
                                    <tr>
                                        <th width="10%">Kode</th>
                                        <th width="50%">Deskripsi Faktor</th>
                                        <th width="13%">(P) Program</th>
                                        <th width="13%">(S) Standar</th>
                                        <th width="13%">(C) Kepatuhan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Data Control Action Needs
                                    $can_data = [
                                        ["kode" => "1", "deskripsi" => "ADMINISTRASI DAN KEPEMIMPINAN", "subitems" => [
                                            ["kode" => "1.1", "deskripsi" => "Kebijakan umum"],
                                            ["kode" => "1.2", "deskripsi" => "Koordinator program"],
                                            ["kode" => "1.3", "deskripsi" => "Partisipasi manajemen senior dan menengah"],
                                            ["kode" => "1.4", "deskripsi" => "Standar kinerja manajemen"],
                                            ["kode" => "1.5", "deskripsi" => "Keikutsertaan manajemen"],
                                            ["kode" => "1.6", "deskripsi" => "Presentasi di rapat manajemen"],
                                            ["kode" => "1.7", "deskripsi" => "Management reference manual"],
                                            ["kode" => "1.8", "deskripsi" => "Audit manajemen dilakukan"],
                                            ["kode" => "1.9", "deskripsi" => "Tanggung jawab individu untuk keselamatan dan kesehatan / kontrol pencegahan kerugian"],
                                            ["kode" => "1.10", "deskripsi" => "Pembentukan keselamatan dan kesehatan tahunan / kontrol pencegahan kerugian"],
                                            ["kode" => "1.11", "deskripsi" => "Bergabung dengan komite keselamatan & kesehatan dan atau perwakilannya"],
                                            ["kode" => "1.12", "deskripsi" => "Menolak untuk bekerja dengan standar bahaya keselamatan dan kesehatan yang rendah"],
                                            ["kode" => "1.13", "deskripsi" => "Daftar pustaka"]
                                        ]],
                                        ["kode" => "2", "deskripsi" => "PELATIHAN MANAJEMEN", "subitems" => [
                                            ["kode" => "2.1", "deskripsi" => "Program orientasi dan pengenalan manajemen"],
                                            ["kode" => "2.2", "deskripsi" => "Pelatihan awal formal untuk personal senior manajemen"],
                                            ["kode" => "2.3", "deskripsi" => "Ulasan dan pembaharuan formal tentang pelatihan senior manajemen"],
                                            ["kode" => "2.4", "deskripsi" => "Pelatihan formal untuk personal supervisor dan manajemen menengah"],
                                            ["kode" => "2.5", "deskripsi" => "Ulasan dan pembaharuan formal untuk personel supervisor dan manajemen menengah"],
                                            ["kode" => "2.6", "deskripsi" => "Pelatihan formal untuk koordinator program"]
                                        ]],
                                        ["kode" => "3", "deskripsi" => "PEMERIKSAAN TERENCANA", "subitems" => [
                                            ["kode" => "3.1", "deskripsi" => "Pemeriksaan umum terencana"],
                                            ["kode" => "3.2", "deskripsi" => "Tindak lanjut prosedur"],
                                            ["kode" => "3.3", "deskripsi" => "Analisa laporan pemeriksaan"],
                                            ["kode" => "3.4", "deskripsi" => "Pemeriksaan bagian penting dalam program inspeksi"],
                                            ["kode" => "3.5", "deskripsi" => "Program pencegahan pemeliharaan"],
                                            ["kode" => "3.6", "deskripsi" => "Pemeriksaan peralatan sebelum penanganan dan pemindahan"],
                                            ["kode" => "3.7", "deskripsi" => "Kondisi alternatif sistem pelaporan"],
                                            ["kode" => "3.8", "deskripsi" => "Laporan perencanaan program perawatan"],
                                            ["kode" => "3.9", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "4", "deskripsi" => "ANALISIS TUGAS DAN PROSEDUR", "subitems" => [
                                            ["kode" => "4.1", "deskripsi" => "Petunjuk dan pedoman penting manajemen"],
                                            ["kode" => "4.2", "deskripsi" => "Inventarisasi tugas penting"],
                                            ["kode" => "4.3", "deskripsi" => "Analisa tugas dan analisa sasaran prosedur"],
                                            ["kode" => "4.4", "deskripsi" => "Analisis tugas dan prosedur untuk tugas penting selesai dan secara teratur diperbaharui"],
                                            ["kode" => "4.5", "deskripsi" => "Analisa bahaya dan prosedur untuk analisa bahaya dan prosedur tugas penting"],
                                            ["kode" => "4.6", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "5", "deskripsi" => "INVESTIGASI KECELAKAAN DAN KEJADIAN", "subitems" => [
                                            ["kode" => "5.1", "deskripsi" => "Prosedur investigasi kecelakaan/kejadian"],
                                            ["kode" => "5.2", "deskripsi" => "Lingkup investigasi yang dibuat"],
                                            ["kode" => "5.3", "deskripsi" => "Perbaikan tindakan dan tindaklanjutan"],
                                            ["kode" => "5.4", "deskripsi" => "Sistem penyampaian kecelakaan besar yang digunakan"],
                                            ["kode" => "5.5", "deskripsi" => "Informasi potensi kejadian besar yang digunakan"],
                                            ["kode" => "5.6", "deskripsi" => "Partisipasi manajemen operasi"],
                                            ["kode" => "5.7", "deskripsi" => "Pelaporan kejadian dan investigasi"],
                                            ["kode" => "5.8", "deskripsi" => "Pemeliharaan laporan kecelakaan / kejadian"],
                                            ["kode" => "5.9", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "6", "deskripsi" => "TUGAS OBSERVASI", "subitems" => [
                                            ["kode" => "6.1", "deskripsi" => "Pentingnya arahan dari manajemen"],
                                            ["kode" => "6.2", "deskripsi" => "Program dalam menyelesaikan tugas observasi"],
                                            ["kode" => "6.3", "deskripsi" => "Tingkat pengamatan tugas yang sudah selesai"],
                                            ["kode" => "6.4", "deskripsi" => "Pembagian tugas observasi"],
                                            ["kode" => "6.5", "deskripsi" => "Analisa laporan pengamatan"],
                                            ["kode" => "6.6", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "7", "deskripsi" => "KESIAPSIAGAAN DARURAT", "subitems" => [
                                            ["kode" => "7.1", "deskripsi" => "Penunjukan koordinator"],
                                            ["kode" => "7.2", "deskripsi" => "Perencanaan darurat tertulis"],
                                            ["kode" => "7.3", "deskripsi" => "Pelatihan pengawas untuk first aid"],
                                            ["kode" => "7.4", "deskripsi" => "Pelatihan karyawan untuk first aid (10%)"],
                                            ["kode" => "7.5", "deskripsi" => "Lampu darurat dan listrik/genset memadai"],
                                            ["kode" => "7.6", "deskripsi" => "Pusat penandaan label warna"],
                                            ["kode" => "7.7", "deskripsi" => "Peralatan pelindung & peralatan penyelamatan"],
                                            ["kode" => "7.8", "deskripsi" => "Pelatihan tanggap darurat"],
                                            ["kode" => "7.9", "deskripsi" => "Petugas pertolongan pertama yang memenuhi syarat"],
                                            ["kode" => "7.10", "deskripsi" => "Gotong royong atau sejenisnya"],
                                            ["kode" => "7.11", "deskripsi" => "Perlindungan terhadap arsip penting"],
                                            ["kode" => "7.12", "deskripsi" => "Perencanaan pasca acara"],
                                            ["kode" => "7.13", "deskripsi" => "Komunikasi gawat darurat tersedia"],
                                            ["kode" => "7.14", "deskripsi" => "Rencana komunikasi keselamatan publik"]
                                        ]],
                                        ["kode" => "8", "deskripsi" => "ATURAN ORGANISASI", "subitems" => [
                                            ["kode" => "8.1", "deskripsi" => "Keselamatan umum dan peraturan kesehatan"],
                                            ["kode" => "8.2", "deskripsi" => "Khusus aturan kerja"],
                                            ["kode" => "8.3", "deskripsi" => "Sistem izin kerja dan prosedur khusus"],
                                            ["kode" => "8.4", "deskripsi" => "Pengetahuan tentang peraturan dan review"],
                                            ["kode" => "8.5", "deskripsi" => "Usaha kepatuhan terhadap aturan"],
                                            ["kode" => "8.6", "deskripsi" => "Penggunaan tanda/petunjuk dan kode warna sesuai"],
                                            ["kode" => "8.7", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "9", "deskripsi" => "ACCIDENT/INCIDENT ANALYSIS", "subitems" => [
                                            ["kode" => "9.1", "deskripsi" => "Statistik kinerja dihitung dan digunakan"],
                                            ["kode" => "9.2", "deskripsi" => "Penyakit akibat kerja dan analisa penyakit"],
                                            ["kode" => "9.3", "deskripsi" => "Analisa dan identifikasi terhadap kerusakan barang dan aset"],
                                            ["kode" => "9.4", "deskripsi" => "Tim pemecah masalah"]
                                        ]],
                                        ["kode" => "10", "deskripsi" => "PELATIHAN KARYAWAN", "subitems" => [
                                            ["kode" => "10.1", "deskripsi" => "Training need analysis"],
                                            ["kode" => "10.2", "deskripsi" => "Program pelatihan karyawan"],
                                            ["kode" => "10.3", "deskripsi" => "Evaluasi program pelatihan"]
                                        ]],
                                        ["kode" => "11", "deskripsi" => "ALAT PELINDUNG DIRI", "subitems" => [
                                            ["kode" => "11.1", "deskripsi" => "Standar alat pelindung diri"],
                                            ["kode" => "11.2", "deskripsi" => "Pencatatan alat pelindung diri"],
                                            ["kode" => "11.3", "deskripsi" => "Penegakan standarisasi"],
                                            ["kode" => "11.4", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "12", "deskripsi" => "PENGENDALIAN KESEHATAN", "subitems" => [
                                            ["kode" => "12.1", "deskripsi" => "Identifikasi bahaya kesehatan"],
                                            ["kode" => "12.2", "deskripsi" => "Kontrol bahaya kesehatan"],
                                            ["kode" => "12.3", "deskripsi" => "Informasi / pelatihan / pendidikan"],
                                            ["kode" => "12.4", "deskripsi" => "Pemantauan industrial hygiene"],
                                            ["kode" => "12.5", "deskripsi" => "Program pemeliharaan kesehatan"],
                                            ["kode" => "12.6", "deskripsi" => "Bantuan medis profesional"],
                                            ["kode" => "12.7", "deskripsi" => "Komunikasi kesehatan untuk pekerja"],
                                            ["kode" => "12.8", "deskripsi" => "Catatan perawatan/pemeliharaan"]
                                        ]],
                                        ["kode" => "13", "deskripsi" => "PROGRAM SISTEM PENILAIAN", "subitems" => [
                                            ["kode" => "13.1", "deskripsi" => "Kepatuhan dengan standar program audit secara menyeluruh"],
                                            ["kode" => "13.2", "deskripsi" => "Kepatuhan dengan standar program audit dalam bentuk fisik"],
                                            ["kode" => "13.3", "deskripsi" => "Kepatuhan dengan standar pencegahan dan pengendalian kebakaran"],
                                            ["kode" => "13.4", "deskripsi" => "Kepatuhan dengan standar kesehatan kerja"],
                                            ["kode" => "13.5", "deskripsi" => "Penyimpanan catatan tentang program sistem evaluasi"]
                                        ]],
                                        ["kode" => "14", "deskripsi" => "ENGINEERING CONTROL", "subitems" => [
                                            ["kode" => "14.1", "deskripsi" => "Desain keselamatan dan konsep pertimbangan teknik kesehatan"],
                                            ["kode" => "14.2", "deskripsi" => "Proses rekayasa keselamatan dan desain pertimbangan kesehatan"],
                                            ["kode" => "14.3", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "15", "deskripsi" => "KOMUNIKASI PERORANGAN", "subitems" => [
                                            ["kode" => "15.1", "deskripsi" => "Pelatihan teknik komunikasi secara perorangan"],
                                            ["kode" => "15.2", "deskripsi" => "Orientasi kerja perorangan / pelantikan baru / orang yang dipindahkan"],
                                            ["kode" => "15.3", "deskripsi" => "Instruksi tugas pelatihan dan penggunaan yang benar"],
                                            ["kode" => "15.4", "deskripsi" => "Rencana program kontak perorangan"],
                                            ["kode" => "15.5", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "16", "deskripsi" => "RAPAT (GROUP MEETING)", "subitems" => [
                                            ["kode" => "16.1", "deskripsi" => "Rapat/group meeting diadakan"],
                                            ["kode" => "16.2", "deskripsi" => "Catatan tentang kejadian, bantuan visual, absensi, pembahasan masalah"],
                                            ["kode" => "16.3", "deskripsi" => "Keterlibatan manajemen dari tingkat menengah sampai atas"],
                                            ["kode" => "16.4", "deskripsi" => "Pemantauan program secara berkala"]
                                        ]],
                                        ["kode" => "17", "deskripsi" => "PROMOSION UMUM", "subitems" => [
                                            ["kode" => "17.1", "deskripsi" => "Program keselamatan bulletin board"],
                                            ["kode" => "17.2", "deskripsi" => "Program penggunaan statistik dan fakta"],
                                            ["kode" => "17.3", "deskripsi" => "Topik promosi yang penting"],
                                            ["kode" => "17.4", "deskripsi" => "Penggunaan penghargaan atau pengenalan"],
                                            ["kode" => "17.5", "deskripsi" => "Program informasi publik"],
                                            ["kode" => "17.6", "deskripsi" => "Promosi performa grup"],
                                            ["kode" => "17.7", "deskripsi" => "Promosi housekeeping"],
                                            ["kode" => "17.8", "deskripsi" => "Catatan aktivitas untuk program promosi"]
                                        ]],
                                        ["kode" => "18", "deskripsi" => "PEREKRUTAN DAN PENEMPATAN", "subitems" => [
                                            ["kode" => "18.1", "deskripsi" => "Analisa kemampuan fisik"],
                                            ["kode" => "18.2", "deskripsi" => "Pemeriksaan fisik sebelum bekerja"],
                                            ["kode" => "18.3", "deskripsi" => "Orientasi umum / program pelantikan"],
                                            ["kode" => "18.4", "deskripsi" => "Pengecekan sebelum pengangkatan dan penempatan karyawan"]
                                        ]],
                                        ["kode" => "19", "deskripsi" => "KONTROL PEMBELIAN", "subitems" => [
                                            ["kode" => "19.1", "deskripsi" => "Spesifikasi pengadaan dalam pembelian meliputi kesehatan dan keselamatan"],
                                            ["kode" => "19.2", "deskripsi" => "Seleksi dan pengendalian kontraktor"]
                                        ]],
                                        ["kode" => "20", "deskripsi" => "KESELAMATAN KERJA KETIKA OFF DUTY (Tidak Bertugas)", "subitems" => [
                                            ["kode" => "20.1", "deskripsi" => "Pelaporan statistik dan analisa"],
                                            ["kode" => "20.2", "deskripsi" => "Menginformasikan tentang berita keselamatan bagi yang tidak bertugas"]
                                        ]]
                                    ];
                                    
                                    $index = 0;
                                    foreach ($can_data as $category):
                                    ?>
                                    <!-- Category Header -->
                                    <tr class="category-header">
                                        <td colspan="5">
                                            <i class="fas fa-folder"></i> 
                                            <?php echo e($category['kode']); ?>. <?php echo e($category['deskripsi']); ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Sub-items -->
                                    <?php foreach ($category['subitems'] as $subitem): 
                                    $subitem_kode = $subitem['kode'];
                                    $subitem_deskripsi = $subitem['deskripsi'];
                                    
                                    // Check if this item was previously selected
                                    $prev_p = isset($_POST['penyebab_dasar'][$index]['program_p']) ? $_POST['penyebab_dasar'][$index]['program_p'] : '';
                                    $prev_s = isset($_POST['penyebab_dasar'][$index]['standar_s']) ? $_POST['penyebab_dasar'][$index]['standar_s'] : '';
                                    $prev_c = isset($_POST['penyebab_dasar'][$index]['kepatuhan_c']) ? $_POST['penyebab_dasar'][$index]['kepatuhan_c'] : '';
                                    ?>
                                    <tr>
                                        <td class="kode-column"><?php echo e($subitem_kode); ?></td>
                                        <td class="deskripsi-column">
                                            <?php echo e($subitem_deskripsi); ?>
                                            <!-- Hidden inputs untuk menyimpan kode dan deskripsi -->
                                            <input type="hidden" name="penyebab_dasar[<?= $index ?>][kode]" value="<?= $subitem_kode ?>">
                                            <input type="hidden" name="penyebab_dasar[<?= $index ?>][deskripsi]" value="<?= htmlspecialchars($subitem_deskripsi) ?>">
                                        </td>
                                        <td class="radio-column">
                                            <div class="radio-group-can">
                                                <label title="Ya">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][program_p]" value="Y" 
                                                        <?= $prev_p == 'Y' ? 'checked' : '' ?>> Y
                                                </label>
                                                <label title="Tidak">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][program_p]" value="N" 
                                                        <?= $prev_p == 'N' ? 'checked' : '' ?>> N
                                                </label>
                                            </div>
                                            <?php if($prev_p == ''): ?>
                                            <div class="not-selected" style="font-size: 12px; margin-top: 5px;">Belum dipilih</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="radio-column">
                                            <div class="radio-group-can">
                                                <label title="Ya">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][standar_s]" value="Y"
                                                        <?= $prev_s == 'Y' ? 'checked' : '' ?>> Y
                                                </label>
                                                <label title="Tidak">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][standar_s]" value="N"
                                                        <?= $prev_s == 'N' ? 'checked' : '' ?>> N
                                                </label>
                                            </div>
                                            <?php if($prev_s == ''): ?>
                                            <div class="not-selected" style="font-size: 12px; margin-top: 5px;">Belum dipilih</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="radio-column">
                                            <div class="radio-group-can">
                                                <label title="Ya">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][kepatuhan_c]" value="Y"
                                                        <?= $prev_c == 'Y' ? 'checked' : '' ?>> Y
                                                </label>
                                                <label title="Tidak">
                                                    <input type="radio" name="penyebab_dasar[<?= $index ?>][kepatuhan_c]" value="N"
                                                        <?= $prev_c == 'N' ? 'checked' : '' ?>> N
                                                </label>
                                            </div>
                                            <?php if($prev_c == ''): ?>
                                            <div class="not-selected" style="font-size: 12px; margin-top: 5px;">Belum dipilih</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                    $index++;
                                    endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Legend Section -->
                        <div class="legend-container">
                            <h4 class="legend-title"><i class="fas fa-info-circle"></i> LEGENDA - Control Action Needs (CAN)</h4>
                            <div class="legend-grid">
                                <div class="legend-item" style="border-left-color: #3498db;">
                                    <strong>P - Program Present</strong>
                                    <p>Apakah kita mempunyai program standar untuk aktivitas ini?<br>
                                    <strong>Y</strong> = Ya, program ada<br>
                                    <strong>N</strong> = Tidak, program tidak ada</p>
                                </div>
                                <div class="legend-item" style="border-left-color: #27ae60;">
                                    <strong>S - Standards</strong>
                                    <p>Apakah standarisasi yang ada sudah memenuhi syarat?<br>
                                    <strong>Y</strong> = Ya, standar memenuhi syarat<br>
                                    <strong>N</strong> = Tidak, standar tidak memenuhi syarat</p>
                                </div>
                                <div class="legend-item" style="border-left-color: #e74c3c;">
                                    <strong>C - Compliance</strong>
                                    <p>Apakah semuanya sesuai dengan standarisasi?<br>
                                    <strong>Y</strong> = Ya, sesuai standar<br>
                                    <strong>N</strong> = Tidak, tidak sesuai standar</p>
                                </div>
                            </div>
                            <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 6px;">
                                <p style="margin: 0; color: #666;"><strong>Instruksi:</strong> Pilih <strong>Y (Ya)</strong> atau <strong>N (Tidak)</strong> untuk setiap kolom P, S, dan C berdasarkan analisis Anda terhadap insiden yang terjadi. <strong>Default: Belum dipilih</strong> (tidak ada radio button yang tercentang).</p>
                            </div>
                        </div>
                        
                    </div>
                    
                    <!-- ================= TINDAKAN PERBAIKAN ================= -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-tools"></i>
                            <h3>Tindakan Perbaikan dan Pencegahan</h3>
                        </div>
                        
                        <div id="tindakanContainer">
                            <!-- Tindakan akan ditambahkan dinamis di sini -->
                            <div class="tindakan-item" data-index="0">
                                <div class="field-group">
                                    <div class="form-group">
                                        <label>Jenis Tindakan</label>
                                        <select name="tindakan_perbaikan[0][jenis_tindakan]" class="jenis-tindakan-select">
                                            <option value="">Pilih Jenis Tindakan</option>
                                            <option value="Korektif">Korektif</option>
                                            <option value="Pencegahan">Pencegahan</option>
                                            <option value="Perbaikan">Perbaikan</option>
                                            <option value="Peningkatan">Peningkatan</option>
                                            <option value="Lainnya">Lainnya</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Penanggung Jawab</label>
                                        <input type="text" name="tindakan_perbaikan[0][penanggungjawab]" 
                                               placeholder="Nama penanggung jawab...">
                                    </div>
                                </div>
                                
                                <!-- REKOMENDASI SEBELUM DESKRIPSI -->
                                <div class="form-group rekomendasi-field">
                                    <label>Rekomendasi</label>
                                    <textarea name="tindakan_perbaikan[0][rekomendasi]" rows="2" 
                                              placeholder="Rekomendasi untuk tindakan ini (opsional)..."></textarea>
                                    <small class="text-muted">Rekomendasi atau saran tambahan sebelum tindakan</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Deskripsi Tindakan</label>
                                    <textarea name="tindakan_perbaikan[0][deskripsi]" rows="3" 
                                              placeholder="Deskripsi lengkap tindakan perbaikan..."></textarea>
                                </div>
                                
                                <!-- FIELD UPLOAD FILE BARU -->
                                <div class="form-group">
                                    <label>Lampiran/Dokumen Pendukung</label>
                                    <div class="file-upload-container">
                                        <input type="file" 
                                               name="tindakan_perbaikan[0][lampiran][]" 
                                               class="file-upload-input"
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                               multiple
                                               onchange="previewFiles(this, 0)">
                                        <div class="file-preview" id="filePreview0"></div>
                                    </div>
                                    <small class="text-muted">Format: PDF, DOC, XLS, JPG, PNG (Maks. 5MB per file)</small>
                                </div>
                                
                                <div class="field-group">
                                    <div class="form-group">
                                        <label>Target Selesai</label>
                                        <input type="date" name="tindakan_perbaikan[0][target_selesai]">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="tindakan_perbaikan[0][status]">
                                            <option value="Pending" selected>Pending</option>
                                            <option value="On Progress">On Progress</option>
                                            <option value="Completed">Completed</option>
                                            <option value="Closed">Closed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="tindakan-actions">
                                    <button type="button" class="btn btn-secondary btn-sm remove-tindakan" onclick="removeTindakan(this)">
                                        <i class="fas fa-trash"></i> Hapus Tindakan
                                    </button>
                                </div>
                                <hr style="margin: 20px 0;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-primary" id="addTindakan">
                                <i class="fas fa-plus"></i> Tambah Tindakan Perbaikan
                            </button>
                        </div>
                    </div>
                    
                    <!-- ================= SUMMARY TINDAKAN PERBAIKAN ================= -->

          
            
           
        
       
                    
                    <!-- ================= TOMBOL AKSI ================= -->
                    <div class="btn-container">
                        <button type="button" class="btn btn-secondary" id="resetForm">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Data
                        </button>
                        
                        <button type="button" class="btn btn-success" id="saveAndPDF">
                            <i class="fas fa-file-download"></i> Simpan & Download PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // ================= GENERATE NOMOR INSIDEN OTOMATIS =================
        document.getElementById('generateNumberBtn').addEventListener('click', function() {
            const year = new Date().getFullYear();
            const month = String(new Date().getMonth() + 1).padStart(2, '0');
            
            // Generate format: INS-YYYYMM-001
            const insidenNumber = `INS-${year}${month}-001`;
            document.querySelector('input[name="insiden_number"]').value = insidenNumber;
        });
        
        // ================= TINDAKAN PERBAIKAN =================
        let tindakanCounter = 1;

        window.removeTindakan = function(button) {
            const tindakanItem = button.closest('.tindakan-item');
            if (tindakanItem) {
                const index = parseInt(tindakanItem.dataset.index);
                if (index === 0) {
                    // Reset item pertama
                    const inputs = tindakanItem.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        if (input.type !== 'button' && input.type !== 'file') {
                            input.value = '';
                            if (input.tagName === 'SELECT') {
                                input.selectedIndex = 0;
                            }
                        }
                    });
                    // Clear file preview
                    const preview = tindakanItem.querySelector('.file-preview');
                    if (preview) preview.innerHTML = '';
                    // Reset file input
                    const fileInput = tindakanItem.querySelector('input[type="file"]');
                    if (fileInput) fileInput.value = '';
                } else {
                    // Hapus item selain pertama
                    tindakanItem.remove();
                }
            }
            updateTindakanSummary();
        };

        // Fungsi untuk preview file yang diupload
        window.previewFiles = function(input, index) {
            const previewContainer = document.getElementById('filePreview' + index);
            if (!previewContainer) return;
            
            previewContainer.innerHTML = '';
            
            Array.from(input.files).forEach(file => {
                const fileDiv = document.createElement('div');
                fileDiv.className = 'file-item';
                fileDiv.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
                    <button type="button" class="btn-remove-file" onclick="removeFile(this, '${input.name}', ${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(fileDiv);
            });
        }

        // Fungsi untuk menghapus file dari preview
        window.removeFile = function(button, inputName, index) {
            const fileItem = button.closest('.file-item');
            if (fileItem) {
                fileItem.remove();
                
                // Reset the file input
                const fileInput = document.querySelector(`[name="tindakan_perbaikan[${index}][lampiran][]"]`);
                if (fileInput) {
                    fileInput.value = '';
                }
            }
        }

        // Event listener untuk menambah tindakan
        document.getElementById('addTindakan').addEventListener('click', function() {
            const container = document.getElementById('tindakanContainer');
            const newIndex = tindakanCounter;
            
            const newTindakan = document.createElement('div');
            newTindakan.className = 'tindakan-item';
            newTindakan.dataset.index = newIndex;
            
            newTindakan.innerHTML = `
                <div class="field-group">
                    <div class="form-group">
                        <label>Jenis Tindakan</label>
                        <select name="tindakan_perbaikan[${newIndex}][jenis_tindakan]" class="jenis-tindakan-select">
                            <option value="">Pilih Jenis Tindakan</option>
                            <option value="Korektif">Korektif</option>
                            <option value="Pencegahan">Pencegahan</option>
                            <option value="Perbaikan">Perbaikan</option>
                            <option value="Peningkatan">Peningkatan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Penanggung Jawab</label>
                        <input type="text" name="tindakan_perbaikan[${newIndex}][penanggungjawab]" 
                               placeholder="Nama penanggung jawab...">
                    </div>
                </div>
                
                <!-- REKOMENDASI SEBELUM DESKRIPSI -->
                <div class="form-group rekomendasi-field">
                    <label>Rekomendasi</label>
                    <textarea name="tindakan_perbaikan[${newIndex}][rekomendasi]" rows="2" 
                              placeholder="Rekomendasi untuk tindakan ini (opsional)..."></textarea>
                    <small class="text-muted">Rekomendasi atau saran tambahan sebelum tindakan</small>
                </div>
                
                <div class="form-group">
                    <label>Deskripsi Tindakan</label>
                    <textarea name="tindakan_perbaikan[${newIndex}][deskripsi]" rows="3" 
                              placeholder="Deskripsi lengkap tindakan perbaikan..."></textarea>
                </div>
                
                <!-- FIELD UPLOAD FILE -->
                <div class="form-group">
                    <label>Lampiran/Dokumen Pendukung</label>
                    <div class="file-upload-container">
                        <input type="file" 
                               name="tindakan_perbaikan[${newIndex}][lampiran][]" 
                               class="file-upload-input"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                               multiple
                               onchange="previewFiles(this, ${newIndex})">
                        <div class="file-preview" id="filePreview${newIndex}"></div>
                    </div>
                    <small class="text-muted">Format: PDF, DOC, XLS, JPG, PNG (Maks. 5MB per file)</small>
                </div>
                
                <div class="field-group">
                    <div class="form-group">
                        <label>Target Selesai</label>
                        <input type="date" name="tindakan_perbaikan[${newIndex}][target_selesai]">
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="tindakan_perbaikan[${newIndex}][status]">
                            <option value="Pending" selected>Pending</option>
                            <option value="On Progress">On Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
                
                <div class="tindakan-actions">
                    <button type="button" class="btn btn-secondary btn-sm remove-tindakan" onclick="removeTindakan(this)">
                        <i class="fas fa-trash"></i> Hapus Tindakan
                    </button>
                </div>
                <hr style="margin: 20px 0;">
            `;
            
            container.appendChild(newTindakan);
            tindakanCounter++;
            updateTindakanSummary();
        });
        
        // Validasi ukuran file
        document.addEventListener('change', function(e) {
            if (e.target.type === 'file') {
                const files = e.target.files;
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                for (let file of files) {
                    if (file.size > maxSize) {
                        alert(`File ${file.name} melebihi ukuran maksimal 5MB`);
                        e.target.value = '';
                        const preview = e.target.parentElement.querySelector('.file-preview');
                        if (preview) preview.innerHTML = '';
                        break;
                    }
                }
            }
        });
        
        // ================= ANALISIS FAKTOR =================
        let selectedFactors = {
            personil: [],
            pekerjaan: []
        };
        
        <?php 
        if (isset($_POST['faktor_personil'])) {
            foreach ($_POST['faktor_personil'] as $faktor_value) {
                $parts = explode('|', $faktor_value, 2);
                if (count($parts) === 2) {
                    list($kode, $deskripsi) = $parts;
                    echo "selectedFactors.personil.push({kode: '" . addslashes(trim($kode)) . "', deskripsi: '" . addslashes(trim($deskripsi)) . "'});\n";
                }
            }
        }

        if (isset($_POST['faktor_pekerjaan'])) {
            foreach ($_POST['faktor_pekerjaan'] as $faktor_value) {
                $parts = explode('|', $faktor_value, 2);
                if (count($parts) === 2) {
                    list($kode, $deskripsi) = $parts;
                    echo "selectedFactors.pekerjaan.push({kode: '" . addslashes(trim($kode)) . "', deskripsi: '" . addslashes(trim($deskripsi)) . "'});\n";
                }
            }
        }
        ?>
        
        // Function to update selected factors display
        function updateSelectedFactorsDisplay() {
            const selectedList = document.getElementById('selectedFaktorList');
            const personilCount = document.getElementById('personilCount');
            const pekerjaanCount = document.getElementById('pekerjaanCount');
            const totalCount = document.getElementById('totalCount');
            
            let html = '';
            let total = 0;
            
            // Personil factors
            if (selectedFactors.personil.length > 0) {
                selectedFactors.personil.forEach(factor => {
                    html += `
                        <div class="selected-item" data-kode="${factor.kode}" data-tipe="personil">
                            <div class="selected-item-code">${factor.kode}</div>
                            <div class="selected-item-desc">${factor.deskripsi}</div>
                            <span class="selected-item-type personil-type">Personil</span>
                            <button type="button" class="remove-selected" onclick="removeFactor('${factor.kode}', 'personil')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                });
                total += selectedFactors.personil.length;
            }
            
            // Pekerjaan factors
            if (selectedFactors.pekerjaan.length > 0) {
                selectedFactors.pekerjaan.forEach(factor => {
                    html += `
                        <div class="selected-item" data-kode="${factor.kode}" data-tipe="pekerjaan">
                            <div class="selected-item-code">${factor.kode}</div>
                            <div class="selected-item-desc">${factor.deskripsi}</div>
                            <span class="selected-item-type pekerjaan-type">Pekerjaan</span>
                            <button type="button" class="remove-selected" onclick="removeFactor('${factor.kode}', 'pekerjaan')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                });
                total += selectedFactors.pekerjaan.length;
            }
            
            if (html === '') {
                html = '<div class="empty-selection">Belum ada faktor yang dipilih</div>';
            }
            
            selectedList.innerHTML = html;
            personilCount.textContent = selectedFactors.personil.length;
            pekerjaanCount.textContent = selectedFactors.pekerjaan.length;
            totalCount.textContent = total;
            
            // Update checkboxes
            updateCheckboxStates();
            
            // Update hidden inputs
            updateHiddenFactorInputs();
        }
        
        // Function to remove factor
        window.removeFactor = function(kode, tipe) {
            const escapedKode = kode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            
            if (tipe === 'personil') {
                selectedFactors.personil = selectedFactors.personil.filter(f => f.kode !== kode);
            } else {
                selectedFactors.pekerjaan = selectedFactors.pekerjaan.filter(f => f.kode !== kode);
            }
            updateSelectedFactorsDisplay();
        };
        
        // Function to update checkbox states
        function updateCheckboxStates() {
            document.querySelectorAll('.factor-checkbox').forEach(checkbox => {
                const value = checkbox.value;
                const tipe = checkbox.dataset.tipe;
                
                let isSelected = false;
                if (tipe === 'personil') {
                    isSelected = selectedFactors.personil.some(f => f.kode + '|' + f.deskripsi === value);
                } else if (tipe === 'pekerjaan') {
                    isSelected = selectedFactors.pekerjaan.some(f => f.kode + '|' + f.deskripsi === value);
                }
                
                checkbox.checked = isSelected;
            });
        }
        
        // Function to update hidden inputs
        function updateHiddenFactorInputs() {
            const container = document.getElementById('hiddenFactorsContainer');
            container.innerHTML = '';
            
            // Add personil factors
            selectedFactors.personil.forEach((factor, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'faktor_personil[]';
                input.value = factor.kode + '|' + factor.deskripsi;
                container.appendChild(input);
            });
            
            // Add pekerjaan factors
            selectedFactors.pekerjaan.forEach((factor, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'faktor_pekerjaan[]';
                input.value = factor.kode + '|' + factor.deskripsi;
                container.appendChild(input);
            });
        }
        
        // Handle checkbox changes
        document.querySelectorAll('.factor-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const value = this.value;
                const parts = value.split('|');
                if (parts.length < 2) {
                    console.error('Format value tidak valid:', value);
                    return;
                }
                
                const kode = parts[0].trim();
                const deskripsi = parts.slice(1).join('|').trim();
                const tipe = this.dataset.tipe;
                
                if (this.checked) {
                    // Add to selected factors
                    const factor = {
                        kode: kode,
                        deskripsi: deskripsi
                    };
                    
                    if (tipe === 'personil') {
                        const exists = selectedFactors.personil.some(f => f.kode === kode);
                        if (!exists) {
                            selectedFactors.personil.push(factor);
                        }
                    } else if (tipe === 'pekerjaan') {
                        const exists = selectedFactors.pekerjaan.some(f => f.kode === kode);
                        if (!exists) {
                            selectedFactors.pekerjaan.push(factor);
                        }
                    }
                } else {
                    // Remove from selected factors
                    if (tipe === 'personil') {
                        selectedFactors.personil = selectedFactors.personil.filter(f => f.kode !== kode);
                    } else if (tipe === 'pekerjaan') {
                        selectedFactors.pekerjaan = selectedFactors.pekerjaan.filter(f => f.kode !== kode);
                    }
                }
                
                updateSelectedFactorsDisplay();
            });
        });
        
        // Search functionality for factors
        document.getElementById('searchFaktor').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.faktor-master-table tbody tr');
            
            rows.forEach(row => {
                if (row.classList.contains('category-row')) {
                    row.style.display = '';
                    return;
                }
                
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    // Show parent rows if child matches
                    if (row.classList.contains('sub-factor-row')) {
                        const parentCode = row.querySelector('.factor-checkbox').dataset.parent;
                        const parentRows = document.querySelectorAll('.main-factor-row');
                        parentRows.forEach(parentRow => {
                            const mainCode = parentRow.querySelector('td strong').textContent;
                            if (mainCode === parentCode) {
                                parentRow.style.display = '';
                            }
                        });
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Clear all factor selections
        document.getElementById('clearFaktor').addEventListener('click', function() {
            if (confirm('Apakah Anda yakin ingin menghapus semua pilihan faktor?')) {
                selectedFactors.personil = [];
                selectedFactors.pekerjaan = [];
                updateSelectedFactorsDisplay();
            }
        });
        
        // Clear deskripsi
        document.getElementById('clearDeskripsi').addEventListener('click', function() {
            if (confirm('Apakah Anda yakin ingin menghapus deskripsi kejadian?')) {
                document.getElementById('input_deskripsi').value = '';
            }
        });
        
        // Initial display update
        updateSelectedFactorsDisplay();
        
        // ================= FORM HANDLING =================
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset semua data?')) {
                // Reset form utama
                document.getElementById('scatForm').reset();
                document.getElementById('tingkat_risiko_input').value = '';
                selectedFactors.personil = [];
                selectedFactors.pekerjaan = [];
                updateSelectedFactorsDisplay();
                
                // Reset semua tindakan perbaikan kecuali yang pertama
                const tindakanItems = document.querySelectorAll('.tindakan-item');
                tindakanItems.forEach((item, index) => {
                    if (index > 0) {
                        item.remove();
                    } else {
                        // Reset form item pertama
                        const inputs = item.querySelectorAll('input, select, textarea');
                        inputs.forEach(input => {
                            if (input.type !== 'button' && input.type !== 'file') {
                                input.value = '';
                                if (input.tagName === 'SELECT') {
                                    input.selectedIndex = 0;
                                }
                            }
                        });
                        // Clear file preview
                        const preview = item.querySelector('.file-preview');
                        if (preview) preview.innerHTML = '';
                        // Reset file input
                        const fileInput = item.querySelector('input[type="file"]');
                        if (fileInput) fileInput.value = '';
                    }
                });
                
                // Reset counter
                tindakanCounter = 1;
                
                // Clear all error messages
                document.querySelectorAll('.error-message').forEach(msg => msg.remove());
                document.querySelectorAll('[required]').forEach(field => {
                    field.style.borderColor = '#e0e0e0';
                });
                
                // Reset semua radio button CAN ke tidak terpilih
                document.querySelectorAll('input[name^="penyebab_dasar"]').forEach(radio => {
                    radio.checked = false;
                });
                
                // Reset analisis risiko
                document.getElementById('riskResult').innerHTML = '<p>Bagian ini opsional. Isi parameter di atas untuk melihat analisis risiko.</p>';
                
                // Reset semua input rupiah
                document.querySelectorAll('input[name="perkiraan_kerugian"], input[name="potensi_kerugian"]').forEach(input => {
                    input.value = '';
                });
                
                // Reset nomor insiden
                document.querySelector('input[name="insiden_number"]').value = '';
                
                // Update summary
                updateTindakanSummary();
            }
        }
        
        document.getElementById('resetForm').addEventListener('click', resetForm);
        document.getElementById('saveAndPDF').addEventListener('click', saveAndPDF);
        
        // ================= FORMAT CURRENCY & UPDATE HIDDEN VALUES =================
        function formatCurrency(input) {
            // Hapus semua karakter selain angka
            let value = input.value.replace(/[^\d]/g, '');
            
            // Simpan nilai raw ke hidden input
            const rawInputId = input.id + '_raw';
            const rawInput = document.getElementById(rawInputId);
            
            if (rawInput) {
                rawInput.value = value;
            }
            
            // Format ke Rupiah
            if (value) {
                let formatted = 'Rp ' + parseInt(value, 10).toLocaleString('id-ID');
                input.value = formatted;
            } else {
                input.value = '';
                if (rawInput) rawInput.value = '';
            }
        }

        // Function untuk mengupdate hidden values sebelum submit
        function updateHiddenCurrencyValues() {
            const perkiraanInput = document.getElementById('perkiraan_kerugian');
            const potensiInput = document.getElementById('potensi_kerugian'); // NAMA BERUBAH
            
            if (perkiraanInput) {
                const perkiraanRaw = perkiraanInput.value.replace(/[^\d]/g, '');
                document.getElementById('perkiraan_kerugian_raw').value = perkiraanRaw;
            }
            
            if (potensiInput) {
                const potensiRaw = potensiInput.value.replace(/[^\d]/g, '');
                document.getElementById('potensi_kerugian_raw').value = potensiRaw; // NAMA BERUBAH
            }
        }

        // Handle form submission
        document.getElementById('scatForm').addEventListener('submit', function(e) {
            // Update hidden currency values sebelum submit
            updateHiddenCurrencyValues();
            
            // Update hidden factor inputs sebelum submit
            updateHiddenFactorInputs();
            
            // Validate required fields
            const requiredFields = document.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#e74c3c';
                    if (!field.parentNode.querySelector('.error-message')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'error-message';
                        errorMsg.textContent = 'Field ini wajib diisi';
                        field.parentNode.appendChild(errorMsg);
                    }
                } else {
                    field.style.borderColor = '#e0e0e0';
                    const errorMsg = field.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Validasi tingkat risiko
            const riskInput = document.getElementById('tingkat_risiko_input');
            if (!riskInput.value) {
                riskInput.value = 'Tidak Dianalisis';
            }
            
            if (!valid) {
                e.preventDefault();
                alert('Harap isi semua field yang wajib diisi!');
            }
        });
        
        function saveAndPDF() {
            document.getElementById('downloadPDFFlag').value = '1';
            
            // Update hidden currency values sebelum submit
            updateHiddenCurrencyValues();
            
            // Update hidden factor inputs sebelum submit
            updateHiddenFactorInputs();
            
            // Submit form
            document.getElementById('scatForm').submit();
        }
        
        // ================= ANALISIS RISIKO SEDERHANA =================
        const kemungkinanKeparahanInputs = document.querySelectorAll('input[name="kemungkinan_keparahan"]');
        const kemungkinanKejadianInputs = document.querySelectorAll('input[name="kemungkinan_kejadian"]');
        const frekuensiTerpaparInputs = document.querySelectorAll('input[name="frekuensi_terpapar"]');
        const tingkatRisikoInput = document.getElementById('tingkat_risiko_input');
        const riskResult = document.getElementById('riskResult');

        function calculateRiskLevel() {
            const keparahan = getSelectedValue('kemungkinan_keparahan');
            const kejadian = getSelectedValue('kemungkinan_kejadian');
            const frekuensi = getSelectedValue('frekuensi_terpapar');
            
            // Jika tidak ada yang dipilih, tampilkan pesan opsional
            if (!keparahan && !kejadian && !frekuensi) {
                riskResult.innerHTML = '<p>Bagian ini opsional. Isi parameter di atas untuk melihat analisis risiko.</p>';
                tingkatRisikoInput.value = 'Tidak Dianalisis';
                return;
            }
            
            // Jika ada yang dipilih tapi tidak lengkap
            if (!keparahan || !kejadian || !frekuensi) {
                riskResult.innerHTML = '<p style="color: #f39c12;">Pilih semua parameter untuk analisis risiko lengkap</p>';
                tingkatRisikoInput.value = 'Belum Lengkap';
                return;
            }
            
            // Logika sederhana untuk menentukan tingkat risiko
            let riskLevel = '';
            let riskColorClass = '';
            let riskExplanation = '';
            
            if (keparahan === 'Major (A)' || kejadian === 'High (A)' || frekuensi === 'Extensive (A)') {
                if (keparahan === 'Major (A)' && (kejadian === 'High (A)' || frekuensi === 'Extensive (A)')) {
                    riskLevel = 'Extreme';
                    riskColorClass = 'risk-extreme';
                    riskExplanation = 'Risiko sangat tinggi, perlu tindakan segera';
                } else {
                    riskLevel = 'High';
                    riskColorClass = 'risk-high';
                    riskExplanation = 'Risiko tinggi, perlu perhatian serius';
                }
            } else if (keparahan === 'Serious (B)' || kejadian === 'Moderate (B)' || frekuensi === 'Moderate (B)') {
                riskLevel = 'Medium';
                riskColorClass = 'risk-medium';
                riskExplanation = 'Risiko sedang, perlu pengendalian';
            } else {
                riskLevel = 'Low';
                riskColorClass = 'risk-low';
                riskExplanation = 'Risiko rendah, tetap perlu monitoring';
            }
            
            // Update tampilan
            riskResult.innerHTML = `
                <div class="risk-level-display ${riskColorClass}">
                    ${riskLevel} Risk
                    <div class="risk-explanation">${riskExplanation}</div>
                </div>
                <div style="margin-top: 15px; text-align: center; font-size: 14px; color: #7f8c8d;">
                    <div>Keparahan: ${keparahan}</div>
                    <div>Kejadian: ${kejadian}</div>
                    <div>Frekuensi: ${frekuensi}</div>
                </div>
            `;
            
            // Update input hidden
            tingkatRisikoInput.value = riskLevel;
        }

        // Helper function untuk mendapatkan nilai yang dipilih
        function getSelectedValue(name) {
            const selected = document.querySelector(`input[name="${name}"]:checked`);
            return selected ? selected.value : '';
        }

        // Tambahkan event listener untuk semua radio button
        kemungkinanKeparahanInputs.forEach(input => {
            input.addEventListener('change', calculateRiskLevel);
        });

        kemungkinanKejadianInputs.forEach(input => {
            input.addEventListener('change', calculateRiskLevel);
        });

        frekuensiTerpaparInputs.forEach(input => {
            input.addEventListener('change', calculateRiskLevel);
        });

        // Hitung risiko awal jika ada data sebelumnya
        calculateRiskLevel();
        
        // ================= SUMMARY TINDAKAN PERBAIKAN =================
        function updateTindakanSummary() {
            const tindakanItems = document.querySelectorAll('.tindakan-item');
            const totalTindakan = document.getElementById('totalTindakan');
            const pendingCount = document.getElementById('pendingCount');
            const progressCount = document.getElementById('progressCount');
            const completedCount = document.getElementById('completedCount');
            const summaryTableBody = document.getElementById('summaryTableBody');
            
            let total = 0;
            let pending = 0;
            let progress = 0;
            let completed = 0;
            let tableHTML = '';
            
            tindakanItems.forEach((item, index) => {
                const jenisTindakan = item.querySelector('[name*="[jenis_tindakan]"]')?.value || '';
                const penanggungjawab = item.querySelector('[name*="[penanggungjawab]"]')?.value || '';
                const targetSelesai = item.querySelector('[name*="[target_selesai]"]')?.value || '';
                const status = item.querySelector('[name*="[status]"]')?.value || '';
                const deskripsi = item.querySelector('[name*="[deskripsi]"]')?.value || '';
                
                // Hanya hitung jika ada data minimal
                if (jenisTindakan || penanggungjawab || deskripsi) {
                    total++;
                    
                    // Hitung status
                    switch(status) {
                        case 'Pending':
                            pending++;
                            break;
                        case 'On Progress':
                            progress++;
                            break;
                        case 'Completed':
                        case 'Closed':
                            completed++;
                            break;
                    }
                    
                    // Tambahkan ke tabel summary
                    const shortDesc = deskripsi.length > 50 ? deskripsi.substring(0, 50) + '...' : deskripsi;
                    tableHTML += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;">${index + 1}</td>
                            <td style="padding: 10px;">${escapeHtml(jenisTindakan)}</td>
                            <td style="padding: 10px;">${escapeHtml(penanggungjawab)}</td>
                            <td style="padding: 10px;">${targetSelesai || '-'}</td>
                            <td style="padding: 10px;">
                                <span class="status-badge" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                    ${status === 'Pending' ? 'background: #d4edda; color: #155724;' : 
                                    status === 'On Progress' ? 'background: #fff3cd; color: #856404;' :
                                    status === 'Completed' ? 'background: #d1ecf1; color: #0c5460;' :
                                    'background: #f8d7da; color: #721c24;'}">
                                    ${status || 'Pending'}
                                </span>
                            </td>
                            <td style="padding: 10px;">${escapeHtml(shortDesc)}</td>
                        </tr>
                    `;
                }
            });
            
            // Update tampilan
            totalTindakan.textContent = total;
            pendingCount.textContent = pending;
            progressCount.textContent = progress;
            completedCount.textContent = completed;
            
            if (tableHTML) {
                summaryTableBody.innerHTML = tableHTML;
            } else {
                summaryTableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #95a5a6; font-style: italic;">
                            Belum ada data tindakan perbaikan
                        </td>
                    </tr>
                `;
            }
            
            // Update chart
            updateTindakanChart(pending, progress, completed, total);
        }

        function updateTindakanChart(pending, progress, completed, total) {
            const pendingBar = document.getElementById('pendingBar');
            const progressBar = document.getElementById('progressBar');
            const completedBar = document.getElementById('completedBar');
            
            const maxHeight = total > 0 ? Math.max(pending, progress, completed) : 1;
            
            setTimeout(() => {
                pendingBar.style.height = total > 0 ? ((pending / maxHeight) * 100) + '%' : '0%';
                progressBar.style.height = total > 0 ? ((progress / maxHeight) * 100) + '%' : '0%';
                completedBar.style.height = total > 0 ? ((completed / maxHeight) * 100) + '%' : '0%';
            }, 100);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ================= EXPORT KE EXCEL =================
        document.getElementById('exportSummaryBtn').addEventListener('click', function() {
            const tindakanItems = document.querySelectorAll('.tindakan-item');
            const exportData = [];
            
            // Data insiden
            const insidenData = {
                judul_insiden: document.querySelector('[name="judul_insiden"]')?.value || '',
                tanggal: document.querySelector('[name="tanggal"]')?.value || '',
                lokasi: document.querySelector('[name="lokasi"]')?.value || '',
                penyelidik: document.querySelector('[name="penyelidik"]')?.value || '',
                insiden_number: document.querySelector('[name="insiden_number"]')?.value || ''
            };
            
            // Kumpulkan data tindakan
            tindakanItems.forEach((item, index) => {
                const jenisTindakan = item.querySelector('[name*="[jenis_tindakan]"]')?.value || '';
                const deskripsi = item.querySelector('[name*="[deskripsi]"]')?.value || '';
                const rekomendasi = item.querySelector('[name*="[rekomendasi]"]')?.value || '';
                const penanggungjawab = item.querySelector('[name*="[penanggungjawab]"]')?.value || '';
                const targetSelesai = item.querySelector('[name*="[target_selesai]"]')?.value || '';
                const status = item.querySelector('[name*="[status]"]')?.value || '';
                
                if (jenisTindakan || deskripsi || penanggungjawab) {
                    exportData.push({
                        no: index + 1,
                        jenis_tindakan: jenisTindakan,
                        deskripsi: deskripsi,
                        rekomendasi: rekomendasi,
                        penanggungjawab: penanggungjawab,
                        target_selesai: targetSelesai,
                        status: status
                    });
                }
            });
            
            if (exportData.length === 0) {
                alert('Tidak ada data tindakan untuk diexport');
                return;
            }
            
            // Buat form untuk submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_excel.php';
            form.style.display = 'none';
            form.target = '_blank'; // Buka di tab baru
            
            // Tambahkan input untuk data tindakan
            const tindakanInput = document.createElement('input');
            tindakanInput.type = 'hidden';
            tindakanInput.name = 'tindakan_data';
            tindakanInput.value = JSON.stringify(exportData);
            form.appendChild(tindakanInput);
            
            // Tambahkan input untuk data insiden
            const insidenInput = document.createElement('input');
            insidenInput.type = 'hidden';
            insidenInput.name = 'insiden_data';
            insidenInput.value = JSON.stringify(insidenData);
            form.appendChild(insidenInput);
            
            // Tambahkan CSRF token jika diperlukan
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
            form.appendChild(csrfInput);
            
            // Tambahkan ke dokumen dan submit
            document.body.appendChild(form);
            form.submit();
            
            // Hapus form setelah submit
            setTimeout(() => {
                document.body.removeChild(form);
            }, 1000);
        });

        // ================= EVENT LISTENERS UNTUK UPDATE SUMMARY =================
        // Update summary ketika form berubah
        document.addEventListener('input', function(e) {
            if (e.target.closest('.tindakan-item')) {
                updateTindakanSummary();
            }
        });

        // Update summary ketika status berubah
        document.addEventListener('change', function(e) {
            if (e.target.name && e.target.name.includes('[status]')) {
                updateTindakanSummary();
            }
            
            // Update summary ketika menambah/menghapus tindakan
            if (e.target.id === 'addTindakan' || e.target.closest('.remove-tindakan')) {
                setTimeout(updateTindakanSummary, 100);
            }
        });

        // Update summary saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            updateTindakanSummary();
            
            // Auto-update setiap 5 detik jika ada perubahan
            setInterval(updateTindakanSummary, 5000);
        });

        // Format currency on page load if there's previous data
        document.addEventListener('DOMContentLoaded', function() {
            const perkiraanInput = document.getElementById('perkiraan_kerugian');
            const potensiInput = document.getElementById('potensi_kerugian');
            
            if (perkiraanInput && perkiraanInput.value) {
                formatCurrency(perkiraanInput);
                // Update hidden value
                const perkiraanRaw = perkiraanInput.value.replace(/[^\d]/g, '');
                document.getElementById('perkiraan_kerugian_raw').value = perkiraanRaw;
            }
            if (potensiInput && potensiInput.value) {
                formatCurrency(potensiInput);
                // Update hidden value
                const potensiRaw = potensiInput.value.replace(/[^\d]/g, '');
                document.getElementById('potensi_kerugian_raw').value = potensiRaw;
            }
        });
    </script>
</body>
</html>