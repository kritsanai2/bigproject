<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 

// --- ค่าคงที่ ---
define('FULL_RATE', 300);
define('HALF_RATE', 150);

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month_name($month_num) {
    $months = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
    return $months[intval($month_num)] ?? '';
}

// 1. รับค่าตัวกรอง
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter  = isset($_GET['year']) ? intval($_GET['year']) : 0;

// --- สร้างหัวข้อรายงานแบบ Dynamic ---
if ($month_filter == 0 && $year_filter == 0) {
    $report_title = "รายงานเงินเดือนทั้งหมด";
} else {
    $report_title = "รายงานเงินเดือน";
    if ($month_filter > 0) $report_title .= " เดือน " . thai_month_name($month_filter);
    if ($year_filter > 0) $report_title .= " ปี " . ($year_filter + 543);
    else $report_title .= " (ทุกปี)";
}

// 2. ดึงข้อมูลและคำนวณเงินเดือน (โค้ดเดียวกับใน dashboard)
$sql = "
    SELECT 
        e.employee_id, e.full_name, p.full_days, p.half_days, p.late_days,
        p.leave_days, p.absent_days, p.work_days, p.amount
    FROM employees e
    LEFT JOIN employee_payments p ON e.employee_id = p.employee_id
        AND (? = 0 OR MONTH(p.pay_month) = ?)
        AND (? = 0 OR YEAR(p.pay_month) = ?)
    WHERE e.status = 1
    ORDER BY e.employee_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $month_filter, $month_filter, $year_filter, $year_filter);
$stmt->execute();
$result = $stmt->get_result();

$calculated_data = [];
if($result){
    while($row = $result->fetch_assoc()){
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + ((float)$row['late_days'] * 0.5);
        $amount = ((int)$row['full_days'] * FULL_RATE) + ((int)$row['half_days'] * HALF_RATE) + ((int)$row['late_days'] * HALF_RATE);
        
        $calculated_data[] = [
            'id' => $row['employee_id'], 'full_name' => $row['full_name'], 'full' => (int)$row['full_days'],
            'half' => (int)$row['half_days'], 'late' => (int)$row['late_days'],
            'leave' => (int)$row['leave_days'], 'absent' => (int)$row['absent_days'],
            'work_days' => $work_days_paid, 'amount' => $amount
        ];
    }
}

// 3. สร้างและส่งออกไฟล์ CSV
$filename = "payroll_report_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM (Byte Order Mark) สำหรับ UTF-8
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวข้อ
fputcsv($output, [$report_title]);
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
    
    fputcsv($output, []); // บรรทัดว่าง
    // แถวรวม
    fputcsv($output, [
        '', 
        '', 
        'ยอดรวมทั้งหมด',
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