<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Function untuk escape output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : false;
    
    if ($confirm === 'true') {
        // Delete related data first (to maintain referential integrity)
        $mysqli->query("DELETE FROM jenis_kerugian WHERE insiden_id = $delete_id");
        $mysqli->query("DELETE FROM jenis_kejadian WHERE insiden_id = $delete_id");
        $mysqli->query("DELETE FROM penyebab_langsung WHERE insiden_id = $delete_id");
        $mysqli->query("DELETE FROM faktor_analisis WHERE insiden_id = $delete_id");
        $mysqli->query("DELETE FROM penyebab_dasar WHERE insiden_id = $delete_id");
        
        // Delete the main incident
        $result = $mysqli->query("DELETE FROM insiden WHERE id = $delete_id");
        
        if ($result) {
            $_SESSION['message'] = "Insiden berhasil dihapus!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Gagal menghapus insiden: " . $mysqli->error;
            $_SESSION['message_type'] = "error";
        }
        
        header("Location: dashboard.php");
        exit;
    } else {
        // Show confirmation dialog
        $confirm_message = "Apakah Anda yakin ingin menghapus insiden ini?";
        $confirm_url = "dashboard.php?delete_id=$delete_id&confirm=true";
    }
}

// Handle multiple delete
if (isset($_POST['delete_selected'])) {
    $selected_ids = $_POST['selected_insiden'] ?? [];
    
    if (!empty($selected_ids)) {
        $deleted_count = 0;
        foreach ($selected_ids as $id) {
            $id = intval($id);
            
            // Delete related data
            $mysqli->query("DELETE FROM jenis_kerugian WHERE insiden_id = $id");
            $mysqli->query("DELETE FROM jenis_kejadian WHERE insiden_id = $id");
            $mysqli->query("DELETE FROM penyebab_langsung WHERE insiden_id = $id");
            $mysqli->query("DELETE FROM faktor_analisis WHERE insiden_id = $id");
            $mysqli->query("DELETE FROM penyebab_dasar WHERE insiden_id = $id");
            
            // Delete the main incident
            if ($mysqli->query("DELETE FROM insiden WHERE id = $id")) {
                $deleted_count++;
            }
        }
        
        $_SESSION['message'] = "$deleted_count insiden berhasil dihapus!";
        $_SESSION['message_type'] = "success";
        
        header("Location: dashboard.php");
        exit;
    }
}

// Show message if exists
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Total insiden
$total_insiden = 0;
$insiden_bulan_ini = 0;
$total_penyebab = 0;
$insiden_kemarin = 0;

$result = $mysqli->query("SELECT COUNT(*) as total FROM insiden");
if ($result) {
    $row = $result->fetch_assoc();
    $total_insiden = $row['total'];
}

