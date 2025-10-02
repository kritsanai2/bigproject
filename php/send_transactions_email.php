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
// require __DIR__ . '/../vendor/setasign/fpdf/fpdf.php'; // ไม่จำเป็นต้อง require fpdf.php อีกเพราะ autoload จัดการให้แล้ว

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

$files_to_attach = []; 
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }
    
    // 1. รับค่าจาก POST
    $recipient_email = trim($_POST['email'] ?? '');
    $selected_formats = $_POST['file_formats'] ?? [];
    $type_filter = $_POST['type'] ?? '';
    $filter_month = isset($_POST['month']) ? intval($_POST['month']) : 0; 
    $filter_year = isset($_POST['year']) ? intval($_POST['year']) : 0;
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    if (!is_array($selected_formats) || empty($selected_formats)) throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");

    // 2. สร้างหัวข้อรายงาน
    $report_title = "รายงาน";
    if ($type_filter == 'income') $report_title .= "รายรับ";
    elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
    else $report_title .= "รายรับ-รายจ่าย";
    if ($filter_month > 0) $report_title .= " เดือน " . thai_month_name($filter_month);
    if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
    if ($filter_month == 0 && $filter_year == 0) $report_title .= "ทั้งหมด";

    // 3. ดึงข้อมูลรายการธุรกรรม
    $sql = "SELECT t.*, od.product_id, p.product_name, o.order_id
            FROM transactions t
            LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
            LEFT JOIN products p ON od.product_id = p.product_id
            LEFT JOIN orders o ON od.order_id = o.order_id";

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
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // --- (เพิ่มใหม่) ดึงยอดรวมเงินเดือนจากตาราง `salary` ---
    $total_salary_expense = 0;
    if ($type_filter === '' || $type_filter === 'expense') {
        $salary_sql = "SELECT SUM(total_amount) as total FROM salary";
        $salary_params = [];
        $salary_types = "";
        $salary_where = [];

        if ($filter_month > 0) {
            $salary_where[] = "MONTH(pay_month) = ?";
            $salary_params[] = $filter_month;
            $salary_types .= "i";
        }
        if ($filter_year > 0) {
            $salary_where[] = "YEAR(pay_month) = ?";
            $salary_params[] = $filter_year;
            $salary_types .= "i";
        }

        if (!empty($salary_where)) {
            $salary_sql .= " WHERE " . implode(" AND ", $salary_where);
        }

        $stmt_salary = $conn->prepare($salary_sql);
        if (!empty($salary_params)) {
            $stmt_salary->bind_param($salary_types, ...$salary_params);
        }
        $stmt_salary->execute();
        $salary_result = $stmt_salary->get_result()->fetch_assoc();
        $total_salary_expense = $salary_result['total'] ?? 0;
    }

    // --- (แก้ไข) คำนวณยอดรวม ---
    $total_income = 0; $total_expense = 0;
    foreach($data as $row){
        if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
    }
    
    // (แก้ไข) นำยอดรวมเงินเดือนมาบวกเพิ่มเข้าไปในรายจ่าย
    $total_expense += $total_salary_expense;
    
    $balance = $total_income - $total_expense;

    // 4. วนลูปสร้างไฟล์ตาม Format ที่เลือก
    $temp_dir = sys_get_temp_dir();
    foreach ($selected_formats as $file_format) {
        $file_format = strtolower($file_format); 
        $file_name_base = "transactions_report_" . date('Ymd_His');
        
        if ($file_format === 'pdf') {
            $attachment_filename = $file_name_base . ".pdf";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();
            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
            $pdf->Ln(5);
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(220, 220, 220);
            $header = ['ลำดับ', 'ประเภท', 'จำนวนเงิน', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ'];
            $w = [15, 30, 35, 25, 65, 20]; 
            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();
            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
                    $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
                    $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
                    $pdf->Cell($w[1], 8, iconv('UTF-8', 'TIS-620', thai_type($row['transaction_type'])), 1, 0, 'C');
                    $pdf->Cell($w[2], 8, number_format($row['amount'], 2), 1, 0, 'R');
                    $pdf->Cell($w[3], 8, $date_be, 1, 0, 'C');
                    $pdf->Cell($w[4], 8, iconv('UTF-8', 'TIS-620', $desc), 1, 0, 'L');
                    $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $row['order_id'] ?? '-'), 1, 1, 'C');
                }
                $pdf->SetFont('THSarabunNew', 'B', 12);
                $pdf->Cell(array_sum($w), 0.5, '', 'T', 1);
                $pdf->Cell(105, 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายรับ'), 'LBR', 0, 'R');
                $pdf->Cell(85, 8, number_format($total_income, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
                $pdf->Cell(105, 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายจ่าย'), 'LBR', 0, 'R');
                $pdf->Cell(85, 8, number_format($total_expense, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
                $pdf->Cell(105, 8, iconv('UTF-8', 'TIS-620', 'ยอดคงเหลือ'), 'LBR', 0, 'R');
                $pdf->Cell(85, 8, number_format($balance, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
            } else { $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C'); }
            $pdf->Output('F', $file_path); 

        } elseif ($file_format === 'excel') {
            $attachment_filename = $file_name_base . ".csv";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename; 
            
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            fprintf($output, "\xEF\xBB\xBF");
            fputcsv($output, [$report_title]);
            fputcsv($output, []);
            fputcsv($output, ['ลำดับ', 'ประเภท', 'จำนวนเงิน (บาท)', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ']);
            $i = 1;
            if (!empty($data)) {
                foreach ($data as $row) {
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
                    $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
                    fputcsv($output, [ $i++, thai_type($row['transaction_type']), number_format($row['amount'], 2), $date_be, $desc, $row['order_id'] ?? '-']);
                }
                fputcsv($output, []);
                fputcsv($output, ['สรุปยอดรวม']);
                fputcsv($output, ['ยอดรวมรายรับ', number_format($total_income, 2)]);
                fputcsv($output, ['ยอดรวมรายจ่าย', number_format($total_expense, 2)]);
                fputcsv($output, ['ยอดคงเหลือ', number_format($balance, 2)]);
            }
            fclose($output);
        }
        $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
    } // End foreach
    
    // 5. ตั้งค่า PHPMailer และส่งอีเมล
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com'; 
    $mail->Password   = 'ivjo hwqy kraq sgwe'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465; 

    $mail->setFrom($mail->Username, 'รายงานระบบบัญชี');
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = $report_title;
    $mail->Body    = "รายงานรายรับ-รายจ่ายที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";
    $mail->AltBody = "รายงานรายรับ-รายจ่ายได้แนบมากับอีเมลนี้แล้ว";

    if (empty($files_to_attach)) throw new Exception("ไม่สามารถสร้างไฟล์แนบได้");
    
    foreach ($files_to_attach as $file) {
        $mail->addAttachment($file['path'], $file['name']); 
    }

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception("Mailer Error: " . $mail->ErrorInfo); 
    }

} catch (Exception $e) {
    http_response_code(500);
    $safe_message = preg_replace('/\[SMTP\] Connected to:.*Password:\s*\[[^\s]+\]/', '[SMTP] Connected. Password: [HIDDEN]', $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาดในการส่ง: " . $safe_message]);
} finally {
    // ลบไฟล์ชั่วคราวทั้งหมด
    if (!empty($files_to_attach)) {
        foreach ($files_to_attach as $file) {
            if (file_exists($file['path'])) unlink($file['path']);
        }
    }
}
?>