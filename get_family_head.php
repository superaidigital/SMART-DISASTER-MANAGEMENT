<?php
/**
 * evacuee_form.php
 * ฟอร์มลงทะเบียนและแก้ไขข้อมูลผู้ประสบภัย (v5.1.12 Camera Fix)
 * แก้ไข: ปุ่มกดถ่ายภาพไม่ทำงาน โดยเปลี่ยนมาใช้ data-bs-target แทน JavaScript Trigger
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์การใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = ($id > 0) ? 'edit' : 'add';
$shelter_id_url = isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : 0;

// กำหนดค่าเริ่มต้นตัวแปร (Data Initialization)
$data = [
    'prefix' => 'นาย', 'fname' => '', 'lname' => '', 'id_card' => '', 'id_type' => 'thai_id',
    'birth_date' => '', 'gender' => 'ชาย', 'stay_type' => 'shelter', 'stay_detail' => '',
    'age' => '', 'phone' => '', 'religion' => '', 'occupation' => '', 'skills' => '',
    'dietary_restriction' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => '',
    'family_code' => '', 'is_family_head' => 0, 'shelter_id' => $shelter_id_url,
    'id_card_no' => '', 'id_card_moo' => '', 'id_card_subdistrict' => '', 'id_card_district' => '', 'id_card_province' => '', 'id_card_zipcode' => '',
    'current_no' => '', 'current_moo' => '', 'current_subdistrict' => '', 'current_district' => '', 'current_province' => '', 'current_zipcode' => '',
    'vulnerable_group' => '', 'health_status' => 'ปกติ', 'medical_condition' => '', 'drug_allergy' => '',
    'photo_base64' => ''
];

// ดึงข้อมูลกรณีแก้ไข (Edit Mode)
if ($mode == 'edit') {
    $sql = "SELECT * FROM evacuees WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) { 
            $data = array_merge($data, $row); 
            // ปรับมาตรฐานข้อมูลเพศ
            if($data['gender'] == 'male') $data['gender'] = 'ชาย';
            if($data['gender'] == 'female') $data['gender'] = 'หญิง';
        }
        mysqli_stmt_close($stmt);
    }
}

$selected_vulnerable = explode(',', $data['vulnerable_group'] ?? '');
include 'includes/header.php';
?>

<!-- Dependencies: Datepicker Thai & SweetAlert -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.th.min.js"></script>
<!-- Plugin bootstrap-datepicker-thai -->
<script src="https://jojosati.github.io/bootstrap-datepicker-thai/js/bootstrap-datepicker-thai.js"></script>
<script src="https://jojosati.github.io/bootstrap-datepicker-thai/js/locales/bootstrap-datepicker.th.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { --primary: #0f172a; --accent: #2563eb; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --input-h: 38px; }
    body { background-color: #f1f5f9; font-family: 'Prompt', sans-serif; font-size: 14px; }
    .container-intel { max-width: 1550px; margin: 0 auto; }
    .form-card { border-radius: 12px; border: none; box-shadow: 0 5px 25px rgba(0,0,0,0.05); background: #fff; overflow: hidden; position: relative; }
    .progress-top { position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #e2e8f0; z-index: 10; }
    #formProgress { height: 100%; background: var(--success); width: 0%; transition: width 0.4s ease; }
    .section-header { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--primary); margin-bottom: 12px; padding-bottom: 5px; border-bottom: 1.5px solid #f1f5f9; font-size: 1rem; }
    .section-header i { color: var(--accent); }
    .panel-intel { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 15px; }
    .segmented-toggle { display: flex; background: #f1f5f9; padding: 3px; border-radius: 8px; height: var(--input-h); }
    .segmented-toggle .btn-check + label { flex: 1; border: none; border-radius: 6px !important; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; color: #64748b; cursor: pointer; transition: 0.2s; }
    .segmented-toggle .btn-check:checked + label { background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: var(--accent); }
    .v-badge { border-radius: 8px; border: 1.5px solid #e2e8f0; padding: 6px 10px; background: #fff; cursor: pointer; display: flex; align-items: center; height: 38px; width: 100%; transition: 0.2s; }
    .v-badge.active { border-color: var(--success); background-color: #f0fdf4; transform: translateY(-1px); box-shadow: 0 3px 10px rgba(16, 185, 129, 0.1); }
    .form-label { font-weight: 600; color: #475569; margin-bottom: 3px; font-size: 0.85rem; }
    .required-mark { color: var(--danger); font-weight: bold; }
    #photo-output { width: 120px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; background: #f8fafc; cursor: pointer; transition: 0.3s; }
    #photo-output:hover { opacity: 0.8; border-color: var(--accent); }
    #camera-preview { width: 100%; max-width: 420px; border-radius: 12px; background: #000; transform: scaleX(-1); }
    .datepicker { font-family: 'Prompt', sans-serif !important; padding: 10px !important; }
    /* Fix clickable area */
    .clickable-photo { cursor: pointer; position: relative; display: inline-block; }
    .clickable-photo:active { transform: scale(0.98); }
