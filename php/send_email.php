<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก (เอาออกเมื่อใช้งานจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ตั้งค่า Header ให้เป็น JSON ทันทีเพื่อความแน่นอน
header('Content-Type: application/json');

// =========================================================
// !!! 1. แก้ไข PATH เหล่านี้ให้ถูกต้องกับโครงสร้างโปรเจกต์ของคุณ !!!
// (สมมติว่า 'vendor' อยู่ในระดับเดียวกับไฟล์ PHP หลักทั้งหมด)
// =========================================================
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php'; 
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// Path FPDF
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; 
require __DIR__ . '/../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// ไม่ต้องใช้ namespace สำหรับ FPDF

// ฟังก์ชันช่วย
function thai_type_text($type) { 
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก'; 
}

// ใช้ try-catch เพื่อดักจับข้อผิดพลาดทั้งหมดและส่ง JSON response กลับ
$files_to_attach = []; // ประกาศ Array นอก try/catch เพื่อใช้ใน finally
try {
    // 1. ตรวจสอบการร้องขอ (Request)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    // 2. ดึงค่าจาก POST
    $recipient_email = trim($_POST['email'] ?? '');
    $email_subject = trim($_POST['subject'] ?? 'รายงานสต็อก');

    // *** จุดแก้ไข 1: รับค่า file_formats เป็น Array ***
    $selected_formats = $_POST['file_formats'] ?? []; 
    
    // ดึงค่าตัวกรองทั้งหมดที่ส่งมาจาก JavaScript
    $type_filter = $_POST['type'] ?? '';
    $month_filter = intval($_POST['month'] ?? 0);
    $filter_year = intval($_POST['year'] ?? date('Y'));
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    }
    if (!is_array($selected_formats) || empty($selected_formats)) {
        throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");
    }

    // 3. เตรียมข้อมูลและเชื่อมต่อฐานข้อมูล
    require_once "db.php"; 
    $temp_dir = sys_get_temp_dir();
    
    // =========================================================
    // *** 3.1 ดึงข้อมูลจากฐานข้อมูล (ใช้ Prepared Statement) ***
    // =========================================================
    $sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit 
            FROM stock s JOIN products p ON s.product_id = p.product_id";
            
    $where_clauses = [];
    $params = [];
    $types = '';

    if ($type_filter) { $where_clauses[] = "s.stock_type = ?"; $params[] = $type_filter; $types .= 's'; }
    if ($month_filter > 0) { $where_clauses[] = "MONTH(s.stock_date) = ?"; $params[] = (int)$month_filter; $types .= 'i'; }
    if ($filter_year > 0) { $where_clauses[] = "YEAR(s.stock_date) = ?"; $params[] = (int)$filter_year; $types .= 'i'; }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }
    $sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // =========================================================
    // *** จุดแก้ไข 2: วนลูปสร้างไฟล์ตาม Format ที่เลือก (PDF/Excel) ***
    // =========================================================
    
    // ต้องเก็บข้อมูลไว้ใน Array เพื่อใช้สร้างไฟล์หลายครั้ง
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    foreach ($selected_formats as $file_format) {
        $file_format = strtolower($file_format); // ตรวจสอบความถูกต้อง
        $time_stamp = date('Ymd_His');
        
        if ($file_format === 'pdf') {
            $attachment_filename = "stock_report_{$time_stamp}_pdf.pdf";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง PDF ---
            $pdf = new FPDF();
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();

            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'รายงานสต็อกสินค้า'), 0, 1, 'C');

            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(20, 10, iconv('UTF-8', 'TIS-620', 'ลำดับ'), 1, 0, 'C', true);
            $pdf->Cell(30, 10, iconv('UTF-8', 'TIS-620', 'วันที่'), 1, 0, 'C', true);
            $pdf->Cell(70, 10, iconv('UTF-8', 'TIS-620', 'สินค้า'), 1, 0, 'C', true);
            $pdf->Cell(25, 10, iconv('UTF-8', 'TIS-620', 'ประเภท'), 1, 0, 'C', true);
            $pdf->Cell(25, 10, iconv('UTF-8', 'TIS-620', 'จำนวน'), 1, 0, 'C', true);
            $pdf->Cell(20, 10, iconv('UTF-8', 'TIS-620', 'หน่วย'), 1, 1, 'C', true);

            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            foreach ($data as $row) {
                $pdf->Cell(20, 8, $i++, 1, 0, 'C');
                $date_th = date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543);
                $pdf->Cell(30, 8, $date_th, 1, 0, 'C');
                $pdf->Cell(70, 8, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
                $pdf->Cell(25, 8, iconv('UTF-8', 'TIS-620', thai_type_text($row['stock_type'])), 1, 0, 'C');
                $pdf->Cell(25, 8, number_format($row['quantity']), 1, 0, 'R');
                $pdf->Cell(20, 8, iconv('UTF-8', 'TIS-620', $row['unit']), 1, 1, 'C');
            }
            if (empty($data)) { 
                $pdf->Cell(190, 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูล'), 1, 1, 'C'); 
            }

            ob_start();
            $pdf->Output('F', $file_path); // บันทึกเป็นไฟล์ (File)
            ob_end_clean();
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
            
        } elseif ($file_format === 'excel') {
            $attachment_filename = "stock_report_{$time_stamp}_excel.csv";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง CSV ---
            $output = fopen($file_path, 'w');
            if ($output === false) {
                throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            }
            
            fprintf($output, "\xEF\xBB\xBF"); // UTF-8 BOM
            
            $header_row = ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย'];
            fputcsv($output, $header_row);
            
            $i = 1;
            foreach ($data as $row) {
                $date_th = date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543);
                $type_text = thai_type_text($row['stock_type']);
                
                fputcsv($output, [
                    $i++, $date_th, $row['product_name'], $type_text, $row['quantity'], $row['unit']
                ]);
            }
            fclose($output);
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
        } 
    } // End foreach ($selected_formats)
    
    // 4. การตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // *** ตั้งค่า SMTP ***
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe'; // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465; 

    // 5. ตั้งค่าเนื้อหาและผู้รับ
    $mail->setFrom('miyxrx@gmail.com', 'รายงานคลังสินค้า');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = $email_subject;
    $mail->Body    = "รายงานสต็อกที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานสต็อกได้แนบมากับอีเมลนี้แล้ว";

    // *** จุดแก้ไข 3: แนบไฟล์ทั้งหมด ***
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

    // 7. ส่งอีเมล
    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception($mail->ErrorInfo); 
    }

} catch (Exception $e) {
    // 9. ตอบกลับข้อผิดพลาด (Error Response)
    error_log("Email sending failed: " . $e->getMessage()); 
    
    $safe_message = preg_replace('/\[SMTP\] Connected to:.*Password:\s*\[[^\s]+\]/', '[SMTP] Connected. Password: [HIDDEN]', $e->getMessage());
    
    $message = "เกิดข้อผิดพลาดในการส่ง: " . $safe_message;
    echo json_encode(['status' => 'error', 'message' => $message]);
    
} finally {
    // *** จุดแก้ไข 4: ลบไฟล์ชั่วคราวทั้งหมด ***
    if (!empty($files_to_attach)) {
        foreach ($files_to_attach as $file) {
            if (file_exists($file['path'])) unlink($file['path']);
        }
    }
}
?>