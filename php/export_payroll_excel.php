<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 


// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;

// ฟังก์ชันช่วย
function thai_month_name_excel($month_year) {
    $parts = explode('-', $month_year);
    if (count($parts) !== 2) return $month_year;
    $y = (int)$parts[0];
    $m = (int)$parts[1];
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return 'ประจำเดือน ' . $months[$m - 1] . ' ' . ($y + 543);
}

// 1. รับค่าตัวกรอง
$selected_month = $_GET['month'] ?? date('Y-m');

// 2. ดึงข้อมูลและคำนวณเงินเดือน (โค้ดเดียวกับใน dashboard)
$calculated_data = [];
$stmt = $conn->prepare("
    SELECT 
        e.employee_id, e.full_name,
        SUM(CASE WHEN a.morning='present' AND a.afternoon='present' THEN 1 ELSE 0 END) AS full_days,
        SUM(CASE WHEN (a.morning='present' AND a.afternoon NOT IN ('present', 'late')) OR (a.morning NOT IN ('present', 'late') AND a.afternoon='present') THEN 1 ELSE 0 END) AS half_days,
        COUNT(DISTINCT CASE WHEN a.morning='late' OR a.afternoon='late' THEN a.attend_date END) AS late_days,
        SUM(CASE WHEN a.morning='leave' AND a.afternoon='leave' THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN a.morning='absent' AND a.afternoon='absent' THEN 1 ELSE 0 END) AS absent_days
    FROM employees e
    LEFT JOIN attendances a ON e.employee_id = a.employee_id AND DATE_FORMAT(a.attend_date, '%Y-%m') = ?
    WHERE e.status = 1
    GROUP BY e.employee_id
    ORDER BY e.employee_id
");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$result = $stmt->get_result();

if($result){
    while($row = $result->fetch_assoc()){
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + (float)$row['late_days'];
        $amount = ((int)$row['full_days'] * $full_rate) + ((int)$row['half_days'] * $half_rate) + ((int)$row['late_days'] * $full_rate);
        
        $calculated_data[] = [
            'id' => $row['employee_id'], 'full_name' => $row['full_name'], 'full' => (int)$row['full_days'],
            'half' => (int)$row['half_days'], 'late' => (int)$row['late_days'],
            'leave' => (int)$row['leave_days'], 'absent' => (int)$row['absent_days'],
            'work_days' => $work_days_paid, 'amount' => $amount
        ];
    }
}

// 3. สร้างและส่งออกไฟล์ CSV
$filename = "payroll_report_" . $selected_month . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM (Byte Order Mark) สำหรับ UTF-8
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวข้อ
fputcsv($output, [thai_month_name_excel($selected_month)]);
fputcsv($output, []); // บรรทัดว่าง

// เขียนหัวตาราง
fputcsv($output, ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)']);

// เขียนข้อมูล
$i = 1;
$totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);

if (!empty($calculated_data)) {
    foreach ($calculated_data as $d) {
        // คำนวณยอดรวม
        foreach(['full','half','late','leave','absent','work_days','amount'] as $key) {
             $totals[$key] += $d[$key]; 
        }

        fputcsv($output, [
            $i++, 
            $d['id'], 
            $d['full_name'], 
            $d['full'], 
            $d['half'], 
            $d['late'], 
            $d['leave'], 
            $d['absent'], 
            number_format($d['work_days'], 1), 
            number_format($d['amount'], 2)
        ]);
    }
    
    // แถวรวม
    fputcsv($output, [
        'ยอดรวมทั้งหมด', 
        '', 
        '',
        number_format($totals['full']),
        number_format($totals['half']),
        number_format($totals['late']),
        number_format($totals['leave']),
        number_format($totals['absent']),
        number_format($totals['work_days'], 1),
        number_format($totals['amount'], 2)
    ]);
}

fclose($output);
exit();
?>