</style>

<div class="container-fluid py-2 container-intel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-user-shield text-primary me-2"></i><?php echo $mode == 'edit' ? 'แก้ไขข้อมูล: '.htmlspecialchars($data['fname']) : 'ลงทะเบียนผู้ประสบภัย'; ?></h4>
        <div class="d-flex gap-2">
            <span id="save-status" class="badge bg-light text-muted border py-2 px-3 rounded-pill d-none d-md-block">พร้อมทำงาน</span>
            <button type="button" onclick="clearLocalDraft()" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold text-danger"><i class="fas fa-trash-alt me-1"></i> ล้างร่าง</button>
            <a href="evacuee_list.php?shelter_id=<?php echo $data['shelter_id']; ?>" class="btn btn-sm btn-white border rounded-pill px-3 fw-bold">ย้อนกลับ</a>
            <button type="button" onclick="submitIntelForm()" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
        </div>
    </div>

    <form action="evacuee_save.php" method="POST" id="evacueeForm" novalidate autocomplete="off">
        <div class="card form-card shadow-sm">
            <div class="progress-top"><div id="formProgress"></div></div>
            
            <input type="hidden" name="btn_save" value="1">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            <input type="hidden" name="shelter_id" value="<?php echo $data['shelter_id']; ?>">
            <input type="hidden" name="birth_date" id="birth_date_db" value="<?php echo htmlspecialchars($data['birth_date']); ?>">
            <input type="hidden" name="photo_base64" id="photo_base64" value="<?php echo htmlspecialchars($data['photo_base64']); ?>">

            <div class="card-body p-3">
                <div class="row g-3">
                    <!-- Column 1: Identity & Photo -->
                    <div class="col-lg-7 border-end pe-lg-4">
                        
                        <div class="row align-items-center mb-4">
                            <div class="col-auto text-center">
                                <!-- แก้ไข: ใช้ data-bs-target และเพิ่ม style cursor -->
                                <div class="clickable-photo" data-bs-toggle="modal" data-bs-target="#cameraModal">
                                    <img id="photo-output" src="<?php echo $data['photo_base64'] ?: 'assets/img/no-avatar.png'; ?>" onerror="this.src='https://via.placeholder.com/150x200?text=Photo'">
                                    <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-1 shadow border border-white" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#cameraModal">
                                        <i class="fas fa-camera me-1"></i> ถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                            <div class="col">
                                <div class="section-header mt-0 border-0"><i class="fas fa-id-card"></i> ข้อมูลระบุตัวตน</div>
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="form-label">ประเภทบัตร</label>
                                        <select name="id_type" id="id_type" class="form-select" onchange="handleIdTypeChange()">
                                            <option value="thai_id" <?php echo $data['id_type'] == 'thai_id' ? 'selected' : ''; ?>>บัตรประชาชนไทย</option>
                                            <option value="passport" <?php echo $data['id_type'] == 'passport' ? 'selected' : ''; ?>>Passport/ต่างด้าว</option>
                                            <option value="none" <?php echo $data['id_type'] == 'none' ? 'selected' : ''; ?>>ไม่มีบัตร/สูญหาย</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">เลขประจำตัว <span id="star_id" class="required-mark">*</span></label>
                                        <div class="input-group">
                                            <input type="text" name="id_card" id="id_card_input" class="form-control fw-bold text-primary fs-5" value="<?php echo htmlspecialchars($data['id_card']); ?>" placeholder="x-xxxx-xxxxx-xx-x">
                                            <button type="button" class="btn btn-dark d-none" id="btn_generate_id" onclick="generateTempId()"><i class="fas fa-dice"></i></button>
                                        </div>
                                        <div id="id_status" class="extra-small mt-1"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel-intel mb-3">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">สถานะครอบครัว</label>
                                    <div class="segmented-toggle">
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_yes" value="1" <?php echo $data['is_family_head'] == 1 ? 'checked' : ''; ?>>
                                        <label class="btn" for="head_yes">หัวหน้า</label>
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_no" value="0" <?php echo $data['is_family_head'] == 0 ? 'checked' : ''; ?>>
                                        <label class="btn" for="head_no">สมาชิก</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label text-success fw-bold">รหัสกลุ่ม (Family Code)</label>
                                    <div class="input-group">
                                        <input type="text" name="family_code" id="family_code" class="form-control fw-bold text-success border-success border-opacity-50" value="<?php echo htmlspecialchars($data['family_code']); ?>" placeholder="พิมพ์ค้นหา" onblur="checkFamilyHead()">
                                        <button type="button" class="btn btn-success border-success border-opacity-50 text-white" onclick="generateFamilyCode()"><i class="fas fa-sync-alt"></i></button>
                                    </div>
                                    <div id="family_msg" class="extra-small mt-1 text-success fw-bold"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เพศ</label>
                                    <div class="segmented-toggle">
                                        <input type="radio" class="btn-check" name="gender" id="g_male" value="ชาย" <?php echo ($data['gender']=='ชาย' || $data['gender']=='male') ? 'checked' : ''; ?>>
                                        <label class="btn" for="g_male">ชาย</label>
                                        <input type="radio" class="btn-check" name="gender" id="g_female" value="หญิง" <?php echo ($data['gender']=='หญิง' || $data['gender']=='female') ? 'checked' : ''; ?>>
                                        <label class="btn" for="g_female">หญิง</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-header"><i class="fas fa-user"></i> 3. ข้อมูลส่วนบุคคลหลัก</div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-2">
                                <label class="form-label">คำนำหน้า</label>
                                <select name="prefix" class="form-select" onchange="smartSyncByPrefix(this.value)">
                                    <?php foreach(['นาย','นาง','นางสาว','เด็กชาย','เด็กหญิง'] as $p) echo "<option value='$p' ".($data['prefix']==$p ? 'selected':'').">$p</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">ชื่อจริง <span class="required-mark">*</span></label>
                                <input type="text" name="fname" id="fname" class="form-control" value="<?php echo htmlspecialchars($data['fname']); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">นามสกุล <span class="required-mark">*</span></label>
                                <input type="text" name="lname" id="lname" class="form-control" value="<?php echo htmlspecialchars($data['lname']); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label text-primary fw-bold">วันเกิด (วว/ดด/ปปปป) <span class="required-mark">*</span></label>
                                <div class="input-group">
                                    <input type="text" id="birth_date_display" class="form-control fw-bold border-primary" 
                                           placeholder="วว/ดด/ปปปป" readonly style="background:#fff;cursor:pointer;" required
                                           data-provide="datepicker" data-date-language="th-th" />
                                    <span class="input-group-text bg-primary text-white"><i class="fas fa-calendar-day"></i></span>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <label class="form-label">อายุ (Auto)</label>
                                <input type="number" name="age" id="age_input" class="form-control text-center fw-bold text-primary fs-5" value="<?php echo htmlspecialchars($data['age']); ?>" oninput="manualAgeSync()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เบอร์ติดต่อ <span class="required-mark">*</span></label>
                                <input type="tel" name="phone" id="phone" class="form-control fw-bold" value="<?php echo htmlspecialchars($data['phone']); ?>" maxlength="10" placeholder="0XXXXXXXXX">
                            </div>
                        </div>

                        <div class="panel-intel">
                            <div class="section-header border-0 mb-2"><i class="fas fa-heartbeat text-danger"></i> สุขภาพ & ความเปราะบาง</div>
                            <div class="row g-2">
                                <div class="col-md-12">
                                    <div class="row g-1">
                                        <?php 
                                        $v_types = [
                                            'elderly'=>['t'=>'สูงอายุ', 'l'=>'yellow'], 'disabled'=>['t'=>'พิการ', 'l'=>'yellow'], 
                                            'pregnant'=>['t'=>'มีครรภ์', 'l'=>'yellow'], 'child'=>['t'=>'เด็กเล็ก', 'l'=>'green'], 
                                            'bedridden'=>['t'=>'ติดเตียง', 'l'=>'red'], 'other'=>['t'=>'อื่นๆ', 'l'=>'yellow']
                                        ];
                                        foreach($v_types as $k => $info): ?>
                                        <div class="col-4">
                                            <label class="v-badge bg-white shadow-sm" id="card_v_<?php echo $k; ?>">
                                                <input class="form-check-input v-checkbox me-1" type="checkbox" name="vulnerable_group[]" value="<?php echo $k; ?>" id="v_<?php echo $k; ?>" data-level="<?php echo $info['l']; ?>" onchange="handleIntelligenceHealth()" <?php echo in_array($k, $selected_vulnerable) ? 'checked' : ''; ?>>
                                                <span class="fw-bold small"><?php echo $info['t']; ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label class="form-label x-small">โรคประจำตัว</label>
                                    <input type="text" name="medical_condition" class="form-control" value="<?php echo htmlspecialchars($data['medical_condition']); ?>">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label class="form-label x-small text-danger">ประวัติการแพ้ (ยา/อาหาร)</label>
                                    <input type="text" name="drug_allergy" class="form-control border-danger text-danger fw-bold" value="<?php echo htmlspecialchars($data['drug_allergy']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Address & Details -->
                    <div class="col-lg-5">
                        <div class="section-header mt-0"><i class="fas fa-home"></i> 4. ที่อยู่ตามทะเบียนบ้าน</div>
                        <div class="panel-intel mb-3">
                            <div class="row g-2">
                                <div class="col-4"><label class="form-label x-small">เลขที่</label><input type="text" name="id_card_no" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_no']); ?>"></div>
                                <div class="col-3"><label class="form-label x-small">หมู่ที่</label><input type="text" name="id_card_moo" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_moo']); ?>"></div>
                                <div class="col-5"><label class="form-label x-small">ตำบล</label><input type="text" name="id_card_subdistrict" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_subdistrict']); ?>"></div>
                                <div class="col-5"><label class="form-label x-small">อำเภอ</label><input type="text" name="id_card_district" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_district']); ?>"></div>
                                <div class="col-4"><label class="form-label x-small">จังหวัด</label><input type="text" name="id_card_province" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_province']); ?>"></div>
                                <div class="col-3"><label class="form-label x-small">รหัส ปณ.</label><input type="text" name="id_card_zipcode" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['id_card_zipcode']); ?>" maxlength="5"></div>
                            </div>
                        </div>

                        <div class="p-3 border rounded-3 bg-light mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold x-small text-success mb-0 text-uppercase">ที่อยู่ปัจจุบัน (จุดอพยพ)</h6>
                                <button type="button" class="btn btn-xs btn-outline-success px-2 fw-bold" onclick="copyAddress()"><i class="fas fa-copy"></i> เหมือนทะเบียนบ้าน</button>
                            </div>
                            <div class="row g-2">
                                <div class="col-3"><input type="text" id="current_no" name="current_no" class="form-control form-control-sm" placeholder="เลขที่" value="<?php echo htmlspecialchars($data['current_no']); ?>"></div>
                                <div class="col-3"><input type="text" id="current_moo" name="current_moo" class="form-control form-control-sm" placeholder="หมู่" value="<?php echo htmlspecialchars($data['current_moo']); ?>"></div>
                                <div class="col-6"><input type="text" id="current_subdistrict" name="current_subdistrict" class="form-control form-control-sm" placeholder="ตำบล" value="<?php echo htmlspecialchars($data['current_subdistrict']); ?>"></div>
                                <div class="col-5"><input type="text" id="current_district" name="current_district" class="form-control form-control-sm" placeholder="อำเภอ" value="<?php echo htmlspecialchars($data['current_district']); ?>"></div>
                                <div class="col-4"><input type="text" id="current_province" name="current_province" class="form-control form-control-sm" placeholder="จังหวัด" value="<?php echo htmlspecialchars($data['current_province']); ?>"></div>
                                <div class="col-3"><input type="text" id="current_zipcode" name="current_zipcode" class="form-control form-control-sm" placeholder="ปณ." value="<?php echo htmlspecialchars($data['current_zipcode']); ?>"></div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-4"><label class="form-label x-small">ศาสนา</label><input type="text" name="religion" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['religion']); ?>"></div>
                            <div class="col-md-4"><label class="form-label x-small">อาชีพเดิม</label><input type="text" name="occupation" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['occupation']); ?>"></div>
                            <div class="col-md-4">
                                <label class="form-label x-small">การพักพิง</label>
                                <div class="segmented-toggle">
                                    <input type="radio" class="btn-check" name="stay_type" id="stay_in" value="shelter" <?php echo $data['stay_type']=='shelter' ? 'checked' : ''; ?>>
                                    <label class="btn" for="stay_in">ในศูนย์</label>
                                    <input type="radio" class="btn-check" name="stay_type" id="stay_out" value="outside" <?php echo $data['stay_type']=='outside' ? 'checked' : ''; ?>>
                                    <label class="btn" for="stay_out">นอกศูนย์</label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-3 border mb-3">
                            <label class="form-label fw-bold d-block mb-2">ระดับความเร่งด่วน (Auto Triage)</label>
                            <div class="d-flex gap-1">
                                <input type="radio" class="btn-check" name="health_status" id="tr_green" value="ปกติ" <?php echo ($data['health_status']=='ปกติ') ? 'checked' : ''; ?>>
                                <label class="btn btn-sm btn-outline-success border-2 flex-grow-1" for="tr_green">ปกติ</label>
                                <input type="radio" class="btn-check" name="health_status" id="tr_yellow" value="บาดเจ็บเล็กน้อย" <?php echo ($data['health_status']=='บาดเจ็บเล็กน้อย') ? 'checked' : ''; ?>>
                                <label class="btn btn-sm btn-outline-warning border-2 flex-grow-1" for="tr_yellow">เฝ้าระวัง</label>
                                <input type="radio" class="btn-check" name="health_status" id="tr_red" value="ป่วยติดเตียง/บาดเจ็บสาหัส" <?php echo ($data['health_status']=='ป่วยติดเตียง/บาดเจ็บสาหัส') ? 'checked' : ''; ?>>
                                <label class="btn btn-sm btn-outline-danger border-2 flex-grow-1" for="tr_red">วิกฤต</label>
                            </div>
                        </div>

                        <div class="p-3 bg-white border rounded-3 mb-3">
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" id="pdpa_check" required>
                                <label class="form-check-label fw-bold" for="pdpa_check">ยินยอมประมวลผลข้อมูล (PDPA Consent)</label>
                            </div>
                        </div>

                        <div class="d-grid shadow-sm">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold py-3 rounded-3">
                                <i class="fas fa-check-circle me-2"></i> บันทึกและลงทะเบียน
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="fas fa-camera me-2"></i>ถ่ายรูปผู้ประสบภัย</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <video id="camera-preview" autoplay playsinline style="width:100%; border-radius:10px;"></video>
                <canvas id="camera-canvas" class="d-none" width="640" height="480"></canvas>
                <div class="mt-3 d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="toggleCameraFacing()"><i class="fas fa-sync"></i> สลับกล้อง</button>
                    <button type="button" class="btn btn-dark btn-lg rounded-pill px-5" onclick="capturePhoto()"><i class="fas fa-camera"></i> ถ่ายภาพ</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
