<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// 1. เรียกใช้ Library
require '../vendor/autoload.php';
require_once 'db.php'; 

// ตรวจสอบและแก้ไข Path PHPMailer (ตามการตั้งค่าของคุณ)
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'; 
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; // FPDF

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// ไม่ต้องใช้ FPDF namespace

// กำหนด Path FPDF สำหรับสร้าง PDF
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

try {
    // 2. รับค่าจาก POST
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg = $_POST['yearlyChartImg'] ?? null;
    $selected_year = $_POST['year'] ?? date('Y');
    $recipient_email = trim($_POST['email'] ?? '');

    if (!$monthlyChartImg || !$yearlyChartImg || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("ข้อมูลกราฟหรืออีเมลผู้รับไม่ถูกต้อง");
    }

    // 3. เตรียมไฟล์ชั่วคราวสำหรับรูปภาพ
    $monthlyData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $monthlyChartImg));
    $yearlyData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $yearlyChartImg));

    $temp_dir = sys_get_temp_dir();
    $monthlyTempFile = $temp_dir . '/monthly_' . uniqid() . '.png';
    $yearlyTempFile = $temp_dir . '/yearly_' . uniqid() . '.png';
    $pdf_file_path = $temp_dir . '/graph_report_' . uniqid() . '.pdf';

    file_put_contents($monthlyTempFile, $monthlyData);
    file_put_contents($yearlyTempFile, $yearlyData);

    // 4. สร้าง PDF เป็นไฟล์ชั่วคราว
    $pdf = new FPDF();
    $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
    $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 20);
    $pdf->Cell(0, 15, iconv('UTF-8', 'TIS-620', 'รายงานกราฟสรุปสต็อกสินค้า'), 0, 1, 'C');
    
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'สรุปยอดรับเข้า-จ่ายออกรายเดือน (ปี พ.ศ. ' . ($selected_year + 543) . ')'), 0, 1, 'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
    $pdf->SetY($pdf->GetY() + 100); 

    $pdf->SetFont('THSarabunNew', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'สรุปยอดรับเข้า-จ่ายออกรายปี'), 0, 1, 'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    ob_start();
    $pdf->Output('F', $pdf_file_path); // บันทึกเป็นไฟล์
    ob_end_clean();

    // 5. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // ** การตั้งค่า SMTP (ตามการแก้ไขล่าสุดของคุณ) **
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe'; // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom('miyxrx@gmail.com', 'รายงานระบบสต็อก');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟสต็อกสินค้า ปี พ.ศ. ' . ($selected_year + 543);
    $mail->Body    = "รายงานกราฟสรุปสต็อกสินค้าที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานกราฟสต็อกได้แนบมากับอีเมลนี้แล้ว";

    // แนบไฟล์ PDF ที่สร้างขึ้น
    $mail->addAttachment($pdf_file_path, 'stock_graph_report_' . $selected_year . '.pdf'); 

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานกราฟทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    error_log("Email sending failed: " . $e->getMessage()); 
    $safe_message = preg_replace('/\[SMTP\] Connected to:.*Password:\s*\[[^\s]+\]/', '[SMTP] Connected. Password: [HIDDEN]', $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาดในการส่ง: " . $safe_message]);
} finally {
    // 6. ลบไฟล์ชั่วคราวทั้งหมด
    if (isset($monthlyTempFile) && file_exists($monthlyTempFile)) unlink($monthlyTempFile);
    if (isset($yearlyTempFile) && file_exists($yearlyTempFile)) unlink($yearlyTempFile);
    if (isset($pdf_file_path) && file_exists($pdf_file_path)) unlink($pdf_file_path);
}
?>