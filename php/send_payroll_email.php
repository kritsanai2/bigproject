<?php
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require_once 'db.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- FPDF Setup ---
// FPDF is typically handled by Composer's autoloader, but if you have a manual setup, this is okay.
// Just ensure the font path is correct.
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; 

// --- Constants ---
define('FULL_RATE', 300);
define('HALF_RATE', 150);

// --- Helper Function ---
function thai_month_name($month_num) {
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
               5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
               9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month_num)] ?? '';
}

$files_to_attach = []; // Initialize here to be accessible in finally block

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // --- รับค่าจาก POST ---
    $recipient_email = trim($_POST['email'] ?? '');
    $selected_formats = $_POST['file_formats'] ?? []; 
    $month_filter = isset($_POST['month']) ? intval($_POST['month']) : intval(date('m'));
    $year_filter  = isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y'));

    if (!is_array($selected_formats) || empty($selected_formats)) {
        throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");
    }
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    }

    // --- หัวข้อรายงาน ---
    $report_title = "รายงานเงินเดือน";
    if ($month_filter > 0) $report_title .= " เดือน " . thai_month_name($month_filter);
    if ($year_filter > 0)  $report_title .= " ปี " . ($year_filter + 543);

    // --- ดึงข้อมูลจากฐานข้อมูล (IMPROVEMENT: ย้ายเงื่อนไขไปไว้ใน ON clause ของ LEFT JOIN) ---
    $sql = "
        SELECT 
            e.employee_id, e.full_name,
            p.full_days, p.half_days, p.late_days, p.leave_days, p.absent_days
        FROM employees e
        LEFT JOIN employee_payments p ON e.employee_id = p.employee_id
            AND (? = 0 OR MONTH(p.pay_month) = ?)
            AND (? = 0 OR YEAR(p.pay_month) = ?)
        WHERE e.status = 1
        ORDER BY e.employee_id ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $month_filter, $month_filter, $year_filter, $year_filter);
    $stmt->execute();
    $result = $stmt->get_result();

    $calculated_data = [];
    while ($row = $result->fetch_assoc()) {
        $full_days = (int)($row['full_days'] ?? 0);
        $half_days = (int)($row['half_days'] ?? 0);
        $late_days = (int)($row['late_days'] ?? 0);
        
        $work_days_paid = (float)$full_days + ((float)$half_days * 0.5) + ((float)$late_days * 0.5);
        $amount = ($full_days * FULL_RATE) + (($half_days + $late_days) * HALF_RATE);
        
        $calculated_data[] = [
            'id'        => $row['employee_id'],
            'full_name' => $row['full_name'],
            'full'      => $full_days,
            'half'      => $half_days,
            'late'      => $late_days,
            'leave'     => (int)($row['leave_days'] ?? 0),
            'absent'    => (int)($row['absent_days'] ?? 0),
            'work_days' => $work_days_paid,
            'amount'    => $amount
        ];
    }
    
    if (empty($calculated_data)) {
        throw new Exception("ไม่พบข้อมูลสำหรับสร้างรายงาน");
    }

    // --- คำนวณยอดรวม ---
    $totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);
    foreach ($calculated_data as $d) {
        foreach($totals as $key => &$value) {
            $value += $d[$key];
        }
    }
    unset($value);

    // --- สร้างไฟล์ตามรูปแบบ ---
    $temp_dir = sys_get_temp_dir();
    foreach($selected_formats as $format) {
        $file_name_base = "payroll_report_" . date('Y-m-d');
        $ext = ($format === 'pdf') ? '.pdf' : '.csv';
        $file_path = $temp_dir . DIRECTORY_SEPARATOR . uniqid($file_name_base) . $ext;

        if ($format === 'pdf') {
            $pdf = new FPDF('L'); 
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php'); 
            $pdf->AddPage();
            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
            $pdf->Ln(5);
            
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(230, 230, 230);
            $header = ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)'];
            $w = [15, 20, 75, 20, 20, 20, 20, 20, 30, 30];
            foreach($header as $i => $h) {
                $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $h), 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            foreach($calculated_data as $d){
                $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
                $pdf->Cell($w[1], 8, $d['id'], 1, 0, 'C');
                $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $d['full_name']), 1, 0, 'L');
                $pdf->Cell($w[3], 8, $d['full'], 1, 0, 'C');
                $pdf->Cell($w[4], 8, $d['half'], 1, 0, 'C');
                $pdf->Cell($w[5], 8, $d['late'], 1, 0, 'C');
                $pdf->Cell($w[6], 8, $d['leave'], 1, 0, 'C');
                $pdf->Cell($w[7], 8, $d['absent'], 1, 0, 'C');
                $pdf->Cell($w[8], 8, number_format($d['work_days'], 1), 1, 0, 'C');
                $pdf->Cell($w[9], 8, number_format($d['amount'], 2), 1, 1, 'R');
            }
            
            // FIX: แก้ไขและลดความซับซ้อนของแถว Total
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(array_sum(array_slice($w, 0, 3)), 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
            $pdf->Cell($w[3], 10, number_format($totals['full']), 1, 0, 'C', true);
            $pdf->Cell($w[4], 10, number_format($totals['half']), 1, 0, 'C', true);
            $pdf->Cell($w[5], 10, number_format($totals['late']), 1, 0, 'C', true);
            $pdf->Cell($w[6], 10, number_format($totals['leave']), 1, 0, 'C', true);
            $pdf->Cell($w[7], 10, number_format($totals['absent']), 1, 0, 'C', true);
            $pdf->Cell($w[8], 10, number_format($totals['work_days'], 1), 1, 0, 'C', true);
            $pdf->Cell($w[9], 10, number_format($totals['amount'], 2), 1, 1, 'R', true);
            
            $pdf->Output('F', $file_path);
        } else { // CSV
            $out = fopen($file_path, 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, [$report_title]);
            fputcsv($out, []); // Blank line
            fputcsv($out, ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)']);
            $i = 1;
            foreach($calculated_data as $d){
                fputcsv($out, [$i++, $d['id'], $d['full_name'], $d['full'], $d['half'], $d['late'], $d['leave'], $d['absent'], number_format($d['work_days'], 1), number_format($d['amount'], 2)]);
            }
            fputcsv($out, []); // Blank line
            fputcsv($out, ['', '', 'ยอดรวมทั้งหมด', $totals['full'], $totals['half'], $totals['late'], $totals['leave'], $totals['absent'], number_format($totals['work_days'], 1), number_format($totals['amount'], 2)]);
            fclose($out);
        }

        $files_to_attach[] = ['path' => $file_path, 'name' => basename($file_path)];
    }

    // --- ส่งอีเมล ---
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'gfc20140@gmail.com'; // อีเมลที่ใช้ยืนยันตัวตนกับ SMTP
    $mail->Password = 'ivjo hwqy kraq sgwe';   // รหัสผ่าน App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // FIX: อีเมลผู้ส่ง (From) ต้องเป็นอีเมลเดียวกับ Username เพื่อให้ผ่าน Policy ของ Gmail
    $mail->setFrom('gfc20140@gmail.com', 'รายงานระบบเงินเดือน');
    
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = $report_title;
    $mail->Body = "รายงานเงินเดือนที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานเงินเดือนได้แนบมากับอีเมลนี้แล้ว";

    foreach($files_to_attach as $f) {
        $mail->addAttachment($f['path'], $f['name']);
    }
    
    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);

} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // --- ลบไฟล์ชั่วคราวหลังส่งเสร็จหรือเกิด Error ---
    foreach($files_to_attach as $f) {
        if (file_exists($f['path'])) {
            unlink($f['path']);
        }
    }
}