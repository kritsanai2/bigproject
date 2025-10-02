<?php
// send_employee_graphs_email.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require '../vendor/autoload.php'; 
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use FPDF;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$tempFiles = []; // Array สำหรับเก็บ Path ของไฟล์ชั่วคราวทั้งหมด

try {
    // --- รับค่าจาก POST ---
    $monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
    $yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
    $selected_year   = $_POST['year']            ?? date('Y');
    $recipient_email = trim($_POST['email'] ?? '');

    if (!$monthlyChartImg || !$yearlyChartImg || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("ข้อมูลกราฟหรืออีเมลผู้รับไม่ถูกต้อง");
    }

    // --- ฟังก์ชันสำหรับบันทึกรูปภาพ base64 ---
    function saveBase64Image($base64, $prefix) {
        $temp_dir = sys_get_temp_dir();
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $file = $temp_dir . DIRECTORY_SEPARATOR . $prefix . '_' . uniqid() . '.png';
        if (file_put_contents($file, $data) === false) {
            throw new Exception("ไม่สามารถบันทึกไฟล์รูปภาพชั่วคราวได้");
        }
        return $file;
    }

    // สร้างไฟล์รูปภาพชั่วคราว
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');
    $tempFiles[] = $monthlyTempFile;
    $tempFiles[] = $yearlyTempFile;

    // --- สร้าง PDF ---
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddFont('THSarabunNew','','THSarabunNew.php');
    $pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
    
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',20);
    $pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดเงินเดือนพนักงาน'),0,1,'C');
    
    // กราฟรายเดือน
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดเงินเดือนรายเดือน (ปี พ.ศ. '.($selected_year+543).')'),0,1,'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
    $pdf->Ln(105); // เว้นที่ว่างหลังกราฟแรก
    
    // กราฟรายปี
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปยอดเงินเดือนรวมรายปี'),0,1,'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    // บันทึก PDF เป็นไฟล์ชั่วคราว
    $pdf_file_path = sys_get_temp_dir() . '/salary_report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);
    $tempFiles[] = $pdf_file_path;

    // --- ตั้งค่า PHPMailer และส่งอีเมล ---
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; // **อีเมลผู้ส่ง**
    $mail->Password   = 'ivjo hwqy kraq sgwe';   // **App Password**
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    // **แก้ไข: ใช้อีเมลเดียวกับ Username เพื่อความถูกต้อง**
    $mail->setFrom($mail->Username, 'ระบบรายงานเงินเดือน'); 
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟเงินเดือน ประจำปี ' . ($selected_year + 543);
    $mail->Body    = "สวัสดีครับ <br><br>รายงานกราฟสรุปยอดเงินเดือนที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานกราฟสรุปยอดเงินเดือนได้แนบมากับอีเมลนี้แล้ว";

    $mail->addAttachment($pdf_file_path, 'salary_graph_report_'.date('Y-m-d').'.pdf'); 

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception("Mailer Error: " . $mail->ErrorInfo); 
    }

} catch (Exception $e) {
    // ส่งข้อความ Error กลับเป็น JSON
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // --- ลบไฟล์ชั่วคราวทั้งหมด ---
    foreach($tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
?>