// Insiden bulan ini
$result2 = $mysqli->query("SELECT COUNT(*) as total FROM insiden WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
if ($result2) {
    $row = $result2->fetch_assoc();
    $insiden_bulan_ini = $row['total'];
}

// Insiden kemarin
$result_kemarin = $mysqli->query("SELECT COUNT(*) as total FROM insiden WHERE tanggal = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
if ($result_kemarin) {
    $row = $result_kemarin->fetch_assoc();
    $insiden_kemarin = $row['total'];
}

// Total penyebab
$result3 = $mysqli->query("SELECT COUNT(*) as total FROM penyebab_langsung");
if ($result3) {
    $row = $result3->fetch_assoc();
    $total_penyebab = $row['total'];
}

// Get all insiden for bulk operations
$all_insiden = [];
$result_all = $mysqli->query("SELECT i.*, u.username FROM insiden i LEFT JOIN users u ON i.dibuat_oleh = u.id ORDER BY i.created_at DESC");
if ($result_all) {
    while ($row = $result_all->fetch_assoc()) {
        $all_insiden[] = $row;
    }
}

// 5 insiden terbaru untuk display
$insiden_terbaru = array_slice($all_insiden, 0, 5);

// Data untuk statistik cepat
$hari_ini = date('Y-m-d');
$insiden_hari_ini = 0;
$result_hari_ini = $mysqli->query("SELECT COUNT(*) as total FROM insiden WHERE tanggal = '$hari_ini'");
if ($result_hari_ini) {
    $row = $result_hari_ini->fetch_assoc();
    $insiden_hari_ini = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard SCAT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --card-bg: #ffffff;
            --sidebar-bg: #2c3e50;
            --hover-bg: #34495e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }
        
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
        
        .main-content {
            margin-left: 260px;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title h1 {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .page-title p {
            color: var(--gray);
            font-size: 1rem;
        }
        
        .date-time {
            background: var(--card-bg);
            padding: 12px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            font-weight: 500;
        }
        
        /* STATISTIK CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card:nth-child(1) { border-left-color: var(--success); }
        .stat-card:nth-child(2) { border-left-color: var(--secondary); }
        .stat-card:nth-child(3) { border-left-color: var(--warning); }
        .stat-card:nth-child(4) { border-left-color: var(--danger); }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: rgba(39, 174, 96, 0.12);
            color: var(--success);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: rgba(52, 152, 219, 0.12);
            color: var(--secondary);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: rgba(243, 156, 18, 0.12);
            color: var(--warning);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background: rgba(231, 76, 60, 0.12);
            color: var(--danger);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 3px;
            color: var(--dark);
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            color: #777;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* INSIDEN TERBARU */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h3 {
            color: var(--dark);
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .see-all {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .see-all:hover {
            color: var(--primary);
            transform: translateX(3px);
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #eee;
            font-size: 0.9rem;
        }
        
        .table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        .table tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: rgba(39, 174, 96, 0.12);
            color: var(--success);
        }
        
        .badge-warning {
            background: rgba(243, 156, 18, 0.12);
            color: var(--warning);
        }
        
        .badge-danger {
            background: rgba(231, 76, 60, 0.12);
            color: var(--danger);
        }
        
        .action-link {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .action-link:hover {
            color: var(--primary);
            transform: translateX(3px);
        }
        
        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        .btn-view {
            background: var(--secondary);
            color: white;
        }
        
        .btn-edit {
            background: var(--warning);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* BULK ACTION */
        .bulk-action-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bulk-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .select-all input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .bulk-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-bulk-delete {
            background: var(--danger);
            color: white;
            padding: 8px 15px;
        }
        
        /* CHECKBOX COLUMN */
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .checkbox-column input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        /* TENTANG SCAT */
        .info-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-top: 20px;
            border-left: 4px solid var(--secondary);
        }
        
        .info-card h3 {
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .scat-info {
            margin-top: 15px;
        }
        
        .scat-step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .scat-step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .step-number {
            background: var(--secondary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            font-size: 0.85rem;
            color: var(--dark);
            margin: 0 0 3px 0;
        }
        
        .step-content p {
            font-size: 0.8rem;
            color: #666;
            margin: 0;
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
            opacity: 0.5;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* MESSAGE ALERT */
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* CONFIRMATION MODAL */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }
        
        .modal {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            color: var(--dark);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            margin-bottom: 25px;
            color: #555;
            line-height: 1.6;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
        
        .btn-confirm {
            background: var(--danger);
            color: white;
        }
        
        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .page-link {
            padding: 8px 12px;
            border-radius: 6px;
            background: #f8f9fa;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: var(--secondary);
            color: white;
        }
        
        .page-link.active {
            background: var(--secondary);
            color: white;
        }
        
        /* ANIMATIONS */
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
        
        /* RESPONSIVE DESIGN */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .date-time {
                width: 100%;
                text-align: center;
            }
            
            .bulk-action-section {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .bulk-controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                padding: 20px 15px;
            }
            
            .stat-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table th,
            .table td {
                padding: 10px 12px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>SCAT</h2>
            <div class="version"> Systematic Cause Analysis technique v1.0</div>
        </div>
        
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
            <div class="user-info">
                <h4><?php echo e($_SESSION['fullname']); ?></h4>
                <div class="role"><?php echo e(ucfirst($_SESSION['role'])); ?></div>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="form_input_scat.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Input SCAT</span>
            </a>
           
            <a href="logout.php" class="nav-item logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="page-header">
            <div class="page-title">
                <h1>Dashboard SCAT</h1>
                <p>Selamat datang, <?php echo e($_SESSION['fullname']); ?>! Pantau dan kelola insiden keselamatan Anda di sini.</p>
            </div>
            <div class="date-time">
                <i class="fas fa-calendar-alt"></i> 
                <?php echo date('l, d F Y'); ?> 
                <span id="live-time"></span>
            </div>
        </div>
        
        <!-- MESSAGE ALERT -->
        <?php if (isset($message)): ?>
        <div class="alert-message alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>"></i>
            <?php echo e($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- STATISTIK UTAMA -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_insiden; ?></h3>
                    <p>Total Insiden</p>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i>
                        <?php echo $insiden_bulan_ini; ?> bulan ini
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $insiden_hari_ini; ?></h3>
                    <p>Insiden Hari Ini</p>
                    <div class="stat-trend">
                        <i class="fas fa-calendar-day"></i>
                        <?php echo date('d M Y'); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_penyebab; ?></h3>
                    <p>Total Penyebab</p>
                    <div class="stat-trend">
                        <i class="fas fa-search"></i>
                        Teridentifikasi
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $insiden_kemarin; ?></h3>
                    <p>Insiden Kemarin</p>
                    <div class="stat-trend">
                        <i class="fas fa-calendar-minus"></i>
                        Sebelumnya
                    </div>
                </div>
            </div>
        </div>
        
        <!-- BULK ACTION SECTION -->
        <div class="bulk-action-section">
            <div class="bulk-controls">
                <div class="select-all">
                    <input type="checkbox" id="selectAll">
                    <label for="selectAll">Pilih Semua</label>
                </div>
                <div>
                    <span id="selectedCount">0</span> dipilih
                </div>
            </div>
            <div class="bulk-buttons">
                <form method="POST" id="bulkDeleteForm" onsubmit="return confirmBulkDelete()">
                    <input type="hidden" name="delete_selected" value="1">
                    <button type="submit" class="btn btn-bulk-delete">
                        <i class="fas fa-trash"></i> Hapus yang Dipilih
                    </button>
                </form>
            </div>
        </div>
        
        <!-- INSIDEN TERBARU (TABEL) -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Semua Insiden</h3>
               
            </div>
            <div class="table-responsive">
                <form method="POST" id="insidenForm">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="toggleAll">
                                </th>
                                <th>#</th>
                                <th>Judul Insiden</th>
                                <th>Tanggal</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($all_insiden) > 0): ?>
                                <?php foreach ($all_insiden as $index => $insiden): ?>
                                    <tr>
                                        <td class="checkbox-column">
                                            <input type="checkbox" name="selected_insiden[]" value="<?php echo $insiden['id']; ?>" class="insiden-checkbox">
                                        </td>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo e($insiden['judul_insiden']); ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($insiden['tanggal'])); ?></td>
                                        <td><?php echo e($insiden['lokasi']); ?></td>
                                        <td>
                                            <?php 
                                            $status = $insiden['id'] % 3;
                                            if ($status == 0) echo '<span class="badge badge-success">Selesai</span>';
                                            elseif ($status == 1) echo '<span class="badge badge-warning">Proses</span>';
                                            else echo '<span class="badge badge-danger">Butuh Tindakan</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="detail_insiden.php?id=<?php echo $insiden['id']; ?>" class="btn btn-view btn-sm">
                                                    <i class="fas fa-eye"></i> Lihat
                                                </a>
                                               <a href="form_edit_scat.php?id=<?php echo $insiden['id']; ?>" class="btn btn-edit btn-sm">  <i class="fas fa-edit"></i> Edit
</a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $insiden['id']; ?>)" class="btn btn-delete btn-sm">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Belum ada insiden yang tercatat</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        
        <!-- TENTANG SCAT -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Tentang SCAT (Safety Control Analysis Technique)</h3>
            <p>SCAT adalah metode sistematis untuk mengidentifikasi, menganalisis, dan mengendalikan bahaya di tempat kerja.</p>
            
            <div class="scat-info">
                <div class="scat-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Identifikasi Insiden</h4>
                        <p>Catat semua insiden yang terjadi di tempat kerja</p>
                    </div>
                </div>
                
                <div class="scat-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Analisis Faktor</h4>
                        <p>Temukan akar penyebab dengan analisis faktor personil dan pekerjaan</p>
                    </div>
                </div>
                
                <div class="scat-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Implementasi Tindakan</h4>
                        <p>Terapkan solusi preventif untuk mencegah terulangnya insiden</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRMATION MODAL -->
    <?php if (isset($confirm_message)): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
            </div>
            <div class="modal-body">
                <p><?php echo e($confirm_message); ?></p>
                <p><strong>Data yang dihapus tidak dapat dikembalikan!</strong></p>
            </div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Batal
                </a>
                <a href="<?php echo e($confirm_url); ?>" class="btn btn-confirm">
                    <i class="fas fa-trash"></i> Ya, Hapus
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Live time update
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('live-time').innerHTML = ' | ' + timeString;
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
        
        // Animasi stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Checkbox functionality
        const toggleAllCheckbox = document.getElementById('toggleAll');
        const selectAllCheckbox = document.getElementById('selectAll');
        const insidenCheckboxes = document.querySelectorAll('.insiden-checkbox');
        const selectedCount = document.getElementById('selectedCount');
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.insiden-checkbox:checked').length;
            selectedCount.textContent = selected;
        }
        
        // Toggle all in table
        if (toggleAllCheckbox) {
            toggleAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                insidenCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                selectAllCheckbox.checked = isChecked;
                updateSelectedCount();
            });
        }
        
        // Select all in bulk section
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                insidenCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                if (toggleAllCheckbox) {
                    toggleAllCheckbox.checked = isChecked;
                }
                updateSelectedCount();
            });
        }
        
        // Individual checkbox change
        insidenCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                
                // Update select all checkbox state
                const allChecked = document.querySelectorAll('.insiden-checkbox:checked').length === insidenCheckboxes.length;
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                if (toggleAllCheckbox) {
                    toggleAllCheckbox.checked = allChecked;
                }
            });
        });
        
        // Initial count update
        updateSelectedCount();
        
        // Single delete confirmation
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus insiden ini?\nData yang dihapus tidak dapat dikembalikan.')) {
                window.location.href = 'dashboard.php?delete_id=' + id + '&confirm=true';
            }
        }
        
        // Bulk delete confirmation
        function confirmBulkDelete() {
            const selectedCount = document.querySelectorAll('.insiden-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Pilih minimal satu insiden untuk dihapus.');
                return false;
            }
            
            return confirm(`Apakah Anda yakin ingin menghapus ${selectedCount} insiden?\nData yang dihapus tidak dapat dikembalikan.`);
        }
        
        // Populate form with selected checkboxes before bulk delete
        document.getElementById('bulkDeleteForm')?.addEventListener('submit', function(e) {
            const selectedCheckboxes = document.querySelectorAll('.insiden-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu insiden untuk dihapus.');
                return;
            }
            
            // Add selected IDs to form
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_insiden[]';
                hiddenInput.value = checkbox.value;
                this.appendChild(hiddenInput);
            });
        });
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal-overlay');
                if (modal) {
                    window.location.href = 'dashboard.php';
                }
            }
        });
        
        // Close modal on overlay click
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                window.location.href = 'dashboard.php';
            }
        });
    </script>
</body>
</html>