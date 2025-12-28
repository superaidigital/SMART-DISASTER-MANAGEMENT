<?php
/**
 * includes/header.php
 * ส่วนหัวของหน้าเว็บ (Navbar & Sidebar Structure)
 * ปรับปรุง: แก้ไขลูกศรเมนูซ้อน (เหลือลูกศรตัวเดียว) และจัดสไตล์ Modern
 */

// เริ่ม Session หากยังไม่มี
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// เชื่อมต่อฐานข้อมูลและโหลดฟังก์ชัน
require_once __DIR__ . '/../config/db.php'; 

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'guest';
$is_admin = ($role === 'admin');
$my_shelter_id = $_SESSION['shelter_id'] ?? '';

// ดึงชื่อศูนย์ที่รับผิดชอบมาแสดง
$user_shelter_name = '';
if ($my_shelter_id && isset($conn)) {
    $stmt_sh = $conn->prepare("SELECT name FROM shelters WHERE id = ?");
    if ($stmt_sh) {
        $stmt_sh->bind_param("i", $my_shelter_id);
        $stmt_sh->execute();
        $res_sh = $stmt_sh->get_result();
        if ($row_sh = $res_sh->fetch_assoc()) {
            $user_shelter_name = $row_sh['name'];
        }
        $stmt_sh->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการศูนย์พักพิงอัจฉริยะ</title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --sidebar-width: 270px;
            --nav-bg: #0f172a;
            --nav-hover: #1e293b;
            --active-gold: #fbbf24;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --body-bg: #f8fafc;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--body-bg);
            color: #334155;
            overflow-x: hidden;
        }

        .wrapper { display: flex; width: 100%; align-items: stretch; }

        /* Sidebar Styling */
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background-color: var(--nav-bg);
            color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 1050;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            overflow-y: auto;
        }

        body.sidebar-collapsed #sidebar { margin-left: calc(var(--sidebar-width) * -1); }
        body.sidebar-collapsed #content { margin-left: 0; }

        #content {
            width: 100%;
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-left: var(--sidebar-width);
            position: relative;
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: all 0.3s;
        }
        .sidebar-overlay.active { display: block; opacity: 1; }

        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(var(--sidebar-width) * -1); }
            #sidebar.show-mobile { margin-left: 0; }
            #content { margin-left: 0; }
        }

        /* Sidebar Header & User Panel */
        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0,0,0,0.2);
            text-align: center;
            border-bottom: 3px solid var(--active-gold);
        }

        .user-panel {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex; align-items: center; gap: 12px;
        }

        .user-avatar {
            width: 42px; height: 42px;
            background: var(--active-gold);
            color: var(--nav-bg);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 1.2rem;
        }

        /* Menu Components */
        .menu-label {
            font-size: 0.7rem; text-transform: uppercase;
            color: var(--text-muted); padding: 20px 25px 8px;
            font-weight: 600; letter-spacing: 1px;
        }

        ul.components { padding: 0; list-style: none; }
        ul.components li a {
            padding: 12px 25px; font-size: 0.95rem;
            display: flex; align-items: center;
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s; border-left: 4px solid transparent;
        }

        ul.components li a:hover, ul.components li a.active {
            color: #fff; background-color: var(--nav-hover);
            border-left-color: var(--active-gold);
        }

        ul.components li a i { width: 24px; text-align: center; margin-right: 12px; font-size: 1.1rem; }
        ul.components li a.active i { color: var(--active-gold); }

        /* --- แก้ไขลูกศรตัวเดียว --- */
        .dropdown-toggle::after {
            display: none !important; /* ปิดลูกศรเดิมของ Bootstrap */
        }
        
        .dropdown-icon { 
            margin-left: auto; 
            font-size: 0.75rem; 
            transition: transform 0.3s; 
            opacity: 0.5;
        }
        
        a[aria-expanded="true"] .dropdown-icon { 
            transform: rotate(180deg); 
            opacity: 1;
            color: var(--active-gold);
        }

        ul.collapse { background-color: rgba(0,0,0,0.1); list-style: none; padding: 0; }
        ul.collapse li a { padding-left: 60px; font-size: 0.88rem; border-left: none; }
        ul.collapse li a:hover { border-left: none; background-color: rgba(255,255,255,0.05); }

        /* Top Navbar */
        .top-navbar {
            height: 70px;
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 0 25px; display: flex; align-items: center;
            justify-content: space-between; position: sticky; top: 0; z-index: 900;
        }

        #sidebarCollapse {
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 10px; background: #f1f5f9; color: #475569;
            cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0;
        }
        #sidebarCollapse:hover { background: #fff; color: var(--active-gold); border-color: var(--active-gold); }
    </style>
