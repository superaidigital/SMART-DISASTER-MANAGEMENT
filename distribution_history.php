<?php
/**
 * distribution_history.php
 * หน้าแสดงประวัติการแจกจ่ายพัสดุทั้งหมด
 * รองรับการกรองข้อมูลตามวันที่ ศูนย์ และการค้นหา
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'guest';
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// 2. รับค่าการกรอง (Filters)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$f_shelter = ($role === 'admin') ? (isset($_GET['f_shelter']) ? (int)$_GET['f_shelter'] : 0) : $my_shelter_id;
$f_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$f_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$f_type = isset($_GET['f_type']) ? $_GET['f_type'] : '';

// 3. เตรียม Query
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR i.item_name LIKE ? OR d.recipient_group LIKE ?)";
    $s_param = "%$search%";
    $params = array_merge($params, [$s_param, $s_param, $s_param, $s_param]);
    $types .= "ssss";
}

if ($f_shelter > 0) {
    $where_clauses[] = "i.shelter_id = ?";
    $params[] = $f_shelter;
    $types .= "i";
}

if (!empty($f_date_start)) {
    $where_clauses[] = "DATE(d.created_at) >= ?";
    $params[] = $f_date_start;
    $types .= "s";
}

if (!empty($f_date_end)) {
    $where_clauses[] = "DATE(d.created_at) <= ?";
    $params[] = $f_date_end;
    $types .= "s";
}

if (!empty($f_type)) {
    $where_clauses[] = "d.recipient_type = ?";
    $params[] = $f_type;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// ดึงข้อมูลประวัติ
$sql = "SELECT d.*, 
               e.first_name, e.last_name, 
               i.item_name, i.unit,
               s.name as shelter_name,
               u.username as recorder_name
        FROM distributions d
        LEFT JOIN evacuees e ON d.evacuee_id = e.id
        JOIN inventory i ON d.item_id = i.id
        JOIN shelters s ON i.shelter_id = s.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE $where_sql
        ORDER BY d.created_at DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ดึงรายชื่อศูนย์สำหรับ Admin Filter
$shelters = ($role === 'admin') ? $conn->query("SELECT id, name FROM shelters ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC) : [];

include 'includes/header.php';
?>

<style>
    .history-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white; padding: 30px; border-radius: 20px; margin-bottom: 25px;
        border-bottom: 5px solid #10b981;
    }
    .filter-card { border-radius: 15px; border: none; background: #fff; }
    .table-card { border-radius: 15px; border: none; overflow: hidden; }
    .badge-evacuee { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-general { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="history-header shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold mb-1"><i class="fas fa-history me-2"></i>ประวัติการแจกจ่ายพัสดุ</h2>
                <p class="opacity-75 mb-0">ตรวจสอบบันทึกการสนับสนุนพัสดุและทรัพยากรย้อนหลัง</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="distribution_manager.php" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">
                    <i class="fas fa-plus me-2"></i>บันทึกการแจกจ่ายใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">ค้นหาพัสดุ หรือ ชื่อผู้รับ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0" value="<?php echo htmlspecialchars($search); ?>" placeholder="พิมพ์สิ่งที่ต้องการค้นหา...">
                    </div>
                </div>
                <?php if($role === 'admin'): ?>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">ศูนย์พักพิง</label>
                    <select name="f_shelter" class="form-select bg-light">
                        <option value="0">ทั้งหมด</option>
                        <?php foreach($shelters as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $f_shelter == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">วันที่เริ่ม</label>
                    <input type="date" name="date_start" class="form-control bg-light" value="<?php echo $f_date_start; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">วันที่สิ้นสุด</label>
                    <input type="date" name="date_end" class="form-control bg-light" value="<?php echo $f_date_end; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3">
                        <i class="fas fa-filter me-2"></i>กรองข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- History Table -->
    <div class="card table-card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">วัน-เวลา</th>
                            <th>ผู้รับ / ปลายทาง</th>
                            <th>รายการพัสดุ</th>
                            <th class="text-center">จำนวน</th>
                            <th>ศูนย์พักพิง</th>
                            <th class="pe-4">ผู้บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($history)): ?>
                            <?php foreach($history as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</small>
                                </td>
                                <td>
                                    <?php if($row['recipient_type'] === 'evacuee'): ?>
                                        <span class="badge badge-evacuee mb-1"><i class="fas fa-user me-1"></i>รายบุคคล</span>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                        <?php if($row['proxy_receiver']): ?>
                                            <small class="text-muted">ผู้รับแทน: <?php echo htmlspecialchars($row['proxy_receiver']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-general mb-1"><i class="fas fa-users me-1"></i>หน่วยงาน</span>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['recipient_group']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                    <?php if($row['note']): ?>
                                        <small class="text-muted text-truncate d-block" style="max-width: 150px;"><?php echo htmlspecialchars($row['note']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="h5 fw-bold text-primary mb-0"><?php echo number_format($row['quantity']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['unit']); ?></small>
                                </td>
                                <td>
                                    <div class="small"><i class="fas fa-home me-1 text-muted"></i><?php echo htmlspecialchars($row['shelter_name']); ?></div>
                                </td>
                                <td class="pe-4">
                                    <div class="small fw-bold text-dark"><i class="fas fa-user-edit me-1 text-muted"></i><?php echo htmlspecialchars($row['recorder_name'] ?? 'System'); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                                    <p class="text-muted">ไม่พบประวัติการแจกจ่ายพัสดุตามเงื่อนไขที่กำหนด</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>