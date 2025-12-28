<?php
/**
 * qr_scanner.php
 * หน้าสำหรับสแกน QR Code จากบัตรประจำตัวผู้อพยพ
 * ปรับปรุง: เพิ่มระบบดึงข้อมูลผู้พักพิงมาแสดงผลอัตโนมัติหลังสแกน
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';
?>

<div class="container py-4 text-center">
    <h2 class="mb-4 fw-bold"><i class="fas fa-qrcode text-primary"></i> สแกนบัตรประจำตัวผู้อพยพ</h2>
    
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <!-- บริเวณแสดงภาพจากกล้อง -->
            <div id="reader-container" class="position-relative">
                <div id="reader" class="shadow-sm" style="width: 100%; border-radius: 15px; overflow: hidden; background: #000; border: none;"></div>
                <div id="loading-overlay" class="position-absolute top-50 start-50 translate-middle d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            
            <!-- ส่วนแสดงผลลัพธ์เมื่อสแกนสำเร็จ -->
            <div id="result" class="mt-4" style="display: none;">
                <div class="card border-0 shadow rounded-4 overflow-hidden">
                    <div class="card-header bg-success text-white py-3 border-0">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-check-circle me-2"></i>พบข้อมูลผู้พักพิง</h5>
                    </div>
                    <div class="card-body p-4 text-start">
                        <div class="text-center mb-3">
                             <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 70px; height: 70px;">
                                <i class="fas fa-user fa-2x text-secondary"></i>
                             </div>
                             <h4 id="display-name" class="fw-bold text-dark mb-0">-</h4>
                             <p id="display-id-code" class="text-muted small">-</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="text-muted small d-block">เลขบัตรประชาชน</label>
                                <span id="display-id-card" class="fw-bold">-</span>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small d-block">อายุ</label>
                                <span id="display-age" class="fw-bold">-</span>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">ศูนย์พักพิง</label>
                                <span id="display-shelter" class="fw-bold text-primary">-</span>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">สถานะสุขภาพ/โรคประจำตัว</label>
                                <div id="display-health" class="p-2 bg-light rounded-3 mt-1 small">-</div>
                            </div>
                        </div>

                        <hr class="my-4 opacity-50">
                        
                        <div id="action-buttons" class="d-grid gap-2">
                            <!-- ปุ่มดำเนินการจะถูกปรับแต่งโดย JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-1"></i> ยกเลิกและกลับหน้าหลัก
                </a>
            </div>
        </div>
    </div>
</div>

<!-- โหลด Library สำหรับสแกน QR Code -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    function onScanSuccess(decodedText, decodedResult) {
        // เมื่อสแกนสำเร็จ
        console.log(`Code matched = ${decodedText}`, decodedResult);
        
        // รูปแบบข้อมูลในบัตรคือ "EVAC-ID" หรือ "ID" ปกติ
        let evacueeId = decodedText;
        if (decodedText.includes('-')) {
            const parts = decodedText.split('-');
            if (parts[0] === 'EVAC' && parts[1]) {
                evacueeId = parts[1];
            }
        }
        
        // หยุดการสแกนทันทีเพื่อประมวลผลข้อมูล
        html5QrcodeScanner.clear().then(() => {
            fetchEvacueeData(evacueeId, decodedText);
        }).catch(error => {
            console.error("Failed to clear scanner", error);
        });
    }

    async function fetchEvacueeData(id, rawText) {
        const loading = document.getElementById('loading-overlay');
        const reader = document.getElementById('reader');
        const resultDiv = document.getElementById('result');

        loading.classList.remove('d-none');
        reader.style.opacity = '0.5';

        try {
            // ดึงข้อมูลจาก API
            const response = await fetch(`get_evacuee_details.php?id=${id}`);
            const data = await response.json();

            loading.classList.add('d-none');
            reader.style.display = 'none';
            resultDiv.style.display = 'block';

            if (data.success) {
                // อัปเดตข้อมูลบน UI
                document.getElementById('display-name').innerText = `${data.first_name} ${data.last_name}`;
                document.getElementById('display-id-code').innerText = "รหัสอ้างอิง: " + rawText;
                document.getElementById('display-id-card').innerText = data.id_card;
                document.getElementById('display-age').innerText = data.age + " ปี";
                document.getElementById('display-shelter').innerText = data.shelter_name || "ไม่ระบุศูนย์";
                document.getElementById('display-health').innerText = data.health_condition || "ปกติ / ไม่มีข้อมูล";
                document.getElementById('display-health').className = data.health_condition ? "p-2 bg-danger bg-opacity-10 text-danger rounded-3 mt-1 small fw-bold" : "p-2 bg-light rounded-3 mt-1 small";

                // สร้างปุ่มดำเนินการ
                document.getElementById('action-buttons').innerHTML = `
                    <a href="evacuee_form.php?id=${id}&mode=edit" class="btn btn-primary btn-lg fw-bold rounded-3 mb-2 shadow-sm">
                        <i class="fas fa-user-edit me-2"></i>ดู / แก้ไขข้อมูลส่วนตัว
                    </a>
                    <a href="distribution_manager.php?evacuee_id=${id}" class="btn btn-success btn-lg fw-bold rounded-3 mb-2 shadow-sm">
                        <i class="fas fa-box-open me-2"></i>บันทึกการจ่ายพัสดุ
                    </a>
                    <button onclick="location.reload()" class="btn btn-light border fw-bold rounded-3 py-2 mt-2">
                        <i class="fas fa-sync-alt me-2"></i>สแกนคนถัดไป
                    </button>
                `;
            } else {
                // กรณีไม่พบข้อมูล
                document.getElementById('display-name').innerText = "ไม่พบข้อมูล";
                document.getElementById('display-id-code').innerText = rawText;
                document.getElementById('action-buttons').innerHTML = `
                    <div class="alert alert-warning small mb-3">ไม่พบรหัสผู้อพยพนี้ในระบบ</div>
                    <button onclick="location.reload()" class="btn btn-secondary fw-bold rounded-3 py-2">
                        <i class="fas fa-redo me-2"></i>ลองใหม่อีกครั้ง
                    </button>
                `;
            }

        } catch (error) {
            console.error("Fetch error:", error);
            alert("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
            location.reload();
        }
    }

    function onScanFailure(error) {
        // ปล่อยว่างเพื่อให้สแกนต่อเนื่อง
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 15, 
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        },
        false
    );
    
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>

<style>
    #reader__scan_region video {
        object-fit: cover !important;
        border-radius: 15px;
    }
    #reader {
        border: none !important;
    }
    #reader__dashboard_section_csr button {
        background-color: #2563eb !important;
        color: white !important;
        border: none !important;
        padding: 8px 20px !important;
        border-radius: 8px !important;
        font-weight: 500 !important;
        margin: 10px 0;
    }
    .card-header.bg-success {
        background: linear-gradient(135deg, #198754 0%, #157347 100%) !important;
    }
</style>

<?php include 'includes/footer.php'; ?>