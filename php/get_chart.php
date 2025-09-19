<?php
$conn = new mysqli("localhost","root","","bigproject");
$conn->set_charset("utf8mb4");
$month = intval($_GET['month']);
$year = intval($_GET['year']);
$days_in_month = cal_days_in_month(CAL_GREGORIAN,$month,$year);
$start = "$year-$month-01";
$end = "$year-$month-$days_in_month";

// ดึงพนักงาน
$emp = [];
$res_emp = $conn->query("SELECT employee_id, full_name FROM employees WHERE deleted=0");
while($r=$res_emp->fetch_assoc()) $emp[$r['employee_id']]=$r['full_name'];

// ดึงจำนวนวันทำงาน
$work_days = [];
foreach($emp as $id=>$name){
    $res = $conn->query("SELECT morning, afternoon FROM attendances WHERE employee_id='$id' AND attend_date BETWEEN '$start' AND '$end'");
    $days = 0;
    while($r=$res->fetch_assoc()){
        if($r['morning'] && strtolower($r['morning'])!='absent') $days+=0.5;
        if($r['afternoon'] && strtolower($r['afternoon'])!='absent') $days+=0.5;
    }
    $work_days[] = $days;
}

// สร้างสี
$colors = [];
foreach($work_days as $i){
    $colors[] = "rgba(".rand(50,200).",".rand(100,200).",".rand(150,255).",0.7)";
}

echo json_encode([
    'labels'=>array_values($emp),
    'work_days'=>$work_days,
    'colors'=>$colors
]);
?>
