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

$tempFiles = []; // Array to keep track of all temporary files for cleanup

try {
    // 1. รับค่าจาก POST ให้ถูกต้องตามที่หน้าเว็บส่งมา
    $dailyChartImg   = $_POST['dailyChartImg']   ?? null;
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
    $daily_filter_year   = (int)($_POST['daily_year'] ?? date('Y'));
    $daily_filter_month  = (int)($_POST['daily_month'] ?? date('m'));
    $monthly_filter_year = (int)($_POST['monthly_year'] ?? date('Y'));
    $recipient_email = trim($_POST['email'] ?? '');

    if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("ข้อมูลกราฟหรืออีเมลผู้รับไม่ถูกต้อง");
    }

    // 2. ฟังก์ชันช่วยสร้างไฟล์รูปภาพชั่วคราว
    function saveBase64Image($base64, $prefix) {
        $temp_dir = sys_get_temp_dir();
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $file = $temp_dir . DIRECTORY_SEPARATOR . $prefix . '_' . uniqid() . '.png';
        if (file_put_contents($file, $data) === false) {
             throw new Exception("ไม่สามารถบันทึกไฟล์รูปภาพชั่วคราวได้");
        }
        return $file;
    }
    
    $dailyTempFile   = saveBase64Image($dailyChartImg, 'daily_sales');
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly_sales');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly_sales');
    $tempFiles = [$dailyTempFile, $monthlyTempFile, $yearlyTempFile];
    
    // 3. สร้าง PDF พร้อม Layout ที่ปรับปรุงแล้ว
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddFont('THSarabunNew','','THSarabunNew.php');
    $pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
    
    // --- หน้า 1: กราฟรายวัน และ รายเดือน ---
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',20);
    $pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย'),0,1,'C');
    
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','ยอดขายรายวัน (เดือน '.thai_month($daily_filter_month).' ปี พ.ศ. '.($daily_filter_year+543).')'),0,1,'L');
    $pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
    $pdf->Ln(105);
    
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','ยอดขายรายเดือน (ปี พ.ศ. '.($monthly_filter_year+543).')'),0,1,'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);

    // --- หน้า 2: กราฟรายปี ---
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',20);
    $pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย (ต่อ)'),0,1,'C');
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดขายรายปี'),0,1,'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    $pdf_file_path = sys_get_temp_dir() . '/sales_report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);
    $tempFiles[] = $pdf_file_path;

    // 4. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com';
    $mail->Password   = 'ivjo hwqy kraq sgwe';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom($mail->Username, 'ระบบรายงานยอดขาย'); 
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟสรุปยอดขาย';
    $mail->Body    = "สวัสดีครับ <br><br>รายงานกราฟสรุปยอดขายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานกราฟสรุปยอดขายได้แนบมากับอีเมลนี้แล้ว";

    $mail->addAttachment($pdf_file_path, 'sales_graph_report_'.date('Y-m-d').'.pdf'); 

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception("Mailer Error: " . $mail->ErrorInfo); 
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // 5. ลบไฟล์ชั่วคราวทั้งหมด
    foreach($tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
?>

