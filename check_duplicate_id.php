<?php
// check_duplicate_id.php - API สำหรับตรวจสอบเลขบัตรประชาชนซ้ำ
require_once 'config/db.php';

header('Content-Type: application/json');

$id_card = isset($_GET['id_card']) ? mysqli_real_escape_string($conn, $_GET['id_card']) : '';
$current_id = isset($_GET['current_id']) ? (int)$_GET['current_id'] : 0;

$response = ['exists' => false];

if (!empty($id_card)) {
    // เช็คว่ามีเลขบัตรนี้ในระบบหรือยัง (ยกเว้น ID ปัจจุบัน กรณีแก้ไข)
    $sql = "SELECT e.id, s.name as shelter_name 
            FROM evacuees e 
            LEFT JOIN shelters s ON e.shelter_id = s.id 
            WHERE e.id_card = '$id_card' AND e.id != $current_id 
            LIMIT 1";
            
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $response['exists'] = true;
        $response['shelter_name'] = $row['shelter_name'] ?? 'ไม่ทราบศูนย์';
    }
}

echo json_encode($response);
?>