let currentFacingMode = "user";

$(function () {
    // 1. Initialize Datepicker Thai
    $('#birth_date_display').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        language: 'th-th' 
    }).on('changeDate', function(e) {
        if(e.date) {
            let mDate = moment(e.date);
            let dbDate = mDate.format('YYYY-MM-DD');
            $('#birth_date_db').val(dbDate);
            let age = moment().diff(mDate, 'years');
            $('#age_input').val(age);
            smartIntelligenceSync(age);
            saveDraft();
        }
    });

    // 2. Initial Data Load
    let initialDate = $('#birth_date_db').val();
    if(initialDate && initialDate !== '0000-00-00') {
        let m = moment(initialDate);
        $('#birth_date_display').datepicker('update', m.toDate());
    }
    
    if(<?php echo $mode == 'add' ? 'true' : 'false'; ?>) restoreDraft();

    // 3. Events
    $('#id_card_input').on('input', function() {
        let v = $(this).val().replace(/\D/g, '');
        if ($('#id_type').val() === 'thai_id') {
            let n = '';
            if(v.length > 0) n += v.substring(0,1);
            if(v.length > 1) n += '-' + v.substring(1,5);
            if(v.length > 5) n += '-' + v.substring(5,10);
            if(v.length > 10) n += '-' + v.substring(10,12);
            if(v.length > 12) n += '-' + v.substring(12,13);
            $(this).val(n);
            if(v.length === 13) { validateThaiID(v); checkDuplicateID(v); }
        }
        saveDraft();
    });

    // Camera Init
    $('#cameraModal').on('shown.bs.modal', function () {
        startCamera();
    }).on('hidden.bs.modal', function () {
        if(stream) stream.getTracks().forEach(t => t.stop());
    });

    handleIntelligenceHealth();
    updateProgress();
    
    $('input, select').on('change input', function() {
        updateProgress();
        saveDraft();
    });
});

