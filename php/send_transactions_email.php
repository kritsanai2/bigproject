<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
// No use statement for FPDF; FPDF does not use namespaces

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// ใช้ try-catch เพื่อดักจับข้อผิดพลาดทั้งหมดและส่ง JSON response กลับ
$files_to_attach = []; 
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    // 1. รับค่าจาก POST
    $recipient_email = trim($_POST['email'] ?? '');
    $selected_formats = $_POST['file_formats'] ?? [strtolower(trim($_POST['file_format'] ?? 'pdf'))]; 
    
    // ดึงค่าตัวกรอง
    $type_filter = $_POST['type'] ?? '';
    $filter_month = isset($_POST['month']) ? intval($_POST['month']) : 0; 
    $current_year = intval(date('Y'));
    $filter_year = isset($_POST['year']) ? intval($_POST['year']) : $current_year;
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    if (!is_array($selected_formats) || empty($selected_formats)) throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");

    // 2. สร้างหัวข้อรายงาน
    $report_period = "";
    if ($filter_month > 0) $report_period .= "ประจำเดือน " . thai_month_name($filter_month);
    if ($filter_year > 0) $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
    if ($type_filter) $report_period .= " (ประเภท: " . thai_type($type_filter) . ")";

    $report_title = 'รายงานรายรับ-รายจ่าย' . $report_period;


    // 3. ดึงข้อมูลและเตรียมฐานข้อมูล (แก้ไข SQL)
    $temp_dir = sys_get_temp_dir();
    
    $sql = "SELECT t.*, od.product_id, p.product_name
            FROM transactions t
            LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
            LEFT JOIN products p ON od.product_id = p.product_id"; // ไม่ JOIN expense_categories

    $params = []; $types = ""; $where_clauses = [];
    if ($type_filter) { $where_clauses[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
    if ($filter_month > 0) { $where_clauses[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
    if ($filter_year > 0) { $where_clauses[] = "YEAR(t.transaction_date) = ?"; $params[] = $filter_year; $types .= "i"; }

    if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
    $sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = []; $total_income = 0; $total_expense = 0;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
        }
    }
    $balance = $total_income - $total_expense;


    // 4. วนลูปสร้างไฟล์ตาม Format ที่เลือก
    foreach ($selected_formats as $file_format) {
        $file_format = strtolower($file_format); 
        $time_stamp = date('Ymd_His');
        
        if ($file_format === 'pdf') {
            $attachment_filename = "transactions_report_{$time_stamp}_pdf.pdf";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง PDF ---
            $pdf = new FPDF('P');
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();

            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(230, 230, 230);
            $header = ['ลำดับ', 'รหัส', 'วันที่', 'ประเภท', 'จำนวนเงิน (บาท)', 'รายละเอียด'];
            $w = [15, 25, 25, 20, 30, 75]; 
            $grand_w = array_sum($w);

            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();

            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    // ใช้ $row['expense_type'] ที่มีอยู่ในตาราง transactions โดยตรง
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : (!empty($row['product_name']) ? "ขาย: " . $row['product_name'] : 'รายรับจากออเดอร์');
                    
                    $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
                    $pdf->Cell($w[1], 8, iconv('UTF-8', 'TIS-620', $row['transaction_id']), 1, 0, 'C');
                    $pdf->Cell($w[2], 8, date('d/m/Y', strtotime($row['transaction_date']) + 543), 1, 0, 'C');
                    $pdf->Cell($w[3], 8, iconv('UTF-8', 'TIS-620', thai_type($row['transaction_type'])), 1, 0, 'C');
                    $pdf->Cell($w[4], 8, number_format($row['amount'], 2), 1, 0, 'R');
                    $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $desc), 1, 1, 'L');
                }
                
                $pdf->SetFont('THSarabunNew', 'B', 12);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายรับ/รายจ่าย/คงเหลือ'), 1, 0, 'R', true);
                $pdf->Cell($w[4], 10, iconv('UTF-8', 'TIS-620', 'รับ: '.number_format($total_income, 2)), 1, 0, 'R', true);
                $pdf->Cell($w[5], 10, iconv('UTF-8', 'TIS-620', 'จ่าย: '.number_format($total_expense, 2) . ' / คงเหลือ: ' . number_format($balance, 2)), 1, 1, 'R', true);
                
            } else { $pdf->Cell($grand_w, 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C'); }

            ob_start();
            $pdf->Output('F', $file_path); 
            ob_end_clean();
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
            
        } elseif ($file_format === 'excel') {
            $attachment_filename = "transactions_report_{$time_stamp}_excel.csv";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง CSV ---
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            
            fprintf($output, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($output, [$report_title]);
            fputcsv($output, []); 
            fputcsv($output, ['ลำดับ', 'รหัส', 'วันที่', 'ประเภท', 'จำนวนเงิน (บาท)', 'รายละเอียด']);
            
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : (!empty($row['product_name']) ? "ขาย: " . $row['product_name'] : 'รายรับจากออเดอร์');
                    
                    fputcsv($output, [
                        $i++, 
                        $row['transaction_id'], 
                        date('d/m/Y', strtotime($row['transaction_date']) + 543),
                        thai_type($row['transaction_type']), 
                        number_format($row['amount'], 2), 
                        $desc
                    ]);
                }
                fputcsv($output, []);
                fputcsv($output, ['สรุปยอดรวม:', '', '', 'รายรับรวม', 'รายจ่ายรวม', 'ยอดคงเหลือ']);
                fputcsv($output, ['', '', '', number_format($total_income, 2), number_format($total_expense, 2), number_format($balance, 2)]);
            }
            fclose($output);
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
        } 
    } // End foreach ($selected_formats)
    
    // 5. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    // *** ตั้งค่า SMTP (ตามการตั้งค่าล่าสุด) ***
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe'; // App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465; 

    // 6. ตั้งค่าเนื้อหาและผู้รับ
    $mail->setFrom('miyxrx@gmail.com', 'รายงานระบบบัญชี');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = $report_title;
    $mail->Body    = "รายงานรายรับ-รายจ่ายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานรายรับ-รายจ่ายได้แนบมากับอีเมลนี้แล้ว";

    // 7. แนบไฟล์ทั้งหมด
    if (empty($files_to_attach)) {
        throw new Exception("ไม่พบไฟล์แนบที่สร้างขึ้น (ข้อมูลว่างเปล่า)");
    }
    foreach ($files_to_attach as $file) {
        if (file_exists($file['path'])) {
            $mail->addAttachment($file['path'], $file['name']); 
        } else {
            throw new Exception("ไม่พบไฟล์แนบที่สร้างขึ้น: " . htmlspecialchars($file['name']));
        }
    }

    // 8. ส่งอีเมล
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
    // 10. ลบไฟล์ชั่วคราวทั้งหมด
    if (!empty($files_to_attach)) {
        foreach ($files_to_attach as $file) {
            if (file_exists($file['path'])) unlink($file['path']);
        }
    }
}
?>