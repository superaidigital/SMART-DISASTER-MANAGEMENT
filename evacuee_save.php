<?php
/**
 * evacuee_save.php
 * ประมวลผลการบันทึกข้อมูลผู้ประสบภัย (Insert/Update)
 * FIX: แก้ไข Foreign Key Constraint Fail โดยการจัดการค่า NULL สำหรับ shelter_id และ incident_id
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'config/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: evacuee_list.php");
    exit();
}

// --- 1. เตรียมข้อมูลพื้นฐาน ---
$id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$mode           = $_POST['mode'] ?? 'add';
$shelter_id     = isset($_POST['shelter_id']) ? (int)$_POST['shelter_id'] : 0;
$id_type        = $_POST['id_type'] ?? 'thai_id';
$id_card        = trim($_POST['id_card'] ?? '');
$family_code    = trim($_POST['family_code'] ?? '');
$stay_type      = $_POST['stay_type'] ?? 'in_center';
$stay_detail    = trim($_POST['stay_detail'] ?? '');

$prefix_select  = $_POST['prefix_select'] ?? '';
$prefix         = ($prefix_select === 'other') ? trim($_POST['prefix'] ?? '') : $prefix_select;

$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$birth_date     = !empty($_POST['birth_date']) ? $_POST['birth_date'] : NULL;
$age            = (int)$_POST['age'];
$gender         = $_POST['gender'] ?? 'male';
$phone          = trim($_POST['phone'] ?? '');

// --- 2. แก้ไขปัญหา Foreign Key (CRITICAL FIX) ---

// ค้นหา incident_id จากศูนย์ที่เลือก
$incident_id = null;
if ($shelter_id > 0) {
    $st_sh = $conn->prepare("SELECT incident_id FROM shelters WHERE id = ?");
    $st_sh->bind_param("i", $shelter_id);
    $st_sh->execute();
    $res_sh = $st_sh->get_result()->fetch_assoc();
    if ($res_sh) {
        $incident_id = $res_sh['incident_id'];
    } else {
        // หาก shelter_id ที่ส่งมาไม่มีใน DB จริงๆ ให้เซตเป็น NULL เพื่อไม่ให้ FK Error
        $shelter_id = null; 
    }
} else {
    // กรณีพักนอกศูนย์ shelter_id ต้องเป็น NULL (ไม่ใช่ 0)
    $shelter_id = null; 
}

// กรณี incident_id ยังเป็น NULL (เช่น Admin เพิ่มคนโดยไม่ระบุศูนย์) 
// ต้องดึง Incident ล่าสุดที่เปิดใช้งานอยู่มาใส่แทน เพื่อไม่ให้ติด FK fk_evacuees_incident
if ($incident_id === null) {
    $res_inc = $conn->query("SELECT id FROM incidents ORDER BY id DESC LIMIT 1");
    if ($row_inc = $res_inc->fetch_assoc()) {
        $incident_id = $row_inc['id'];
    } else {
        // ถ้าไม่มี Incident เลย ต้องหยุดการทำงานเพราะ incident_id ในตาราง evacuees เป็น NOT NULL
        $_SESSION['error'] = "ไม่สามารถบันทึกได้เนื่องจากยังไม่มีการสร้าง 'ภารกิจ/เหตุการณ์' ในระบบ";
        header("Location: evacuee_form.php?id=$id&shelter_id=$shelter_id");
        exit();
    }
}

// --- 3. รวบรวมข้อมูลลงใน Array (32 ตัวแปร) ---
$fields = [
    'incident_id'        => [$incident_id, 'i'],
    'shelter_id'         => [$shelter_id, 'i'], // mysqli จะเปลี่ยน null เป็น NULL ใน DB ให้อัตโนมัติ
    'stay_type'          => [$stay_type, 's'],
    'stay_detail'        => [$stay_detail, 's'],
    'id_type'            => [$id_type, 's'],
    'id_card'            => [$id_card, 's'],
    'family_code'        => [$family_code, 's'],
    'prefix'             => [$prefix, 's'],
    'first_name'         => [$first_name, 's'],
    'last_name'          => [$last_name, 's'],
    'birth_date'         => [$birth_date, 's'],
    'age'                => [$age, 'i'],
    'gender'             => [$gender, 's'],
    'phone'              => [$phone, 's'],
    'id_card_no'         => [trim($_POST['id_card_no'] ?? ''), 's'],
    'id_card_moo'        => [trim($_POST['id_card_moo'] ?? ''), 's'],
    'id_card_subdistrict'=> [trim($_POST['id_card_subdistrict'] ?? ''), 's'],
    'id_card_district'   => [trim($_POST['id_card_district'] ?? ''), 's'],
    'id_card_province'   => [trim($_POST['id_card_province'] ?? ''), 's'],
    'id_card_zipcode'    => [trim($_POST['id_card_zipcode'] ?? ''), 's'],
    'current_no'         => [trim($_POST['current_no'] ?? ''), 's'],
    'current_moo'        => [trim($_POST['current_moo'] ?? ''), 's'],
    'current_subdistrict'=> [trim($_POST['current_subdistrict'] ?? ''), 's'],
    'current_district'   => [trim($_POST['current_district'] ?? ''), 's'],
    'current_province'   => [trim($_POST['current_province'] ?? ''), 's'],
    'current_zipcode'    => [trim($_POST['current_zipcode'] ?? ''), 's'],
    'triage_level'       => [$_POST['triage_level'] ?? 'green', 's'],
    'vulnerable_type'    => [isset($_POST['vulnerable_type']) ? implode(',', $_POST['vulnerable_type']) : '', 's'],
    'vulnerable_detail'  => [trim($_POST['vulnerable_detail'] ?? ''), 's'],
    'medical_condition'  => [trim($_POST['medical_condition'] ?? ''), 's'],
    'drug_allergy'       => [trim($_POST['drug_allergy'] ?? ''), 's'],
    'registered_by'      => [(int)$_SESSION['user_id'], 'i']
];

$conn->begin_transaction();

try {
    if ($age >= 7 && $id_type !== 'none' && empty($id_card)) {
        throw new Exception("กรุณาระบุเลขประจำตัวประชาชน (เนื่องจากอายุ 7 ปีขึ้นไป)");
    }

    $column_names = array_keys($fields);
    $values = array_column($fields, 0);
    $types  = implode('', array_column($fields, 1));

    if ($mode === 'add') {
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $sql = "INSERT INTO evacuees (" . implode(',', $column_names) . ", created_at) VALUES ($placeholders, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
    } else {
        $set_clause = implode('=?,', $column_names) . '=?';
        $sql = "UPDATE evacuees SET $set_clause, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $types .= 'i'; 
        $values[] = $id;
        $stmt->bind_param($types, ...$values);
    }

    if (!$stmt->execute()) { 
        // ตรวจสอบ Error พิเศษกรณี Foreign Key ล้มเหลวอีกครั้ง
        if ($conn->errno == 1452) {
            throw new Exception("ไม่สามารถบันทึกได้: ข้อมูลรหัสศูนย์พักพิง หรือ รหัสภารกิจ ไม่ถูกต้อง (ไม่พบข้อมูลในระบบ)");
        }
        throw new Exception("Database Error: " . $conn->error); 
    }

    $final_id = ($mode === 'add') ? $conn->insert_id : $id;
    $conn->commit();

    $_SESSION['success'] = ($mode === 'add') ? "ลงทะเบียนสำเร็จ" : "แก้ไขข้อมูลสำเร็จ";
    header("Location: evacuee_form.php?id=$final_id&shelter_id=" . ($shelter_id ?? 0));
    exit();

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: evacuee_form.php?id=$id&shelter_id=" . ($shelter_id ?? 0) . "&mode=$mode");
    exit();
}