</head>
<body class="<?php echo (isset($_COOKIE['sidebar_state']) && $_COOKIE['sidebar_state'] == 'collapsed') ? 'sidebar-collapsed' : ''; ?>">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="mb-2"><i class="fas fa-shield-alt fa-2x text-warning"></i></div>
            <div class="fw-bold text-white h6 mb-0">ระบบบริหารศูนย์พักพิงฯ</div>
            <div class="text-muted small" style="font-size: 0.6rem;">SMART DISASTER MANAGEMENT</div>
        </div>

        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="user-panel">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                <div class="overflow-hidden">
                    <div class="text-white small fw-bold text-truncate"><?php echo $_SESSION['username'] ?? 'User'; ?></div>
                    <div class="text-warning text-truncate" style="font-size: 0.7rem;">
                        <i class="fas fa-map-marker-alt me-1"></i><?php echo $user_shelter_name ?: 'ส่วนกลาง'; ?>
                    </div>
                </div>
            </div>

            <ul class="components">
                <li>
                    <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> <span>หน้าหลักภาพรวม</span>
                    </a>
                </li>

                <li class="menu-label">War Room & สถานการณ์</li>
                <li><a href="monitor_dashboard.php" target="_blank"><i class="fas fa-desktop text-danger"></i> <span>War Room (Live)</span></a></li>
                <li><a href="gis_dashboard.php" class="<?php echo $current_page == 'gis_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-map-location-dot text-info"></i> <span>แผนที่ GIS</span></a></li>

                <li class="menu-label">งานทะเบียน & ผู้อพยพ</li>
                <li>
                    <a href="#evacueeSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo (strpos($current_page, 'evacuee') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> <span>ทะเบียนผู้ประสบภัย</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="collapse <?php echo (strpos($current_page, 'evacuee') !== false) ? 'show' : ''; ?>" id="evacueeSubmenu">
                        <li><a href="evacuee_list.php" class="<?php echo $current_page == 'evacuee_list.php' ? 'active' : ''; ?>">บัญชีรายชื่อ</a></li>
                        <li><a href="qr_scanner.php" class="<?php echo $current_page == 'qr_scanner.php' ? 'active' : ''; ?>">สแกน QR Code</a></li>
                    </ul>
                </li>

                <li class="menu-label">ทรัพยากร & คลัง</li>
                <li>
                    <a href="#logisticsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo (strpos($current_page, 'inventory') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-boxes-stacked"></i> <span>จัดการพัสดุ</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="collapse <?php echo (strpos($current_page, 'inventory') !== false) ? 'show' : ''; ?>" id="logisticsSubmenu">
                        <li><a href="inventory_dashboard.php" class="<?php echo $current_page == 'inventory_dashboard.php' ? 'active' : ''; ?>">แดชบอร์ดคลัง</a></li>
                        <li><a href="distribution_manager.php" class="<?php echo $current_page == 'distribution_manager.php' ? 'active' : ''; ?>">บันทึกการแจกจ่าย</a></li>
                    </ul>
                </li>

                <?php if($is_admin): ?>
                <li class="menu-label">ตั้งค่าระบบ</li>
                <li><a href="user_manager.php" class="<?php echo $current_page == 'user_manager.php' ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> <span>จัดการผู้ใช้งาน</span></a></li>
                <?php endif; ?>

                <li class="mt-4 px-3">
                    <a href="logout.php" class="btn btn-danger btn-sm w-100 text-white border-0 shadow-sm py-2">
                        <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <!-- Page Content Area -->
    <div id="content">
        <nav class="top-navbar shadow-sm">
            <div class="d-flex align-items-center">
                <div id="sidebarCollapse">
                    <i class="fas fa-bars-staggered"></i>
                </div>
                <div class="ms-3 d-none d-sm-block fw-bold text-dark">
                    <?php 
                        if($current_page == 'index.php') echo 'ภาพรวมสถานการณ์';
                        elseif($current_page == 'evacuee_list.php') echo 'ทะเบียนรายชื่อผู้ประสบภัย';
                        else echo 'ระบบบริหารจัดการศูนย์พักพิง';
                    ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="family_finder.php" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    <i class="fas fa-search me-1"></i> สำหรับประชาชน
                </a>
                <div class="vr d-none d-md-block" style="height: 20px;"></div>
                <div class="text-secondary small d-none d-md-block">
                    <?php echo date('d M Y'); ?>
                </div>
            </div>
        </nav>

        <div class="p-3 p-md-4">
            <!-- Content Starts Here -->