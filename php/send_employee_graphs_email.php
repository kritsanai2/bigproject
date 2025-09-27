<?php
// send_transactions_graphs_email.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$pdf_file_path = ''; // Initialize variable

try {
    // --- รับค่าจาก POST ---
    $dailyChartImg   = $_POST['dailyChartImg']   ?? null;
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
    $selected_year   = $_POST['year']            ?? date('Y');
    $selected_month  = $_POST['month']           ?? date('m');
    $recipient_email = trim($_POST['email'] ?? '');

    if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("ข้อมูลกราฟหรืออีเมลผู้รับไม่ถูกต้อง");
    }

    // --- สร้าง PDF เก็บในไฟล์ชั่วคราว ---
    // (โค้ดสร้าง PDF เหมือนในไฟล์ export_transactions_graphs.php)
    function saveBase64Image($base64, $prefix) {
        $temp_dir = sys_get_temp_dir();
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
        file_put_contents($file, $data);
        return $file;
    }

    $dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddFont('THSarabunNew','','THSarabunNew.php');
    $pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
    
    // Page 1
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',18);
    $pdf->Cell(0,12,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดรายรับ-รายจ่าย'),0,1,'C');
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายวัน (เดือน '.thai_month($selected_month).' ปี พ.ศ. '.($selected_year+543).')'),0,1,'L');
    $pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
    
    // Page 2
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายเดือน (ปี พ.ศ. '.($selected_year+543).')'),0,1,'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
    $pdf->SetY($pdf->GetY() + 110);
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายปี'),0,1,'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    $pdf_file_path = sys_get_temp_dir() . '/transactions_report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);

    // --- ตั้งค่า PHPMailer และส่งอีเมล ---
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; // ใส่อีเมลของคุณ
    $mail->Password   = 'ivjo hwqy kraq sgwe';    // ใส่ App Password ของคุณ
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom('miyxrx@gmail.com', 'ระบบรายงานการเงิน'); // ตั้งค่าผู้ส่ง
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟการเงิน ประจำเดือน ' . thai_month($selected_month) . ' ' . ($selected_year + 543);
    $mail->Body    = "สวัสดีครับ <br><br>รายงานกราฟสรุปยอดรายรับ-รายจ่ายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานกราฟสรุปยอดรายรับ-รายจ่ายได้แนบมากับอีเมลนี้แล้ว";

    $mail->addAttachment($pdf_file_path, 'transactions_graph_report.pdf'); 

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // --- ลบไฟล์ชั่วคราวทั้งหมด ---
    if (isset($dailyTempFile) && file_exists($dailyTempFile)) unlink($dailyTempFile);
    if (isset($monthlyTempFile) && file_exists($monthlyTempFile)) unlink($monthlyTempFile);
    if (isset($yearlyTempFile) && file_exists($yearlyTempFile)) unlink($yearlyTempFile);
    if (!empty($pdf_file_path) && file_exists($pdf_file_path)) unlink($pdf_file_path);
}
?>