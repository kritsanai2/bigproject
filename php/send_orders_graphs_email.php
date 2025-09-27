<?php
// send_orders_graphs_email.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$pdf_file_path = ''; 

try {
    $dailyChartImg   = $_POST['dailyChartImg']   ?? null;
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
    $selected_year   = $_POST['year']            ?? date('Y');
    $selected_month  = $_POST['month']           ?? date('m');
    $recipient_email = trim($_POST['email'] ?? '');

    if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("ข้อมูลไม่ถูกต้อง");
    }

    function saveBase64Image($base64, $prefix) {
        $temp_dir = sys_get_temp_dir();
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
        file_put_contents($file, $data);
        return $file;
    }

    $dailyTempFile   = saveBase64Image($dailyChartImg, 'daily_order');
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly_order');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly_order');

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddFont('THSarabunNew','','THSarabunNew.php');
    $pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
    
    // (โค้ดสร้าง PDF เหมือนไฟล์ Export)
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',18);
    $pdf->Cell(0,12,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย'),0,1,'C');
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดขายรายวัน (เดือน '.thai_month($selected_month).' ปี พ.ศ. '.($selected_year+543).')'),0,1,'L');
    $pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดขายรายเดือน (ปี พ.ศ. '.($selected_year+543).')'),0,1,'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
    $pdf->SetY($pdf->GetY() + 110);
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดขายรายปี'),0,1,'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    $pdf_file_path = sys_get_temp_dir() . '/orders_report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; // **<-- ใส่อีเมลของคุณ**
    $mail->Password   = 'ivjo hwqy kraq sgwe';    // **<-- ใส่ App Password ของคุณ**
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom('your-sender-email@gmail.com', 'ระบบรายงานยอดขาย');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟยอดขาย ประจำเดือน ' . thai_month($selected_month) . ' ' . ($selected_year + 543);
    $mail->Body    = "สวัสดีครับ <br><br>รายงานกราฟสรุปยอดขายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานกราฟสรุปยอดขายได้แนบมากับอีเมลนี้แล้ว";

    $mail->addAttachment($pdf_file_path, 'orders_graph_report.pdf'); 

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    if (file_exists($dailyTempFile))   unlink($dailyTempFile);
    if (file_exists($monthlyTempFile)) unlink($monthlyTempFile);
    if (file_exists($yearlyTempFile))  unlink($yearlyTempFile);
    if (file_exists($pdf_file_path))   unlink($pdf_file_path);
}
?>