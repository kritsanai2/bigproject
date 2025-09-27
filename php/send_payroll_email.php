<?php
// ตั้งค่า Header ให้เป็น JSON ทันที
header('Content-Type: application/json');

// Path สำหรับ Library
require __DIR__ . '/../vendor/autoload.php';
require_once 'db.php'; 

// Path PHPMailer (ตรวจสอบอีกครั้ง)
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'; 
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// Path FPDF
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// use FPDF\FPDF; // Remove this line, use global FPDF class

// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;

// ฟังก์ชันช่วย
function thai_month_name_email($month_year) {
    $parts = explode('-', $month_year);
    if (count($parts) !== 2) return $month_year;
    $y = (int)$parts[0];
    $m = (int)$parts[1];
    $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    return $months[$m - 1] . ' ' . ($y + 543);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method.");
    
    // 1. รับค่าจาก POST
    $recipient_email = trim($_POST['email'] ?? '');
    // *** จุดแก้ไข 1: รับค่า file_formats เป็น Array ***
    $selected_formats = $_POST['file_formats'] ?? []; 
    $selected_month = $_POST['month'] ?? date('Y-m');
    $report_title = 'รายงานเงินเดือนพนักงาน ประจำเดือน ' . thai_month_name_email($selected_month);

    if (!is_array($selected_formats) || empty($selected_formats)) {
        throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");

    // 2. ดึงข้อมูลและคำนวณเงินเดือน
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

    // 3. เตรียมไฟล์แนบชั่วคราว
    $temp_dir = sys_get_temp_dir();
    $files_to_attach = []; // Array สำหรับเก็บ Path ของไฟล์ทั้งหมด
    $totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);
    // คำนวณยอดรวมทั้งหมดล่วงหน้า
    foreach ($calculated_data as $d) {
        foreach(['full','half','late','leave','absent','work_days','amount'] as $key) { $totals[$key] += $d[$key]; }
    }


    // 4. *** จุดแก้ไข 2: วนลูปสร้างไฟล์ตามรูปแบบที่เลือก ***
    foreach ($selected_formats as $file_format) {
        $extension = $file_format === 'pdf' ? '.pdf' : '.csv';
        $attachment_filename = "payroll_report_" . $selected_month . ($file_format === 'pdf' ? '_pdf' : '_excel') . $extension;
        $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 

        if ($file_format === 'pdf') {
            // --- Logic สร้าง PDF ---
            $pdf = new FPDF('L');
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();

            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');

            $pdf->SetFont('THSarabunNew', 'B', 10);
            $pdf->SetFillColor(230, 230, 230);
            $header = ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)'];
            $w = [15, 20, 55, 20, 20, 20, 20, 20, 30, 30]; 

            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 8, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();

            $pdf->SetFont('THSarabunNew', '', 10);
            $i = 1;
            if (!empty($calculated_data)) {
                foreach ($calculated_data as $d) {
                    $pdf->Cell($w[0], 7, $i++, 1, 0, 'C');
                    $pdf->Cell($w[1], 7, iconv('UTF-8', 'TIS-620', $d['id']), 1, 0, 'C');
                    $pdf->Cell($w[2], 7, iconv('UTF-8', 'TIS-620', $d['full_name']), 1, 0, 'L');
                    $pdf->Cell($w[3], 7, $d['full'], 1, 0, 'C');
                    $pdf->Cell($w[4], 7, $d['half'], 1, 0, 'C');
                    $pdf->Cell($w[5], 7, $d['late'], 1, 0, 'C');
                    $pdf->Cell($w[6], 7, $d['leave'], 1, 0, 'C');
                    $pdf->Cell($w[7], 7, $d['absent'], 1, 0, 'C');
                    $pdf->Cell($w[8], 7, number_format($d['work_days'], 1), 1, 0, 'C');
                    $pdf->Cell($w[9], 7, number_format($d['amount'], 2), 1, 1, 'R');
                }
                $pdf->SetFont('THSarabunNew', 'B', 10);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell($w[0]+$w[1]+$w[2], 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
                $pdf->Cell($w[3], 8, number_format($totals['full']), 1, 0, 'C', true);
                $pdf->Cell($w[4], 8, number_format($totals['half']), 1, 0, 'C', true);
                $pdf->Cell($w[5], 8, number_format($totals['late']), 1, 0, 'C', true);
                $pdf->Cell($w[6], 8, number_format($totals['leave']), 1, 0, 'C', true);
                $pdf->Cell($w[7], 8, number_format($totals['absent']), 1, 0, 'C', true);
                $pdf->Cell($w[8], 8, number_format($totals['work_days'], 1), 1, 0, 'C', true);
                $pdf->Cell($w[9], 8, number_format($totals['amount'], 2), 1, 1, 'R', true);
            } else { $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลเงินเดือนในเดือนที่เลือก'), 1, 1, 'C'); }
            ob_start();
            $pdf->Output('F', $file_path); 
            ob_end_clean();

        } elseif ($file_format === 'excel') {
            // --- Logic สร้าง CSV ---
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            
            fprintf($output, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($output, ['รายงานเงินเดือนพนักงาน ประจำเดือน ' . thai_month_name_email($selected_month)]);
            fputcsv($output, []); 
            fputcsv($output, ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)']);
            
            $i = 1;
            if (!empty($calculated_data)) {
                foreach ($calculated_data as $d) {
                    fputcsv($output, [
                        $i++, $d['id'], $d['full_name'], $d['full'], $d['half'], $d['late'], 
                        $d['leave'], $d['absent'], number_format($d['work_days'], 1), number_format($d['amount'], 2)
                    ]);
                }
                fputcsv($output, ['ยอดรวมทั้งหมด', '', '',
                    number_format($totals['full']), number_format($totals['half']), number_format($totals['late']), 
                    number_format($totals['leave']), number_format($totals['absent']), 
                    number_format($totals['work_days'], 1), number_format($totals['amount'], 2)
                ]);
            }
            fclose($output);
        }

        // ** เก็บ Path ของไฟล์ที่สร้างเสร็จแล้วเพื่อแนบ **
        $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
    } // End foreach ($selected_formats)


    // 5. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // ... (SMTP Config เหมือนเดิม) ...
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

    // ** จุดแก้ไข 3: แนบไฟล์ทั้งหมดใน Array **
    if (empty($files_to_attach)) {
        throw new Exception("ไม่สามารถสร้างไฟล์แนบได้ (ข้อมูลว่างเปล่า)");
    }
    foreach ($files_to_attach as $file) {
        if (file_exists($file['path'])) {
            $mail->addAttachment($file['path'], $file['name']); 
        } else {
            throw new Exception("ไม่พบไฟล์แนบที่สร้างขึ้น: " . htmlspecialchars($file['name']));
        }
    }

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    // ... (Error Handling เหมือนเดิม) ...
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