<?php
// เปิดการแสดงข้อผิดพลาดทั้งหมดเพื่อการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่า Header สำหรับการตอบกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// --- 1. เรียกใช้ Dependencies ---
// ตรวจสอบให้แน่ใจว่าคุณได้รัน `composer require phpmailer/phpmailer` และ `composer require setasign/fpdf` แล้ว
require '../vendor/autoload.php';

// **สำคัญ:** กำหนด Path ไปยังโฟลเดอร์ที่เก็บไฟล์ฟอนต์สำหรับ FPDF
// คุณต้องสร้างโฟลเดอร์ 'fonts' และนำไฟล์ฟอนต์ TH Sarabun ที่แปลงแล้วไปใส่ไว้
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month($m) {
    $months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    return $months[(int)$m] ?? '';
}

function saveBase64Image($base64, $prefix) {
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $file = tempnam(sys_get_temp_dir(), $prefix); // สร้างไฟล์ชั่วคราวที่ปลอดภัย
    file_put_contents($file, $data);
    return $file;
}

// ประกาศตัวแปรไฟล์ชั่วคราวไว้นอก try block
$dailyTempFile = $monthlyTempFile = $yearlyTempFile = $pdf_file_path = null;

try {
    // --- 2. รับและตรวจสอบข้อมูลจาก POST ---
    $dailyChartImg   = $_POST['dailyChartImg']   ?? null;
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
    $selected_year   = (int)($_POST['year'] ?? date('Y'));
    $selected_month  = (int)($_POST['month'] ?? date('m'));
    $recipient_email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg || !$recipient_email) {
        throw new Exception("ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง");
    }

    // --- 3. สร้างไฟล์รูปภาพชั่วคราว ---
    $dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');

    // --- 4. สร้างเอกสาร PDF ---
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
    $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
    
    // -- หน้า 1: กราฟรายวัน --
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 18);
    $pdf->Cell(0, 12, iconv('UTF-8', 'TIS-620', 'รายงานกราฟสรุปยอดรายรับ-รายจ่าย'), 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'สรุปรายวัน (เดือน ' . thai_month($selected_month) . ' ปี พ.ศ. ' . ($selected_year + 543) . ')'), 0, 1, 'L');
    $pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190); // (x, y, width)
    
    // -- หน้า 2: กราฟรายเดือนและรายปี --
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'สรุปรายเดือน (ปี พ.ศ. ' . ($selected_year + 543) . ')'), 0, 1, 'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
    $pdf->SetY($pdf->GetY() + 115); // เว้นระยะห่าง
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'สรุปรายปี'), 0, 1, 'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    $pdf_file_path = sys_get_temp_dir() . '/report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);

    // --- 5. ตั้งค่าและส่งอีเมล ---
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // -- การตั้งค่า Server (SMTP) --
    // **สำคัญ: กรุณากรอกข้อมูล SMTP ของคุณที่นี่**
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';           // เช่น smtp.gmail.com สำหรับ Gmail
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; // ใส่อีเมลของคุณ
    $mail->Password   = 'ivjo hwqy kraq sgwe';    // ใส่ App Password ของคุณ
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;
    
    // -- ผู้ส่งและผู้รับ --
    $mail->setFrom($mail->Username, 'ระบบรายงานอัตโนมัติ'); // ใช้อีเมลเดียวกับ Username
    $mail->addAddress($recipient_email);
    
    // -- เนื้อหาอีเมล --
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟการเงิน ประจำเดือน ' . thai_month($selected_month) . ' ' . ($selected_year + 543);
    $mail->Body    = "สวัสดีครับ <br><br>นี่คือรายงานกราฟสรุปยอดรายรับ-รายจ่ายตามที่คุณร้องขอ<br>ไฟล์ PDF ถูกแนบมาในอีเมลนี้แล้ว<br><br>ขอแสดงความนับถือ,<br>ระบบรายงาน";
    $mail->AltBody = "รายงานกราฟสรุปยอดรายรับ-รายจ่ายได้แนบมากับอีเมลนี้แล้ว";

    // -- แนบไฟล์ --
    $mail->addAttachment($pdf_file_path, 'transactions_graph_report.pdf'); 

    // -- ส่งอีเมล --
    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาด ให้ส่ง JSON กลับไปพร้อมข้อความ
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // --- 6. ลบไฟล์ชั่วคราวทั้งหมดหลังทำงานเสร็จ ---
    if (isset($dailyTempFile) && file_exists($dailyTempFile)) unlink($dailyTempFile);
    if (isset($monthlyTempFile) && file_exists($monthlyTempFile)) unlink($monthlyTempFile);
    if (isset($yearlyTempFile) && file_exists($yearlyTempFile)) unlink($yearlyTempFile);
    if (!empty($pdf_file_path) && file_exists($pdf_file_path)) unlink($pdf_file_path);
}
?>