// --- Backend Functions ---

function checkFamilyHead() {
    let fcode = $('#family_code').val();
    if(fcode.length > 3) {
        $('#family_msg').html('<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบ...');
        $.get('get_family_head.php', { code: fcode })
        .done(function(res) {
            $('#family_msg').empty();
            if(res.found) {
                Swal.fire({
                    title: 'พบข้อมูลครอบครัว!',
                    html: `หัวหน้าครอบครัว: <b>${res.head_name}</b><br>ต้องการคัดลอกที่อยู่มาใช้หรือไม่?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'ใช้ที่อยู่นี้',
                    cancelButtonText: 'ไม่ใช้'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('input[name="id_card_no"]').val(res.address.no);
                        $('input[name="id_card_moo"]').val(res.address.moo);
                        $('input[name="id_card_subdistrict"]').val(res.address.subdistrict);
                        $('input[name="id_card_district"]').val(res.address.district);
                        $('input[name="id_card_province"]').val(res.address.province);
                        $('input[name="id_card_zipcode"]').val(res.address.zipcode);
                        copyAddress(); 
                    }
                });
            }
        })
        .fail(function() { $('#family_msg').empty(); });
    }
}

function checkDuplicateID(id) {
    $('#id_status').html('<i class="fas fa-spinner fa-spin"></i> ตรวจสอบ...');
    $.get('check_duplicate_id.php', { id_card: id, current_id: '<?php echo $id; ?>' })
    .done(function(res) {
        if(res.exists) {
            $('#id_status').html('<span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> ลงทะเบียนซ้ำที่: ' + res.shelter_name + '</span>');
        } else {
            $('#id_status').empty();
        }
    })
    .fail(function() { $('#id_status').empty(); });
}

// --- Logic Functions ---

function handleIntelligenceHealth() {
    let currentLvl = 'green';
    $('.v-checkbox').each(function() {
        if ($(this).is(':checked')) {
            const lvl = $(this).data('level');
            if (lvl === 'red') currentLvl = 'red';
            else if (lvl === 'yellow' && currentLvl !== 'red') currentLvl = 'yellow';
        }
    });
    const map = {'red': 'ป่วยติดเตียง/บาดเจ็บสาหัส', 'yellow': 'บาดเจ็บเล็กน้อย', 'green': 'ปกติ'};
    $(`input[name="health_status"][value="${map[currentLvl]}"]`).prop('checked', true);
}

function copyAddress() {
    const fields = ['no', 'moo', 'subdistrict', 'district', 'province', 'zipcode'];
    fields.forEach(f => {
        $(`#current_${f}`).val($(`input[name="id_card_${f}"]`).val());
    });
    Swal.fire({ icon: 'success', title: 'คัดลอกที่อยู่สำเร็จ', toast: true, position: 'top-end', showConfirmButton: false, timer: 1000 });
}

// Camera Logic
let stream;
function startCamera() {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode } }).then(s => {
            stream = s;
            let v = document.getElementById('camera-preview');
            v.srcObject = stream;
            v.style.transform = (currentFacingMode === "user") ? "scaleX(-1)" : "scaleX(1)";
            v.play();
        }).catch(e => Swal.fire('Error', 'ไม่สามารถเข้าถึงกล้องได้: ' + e.message, 'error'));
    } else {
        Swal.fire('Error', 'อุปกรณ์ของคุณไม่รองรับกล้อง', 'error');
    }
}
function toggleCameraFacing() {
    currentFacingMode = (currentFacingMode === "user") ? "environment" : "user";
    if(stream) stream.getTracks().forEach(t => t.stop());
    startCamera();
}
function capturePhoto() {
    const video = document.getElementById('camera-preview');
    const canvas = document.getElementById('camera-canvas');
    canvas.getContext('2d').drawImage(video, 0, 0, 640, 480);
    const dataURL = canvas.toDataURL('image/jpeg', 0.7);
    document.getElementById('photo-output').src = dataURL;
    document.getElementById('photo_base64').value = dataURL;
    $('#cameraModal').modal('hide'); // Close modal via Bootstrap JS if available, else fallback
    var myModalEl = document.getElementById('cameraModal');
    var modal = bootstrap.Modal.getInstance(myModalEl);
    if(modal) modal.hide();
    
    if(stream) stream.getTracks().forEach(t => t.stop());
}

