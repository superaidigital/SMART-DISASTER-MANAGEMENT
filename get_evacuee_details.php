<?php
/**
 * get_evacuee_details.php
 * ไฟล์ API สำหรับดึงข้อมูลผู้อพยพจากรหัส ID
 * ตรวจสอบให้แน่ใจว่าไฟล์นี้อยู่ในโฟลเดอร์เดียวกับ qr_scanner.php
 */
header('Content-Type: application/json; charset=utf-8');

// ปิดการแสดงผล Error เป็น HTML เพื่อไม่ให้รบกวน JSON
error_reporting(0); 

try {
    // เรียกใช้ไฟล์ฐานข้อมูล (ตรวจสอบ Path ให้ถูกต้อง)
    if (!file_exists('config/db.php')) {
        throw new Exception("ไม่พบไฟล์ config/db.php");
    }
    require_once 'config/db.php';

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'รหัสผู้อพยพไม่ถูกต้อง']);
        exit();
    }

    // ค้นหาข้อมูลผู้พักพิงพร้อมชื่อศูนย์
    $sql = "SELECT e.*, s.name as shelter_name 
            FROM evacuees e 
            LEFT JOIN shelters s ON e.shelter_id = s.id 
            WHERE e.id = ? AND e.check_out_date IS NULL";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("เกิดข้อผิดพลาดในคำสั่ง SQL: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // คำนวณอายุหากไม่มีในฐานข้อมูล
        if (empty($row['age']) && !empty($row['birth_date'])) {
            $birthDate = new DateTime($row['birth_date']);
            $today = new DateTime();
            $row['age'] = $today->diff($birthDate)->y;
        }

        echo json_encode(array_merge(['success' => true], $row));
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้พักพิงที่ยังคงอยู่ในศูนย์']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดทางเทคนิค: ' . $e->getMessage()
    ]);
}