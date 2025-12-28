<?php
/**
 * distribution_save.php
 * ประมวลผลการบันทึกการแจกจ่ายพัสดุ (ตัดสต็อก)
 * รองรับการเบิกหลายรายการพร้อมกันและระบบ Transaction เพื่อความปลอดภัย
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. เชื่อมต่อฐานข้อมูล
require_once 'config/db.php';
require_once 'includes/functions.php';

// 2. ตรวจสอบสิทธิ์และการเข้าถึง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: distribution_manager.php");
    exit();
}

/**
 * 3. ตรวจสอบและซ่อมแซมโครงสร้างตาราง (Fix Database Schema)
 * เพื่อป้องกัน Error ในกรณีที่ตารางยังไม่มีคอลัมน์ที่จำเป็น
 */
$required_columns = [
    'recipient_type'  => "ENUM('evacuee', 'general') DEFAULT 'evacuee' AFTER quantity",
    'recipient_group' => "VARCHAR(255) NULL AFTER recipient_type",
    'proxy_receiver'  => "VARCHAR(255) NULL AFTER recipient_group",
    'note'            => "TEXT NULL AFTER proxy_receiver",
    'created_by'      => "INT NULL AFTER note"
];

foreach ($required_columns as $col => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM `distributions` LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE `distributions` ADD `$col` $definition");
    }
}

// 4. รับค่าข้อมูลพื้นฐานจากฟอร์ม
$shelter_id      = isset($_POST['shelter_id']) ? (int)$_POST['shelter_id'] : 0;
$recipient_type  = $_POST['recipient_type'] ?? 'evacuee';
$evacuee_id      = ($recipient_type === 'evacuee' && !empty($_POST['evacuee_id'])) ? (int)$_POST['evacuee_id'] : NULL;
$recipient_group = ($recipient_type === 'general') ? trim($_POST['recipient_group']) : NULL;
$proxy_receiver  = !empty($_POST['proxy_receiver']) ? trim($_POST['proxy_receiver']) : NULL;
$note            = !empty($_POST['note']) ? trim($_POST['note']) : '';
$created_by      = $_SESSION['user_id'];

// รับค่ารายการพัสดุ (Arrays)
$item_ids   = $_POST['item_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];

// ตรวจสอบความครบถ้วนของข้อมูลเบื้องต้น
if ($shelter_id <= 0 || empty($item_ids)) {
    $_SESSION['error'] = "ข้อมูลไม่ครบถ้วน: กรุณาระบุศูนย์พักพิงและเลือกรายการพัสดุ";
    header("Location: distribution_manager.php?shelter_id=$shelter_id");
    exit();
}

if ($recipient_type === 'evacuee' && !$evacuee_id) {
    $_SESSION['error'] = "กรุณาระบุชื่อผู้ประสบภัยที่รับพัสดุ";
    header("Location: distribution_manager.php?shelter_id=$shelter_id");
    exit();
}

// 5. เริ่ม Transaction
$conn->begin_transaction();
$processed_count = 0;

try {
    foreach ($item_ids as $index => $item_id) {
        $item_id = (int)$item_id;
        $qty_req = (int)$quantities[$index];

        if ($item_id <= 0 || $qty_req <= 0) continue;

        // A. เช็คสต็อก (Lock แถวเพื่อป้องกันข้อมูลคลาดเคลื่อน)
        $stmt_check = $conn->prepare("SELECT item_name, quantity FROM inventory WHERE id = ? AND shelter_id = ? FOR UPDATE");
        $stmt_check->bind_param("ii", $item_id, $shelter_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        $inv_item = $res_check->fetch_assoc();

        if (!$inv_item) {
            throw new Exception("ไม่พบรายการพัสดุรหัส #$item_id ในคลัง");
        }

        if ($inv_item['quantity'] < $qty_req) {
            throw new Exception("พัสดุ '{$inv_item['item_name']}' มีไม่พอ (คงเหลือ {$inv_item['quantity']})");
        }

        // B. ตัดสต็อก
        $stmt_upd = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt_upd->bind_param("ii", $qty_req, $item_id);
        if (!$stmt_upd->execute()) {
            throw new Exception("ไม่สามารถตัดสต็อก '{$inv_item['item_name']}' ได้");
        }

        // C. บันทึกประวัติ
        $sql_ins = "INSERT INTO distributions 
                    (evacuee_id, item_id, quantity, recipient_type, recipient_group, proxy_receiver, note, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bind_param("iiissssi", 
            $evacuee_id, $item_id, $qty_req, $recipient_type, 
            $recipient_group, $proxy_receiver, $note, $created_by
        );

        if (!$stmt_ins->execute()) {
            throw new Exception("บันทึกประวัติล้มเหลว: " . $conn->error);
        }
        $processed_count++;
    }

    if ($processed_count === 0) {
        throw new Exception("ไม่มีรายการพัสดุที่ถูกประมวลผล");
    }

    $conn->commit();
    $_SESSION['success'] = "บันทึกการแจกจ่ายเรียบร้อยแล้ว รวม $processed_count รายการ";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// 6. ส่งกลับไปยังหน้าจัดการ
$redirect_url = "distribution_manager.php?shelter_id=$shelter_id";
if ($evacuee_id) $redirect_url .= "&evacuee_id=$evacuee_id";

header("Location: $redirect_url");
exit();