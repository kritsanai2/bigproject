<?php
require_once "auth.php";
require_once "db.php";

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month($month_num) {
    $months = [ 1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม' ];
    return $months[intval($month_num)] ?? '';
}

// --- รับค่า Filter ---
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year_filter  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$thai_year = $year_filter + 543;
$month_name = thai_month($month_filter);

// --- ตั้งชื่อไฟล์ ---
$filename = "รายงานเงินเดือน_{$month_name}_{$thai_year}.csv";

// --- ดึงข้อมูลจากฐานข้อมูล ---
$sql = "
    SELECT 
        e.employee_id, e.full_name, p.full_days, p.half_days,
        p.late_days, p.leave_days, p.absent_days, p.work_days, p.salary
    FROM employees e
    LEFT JOIN employee_payments p
        ON e.employee_id = p.employee_id
        AND (? = 0 OR MONTH(p.pay_month) = ?)
        AND (? = 0 OR YEAR(p.pay_month) = ?)
    WHERE e.status = 1
    ORDER BY e.employee_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $month_filter, $month_filter, $year_filter, $year_filter);
$stmt->execute();
$result = $stmt->get_result();

// --- ตั้งค่า Header สำหรับดาวน์โหลดไฟล์ ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// --- สร้างไฟล์ CSV ---
$output = fopen('php://output', 'w');

// เพิ่ม BOM เพื่อให้ Excel เปิดไฟล์ UTF-8 ได้ถูกต้อง
fputs($output, "\xEF\xBB\xBF");

// ==========================================================
//  ส่วนที่เพิ่มเข้ามา: เขียนหัวข้อรายงานลงในไฟล์
// ==========================================================
$report_title = "รายงานสรุปเงินเดือนพนักงาน ประจำเดือน {$month_name} ปี {$thai_year}";
fputcsv($output, [$report_title]); // เขียนหัวข้อหลักของรายงาน
fputcsv($output, []);              // เพิ่มบรรทัดว่างเพื่อความสวยงาม
// ==========================================================

// เขียนหัวตาราง
$headers = ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)'];
fputcsv($output, $headers);

// เขียนข้อมูลลงไฟล์
$i = 1;
$totals = array_fill_keys(['full_days', 'half_days', 'late_days', 'leave_days', 'absent_days', 'work_days', 'salary'], 0);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data_row = [
            $i++,
            $row['employee_id'],
            $row['full_name'],
            (int)($row['full_days'] ?? 0),
            (int)($row['half_days'] ?? 0),
            (int)($row['late_days'] ?? 0),
            number_format((float)($row['leave_days'] ?? 0), 1),
            number_format((float)($row['absent_days'] ?? 0), 1),
            number_format((float)($row['work_days'] ?? 0), 1),
            number_format((float)($row['salary'] ?? 0), 2)
        ];
        fputcsv($output, $data_row);

        // คำนวณยอดรวม
        foreach ($totals as $key => &$value) {
            $value += (float)($row[$key] ?? 0);
        }
        unset($value); // Unset reference
    }

    // เขียนแถวสรุปรวม
    $total_row = [
        '', '', 'รวมทั้งหมด',
        number_format($totals['full_days']),
        number_format($totals['half_days']),
        number_format($totals['late_days']),
        number_format($totals['leave_days'], 1),
        number_format($totals['absent_days'], 1),
        number_format($totals['work_days'], 1),
        number_format($totals['salary'], 2)
    ];
    fputcsv($output, $total_row);
} else {
    fputcsv($output, ['ไม่พบข้อมูลตามเงื่อนไขที่เลือก']);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>