<?php
/**
 * ระบบรายงานเหตุการณ์ด่วนสำหรับประชาชน (Public Incident Report)
 * พัฒนาโดย: ผู้ช่วยพัฒนาเว็บ PHP
 * ลักษณะ: สะอาด, ปลอดภัย (SQL Injection Protection), และ Responsive
 */

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// ประมวลผลเมื่อมีการส่งฟอร์ม
$message = "";
$status = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_report'])) {
    // รับค่าและทำความสะอาดข้อมูล (Data Sanitization)
    $report_type = mysqli_real_escape_string($conn, $_POST['report_type']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

    // ตรวจสอบข้อมูลเบื้องต้น
    if (empty($report_type) || empty($description) || empty($location)) {
        $message = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
        $status = "danger";
    } else {
        // ใช้ Prepared Statement เพื่อความปลอดภัยสูงสุด
        $sql = "INSERT INTO incident_reports (user_id, type, description, location, latitude, longitude, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $report_type, $description, $location, $latitude, $longitude);

        if (mysqli_stmt_execute($stmt)) {
            $message = "รายงานเหตุการณ์ของคุณถูกส่งเรียบร้อยแล้ว เจ้าหน้าที่กำลังดำเนินการตรวจสอบ";
            $status = "success";
            
            // ส่วนเสริม: สามารถเรียกฟังก์ชันแจ้งเตือนผ่าน LINE ตรงนี้ได้
            // sendLineNotify("มีการแจ้งเหตุใหม่: $report_type ที่ $location");
        } else {
            $message = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
            $status = "danger";
        }
        mysqli_stmt_close($stmt);
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>รายงานเหตุการณ์ด่วน</h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" id="reportForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">ประเภทเหตุการณ์ <span class="text-danger">*</span></label>
                            <select name="report_type" class="form-select" required>
                                <option value="">-- เลือกประเภท --</option>
                                <option value="น้ำท่วม">น้ำท่วม</option>
                                <option value="ดินโคลนถล่ม">ดินโคลนถล่ม</option>
                                <option value="อัคคีภัย">อัคคีภัย</option>
                                <option value="พายุ/ลมแรง">พายุ/ลมแรง</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">รายละเอียดเหตุการณ์ <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" placeholder="ระบุสิ่งที่เกิดขึ้น เช่น ระดับน้ำสูงเท่าใด มีคนติดค้างหรือไม่" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">สถานที่/จุดสังเกต <span class="text-danger">*</span></label>
                            <input type="text" name="location" id="location_text" class="form-control" placeholder="ชื่อหมู่บ้าน ถนน หรือจุดสังเกตใกล้เคียง" required>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <label class="form-label small text-muted">ละติจูด (Latitude)</label>
                                <input type="text" name="latitude" id="lat" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted">ลองจิจูด (Longitude)</label>
                                <input type="text" name="longitude" id="lng" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="getLocation()">
                                    <i class="fas fa-map-marker-alt me-1"></i> ดึงตำแหน่งปัจจุบันจาก GPS
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="btn_report" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>ส่งรายงาน
                            </button>
                            <a href="public_dashboard.php" class="btn btn-light">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * ฟังก์ชันดึงตำแหน่งพิกัดจาก Browser
 */
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition, showError);
    } else {
        alert("เบราว์เซอร์ของคุณไม่รองรับการดึงข้อมูลตำแหน่ง");
    }
}

function showPosition(position) {
    document.getElementById("lat").value = position.coords.latitude;
    document.getElementById("lng").value = position.coords.longitude;
    
    // แจ้งเตือนผู้ใช้ว่าดึงพิกัดสำเร็จ
    const btn = document.querySelector('button[onclick="getLocation()"]');
    btn.classList.replace('btn-outline-secondary', 'btn-success');
    btn.innerHTML = '<i class="fas fa-check me-1"></i> ดึงตำแหน่งสำเร็จ';
}

function showError(error) {
    switch(error.code) {
        case error.PERMISSION_DENIED:
            alert("ผู้ใช้ปฏิเสธการเข้าถึงตำแหน่งพิกัด");
            break;
        case error.POSITION_UNAVAILABLE:
            alert("ไม่สามารถระบุตำแหน่งพิกัดได้");
            break;
        case error.TIMEOUT:
            alert("การดึงตำแหน่งหมดเวลา");
            break;
        case error.UNKNOWN_ERROR:
            alert("เกิดข้อผิดพลาดที่ไม่รู้จัก");
            break;
    }
}
</script>

<?php include 'includes/footer.php'; ?>