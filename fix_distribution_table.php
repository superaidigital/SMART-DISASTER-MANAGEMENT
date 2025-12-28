<?php
/**
 * fix_distribution_table.php
 * ไฟล์สำหรับสร้างตาราง distributions เพื่อรองรับระบบบันทึกการแจกจ่ายพัสดุ
 */

require_once 'config/db.php';

echo "<h3>ระบบตรวจสอบและแก้ไขฐานข้อมูล</h3>";

try {
    // 1. ตรวจสอบและสร้างตาราง distributions
    $sql = "CREATE TABLE IF NOT EXISTS `distributions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `evacuee_id` int(11) NOT NULL COMMENT 'รหัสผู้อพยพ',
        `item_id` int(11) NOT NULL COMMENT 'รหัสพัสดุในคลัง',
        `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'จำนวนที่แจก',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `evacuee_id` (`evacuee_id`),
        KEY `item_id` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    if ($conn->query($sql)) {
        echo "<div style='color: green;'>[OK] สร้างตาราง distributions เรียบร้อยแล้ว (หรือมีอยู่แล้ว)</div>";
    } else {
        echo "<div style='color: red;'>[Error] ไม่สามารถสร้างตารางได้: " . $conn->error . "</div>";
    }

    // 2. ตรวจสอบความถูกต้องของคอลัมน์ในตาราง inventory (เพื่อให้มั่นใจว่ามีฟิลด์ unit)
    $res = $conn->query("SHOW COLUMNS FROM `inventory` LIKE 'unit'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE `inventory` ADD `unit` VARCHAR(50) DEFAULT 'หน่วย' AFTER `quantity` ");
        echo "<div style='color: blue;'>[Update] เพิ่มคอลัมน์ unit ในตาราง inventory เรียบร้อยแล้ว</div>";
    }

} catch (Exception $e) {
    echo "<div style='color: red;'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
}

echo "<hr><a href='distribution_manager.php'>กลับไปยังหน้าจัดการการแจกจ่าย</a>";