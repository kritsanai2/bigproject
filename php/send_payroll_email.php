<?php
// ตั้งค่า Header ให้เป็น JSON ทันที
header('Content-Type: application/json');

// Path สำหรับ Library
require __DIR__ . '/../vendor/autoload.php';
require_once 'db.php'; 

// Path PHPMailer
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'; 
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// Path FPDF
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- ค่าคงที่ ---
define('FULL_RATE', 300);
define('HALF_RATE', 150);

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month_name($month_num) {
    $months = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
    return $months[intval($month_num)] ?? '';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method.");
    
    // 1. รับค่าจาก POST
    $recipient_email = trim($_POST['email'] ?? '');
    $selected_formats = $_POST['file_formats'] ?? []; 
    $month_filter = isset($_POST['month']) ? intval($_POST['month']) : 0;
    $year_filter  = isset($_POST['year']) ? intval($_POST['year']) : 0;

    if (!is_array($selected_formats) || empty($selected_formats)) {
        throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");
    }
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");

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
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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

    // 3. เตรียมไฟล์แนบชั่วคราว
    $temp_dir = sys_get_temp_dir();
    $files_to_attach = []; 
    $totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);
    foreach ($calculated_data as $d) {
        foreach(['full','half','late','leave','absent','work_days','amount'] as $key) { $totals[$key] += $d[$key]; }
    }


    // 4. วนลูปสร้างไฟล์ตามรูปแบบที่เลือก
    foreach ($selected_formats as $file_format) {
        $file_name_base = "payroll_report_" . date('Y-m-d');
        $extension = $file_format === 'pdf' ? '.pdf' : '.csv';
        $file_path = $temp_dir . DIRECTORY_SEPARATOR . uniqid($file_name_base) . $extension;

        if ($file_format === 'pdf') {
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
            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();
            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            if (!empty($calculated_data)) {
                foreach ($calculated_data as $d) {
                    $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
                    $pdf->Cell($w[1], 8, iconv('UTF-8', 'TIS-620', $d['id']), 1, 0, 'C');
                    $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $d['full_name']), 1, 0, 'L');
                    $pdf->Cell($w[3], 8, $d['full'], 1, 0, 'C');
                    $pdf->Cell($w[4], 8, $d['half'], 1, 0, 'C');
                    $pdf->Cell($w[5], 8, $d['late'], 1, 0, 'C');
                    $pdf->Cell($w[6], 8, $d['leave'], 1, 0, 'C');
                    $pdf->Cell($w[7], 8, $d['absent'], 1, 0, 'C');
                    $pdf->Cell($w[8], 8, number_format($d['work_days'], 1), 1, 0, 'C');
                    $pdf->Cell($w[9], 8, number_format($d['amount'], 2), 1, 1, 'R');
                }
                $pdf->SetFont('THSarabunNew', 'B', 12);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell($w[0]+$w[1]+$w[2], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
                $pdf->Cell($w[3], 10, number_format($totals['full']), 1, 0, 'C', true);
                $pdf->Cell($w[4], 10, number_format($totals['half']), 1, 0, 'C', true);
                $pdf->Cell($w[5], 10, number_format($totals['late']), 1, 0, 'C', true);
                $pdf->Cell($w[6], 10, number_format($totals['leave']), 1, 0, 'C', true);
                $pdf->Cell($w[7], 10, number_format($totals['absent']), 1, 0, 'C', true);
                $pdf->Cell($w[8], 10, number_format($totals['work_days'], 1), 1, 0, 'C', true);
                $pdf->Cell($w[9], 10, number_format($totals['amount'], 2), 1, 1, 'R', true);
            } else { $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C'); }
            $pdf->Output('F', $file_path); 

        } elseif ($file_format === 'excel') {
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            fprintf($output, "\xEF\xBB\xBF");
            fputcsv($output, [$report_title]);
            fputcsv($output, []);
            fputcsv($output, ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)']);
            $i = 1;
            if (!empty($calculated_data)) {
                foreach ($calculated_data as $d) {
                    fputcsv($output, [ $i++, $d['id'], $d['full_name'], $d['full'], $d['half'], $d['late'], $d['leave'], $d['absent'], number_format($d['work_days'], 1), number_format($d['amount'], 2) ]);
                }
                fputcsv($output, []);
                fputcsv($output, [ '', '', 'ยอดรวมทั้งหมด', number_format($totals['full']), number_format($totals['half']), number_format($totals['late']), number_format($totals['leave']), number_format($totals['absent']), number_format($totals['work_days'], 1), number_format($totals['amount'], 2) ]);
            }
            fclose($output);
        }

        $files_to_attach[] = ['path' => $file_path, 'name' => basename($file_path)];
    } // End foreach

    // 5. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom('miyxrx@gmail.com', 'รายงานระบบเงินเดือน');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = $report_title;
    $mail->Body    = "รายงานเงินเดือนที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานเงินเดือนได้แนบมากับอีเมลนี้แล้ว";

    if (empty($files_to_attach)) throw new Exception("ไม่สามารถสร้างไฟล์แนบได้");
    
    foreach ($files_to_attach as $file) {
        $mail->addAttachment($file['path'], $file['name']); 
    }

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    error_log("Email sending failed: " . $e->getMessage()); 
    $safe_message = preg_replace('/\[SMTP\] Connected to:.*Password:\s*\[[^\s]+\]/', '[SMTP] Connected. Password: [HIDDEN]', $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาดในการส่ง: " . $safe_message]);
} finally {
    // 6. ลบไฟล์ชั่วคราวทั้งหมด
    if (isset($files_to_attach)) {
        foreach ($files_to_attach as $file) {
            if (file_exists($file['path'])) unlink($file['path']);
        }
    }
}
?>