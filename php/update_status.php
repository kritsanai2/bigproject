<?php
require_once "db.php";

$empId = $_POST['employee_id'] ?? '';
$period = $_POST['period'] ?? '';
$status = $_POST['status'] ?? '';
$attend_date = $_POST['attend_date'] ?? '';

$status_options = ['present','late','absent','leave'];
$period_options = ['morning','afternoon'];

if($empId && in_array($period, $period_options) && in_array($status, $status_options)) {
    // ตรวจสอบว่ามีบันทึกอยู่แล้ว
    $checkStmt = $conn->prepare("SELECT attendance_id FROM attendances WHERE employee_id=? AND attend_date=?");
    $checkStmt->bind_param("ss", $empId, $attend_date);
    $checkStmt->execute();
    $checkStmt->store_result();

    if($checkStmt->num_rows > 0){
        // update เฉพาะช่วงเช้าหรือบ่าย
        if($period === 'morning') {
            $stmt = $conn->prepare("UPDATE attendances SET morning=? WHERE employee_id=? AND attend_date=?");
        } else {
            $stmt = $conn->prepare("UPDATE attendances SET afternoon=? WHERE employee_id=? AND attend_date=?");
        }
        $stmt->bind_param("sss", $status, $empId, $attend_date);
    } else {
        // insert ใหม่ กำหนดอีกช่องเป็น default 'present'
        $morning = ($period === 'morning') ? $status : 'present';
        $afternoon = ($period === 'afternoon') ? $status : 'present';
        $stmt = $conn->prepare("INSERT INTO attendances (employee_id, attend_date, morning, afternoon) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $empId, $attend_date, $morning, $afternoon);
    }

    $success = $stmt->execute();

    if($success){
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','msg'=>$stmt->error]);
    }
} else {
    echo json_encode(['status'=>'error','msg'=>'ข้อมูลไม่ครบหรือไม่ถูกต้อง']);
}
