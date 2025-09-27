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
// No 'use FPDF\FPDF;' because FPDF does not use namespaces

// ฟังก์ชันช่วย
function thai_month_name($month) {
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}
function thai_month_name_excel($month) {
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
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
    $selected_formats = $_POST['file_formats'] ?? []; 
    
    // ดึงค่าตัวกรอง
    $filter_month = isset($_POST['month']) ? intval($_POST['month']) : 0; 
    $current_year = intval(date('Y'));
    $filter_year = isset($_POST['year']) && intval($_POST['year']) > 0 ? intval($_POST['year']) : $current_year;
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    if (!is_array($selected_formats) || empty($selected_formats)) throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");

    // 2. สร้างหัวข้อรายงาน
    $report_period = "";
    if ($filter_month > 0) $report_period .= "ประจำเดือน " . thai_month_name($filter_month);
    if ($filter_year > 0) $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
    $report_title = 'รายงานการขายรายสินค้า ' . $report_period;


    // 3. ดึงข้อมูลและเตรียมฐานข้อมูล
    require_once "db.php"; 
    $temp_dir = sys_get_temp_dir();
    
    $sql_detailed_sales = "
        SELECT 
            o.order_date, c.full_name AS customer_name, p.product_name, p.unit,
            od.quantity, od.price, (od.quantity * od.price) AS item_total
        FROM order_details od
        JOIN orders o ON od.order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN products p ON od.product_id = p.product_id
    ";

    $params = []; $types = ""; $where_clauses = [];
    if ($filter_month > 0) { $where_clauses[] = "MONTH(o.order_date) = ?"; $params[] = $filter_month; $types .= "i"; }
    if ($filter_year > 0) { $where_clauses[] = "YEAR(o.order_date) = ?"; $params[] = $filter_year; $types .= "i"; }

    if (!empty($where_clauses)) { $sql_detailed_sales .= " WHERE " . implode(" AND ", $where_clauses); }
    $sql_detailed_sales .= " ORDER BY o.order_date DESC, o.order_id DESC";

    $stmt = $conn->prepare($sql_detailed_sales);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result_sales = $stmt->get_result();

    $data = []; $grand_total = 0;
    if ($result_sales) {
        while ($row = $result_sales->fetch_assoc()) {
            $data[] = $row;
            $grand_total += $row['item_total'];
        }
    }


    // 4. วนลูปสร้างไฟล์ตาม Format ที่เลือก
    foreach ($selected_formats as $file_format) {
        $file_format = strtolower($file_format); 
        $time_stamp = date('Ymd_His');
        
        if ($file_format === 'pdf') {
            $attachment_filename = "sales_report_{$time_stamp}_pdf.pdf";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง PDF ---
            $pdf = new FPDF('P');
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();

            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('THSarabunNew', 'B', 10);
            $pdf->SetFillColor(230, 230, 230);
            $header = ['ลำดับ', 'วันที่', 'ลูกค้า', 'สินค้า', 'จำนวน', 'ราคา/หน่วย', 'ราคารวม (บาท)'];
            $w = [15, 25, 45, 45, 20, 20, 20]; 

            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 8, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();

            $pdf->SetFont('THSarabunNew', '', 10);
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    $pdf->Cell($w[0], 7, $i++, 1, 0, 'C');
                    $date_th = date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543);
                    $pdf->Cell($w[1], 7, $date_th, 1, 0, 'C');
                    $pdf->Cell($w[2], 7, iconv('UTF-8', 'TIS-620', $row['customer_name']), 1, 0, 'L');
                    $pdf->Cell($w[3], 7, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
                    $pdf->Cell($w[4], 7, number_format($row['quantity']) . ' ' . iconv('UTF-8', 'TIS-620', $row['unit']), 1, 0, 'R');
                    $pdf->Cell($w[5], 7, number_format($row['price'], 2), 1, 0, 'R');
                    $pdf->Cell($w[6], 7, number_format($row['item_total'], 2), 1, 1, 'R');
                }
                $pdf->SetFont('THSarabunNew', 'B', 10);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(array_sum($w) - $w[6], 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
                $pdf->Cell($w[6], 8, number_format($grand_total, 2), 1, 1, 'R', true);
            } else { $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลการขายในเดือนที่เลือก'), 1, 1, 'C'); }

            ob_start();
            $pdf->Output('F', $file_path); 
            ob_end_clean();
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
            
        } elseif ($file_format === 'excel') {
            $attachment_filename = "sales_report_{$time_stamp}_excel.csv";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            // --- Logic สร้าง CSV ---
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            
            fprintf($output, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($output, [$report_title]);
            fputcsv($output, []); 
            fputcsv($output, ['ลำดับ', 'วันที่', 'ชื่อลูกค้า', 'ชื่อสินค้า', 'จำนวน (หน่วย)', 'ราคา/หน่วย', 'ราคารวม (บาท)']);
            
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    $date_th = date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543);
                    fputcsv($output, [
                        $i++, $date_th, $row['customer_name'], $row['product_name'], 
                        number_format($row['quantity']) . ' ' . $row['unit'],
                        number_format($row['price'], 2), number_format($row['item_total'], 2)
                    ]);
                }
                fputcsv($output, []);
                fputcsv($output, [
                    'ยอดรวมทั้งหมด', '', '', '', '', '', number_format($grand_total, 2)
                ]);
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
    $mail->setFrom('miyxrx@gmail.com', 'รายงานระบบขาย');
    $mail->addAddress($recipient_email);
    
    $mail->isHTML(true);
    $mail->Subject = $report_title;
    $mail->Body    = "รายงานการขายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานการขายได้แนบมากับอีเมลนี้แล้ว";

    // 7. แนบไฟล์ทั้งหมด
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