<?php
// --- ส่วนการตั้งค่าพื้นฐาน ---
// ตั้งค่าให้ PHP แสดงข้อผิดพลาดทั้งหมดบนหน้าจอ (สำหรับช่วงพัฒนาโปรแกรม)
ini_set('display_errors', 1);
error_reporting(E_ALL);
// กำหนดให้ Response ที่ส่งกลับไปเป็นรูปแบบ JSON
header('Content-Type: application/json');

// --- เรียกใช้งาน Library ที่จำเป็น ---
// เรียกใช้ Autoloader ของ Composer เพื่อให้สามารถใช้งาน Library ต่างๆ เช่น PHPMailer
require __DIR__ . '/../vendor/autoload.php';
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db.php';
// กำหนด Path ของโฟลเดอร์ที่เก็บไฟล์ฟอนต์สำหรับ FPDF
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

// --- นำเข้า Class ที่จะใช้งาน ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- ฟังก์ชันช่วยเหลือ ---
// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

// ฟังก์ชันแปลงประเภทสต็อก (import/remove) เป็นภาษาไทย
function thai_type_text($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

// Array สำหรับเก็บ Path ของไฟล์ที่จะสร้าง เพื่อใช้แนบกับอีเมลและลบทิ้งในตอนท้าย
$files_to_attach = [];
try {
    // ตรวจสอบว่า request ที่ส่งมาเป็น POST หรือไม่
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    // 1. รับค่าจาก POST และตรวจสอบข้อมูล
    $recipient_email = trim($_POST['email'] ?? '');
    $selected_formats = $_POST['file_formats'] ?? []; // รูปแบบไฟล์ที่เลือก (pdf, excel)
    $type_filter = $_POST['type'] ?? '';
    $month_filter = intval($_POST['month'] ?? 0);
    $filter_year = intval($_POST['year'] ?? 0);

    // ตรวจสอบความถูกต้องของข้อมูลเบื้องต้น
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    if (empty($selected_formats)) throw new Exception("กรุณาเลือกรูปแบบไฟล์");

    // 2. สร้างหัวข้อรายงานแบบไดนามิกตามเงื่อนไขที่กรอง
    if ($month_filter == 0 && $filter_year == 0 && empty($type_filter)) {
        $report_title = "รายงานสต็อกทั้งหมด";
    } else {
        $report_title = "รายงานสต็อก";
        if (!empty($type_filter)) $report_title .= "ประเภท " . thai_type_text($type_filter);
        if ($month_filter > 0) $report_title .= " เดือน " . thai_month($month_filter);
        if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
    }

    // 3. ดึงข้อมูลจากฐานข้อมูลตามเงื่อนไข
    $temp_dir = sys_get_temp_dir(); // หา Path ของโฟลเดอร์ชั่วคราวในเครื่องเซิร์ฟเวอร์
    $sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit FROM stock s JOIN products p ON s.product_id = p.product_id";
    
    // เตรียมตัวแปรสำหรับสร้าง Dynamic Query
    $where_clauses = [];
    $params = [];
    $types = '';
    // เพิ่มเงื่อนไขการกรองลงใน Array ตามค่าที่ได้รับ
    if ($type_filter) { $where_clauses[] = "s.stock_type = ?"; $params[] = $type_filter; $types .= 's'; }
    if ($month_filter > 0) { $where_clauses[] = "MONTH(s.stock_date) = ?"; $params[] = $month_filter; $types .= 'i'; }
    if ($filter_year > 0) { $where_clauses[] = "YEAR(s.stock_date) = ?"; $params[] = $filter_year; $types .= 'i'; }
    // รวมเงื่อนไขทั้งหมดด้วย ' AND ' แล้วต่อท้าย SQL
    if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
    $sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

    // รัน Query ด้วย Prepared Statement
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);

    // คำนวณยอดรวมรับเข้าและจ่ายออก
    $total_import = 0; $total_remove = 0;
    foreach ($data as $row) {
        if ($row['stock_type'] == 'import') $total_import += $row['quantity'];
        else $total_remove += $row['quantity'];
    }

    // 4. สร้างไฟล์รายงานตามรูปแบบที่ผู้ใช้เลือก
    foreach ($selected_formats as $format) {
        $timestamp = date('Ymd_His');
        // --- กรณีเลือก PDF ---
        if ($format === 'pdf') {
            $filename = "stock_report_{$timestamp}.pdf";
            $filepath = $temp_dir . DIRECTORY_SEPARATOR . $filename;

            $pdf = new FPDF();
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AddPage();
            $pdf->SetFont('THSarabunNew', 'B', 18);
            // สร้างหัวข้อไฟล์ PDF (ใช้ iconv เพื่อรองรับภาษาไทย)
            $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
            $pdf->Ln(5);
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(220, 220, 220);
            $header = ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย'];
            $w = [20, 30, 70, 25, 25, 20]; // กำหนดความกว้างของแต่ละคอลัมน์
            // วาดหัวตาราง
            for($i=0; $i<count($header); $i++) { $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true); }
            $pdf->Ln();
            // วาดข้อมูลในตาราง
            $pdf->SetFont('THSarabunNew', '', 12);
            $i = 1;
            if(!empty($data)){
                foreach ($data as $row) {
                    $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
                    $date_th = date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543);
                    $pdf->Cell($w[1], 8, $date_th, 1, 0, 'C');
                    $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
                    $pdf->Cell($w[3], 8, iconv('UTF-8', 'TIS-620', thai_type_text($row['stock_type'])), 1, 0, 'C');
                    $pdf->Cell($w[4], 8, number_format($row['quantity']), 1, 0, 'R');
                    $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $row['unit']), 1, 1, 'C');
                }
                // วาดแถวสรุปยอด
                $pdf->SetFont('THSarabunNew', 'B', 12);
                $pdf->Cell(array_sum($w) - $w[4] - $w[5], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมรับเข้า'), 1, 0, 'R');
                $pdf->Cell($w[4], 10, number_format($total_import), 1, 1, 'R');
                $pdf->Cell(array_sum($w) - $w[4] - $w[5], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมจ่ายออก'), 1, 0, 'R');
                $pdf->Cell($w[4], 10, number_format($total_remove), 1, 1, 'R');
            } else {
                $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูล'), 1, 1, 'C');
            }
            // บันทึกไฟล์ PDF ลงในเครื่องเซิร์ฟเวอร์ (Output 'F')
            $pdf->Output('F', $filepath);
            // เก็บ Path ของไฟล์ไว้เพื่อใช้แนบอีเมล
            $files_to_attach[] = ['path' => $filepath, 'name' => $filename];

        // --- กรณีเลือก Excel (CSV) ---
        } elseif ($format === 'excel') {
            $filename = "stock_report_{$timestamp}.csv";
            $filepath = $temp_dir . DIRECTORY_SEPARATOR . $filename;
            $output = fopen($filepath, 'w');
            // ใส่ BOM (Byte Order Mark) เพื่อให้ Excel เปิดไฟล์ CSV ภาษาไทยได้ถูกต้อง
            fprintf($output, "\xEF\xBB\xBF");
            // เขียนข้อมูลลงไฟล์ CSV
            fputcsv($output, [$report_title]);
            fputcsv($output, []); // บรรทัดว่าง
            fputcsv($output, ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย']);
            if(!empty($data)){
                $i = 1;
                foreach ($data as $row) {
                    fputcsv($output, [ $i++, date('d/m/Y', strtotime($row['stock_date'])), $row['product_name'], thai_type_text($row['stock_type']), $row['quantity'], $row['unit'] ]);
                }
                fputcsv($output, []); // บรรทัดว่าง
                fputcsv($output, ['ยอดรวมรับเข้า', $total_import]);
                fputcsv($output, ['ยอดรวมจ่ายออก', $total_remove]);
            }
            fclose($output);
            // เก็บ Path ของไฟล์ไว้
            $files_to_attach[] = ['path' => $filepath, 'name' => $filename];
        }
    }

    // 5. ตั้งค่าและส่งอีเมล  สร้างอ็อบเจกต์ของคลาส PHPMailer
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    // ตั้งค่าการส่งผ่าน SMTP ของ Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gfc20140@gmail.com';
    $mail->Password   = 'ivjo hwqy kraq sgwe'; // ต้องเป็น App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    // ตั้งค่าอีเมล
    $mail->setFrom($mail->Username, 'รายงานคลังสินค้า');
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = $report_title; // ใช้หัวข้อรายงานเป็น สร้างหัวเรื่อง (Subject) ของอีเมล
    $mail->Body    = "รายงานสต็อกที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";  //
    $mail->AltBody = "รายงานสต็อกได้แนบมากับอีเมลนี้แล้ว";

    // ตรวจสอบว่ามีไฟล์ให้แนบหรือไม่
    if (empty($files_to_attach)) throw new Exception("ไม่สามารถสร้างไฟล์แนบได้");
    // วนลูปเพื่อแนบไฟล์ทั้งหมดที่สร้างไว้
    foreach ($files_to_attach as $file) {
        $mail->addAttachment($file['path'], $file['name']);
    }

    // สั่งส่งอีเมล และส่ง Response กลับไป
    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'ส่งรายงานทางอีเมลเรียบร้อยแล้ว']);
    } else {
        throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    }

} catch (Exception $e) {
    // กรณีเกิดข้อผิดพลาดใดๆ ใน try block จะเข้ามาทำงานที่นี่
    // ส่ง Response กลับไปเป็น JSON พร้อมข้อความ Error
    echo json_encode(['status' => 'error', 'message' => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
} finally {
    // บล็อกนี้จะทำงานเสมอ ไม่ว่า try จะสำเร็จหรือล้มเหลว
    // ทำหน้าที่ลบไฟล์ชั่วคราวทั้งหมดที่สร้างขึ้น เพื่อไม่ให้เปลืองพื้นที่เซิร์ฟเวอร์
    foreach ($files_to_attach as $file) {
        if (isset($file['path']) && file_exists($file['path'])) {
            unlink($file['path']);
        }
    }
}
?>