<?php
/**
 * evacuee_list.php
 * หน้าแสดงรายชื่อผู้ประสบภัย/ผู้อพยพ
 * แก้ไข: เส้นทางไฟล์ (Path) ให้ถูกต้องเพื่อแก้ Fatal Error
 */

// 1. ตรวจสอบและเริ่ม Session ก่อนสิ่งอื่นใด
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 2. เรียกใช้ไฟล์ตั้งค่าและฟังก์ชัน (แก้ไข Path ให้ถูกต้อง)
require_once 'config/db.php';          // ลบ ../ ออกเพราะไฟล์อยู่ในระดับเดียวกันกับโฟลเดอร์ config
require_once 'includes/functions.php'; 

// 3. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 4. จัดการเรื่อง Shelter ID (ระบบ Fallback สำหรับ Admin/Staff)
$role = $_SESSION['role'] ?? 'guest';
$sess_shelter_id = $_SESSION['shelter_id'] ?? 0;
$shelter_id = $_GET['shelter_id'] ?? '';

// กำหนดค่าเริ่มต้นถ้าไม่มีการส่งค่ามา
if (empty($shelter_id)) {
    $shelter_id = ($role === 'admin') ? 'all' : ($sess_shelter_id ?: 'all');
}

// --- Pagination Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;

try {
    if ($shelter_id === 'all') {
        // --- มุมมองภาพรวมทุกศูนย์ ---
        $shelter = [
            'id' => 'all',
            'name' => 'สรุปรายชื่อผู้ประสบภัย (ทุกศูนย์)',
            'incident_name' => 'ภาพรวมสถานการณ์',
            'location' => 'ครอบคลุมทุกพื้นที่อพยพ',
            'contact_phone' => 'สายด่วน 1784',
            'capacity' => 0
        ];

        $res_cap = $conn->query("SELECT SUM(capacity) as total_cap FROM shelters");
        $row_cap = $res_cap->fetch_assoc();
        $shelter['capacity'] = $row_cap['total_cap'] ?: 0;

        $sql_count = "SELECT COUNT(*) as total FROM evacuees WHERE check_out_date IS NULL";
        $total_records = $conn->query($sql_count)->fetch_assoc()['total'];

        $sql_list = "SELECT e.*, s.name as shelter_name 
                     FROM evacuees e 
                     LEFT JOIN shelters s ON e.shelter_id = s.id 
                     WHERE e.check_out_date IS NULL 
                     ORDER BY e.created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql_list);
        $stmt->bind_param("ii", $offset, $limit);
        
    } else {
        // --- มุมมองเฉพาะศูนย์ ---
        $sql_sh = "SELECT s.*, i.name as incident_name 
                   FROM shelters s 
                   LEFT JOIN incidents i ON s.incident_id = i.id 
                   WHERE s.id = ?";
        $stmt_sh = $conn->prepare($sql_sh);
        $id_int = (int)$shelter_id;
        $stmt_sh->bind_param("i", $id_int);
        $stmt_sh->execute();
        $shelter = $stmt_sh->get_result()->fetch_assoc();

        if (!$shelter) {
            header("Location: evacuee_list.php?shelter_id=all");
            exit();
        }

        $sql_count = "SELECT COUNT(*) as total FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
        $stmt_c = $conn->prepare($sql_count);
        $stmt_c->bind_param("i", $id_int);
        $stmt_c->execute();
        $total_records = $stmt_c->get_result()->fetch_assoc()['total'];

        $sql_list = "SELECT e.*, ? as shelter_name 
                     FROM evacuees e 
                     WHERE e.shelter_id = ? AND e.check_out_date IS NULL 
                     ORDER BY e.created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql_list);
        $stmt->bind_param("siii", $shelter['name'], $id_int, $offset, $limit);
    }

    $stmt->execute();
    $evacuees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total_pages = ceil($total_records / $limit);

    // สถิติกลุ่มเปราะบาง
    $where_vun = ($shelter_id === 'all') ? "WHERE check_out_date IS NULL" : "WHERE shelter_id = " . (int)$shelter_id . " AND check_out_date IS NULL";
    $sql_vun = "SELECT COUNT(*) as v_count FROM evacuees $where_vun AND (age < 15 OR age >= 60 OR (health_condition != '' AND health_condition IS NOT NULL))";
    $vulnerable_count = $conn->query($sql_vun)->fetch_assoc()['v_count'];

} catch (Exception $e) {
    error_log($e->getMessage());
    die("<div class='container mt-5 alert alert-danger'>เกิดข้อผิดพลาดในการเชื่อมต่อข้อมูล: " . $e->getMessage() . "</div>");
}

