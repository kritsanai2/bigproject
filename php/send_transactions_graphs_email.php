<?php
// send_transactions_graphs_email.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- (เพิ่มใหม่) สร้าง Class เพื่อจัดการ Header/Footer ---
class PDF_Report extends FPDF {
    // Page footer
    function Footer() {
        // ไปที่ตำแหน่ง 1.5 ซม. จากด้านล่าง
        $this->SetY(-15);
        $this->SetFont('THSarabunNew','',10);
        
        // พิมพ์วันที่สร้างรายงานชิดซ้าย
        $this->Cell(0, 10, iconv('UTF-8','TIS-620', 'สร้างเมื่อ: ' . date('d/m/') . (date('Y')+543)), 0, 0, 'L');
        
        // พิมพ์เลขหน้าชิดขวา
        $this->Cell(0, 10, iconv('UTF-8','TIS-620', 'หน้า ').$this->PageNo().'/{nb}', 0, 0, 'R');
    }
}

function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$tempFiles = []; // Array to keep track of temp files

try {
    // --- รับค่าจาก POST ---
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

    function saveBase64Image($base64, $prefix) {
        $temp_dir = sys_get_temp_dir();
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
        if (file_put_contents($file, $data) === false) {
             throw new Exception("ไม่สามารถบันทึกไฟล์รูปภาพชั่วคราวได้");
        }
        return $file;
    }

    $dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
    $monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
    $yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');
    $tempFiles = [$dailyTempFile, $monthlyTempFile, $yearlyTempFile];
    
    // --- สร้าง PDF (แก้ไข) ---
    $pdf = new PDF_Report('P', 'mm', 'A4'); // ใช้ Class ที่เราสร้างขึ้นใหม่
    $pdf->AliasNbPages(); // เปิดใช้งานการนับจำนวนหน้าทั้งหมด
    $pdf->AddFont('THSarabunNew','','THSarabunNew.php');
    $pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
    
    // หน้า 1
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',20);
    $pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดรายรับ-รายจ่าย'),0,1,'C');
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายวัน (เดือน '.thai_month($daily_filter_month).' ปี พ.ศ. '.($daily_filter_year+543).')'),0,1,'L');
    $pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
    $pdf->Ln(105);
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายเดือน (ปี พ.ศ. '.($monthly_filter_year+543).')'),0,1,'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);

    // หน้า 2
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew','B',20);
    $pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดรายรับ-รายจ่าย (ต่อ)'),0,1,'C');
    $pdf->SetFont('THSarabunNew','B',16);
    $pdf->Cell(0,10,iconv('UTF-8','TIS-620','สรุปรายปี'),0,1,'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    $pdf_file_path = sys_get_temp_dir() . '/transactions_report_' . uniqid() . '.pdf';
    $pdf->Output('F', $pdf_file_path);
    $tempFiles[] = $pdf_file_path;
    
    // --- ส่งอีเมล ---
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;

    $mail->setFrom($mail->Username, 'ระบบรายงานบัญชี');
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = 'รายงานกราฟรายรับ-รายจ่าย';
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
    foreach ($tempFiles as $file) {
        if (!empty($file) && file_exists($file)) {
            unlink($file);
        }
    }
}
?>