<?php
/**
 * inventory_dashboard.php
 * หน้าแสดงภาพรวมคลังสินค้าและทรัพยากร
 * ปรับปรุง: ดีไซน์ Modern, ระบบแจ้งเตือนสต็อกต่ำ, และสรุปแยกตามหมวดหมู่
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'guest';
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// เตรียมข้อมูลสถิติ
$stats = [
    'total_items' => 0,
    'total_qty' => 0,
    'low_stock_count' => 0,
    'pending_requests' => 0
];

try {
    // 1. สรุปภาพรวมพัสดุ
    $res_main = $conn->query("SELECT 
        COUNT(*) as total_types, 
        SUM(quantity) as total_sum,
        COUNT(CASE WHEN quantity < 50 THEN 1 END) as low_count 
        FROM inventory");
    $data_main = $res_main->fetch_assoc();
    $stats['total_items'] = $data_main['total_types'] ?? 0;
    $stats['total_qty'] = $data_main['total_sum'] ?? 0;
    $stats['low_stock_count'] = $data_main['low_count'] ?? 0;

    // 2. นับคำร้องพัสดุที่รออนุมัติ
    $res_req = $conn->query("SELECT COUNT(*) as req_count FROM requests WHERE status = 'pending'");
    $stats['pending_requests'] = $res_req->fetch_assoc()['req_count'] ?? 0;

    // 3. ดึงรายการสินค้าที่สต็อกต่ำ (Low Stock List)
    $low_stock_items = $conn->query("SELECT * FROM inventory WHERE quantity < 50 ORDER BY quantity ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // 4. สรุปแยกตามหมวดหมู่ (Category Summary)
    $category_summary = $conn->query("SELECT category, SUM(quantity) as qty, COUNT(*) as types 
                                     FROM inventory GROUP BY category ORDER BY qty DESC")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
}

include 'includes/header.php';
?>

<style>
    .inventory-header { 
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%); 
        color: white; padding: 30px; 
        border-radius: 20px; 
        margin-bottom: 25px; 
        border-bottom: 5px solid #0ea5e9;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .card-stat {
        border: none;
        border-radius: 15px;
        transition: transform 0.3s ease;
    }
    .card-stat:hover { transform: translateY(-5px); }
    .progress-thin { height: 6px; border-radius: 10px; }
    .category-icon {
        width: 45px; height: 45px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.2rem;
    }
</style>

<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="inventory-header">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1"><i class="fas fa-boxes-stacked me-2"></i>ระบบบริหารคลังทรัพยากร</h2>
                <p class="opacity-75 mb-0 text-light">ติดตามสถานะพัสดุ สิ่งของบริจาค และการสนับสนุนศูนย์พักพิง</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="btn-group shadow-sm">
                    <a href="inventory_list.php" class="btn btn-primary fw-bold px-4 py-2">
                        <i class="fas fa-list-check me-2"></i>จัดการสต็อก
                    </a>
                    <a href="request_admin.php" class="btn btn-info text-white fw-bold px-4 py-2">
                        <i class="fas fa-file-invoice me-2"></i>ใบเบิกจ่าย
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card card-stat shadow-sm border-start border-5 border-primary">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">รายการพัสดุทั้งหมด</div>
                            <h2 class="fw-bold mb-0 mt-1"><?php echo number_format($stats['total_items']); ?></h2>
                        </div>
                        <div class="category-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stat shadow-sm border-start border-5 border-info">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">จำนวนสิ่งของรวม</div>
                            <h2 class="fw-bold mb-0 mt-1"><?php echo number_format($stats['total_qty']); ?></h2>
                        </div>
                        <div class="category-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stat shadow-sm border-start border-5 border-danger">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">สินค้าใกล้หมด</div>
                            <h2 class="fw-bold mb-0 mt-1 text-danger"><?php echo number_format($stats['low_stock_count']); ?></h2>
                        </div>
                        <div class="category-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stat shadow-sm border-start border-5 border-warning">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small fw-bold text-uppercase">คำร้องรออนุมัติ</div>
                            <h2 class="fw-bold mb-0 mt-1 text-warning"><?php echo number_format($stats['pending_requests']); ?></h2>
                        </div>
                        <div class="category-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Low Stock Alerts -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-bell me-2"></i>รายการพัสดุวิกฤต (สต็อกต่ำกว่า 50)</h6>
                    <a href="inventory_list.php?filter=low" class="btn btn-sm btn-outline-danger border-0 rounded-pill">ดูทั้งหมด</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-muted">
                                <tr>
                                    <th class="ps-4">ชื่อพัสดุ</th>
                                    <th>หมวดหมู่</th>
                                    <th>จำนวนคงเหลือ</th>
                                    <th class="text-center">ระดับ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($low_stock_items)): ?>
                                    <?php foreach($low_stock_items as $item): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><span class="badge bg-light text-dark border fw-normal"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                        <td><?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'หน่วย'); ?></td>
                                        <td class="text-center" style="width: 150px;">
                                            <?php 
                                                $percent = ($item['quantity'] / 50) * 100;
                                                $color = $percent < 30 ? 'bg-danger' : 'bg-warning';
                                            ?>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">ไม่มีพัสดุในระดับวิกฤต</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i>สรุปตามหมวดหมู่</h6>
                </div>
                <div class="card-body">
                    <?php if(!empty($category_summary)): ?>
                        <?php foreach($category_summary as $cat): ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold small"><?php echo htmlspecialchars($cat['category']); ?></span>
                                <span class="text-muted small"><?php echo number_format($cat['qty']); ?> ชิ้น (<?php echo $cat['types']; ?> รายการ)</span>
                            </div>
                            <div class="progress progress-thin" style="height: 10px;">
                                <?php 
                                    $cat_percent = ($stats['total_qty'] > 0) ? ($cat['qty'] / $stats['total_qty']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-primary bg-opacity-75" style="width: <?php echo $cat_percent; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">ไม่พบข้อมูลหมวดหมู่</div>
                    <?php endif; ?>
                    
                    <div class="mt-auto pt-3 border-top text-center">
                        <a href="inventory_save.php?action=add" class="btn btn-outline-primary btn-sm rounded-pill w-100">
                            <i class="fas fa-plus me-1"></i> เพิ่มรายการพัสดุใหม่
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>