// Draft & Progress
function saveDraft() {
    if(<?php echo $mode == 'edit' ? 'true' : 'false'; ?>) return;
    localStorage.setItem('evacuee_draft', JSON.stringify($('#evacueeForm').serializeArray()));
    $('#save-status').text('บันทึกร่าง ' + moment().format('HH:mm:ss')).removeClass('d-none');
}
function restoreDraft() {
    const draft = localStorage.getItem('evacuee_draft');
    if(draft) {
        JSON.parse(draft).forEach(i => {
            $(`[name="${i.name}"]`).val(i.value);
            if(i.name === 'gender') $(`[name="gender"][value="${i.value}"]`).prop('checked', true);
        });
    }
}
function clearLocalDraft() {
    localStorage.removeItem('evacuee_draft');
    location.reload();
}
function updateProgress() {
    let fields = ['fname', 'lname', 'phone', 'id_card_input'];
    let completed = 0;
    fields.forEach(f => { if($('#'+f).val()) completed++; });
    $('#formProgress').css('width', (completed / fields.length * 100) + '%');
}

// Validation
function validateThaiID(id) {
    let sum = 0;
    for(let i=0; i<12; i++) sum += parseFloat(id.charAt(i)) * (13-i);
    let ok = ((11 - (sum % 11)) % 10) === parseFloat(id.charAt(12));
    if(!ok) $('#id_status').html('<span class="text-danger fw-bold">✗ เลขบัตรไม่ถูกต้อง</span>');
}
function submitIntelForm() {
    if(!$('#pdpa_check').is(':checked')) { Swal.fire('PDPA Consent', 'กรุณากดยินยอมการเปิดเผยข้อมูลก่อนบันทึก', 'warning'); return; }
    if(!$('#fname').val() || !$('#lname').val() || !$('#phone').val()) { Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกช่องที่มีเครื่องหมาย *', 'warning'); return; }
    localStorage.removeItem('evacuee_draft');
    $('#evacueeForm').submit();
}

function smartSyncByPrefix(val) {
    if (['นาย', 'เด็กชาย'].includes(val)) $('#g_male').prop('checked', true);
    if (['นาง', 'นางสาว', 'เด็กหญิง'].includes(val)) $('#g_female').prop('checked', true);
    if (['เด็กชาย', 'เด็กหญิง'].includes(val)) { $('#v_child').prop('checked', true); if (!$('#age_input').val()) $('#age_input').val(8); }
    handleIntelligenceHealth();
}
function smartIntelligenceSync(age) { if (!age) return; $('#v_elderly').prop('checked', age >= 60); $('#v_child').prop('checked', age < 15); handleIntelligenceHealth(); }
function manualAgeSync() { let a = parseInt($('#age_input').val()); if(!isNaN(a)) smartIntelligenceSync(a); updateProgress(); }
</script>

<?php include 'includes/footer.php'; ?>