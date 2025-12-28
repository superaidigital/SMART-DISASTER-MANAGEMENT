<?php
/**
 * search_evacuee.php
 * API สำหรับค้นหาข้อมูลผู้ประสบภัยแบบ AJAX เพื่อใช้กับ Select2
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php';

// รับคำค้นหา (q) และรหัสศูนย์ (shelter_id)
$q = isset($_GET['q']) ? $_GET['q'] : '';
$shelter_id = isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : 0;

$results = [];

if (!empty($q)) {
    $search = "%$q%";
    
    // SQL ค้นหาจากชื่อ นามสกุล หรือเลขบัตรประชาชน
    // ใช้ CONCAT เพื่อสร้างชื่อเต็มส่งกลับไปให้ JS แสดงผลได้ทันที
    $sql = "SELECT id, first_name, last_name, id_card, 
            CONCAT(first_name, ' ', last_name) as full_name 
            FROM evacuees 
            WHERE (first_name LIKE ? OR last_name LIKE ? OR id_card LIKE ?)
            AND check_out_date IS NULL"; // ค้นหาเฉพาะคนที่ยังพักอาศัยอยู่

    // ถ้ามีการระบุศูนย์ (เช่น เจ้าหน้าที่ศูนย์) ให้ค้นหาเฉพาะในศูนย์นั้น
    if ($shelter_id > 0) {
        $sql .= " AND shelter_id = ?";
    }

    $sql .= " LIMIT 20";

    try {
        $stmt = $conn->prepare($sql);
        if ($shelter_id > 0) {
            $stmt->bind_param("sssi", $search, $search, $search, $shelter_id);
        } else {
            $stmt->bind_param("sss", $search, $search, $search);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    } catch (Exception $e) {
        // กรณี Error จะไม่ส่งอะไรกลับไป
    }
}

echo json_encode($results);