// 5. โหลดส่วนหัวของเว็บ
include 'includes/header.php';
?>

<style>
    .shelter-header { 
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
        color: white; padding: 25px; 
        border-radius: 15px; 
        margin-bottom: 25px; 
        border-left: 6px solid #fbbf24;
    }
    .stat-mini-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; text-align: center;
    }
</style>

<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="shelter-header shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-warning text-dark mb-2 px-3"><?php echo htmlspecialchars($shelter['incident_name']); ?></span>
                <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($shelter['name']); ?></h2>
                <div class="opacity-75 small">
                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($shelter['location']); ?>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="btn-group shadow-sm">
                    <a href="qr_scanner.php" class="btn btn-outline-light"><i class="fas fa-qrcode me-2"></i>สแกนบัตร</a>
                    <?php if($shelter_id !== 'all'): ?>
                        <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>&mode=add" class="btn btn-primary fw-bold">
                            <i class="fas fa-user-plus me-2"></i>ลงทะเบียนใหม่
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-primary border-4 shadow-sm">
                <div class="text-muted small text-uppercase fw-bold mb-1">ผู้อพยพปัจจุบัน</div>
                <div class="h3 fw-bold mb-0 text-primary"><?php echo number_format($total_records); ?> <small class="fs-6 text-muted">คน</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-warning border-4 shadow-sm">
                <div class="text-muted small text-uppercase fw-bold mb-1">กลุ่มเปราะบาง</div>
                <div class="h3 fw-bold mb-0 text-warning"><?php echo number_format($vulnerable_count); ?> <small class="fs-6 text-muted">คน</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-info border-4 shadow-sm">
                <div class="text-muted small text-uppercase fw-bold mb-1">ความหนาแน่น</div>
                <?php $occ = ($shelter['capacity'] > 0) ? ($total_records / $shelter['capacity']) * 100 : 0; ?>
                <div class="h3 fw-bold mb-0 text-info"><?php echo round($occ, 1); ?>%</div>
            </div>
        </div>
    </div>

    <!-- Data List Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2 text-primary"></i>บัญชีรายชื่อผู้เข้าพักพิง</h6>
            <input type="text" id="tableSearch" class="form-control form-control-sm" style="width: 250px;" placeholder="ค้นหาชื่อ หรือเลขบัตร..." onkeyup="filterTable()">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="evacueeTable">
                    <thead class="bg-light small text-muted text-uppercase">
                        <tr>
                            <th class="ps-4">ชื่อ-นามสกุล</th>
                            <th>เพศ/อายุ</th>
                            <?php if($shelter_id === 'all'): ?><th>ศูนย์พักพิง</th><?php endif; ?>
                            <th>สถานะสุขภาพ</th>
                            <th class="text-end pe-4">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($evacuees)): ?>
                            <?php foreach($evacuees as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 38px; height: 38px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            <small class="text-muted">เลขบัตร: <?php echo maskIDCard($row['id_card']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fw-normal">
                                        <?php echo ($row['gender'] == 'male' ? 'ชาย' : 'หญิง'); ?> / <?php echo $row['age']; ?> ปี
                                    </span>
                                </td>
                                <?php if($shelter_id === 'all'): ?>
                                    <td><span class="text-muted small"><i class="fas fa-home me-1"></i><?php echo htmlspecialchars($row['shelter_name'] ?? '-'); ?></span></td>
                                <?php endif; ?>
                                <td>
                                    <?php if(!empty($row['health_condition'])): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle fw-normal">
                                            <i class="fas fa-notes-medical me-1"></i><?php echo htmlspecialchars($row['health_condition']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">- ปกติ -</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm">
                                        <a href="evacuee_card.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-white border" title="ดูบัตร"><i class="fas fa-id-card text-primary"></i></a>
                                        <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&mode=edit" class="btn btn-sm btn-white border ms-1" title="แก้ไข"><i class="fas fa-edit text-secondary"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้เข้าพักพิง</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="p-3 border-top bg-light text-center">
                <div class="btn-group">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?shelter_id=<?php echo $shelter_id; ?>&page=<?php echo $i; ?>" class="btn btn-sm <?php echo ($page == $i) ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function filterTable() {
        let input = document.getElementById("tableSearch").value.toUpperCase();
        let rows = document.getElementById("evacueeTable").getElementsByTagName("tr");
        for (let i = 1; i < rows.length; i++) {
            let nameCell = rows[i].getElementsByTagName("td")[0];
            if (nameCell) {
                let text = nameCell.textContent || nameCell.innerText;
                rows[i].style.display = text.toUpperCase().indexOf(input) > -1 ? "" : "none";
            }
        }
    }
</script>

<?php include 'includes/footer.php'; ?>