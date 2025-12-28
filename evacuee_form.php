<?php
/**
 * evacuee_form.php
 * ฟอร์มลงทะเบียนและแก้ไขข้อมูลผู้ประสบภัย (v5.1 Supreme Compact Intelligence)
 * ปรับปรุง: ลำดับครอบครัวใหม่, ระบบ Automation เต็มรูปแบบ, ดีไซน์หน้าเดียวแบบ Zero-Scroll
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = ($id > 0) ? 'edit' : 'add';
$shelter_id = isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : 0;

// Mapping ข้อมูลจาก Database ให้ครบถ้วนตาม SQL Schema
$data = [
    'prefix' => ($mode == 'add') ? 'นาย' : '', 
    'first_name' => '', 'last_name' => '', 'id_card' => '', 'id_type' => 'thai_id',
    'birth_date' => '', 'gender' => 'male', 'stay_type' => 'shelter', 'stay_detail' => '',
    'age' => '', 'phone' => '', 'religion' => '', 'occupation' => '', 'skills' => '',
    'dietary_restriction' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => '',
    'family_code' => '', 'is_family_head' => 0,
    'id_card_no' => '', 'id_card_moo' => '', 'id_card_subdistrict' => '', 'id_card_district' => '', 'id_card_province' => '', 'id_card_zipcode' => '',
    'current_no' => '', 'current_moo' => '', 'current_subdistrict' => '', 'current_district' => '', 'current_province' => '', 'current_zipcode' => '',
    'vulnerable_type' => '', 'vulnerable_detail' => '', 'triage_level' => 'green', 
    'medical_condition' => '', 'drug_allergy' => ''
];

if ($mode == 'edit') {
    $stmt = $conn->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $data = array_merge($data, $row); }
}

$selected_vulnerable = explode(',', $data['vulnerable_type'] ?? '');
include 'includes/header.php';
?>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/css/bootstrap-datetimepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment-with-locales.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.47/js/bootstrap-datetimepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { --primary: #0f172a; --accent: #2563eb; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --input-h: 38px; }
    body { background-color: #f1f5f9; font-family: 'Prompt', sans-serif; font-size: 13px; }
    .container-intel { max-width: 1550px; margin: 0 auto; }
    .form-card { border-radius: 12px; border: none; box-shadow: 0 5px 25px rgba(0,0,0,0.05); background: #fff; overflow: hidden; }
    
    .progress-top { position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: #e2e8f0; z-index: 10; }
    #formProgress { height: 100%; background: var(--success); width: 0%; transition: width 0.4s ease; }

    .section-header { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--primary); margin-bottom: 12px; padding-bottom: 5px; border-bottom: 1.5px solid #f1f5f9; font-size: 0.95rem; }
    .section-header i { color: var(--accent); }
    
    .form-label { font-weight: 600; color: #475569; margin-bottom: 3px; font-size: 0.8rem; display: block; }
    .form-control, .form-select { height: var(--input-h) !important; border-radius: 6px !important; border: 1px solid #cbd5e1; padding: 4px 10px; font-size: 0.9rem; transition: 0.2s; }
    .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08); outline: none; }
    
    .panel-intel { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 15px; }

    .segmented-toggle { display: flex; background: #f1f5f9; padding: 3px; border-radius: 8px; height: var(--input-h); }
    .segmented-toggle .btn-check + label { flex: 1; border: none; border-radius: 6px !important; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; color: #64748b; cursor: pointer; transition: 0.2s; }
    .segmented-toggle .btn-check:checked + label { background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: var(--accent); }
    
    .v-badge { border-radius: 8px; border: 1.5px solid #e2e8f0; padding: 6px 10px; background: #fff; cursor: pointer; display: flex; align-items: center; height: 38px; width: 100%; transition: 0.2s; }
    .v-badge.active { border-color: var(--success); background-color: #f0fdf4; transform: translateY(-1px); box-shadow: 0 3px 10px rgba(16, 185, 129, 0.1); }
    
    .required-mark { color: var(--danger); font-weight: bold; }
    .input-group-addon { background: #f8fafc; border: 1px solid #cbd5e1; border-left: none; border-radius: 0 8px 8px 0 !important; color: var(--accent); width: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
</style>

<div class="container-fluid py-2 container-intel">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-microchip text-primary me-2"></i><?php echo $mode == 'edit' ? 'แก้ไขข้อมูลอัจฉริยะ' : 'ลงทะเบียนผู้ประสบภัย (v5.1)'; ?></h4>
        </div>
        <div class="d-flex gap-2">
            <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-sm btn-white border rounded-pill px-3 fw-bold">ย้อนกลับ</a>
            <button type="button" onclick="$('#evacueeForm').submit();" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
        </div>
    </div>

    <form action="evacuee_save.php" method="POST" id="evacueeForm" novalidate>
        <div class="card form-card shadow-sm">
            <div class="progress-top"><div id="formProgress"></div></div>
            
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            <input type="hidden" name="shelter_id" value="<?php echo $shelter_id ?: ($data['shelter_id'] ?? 0); ?>">
            <input type="hidden" name="birth_date" id="birth_date_db" value="<?php echo $data['birth_date']; ?>">

            <div class="card-body p-3">
                <div class="row g-3">
                    <!-- ส่วนที่ 1: ข้อมูลครอบครัวและระบุตัวตน -->
                    <div class="col-lg-7 border-end">
                        <div class="section-header mt-0"><i class="fas fa-fingerprint"></i> 1. การระบุตัวตนและครอบครัว</div>
                        <div class="panel-intel">
                            <div class="row g-2 align-items-end">
                                <!-- ฟีเจอร์: สถานะครอบครัวขึ้นก่อน -->
                                <div class="col-md-3">
                                    <label class="form-label">สถานะในครอบครัว</label>
                                    <div class="segmented-toggle">
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_yes" value="1" <?php echo $data['is_family_head'] == 1 ? 'checked' : ''; ?>>
                                        <label class="btn" for="head_yes">หัวหน้า</label>
                                        <input type="radio" class="btn-check" name="is_family_head" id="head_no" value="0" <?php echo $data['is_family_head'] == 0 ? 'checked' : ''; ?>>
                                        <label class="btn" for="head_no">สมาชิก</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">รหัสกลุ่มครอบครัว (Family Code)</label>
                                    <div class="input-group border rounded-2">
                                        <input type="text" name="family_code" id="family_code" class="form-control border-0 fw-bold text-success" value="<?php echo htmlspecialchars($data['family_code']); ?>" placeholder="พิมพ์รหัสกลุ่มญาติ">
                                        <button type="button" class="btn btn-light border-0 px-2" onclick="generateFamilyCode()" title="สุ่มรหัสกลุ่ม"><i class="fas fa-random text-muted"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ประเภทบัตร</label>
                                    <select name="id_type" id="id_type" class="form-select" onchange="handleIdTypeChange()">
                                        <option value="thai_id" <?php echo $data['id_type'] == 'thai_id' ? 'selected' : ''; ?>>บัตรประชาชนไทย</option>
                                        <option value="passport" <?php echo $data['id_type'] == 'passport' ? 'selected' : ''; ?>>Passport/ต่างด้าว</option>
                                        <option value="none" <?php echo $data['id_type'] == 'none' ? 'selected' : ''; ?>>ไม่มีบัตร/สูญหาย</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mt-2">
                                    <label class="form-label">เลขประจำตัว <span id="star_id" class="required-mark">*</span></label>
                                    <div class="input-group rounded-2 overflow-hidden border">
                                        <input type="text" name="id_card" id="id_card_input" class="form-control border-0 fw-bold text-primary px-3 fs-5" value="<?php echo htmlspecialchars($data['id_card']); ?>" placeholder="x-xxxx-xxxxx-xx-x">
                                        <button type="button" class="btn btn-dark d-none border-0 px-3 fw-bold" id="btn_generate_id" onclick="generateTempId()">สุ่มรหัสชั่วคราว</button>
                                    </div>
                                    <div id="id_status" class="smart-hint"></div>
                                </div>
                            </div>
                        </div>

                        <div class="section-header"><i class="fas fa-user-circle"></i> 2. ข้อมูลส่วนบุคคลและสุขภาพ (Sync Intelligence)</div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-2">
                                <label class="form-label">คำนำหน้า</label>
                                <select name="prefix_select" id="prefix_select" class="form-select" onchange="smartSyncByPrefix(this.value)">
                                    <option value="นาย" <?php echo $data['prefix'] == 'นาย' ? 'selected' : ''; ?>>นาย</option>
                                    <option value="นาง" <?php echo $data['prefix'] == 'นาง' ? 'selected' : ''; ?>>นาง</option>
                                    <option value="นางสาว" <?php echo $data['prefix'] == 'นางสาว' ? 'selected' : ''; ?>>น.ส.</option>
                                    <option value="เด็กชาย" <?php echo $data['prefix'] == 'เด็กชาย' ? 'selected' : ''; ?>>ด.ช.</option>
                                    <option value="เด็กหญิง" <?php echo $data['prefix'] == 'เด็กหญิง' ? 'selected' : ''; ?>>ด.ญ.</option>
                                    <option value="other" <?php echo (!in_array($data['prefix'], ['นาย','นาง','นางสาว','เด็กชาย','เด็กหญิง']) && $data['prefix'] != '') ? 'selected' : ''; ?>>อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">ชื่อจริง <span class="required-mark">*</span></label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($data['first_name']); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">นามสกุล <span class="required-mark">*</span></label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($data['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">วันเกิด (วว/ดด/พศ) <span class="required-mark">*</span></label>
                                <div class="input-group date" id="dtp_birth">
                                    <input type="text" id="birth_date_display" class="form-control fw-bold" placeholder="วว/ดด/พศ" required />
                                    <span class="input-group-addon"><i class="fas fa-calendar-alt"></i></span>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <label class="form-label">อายุ</label>
                                <input type="number" name="age" id="age_input" class="form-control fw-bold text-primary text-center fs-5" value="<?php echo htmlspecialchars($data['age']); ?>" required oninput="manualAgeSync()">
                            </div>
                            <div class="col-md-3">
                                <!-- ฟีเจอร์: เพศอัตโนมัติจากคำนำหน้า -->
                                <label class="form-label text-center d-block">เพศ <small class="text-muted">(Auto)</small></label>
                                <div class="segmented-toggle">
                                    <input type="radio" class="btn-check" name="gender" id="g_male" value="male" <?php echo $data['gender'] == 'male' ? 'checked' : ''; ?>>
                                    <label class="btn" for="g_male">ชาย</label>
                                    <input type="radio" class="btn-check" name="gender" id="g_female" value="female" <?php echo $data['gender'] == 'female' ? 'checked' : ''; ?>>
                                    <label class="btn" for="g_female">หญิง</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <!-- ฟีเจอร์: ตรวจสอบเบอร์โทร 10 หลัก -->
                                <label class="form-label">เบอร์ติดต่อ <span class="required-mark">*</span></label>
                                <input type="text" name="phone" id="phone" class="form-control fw-bold" value="<?php echo htmlspecialchars($data['phone']); ?>" placeholder="0XXXXXXXXX" maxlength="10" oninput="validatePhoneUI()">
                                <small id="phone_msg" class="smart-hint"></small>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-3"><label class="form-label x-small">ศาสนา</label><input type="text" name="religion" class="form-control" value="<?php echo htmlspecialchars($data['religion']); ?>"></div>
                            <div class="col-md-3"><label class="form-label x-small">อาชีพ</label><input type="text" name="occupation" class="form-control" value="<?php echo htmlspecialchars($data['occupation']); ?>"></div>
                            <div class="col-md-3">
                                <label class="form-label text-primary fw-bold">ประเภทการพักพิง</label>
                                <div class="segmented-toggle">
                                    <input type="radio" class="btn-check" name="stay_type" id="stay_in" value="shelter" <?php echo $data['stay_type'] == 'shelter' ? 'checked' : ''; ?> onchange="toggleStayDetail()">
                                    <label class="btn" for="stay_in">ในศูนย์ฯ</label>
                                    <input type="radio" class="btn-check" name="stay_type" id="stay_out" value="outside" <?php echo $data['stay_type'] == 'outside' ? 'checked' : ''; ?> onchange="toggleStayDetail()">
                                    <label class="btn" for="stay_out">นอกศูนย์ฯ</label>
                                </div>
                            </div>
                            <div class="col-md-3 <?php echo $data['stay_type']=='outside' ? '' : 'd-none'; ?>" id="stay_detail_container">
                                <label class="form-label text-warning small fw-bold">ระบุตำแหน่งที่พักนอกศูนย์ฯ</label>
                                <input type="text" name="stay_detail" class="form-control border-warning bg-warning bg-opacity-10" value="<?php echo htmlspecialchars($data['stay_detail']); ?>">
                            </div>
                        </div>

                        <div class="section-header"><i class="fas fa-briefcase-medical"></i> 3. การคัดกรองสุขภาพ (Intelligence Triage)</div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">ระดับดูแล (Triage)</label>
                                <div class="d-grid gap-1">
                                    <input type="radio" class="btn-check" name="triage_level" id="tr_green" value="green" <?php echo $data['triage_level']=='green' ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-success border-2 py-1 fw-bold" for="tr_green">เขียว: ปกติ</label>
                                    <input type="radio" class="btn-check" name="triage_level" id="tr_yellow" value="yellow" <?php echo $data['triage_level']=='yellow' ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-warning border-2 py-1 fw-bold" for="tr_yellow">เหลือง: ดูแล</label>
                                    <input type="radio" class="btn-check" name="triage_level" id="tr_red" value="red" <?php echo $data['triage_level']=='red' ? 'checked' : ''; ?>>
                                    <label class="btn btn-sm btn-outline-danger border-2 py-1 fw-bold" for="tr_red">แดง: วิกฤต</label>
                                </div>
                                <div class="mt-2 x-small text-muted text-center"><i class="fas fa-robot"></i> วิเคราะห์อัตโนมัติ</div>
                            </div>
                            <div class="col-md-9">
                                <!-- ฟีเจอร์: กลุ่มเปราะบางอัจฉริยะ ซิงค์จากอายุอัตโนมัติ -->
                                <div class="row g-1 mb-2">
                                    <?php 
                                    $v_types = [
                                        'elderly'=>['t'=>'สูงอายุ', 'l'=>'yellow'], 'disabled'=>['t'=>'พิการ', 'l'=>'yellow'], 
                                        'pregnant'=>['t'=>'มีครรภ์', 'l'=>'yellow'], 'child'=>['t'=>'เด็กเล็ก', 'l'=>'green'], 
                                        'bedridden'=>['t'=>'ติดเตียง', 'l'=>'red'], 'other'=>['t'=>'อื่น ๆ', 'l'=>'yellow']
                                    ];
                                    foreach($v_types as $key => $info): ?>
                                    <div class="col-4">
                                        <label class="v-badge border-0 bg-light" for="v_<?php echo $key; ?>" id="card_v_<?php echo $key; ?>">
                                            <div class="form-check m-0 small"><input class="form-check-input v-checkbox" type="checkbox" name="vulnerable_type[]" value="<?php echo $key; ?>" id="v_<?php echo $key; ?>" data-level="<?php echo $info['l']; ?>" onchange="handleIntelligenceHealth()" <?php echo in_array($key, $selected_vulnerable) ? 'checked' : ''; ?>><span class="ms-1 fw-bold"><?php echo $info['t']; ?></span></div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row g-1">
                                    <div class="col-md-6"><input type="text" name="medical_condition" class="form-control" style="height:35px !important;" value="<?php echo htmlspecialchars($data['medical_condition']); ?>" placeholder="โรคประจำตัว / ยาประจำ"></div>
                                    <div class="col-md-6"><input type="text" name="drug_allergy" class="form-control border-danger bg-danger bg-opacity-10 text-danger fw-bold" style="height:35px !important;" value="<?php echo htmlspecialchars($data['drug_allergy']); ?>" placeholder="ประวัติการแพ้ยาและอาหาร"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: ที่อยู่และการติดต่อ (40%) -->
                    <div class="col-lg-5">
                        <div class="section-header mt-0"><i class="fas fa-map-marker-alt"></i> 4. ข้อมูลที่อยู่อาศัย (Compact Address)</div>
                        
                        <div class="panel-intel py-2 mb-2 shadow-sm border-primary border-opacity-25">
                            <h6 class="fw-bold x-small text-primary mb-2 text-uppercase">ตามทะเบียนบ้าน (หน้าบัตร)</h6>
                            <div class="row g-2">
                                <div class="col-4"><label class="form-label x-small">บ้านเลขที่</label><input type="text" name="id_card_no" class="form-control form-control-sm" value="<?php echo $data['id_card_no']; ?>"></div>
                                <div class="col-3"><label class="form-label x-small">หมู่ที่</label><input type="text" name="id_card_moo" class="form-control form-control-sm" value="<?php echo $data['id_card_moo']; ?>"></div>
                                <div class="col-5"><label class="form-label x-small">ตำบล / แขวง</label><input type="text" name="id_card_subdistrict" class="form-control form-control-sm" value="<?php echo $data['id_card_subdistrict']; ?>"></div>
                                <div class="col-4 mt-1"><label class="form-label x-small">อำเภอ</label><input type="text" name="id_card_district" class="form-control form-control-sm" value="<?php echo $data['id_card_district']; ?>"></div>
                                <div class="col-5 mt-1"><label class="form-label x-small">จังหวัด</label><input type="text" name="id_card_province" class="form-control form-control-sm" value="<?php echo $data['id_card_province']; ?>"></div>
                                <div class="col-3 mt-1"><label class="form-label x-small text-center">รหัส ปณ.</label><input type="text" name="id_card_zipcode" class="form-control form-control-sm text-center" value="<?php echo $data['id_card_zipcode']; ?>" maxlength="5"></div>
                            </div>
                        </div>

                        <div class="p-3 border rounded-3 bg-white mb-3 shadow-sm border-success border-opacity-25">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold x-small text-success mb-0 text-uppercase">ที่อยู่ปัจจุบัน (ที่อพยพมา)</h6>
                                <button type="button" class="btn btn-xs btn-link text-success p-0 text-decoration-none fw-bold small" onclick="copyAddress()"><i class="fas fa-copy me-1"></i> ใช้ที่อยู่เดียวกับบัตร</button>
                            </div>
                            <div class="row g-2">
                                <div class="col-3"><input type="text" id="current_no" name="current_no" class="form-control form-control-sm" value="<?php echo $data['current_no']; ?>" placeholder="เลขที่"></div>
                                <div class="col-3"><input type="text" id="current_moo" name="current_moo" class="form-control form-control-sm" value="<?php echo $data['current_moo']; ?>" placeholder="หมู่"></div>
                                <div class="col-6"><input type="text" id="current_subdistrict" name="current_subdistrict" class="form-control form-control-sm" value="<?php echo $data['current_subdistrict']; ?>" placeholder="ตำบล"></div>
                                <div class="col-4 mt-1"><input type="text" id="current_district" name="current_district" class="form-control form-control-sm" value="<?php echo $data['current_district']; ?>" placeholder="อำเภอ"></div>
                                <div class="col-5 mt-1"><input type="text" id="current_province" name="current_province" class="form-control form-control-sm" value="<?php echo $data['current_province']; ?>" placeholder="จังหวัด"></div>
                                <div class="col-3 mt-1"><input type="text" id="current_zipcode" name="current_zipcode" class="form-control form-control-sm text-center" value="<?php echo $data['current_zipcode']; ?>" maxlength="5" placeholder="ปณ."></div>
                            </div>
                        </div>

                        <div class="section-header mt-1"><i class="fas fa-phone-alt"></i> 5. การติดต่อฉุกเฉินและข้อมูลเสริม</div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6"><label class="form-label x-small">ชื่อผู้ติดต่อฉุกเฉิน</label><input type="text" name="emergency_contact_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['emergency_contact_name']); ?>" placeholder="บุคคลที่ติดต่อได้"></div>
                            <div class="col-md-6"><label class="form-label x-small">เบอร์ติดต่อฉุกเฉิน</label><input type="text" name="emergency_contact_phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['emergency_contact_phone']); ?>" maxlength="10" placeholder="0XXXXXXXXX"></div>
                            <div class="col-md-12 mt-1"><label class="form-label x-small">ข้อจำกัดด้านอาหาร / แพ้อาหาร</label><input type="text" name="dietary_restriction" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['dietary_restriction']); ?>" placeholder="มังสวิรัติ / ฮาลาล / แพ้อาหารทะเล"></div>
                            <div class="col-12 mt-1"><label class="form-label x-small">ทักษะ / ความสามารถพิเศษ</label><input type="text" name="skills" class="form-control form-control-sm" value="<?php echo htmlspecialchars($data['skills']); ?>" placeholder="เช่น ช่างไฟฟ้า, ปฐมพยาบาล, ทำอาหาร"></div>
                        </div>

                        <div class="text-center mt-4 pt-3 border-top">
                            <button type="submit" id="btn_submit_final" class="btn btn-primary btn-save-compact w-100 shadow fw-bold py-3">
                                <i class="fas fa-check-circle me-2"></i> บันทึกข้อมูลและออกบัตรประจำตัว
                            </button>
                            <p class="text-muted mt-2 x-small">ข้อมูลทั้งหมดจะถูกส่งเข้าสู่ฐานข้อมูลประมวลผลสถานการณ์กลาง</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    $(function () {
        moment.locale('th');
        updateProgressUI();

        // --- 1. Date & Age Sync Intelligence ---
        let initialDate = $('#birth_date_db').val();
        if(initialDate && initialDate !== '0000-00-00') {
            let m = moment(initialDate);
            $('#birth_date_display').val(m.format('DD/MM') + '/' + (m.year() + 543));
        }

        $('#dtp_birth').datetimepicker({
            locale: 'th', format: 'DD/MM/YYYY', viewMode: 'years', useCurrent: false,
            icons: { time: "fa fa-clock", date: "fa fa-calendar", up: "fa fa-chevron-up", down: "fa fa-chevron-down" }
        }).on('dp.change', function (e) {
            if(e.date) {
                $('#birth_date_display').val(e.date.format('DD/MM/') + (e.date.year() + 543));
                $('#birth_date_db').val(e.date.format('YYYY-MM-DD'));
                let age = moment().diff(e.date, 'years');
                $('#age_input').val(age);
                smartIntelligenceSync(age);
                syncIdRequired();
                updateProgressUI();
            }
        });

        // --- 2. ID Checksum & Masking ---
        $('#id_card_input').on('input', function() {
            if ($('#id_type').val() === 'thai_id') {
                let v = $(this).val().replace(/\D/g, '');
                let n = '';
                if(v.length > 0) n += v.substring(0,1);
                if(v.length > 1) n += '-' + v.substring(1,5);
                if(v.length > 5) n += '-' + v.substring(5,10);
                if(v.length > 10) n += '-' + v.substring(10,12);
                if(v.length > 12) n += '-' + v.substring(12,13);
                $(this).val(n);
                if(v.length === 13) {
                    let ok = validateThaiIDChecksum(v);
                    $('#id_status').html(ok ? '<span class="text-success fw-bold">✓ เลขบัตรถูกต้องตามรูปแบบ</span>' : '<span class="text-danger fw-bold">✗ เลขบัตรไม่ถูกต้องตามหลักการคำนวณ</span>');
                } else {
                    $('#id_status').text('');
                }
            }
        });

        // --- 3. Form Submission Handling ---
        $('#evacueeForm').on('submit', function(e) {
            e.preventDefault();
            if(validateFinalForm()) {
                Swal.fire({
                    title: 'ยืนยันข้อมูล?',
                    text: "ระบบได้ทำการคัดกรองระดับความดูแลให้เบื้องต้นแล้ว",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'บันทึก',
                    cancelButtonText: 'ยกเลิก'
                }).then((r) => { if(r.isConfirmed) this.submit(); });
            }
        });

        $('input, select').on('change input', updateProgressUI);
    });

    // --- Core Smart Logic ---

    function smartSyncByPrefix(val) {
        // Auto Gender
        if (['นาย', 'เด็กชาย'].includes(val)) $('#g_male').prop('checked', true);
        if (['นาง', 'นางสาว', 'เด็กหญิง'].includes(val)) $('#g_female').prop('checked', true);
        
        // Auto Vulnerable (Child)
        if (['เด็กชาย', 'เด็กหญิง'].includes(val)) {
            $('#v_child').prop('checked', true);
            if (!$('#age_input').val()) $('#age_input').val(8);
        }
        smartIntelligenceSync($('#age_input').val());
    }

    function smartIntelligenceSync(age) {
        if (!age) return;
        // Auto check vulnerable groups by age
        $('#v_elderly').prop('checked', age >= 60);
        $('#v_child').prop('checked', age < 15);
        handleIntelligenceTriage();
    }

    function handleIntelligenceTriage() {
        let maxLvl = 'green';
        $('.v-checkbox').each(function() {
            const isChecked = $(this).is(':checked');
            $('#card_' + $(this).attr('id')).toggleClass('active', isChecked);
            if (isChecked) {
                const lvl = $(this).data('level');
                if (lvl === 'red') maxLvl = 'red';
                else if (lvl === 'yellow' && maxLvl !== 'red') maxLvl = 'yellow';
            }
        });
        $('#tr_' + maxLvl).prop('checked', true);
    }

    function validatePhoneUI() {
        let p = $('#phone').val().replace(/\D/g, '');
        let ok = p.length === 10 && /^(06|08|09|02)/.test(p);
        $('#phone_msg').html(ok ? '<span class="text-success fw-bold">✓ รูปแบบถูกต้อง</span>' : '<span class="text-danger">เบอร์ 10 หลัก (0xx)</span>');
        return ok;
    }

    function validateThaiIDChecksum(id) {
        let sum = 0;
        for(let i=0; i<12; i++) sum += parseFloat(id.charAt(i)) * (13-i);
        return ((11 - (sum % 11)) % 10) === parseFloat(id.charAt(12));
    }

    // --- Helpers ---
    function updateProgressUI() {
        const reqs = ['first_name', 'last_name', 'age_input', 'phone'];
        let c = 0;
        reqs.forEach(id => { if($('#'+id).val()) c++; });
        if($('#id_card_input').val()) c++;
        if($('#current_no').val()) c++;
        $('#formProgress').css('width', (c / 6 * 100) + '%');
    }

    function validateFinalForm() {
        if(!$('#first_name').val() || !$('#last_name').val()) { Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกชื่อและนามสกุล', 'warning'); return false; }
        if(!validatePhoneUI()) { Swal.fire('แจ้งเตือน', 'กรุณาตรวจสอบเบอร์โทรศัพท์ 10 หลัก', 'warning'); return false; }
        const age = parseInt($('#age_input').val());
        if(age >= 7 && $('#id_type').val() === 'thai_id' && $('#id_card_input').val().replace(/\D/g, '').length < 13) {
            Swal.fire('เลขบัตรไม่ครบ', 'ผู้มีอายุ 7 ปีขึ้นไปต้องระบุเลขบัตรประชาชน', 'warning'); return false;
        }
        return true;
    }

    function handleIdTypeChange() {
        const t = $('#id_type').val();
        $('#btn_generate_id').toggleClass('d-none', t !== 'none');
        $('#id_card_input').prop('readonly', t === 'none').val("");
        syncIdRequired();
    }

    function syncIdRequired() {
        const isOpt = (parseInt($('#age_input').val()) < 7 || $('#id_type').val() === 'none');
        $('#star_id').toggleClass('d-none', isOpt);
    }

    function generateTempId() {
        $('#id_card_input').val("LOST-" + moment().format('YYMMDD') + "-" + Math.floor(1000+Math.random()*8999));
        $('#id_status').html('<span class="text-primary fw-bold">รหัสชั่วคราวออกสำเร็จ</span>');
    }

    function generateFamilyCode() {
        $('#family_code').val("FAM-" + Math.floor(1000+Math.random()*9000));
    }

    function copyAddress() {
        const addrFields = ['no', 'moo', 'subdistrict', 'district', 'province', 'zipcode'];
        addrFields.forEach(f => {
            const val = $(`input[name="id_card_${f}"]`).val();
            $(`#current_${f}`).val(val);
        });
        Swal.fire({ icon: 'success', title: 'คัดลอกสำเร็จ', toast: true, position: 'top-end', showConfirmButton: false, timer: 1000 });
    }

    function toggleStayDetail() { $('#stay_detail_container').toggleClass('d-none', !$('#stay_out').is(':checked')); }
    function manualAgeSync() { syncIdRequired(); smartIntelligenceSync($('#age_input').val()); updateProgressUI(); }
</script>

<?php include 'includes/footer.php'; ?>