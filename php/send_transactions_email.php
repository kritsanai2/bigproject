<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

header('Content-Type: application/json');

// --- เรียกใช้งาน Library ที่จำเป็น ---
try {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    $dbPath = __DIR__ . '/db.php';

    if (!file_exists($autoloadPath)) throw new Exception("ไม่พบไฟล์ autoload.php");
    if (!file_exists($dbPath)) throw new Exception("ไม่พบไฟล์ db.php");

    require_once $autoloadPath;
    require_once $dbPath;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "File loading error: " . $e->getMessage()]);
    exit;
}

define('FPDF_FONTPATH', __DIR__ . '/fonts/');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use FPDF;

// --- ฟังก์ชันช่วยเหลือ ---
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}
function tis620($string) {
    return iconv('UTF-8', 'TIS-620', (string)$string);
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
    $filter_year = isset($_POST['year']) && intval($_POST['year']) > 0 ? intval($_POST['year']) : intval(date('Y'));

    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) throw new Exception("รูปแบบอีเมลผู้รับไม่ถูกต้อง");
    if (!is_array($selected_formats) || empty($selected_formats)) throw new Exception("กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์");

    // 2. สร้างหัวข้อรายงาน
    $report_title = "รายงาน";
    if ($type_filter == 'income') $report_title .= "รายรับ";
    elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
    else $report_title .= "รายรับ-รายจ่าย";
    if ($filter_month > 0) $report_title .= " เดือน " . thai_month_name($filter_month);
    if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);

    // 3. ดึงและรวมข้อมูล
    $sql = "SELECT t.*, od.product_id, p.product_name, o.order_id FROM transactions t LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id LEFT JOIN products p ON od.product_id = p.product_id LEFT JOIN orders o ON od.order_id = o.order_id";
    $params = []; $types = ""; $where_clauses = [];
    if ($type_filter) { $where_clauses[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
    if ($filter_month > 0) { $where_clauses[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
    if ($filter_year > 0) { $where_clauses[] = "YEAR(t.transaction_date) = ?"; $params[] = $filter_year; $types .= "i"; }

    if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $transaction_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $salary_rows = [];
    if ($type_filter === '' || $type_filter === 'expense') {
        $salary_sql = "SELECT total_amount, pay_month FROM salary";
        $s_params = []; $s_types = ""; $s_where = [];
        if ($filter_month > 0) { $s_where[] = "MONTH(pay_month) = ?"; $s_params[] = $filter_month; $s_types .= "i"; }
        if ($filter_year > 0) { $s_where[] = "YEAR(pay_month) = ?"; $s_params[] = $filter_year; $s_types .= "i"; }
        if (!empty($s_where)) { $salary_sql .= " WHERE " . implode(" AND ", $s_where); }
        $stmt_salary = $conn->prepare($salary_sql);
        if (!empty($s_params)) { $stmt_salary->bind_param($s_types, ...$s_params); }
        $stmt_salary->execute();
        $salary_results = $stmt_salary->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($salary_results as $salary) {
            $salary_rows[] = ['transaction_type' => 'expense', 'amount' => $salary['total_amount'], 'transaction_date' => $salary['pay_month'], 'slip_image' => null, 'expense_type' => 'เงินเดือนพนักงานเดือน ' . thai_month_name(date('n', strtotime($salary['pay_month']))), 'order_id' => null, 'product_name' => null];
        }
    }
    
    $all_rows = array_merge($transaction_rows, $salary_rows);
    usort($all_rows, fn($a, $b) => strtotime($b['transaction_date']) - strtotime($a['transaction_date']));

    // ===== (เพิ่มใหม่) คำนวณยอดรวม =====
    $total_income = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'income'), 'amount'));
    $total_expense = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'expense'), 'amount'));
    $balance = $total_income - $total_expense;


    // 4. สร้างไฟล์ตาม Format ที่เลือก
    $temp_dir = sys_get_temp_dir();
    foreach ($selected_formats as $file_format) {
        $file_format = strtolower($file_format);
        $file_name_base = "report_" . date('Ymd_His');

if ($file_format === 'pdf') {
            $attachment_filename = $file_name_base . ".pdf";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename;

            // --- START: อัปเกรดโค้ดสร้าง PDF ทั้งหมด ---

            // 1. ตั้งค่าหน้ากระดาษเป็น A4 แนวตั้ง (Portrait)
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
            $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
            $pdf->AliasNbPages();
            $pdf->AddPage();

            // --- ส่วนหัวของเอกสาร ---
            $pdf->SetFont('THSarabunNew', 'B', 18);
            $pdf->Cell(0, 10, tis620($report_title), 0, 1, 'C');
            $pdf->Ln(5);

            // --- ส่วนหัวของตาราง (ปรับความกว้างสำหรับแนวตั้ง) ---
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(220, 220, 220);
            $header = ['ลำดับ', 'ประเภท', 'จำนวนเงิน', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ', 'หลักฐานการชำระเงิน'];
            $w = [10, 20, 25, 20, 65, 25, 25]; // ความกว้างรวม 190mm พอดีกับ A4 แนวตั้ง
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 10, tis620($header[$i]), 1, 0, 'C', true);
            }
            $pdf->Ln();

            // --- ส่วนเนื้อหาของตาราง ---
            $pdf->SetFont('THSarabunNew', '', 11);
            $images_to_append = [];
            $image_counter = 1;

            if (!empty($all_rows)) {
                $i = 1;
                foreach ($all_rows as $row) {
                    // คำนวณความสูงแถวจากข้อความ
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
                    $lineCount = 1;
                    $wrap_at = 35; // ปรับค่าตัดคำให้เหมาะกับคอลัมน์ที่แคบลง
                    if (mb_strlen($desc, 'UTF-8') > $wrap_at) {
                        $lineCount = ceil(mb_strlen($desc, 'UTF-8') / $wrap_at);
                    }
                    $cellHeight = max(8, $lineCount * 5);

                    // เช็คการขึ้นหน้าใหม่
                    if ($pdf->GetY() + $cellHeight > ($pdf->GetPageHeight() - 20)) {
                        $pdf->AddPage();
                        $pdf->SetFont('THSarabunNew', 'B', 12);
                        for ($h = 0; $h < count($header); $h++) {
                            $pdf->Cell($w[$h], 10, tis620($header[$h]), 1, 0, 'C', true);
                        }
                        $pdf->Ln();
                        $pdf->SetFont('THSarabunNew', '', 11);
                    }

                    // เตรียมข้อมูลรูปภาพสำหรับภาคผนวก
                    $image_reference_text = '-';
                    $imagePath = '../' . ltrim($row['slip_image'] ?? '', '/');
                    if (!empty($row['slip_image']) && file_exists($imagePath)) {
                        $image_reference_text = 'รูปภาพที่ ' . $image_counter;
                        $images_to_append[] = ['path' => $imagePath, 'ref_text' => $image_reference_text];
                        $image_counter++;
                    }

                    // วาด Cell ข้อมูล
                    $startY = $pdf->GetY();
                    $pdf->Cell($w[0], $cellHeight, $i++, 'LR', 0, 'C');
                    $pdf->Cell($w[1], $cellHeight, tis620(thai_type($row['transaction_type'])), 'LR', 0, 'C');
                    $pdf->Cell($w[2], $cellHeight, number_format($row['amount'], 2), 'LR', 0, 'R');
                    $pdf->Cell($w[3], $cellHeight, date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543), 'LR', 0, 'C');
                    
                    $descX = $pdf->GetX();
                    $pdf->MultiCell($w[4], 5, tis620($desc), 0, 'L');
                    $pdf->Rect($descX, $startY, $w[4], $cellHeight);
                    $pdf->SetXY($descX + $w[4], $startY);
                    
                    $pdf->Cell($w[5], $cellHeight, $row['order_id'] ?? '-', 'LR', 0, 'C');
                    $pdf->Cell($w[6], $cellHeight, tis620($image_reference_text), 'LR', 0, 'C');
                    
                    $pdf->Ln($cellHeight);
                    $pdf->Cell(array_sum($w), 0, '', 'T', 1);
                }
            } else {
                $pdf->Cell(array_sum($w), 10, tis620('ไม่พบข้อมูล'), 1, 1, 'C');
            }

            // --- ส่วนสรุปยอดท้ายตาราง ---
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->Ln(5);
            $summary_label_width = array_sum(array_slice($w, 0, 6));
            $summary_value_width = $w[6];
            $pdf->Cell($summary_label_width, 10, tis620('ยอดรวมรายรับ'), 1, 0, 'R');
            $pdf->Cell($summary_value_width, 10, number_format($total_income, 2), 1, 1, 'R');
            $pdf->Cell($summary_label_width, 10, tis620('ยอดรวมรายจ่าย'), 1, 0, 'R');
            $pdf->Cell($summary_value_width, 10, number_format($total_expense, 2), 1, 1, 'R');
            $pdf->Cell($summary_label_width, 10, tis620('ยอดคงเหลือสุทธิ'), 1, 0, 'R');
            $pdf->Cell($summary_value_width, 10, number_format($balance, 2), 1, 1, 'R');

            // --- ภาคผนวก: ส่วนแสดงรูปภาพท้ายรายงาน (พร้อมแก้ปัญหา .jfif) ---
            if (!empty($images_to_append)) {
                 if (!function_exists('imagecreatefromjpeg')) {
                    $pdf->AddPage('P', 'A4');
                    $pdf->SetFont('THSarabunNew', 'B', 12);
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->MultiCell(0, 10, tis620('ข้อผิดพลาด: ไม่สามารถแสดงรูปภาพได้เนื่องจากส่วนเสริม GD Library ไม่ได้ถูกเปิดใช้งานบน Server'), 1, 'C');
                } else {
                    $pdf->AddPage('P', 'A4');
                    $pdf->SetFont('THSarabunNew', 'B', 16);
                    $pdf->Cell(0, 10, tis620('หลักฐานการชำระเงินใบเสร็จ'), 0, 1, 'C');
                    $pdf->Ln(5);

                    foreach ($images_to_append as $image_data) {
                        $imagePath = $image_data['path'];
                        try {
                            $source_image = @imagecreatefromjpeg($imagePath);
                            if (!$source_image) continue;
                            
                            $temp_filename = tempnam(sys_get_temp_dir(), 'pdfimg') . '.jpg';
                            imagejpeg($source_image, $temp_filename, 85);
                            imagedestroy($source_image);

                            list($width_orig, $height_orig) = getimagesize($temp_filename);
                            if ($width_orig == 0 || $height_orig == 0) continue;

                            $maxWidth = 190; $maxHeight = 100;
                            $ratio = $width_orig / $height_orig;
                            $new_w = $maxWidth; $new_h = $new_w / $ratio;
                            if ($new_h > $maxHeight) { $new_h = $maxHeight; $new_w = $new_h * $ratio; }

                            if ($pdf->GetY() + $new_h + 10 > $pdf->GetPageHeight() - 20) { $pdf->AddPage('P', 'A4'); }

                            $pdf->SetFont('THSarabunNew', 'B', 12);
                            $pdf->Cell(0, 10, tis620($image_data['ref_text']), 0, 1, 'L');
                            $pdf->Image($temp_filename, $pdf->GetX(), $pdf->GetY(), $new_w, $new_h);
                            $pdf->Ln($new_h + 10);
                            unlink($temp_filename);
                        } catch (Exception $e) {
                             if (isset($temp_filename) && file_exists($temp_filename)) unlink($temp_filename);
                        }
                    }
                }
            }

            $pdf->Output('F', $file_path);
            // --- END: อัปเกรดโค้ดสร้าง PDF ทั้งหมด ---
        } elseif ($file_format === 'excel') {
            $attachment_filename = $file_name_base . ".csv";
            $file_path = $temp_dir . DIRECTORY_SEPARATOR . $attachment_filename;
            $output = fopen($file_path, 'w');
            if ($output === false) throw new Exception("ไม่สามารถสร้างไฟล์ CSV ได้");
            fprintf($output, "\xEF\xBB\xBF");
            fputcsv($output, [$report_title]);
            fputcsv($output, []);
            fputcsv($output, ['ลำดับ', 'ประเภท', 'จำนวนเงิน (บาท)', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ']);
            if (!empty($all_rows)) {
                $i = 1;
                foreach ($all_rows as $row) {
                    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
                    $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
                    fputcsv($output, [ $i++, thai_type($row['transaction_type']), number_format($row['amount'], 2), $date_be, $desc, $row['order_id'] ?? '-']);
                }
            }
            
            // ===== (เพิ่มใหม่) ส่วนสรุปยอดท้ายไฟล์ CSV =====
            fputcsv($output, []); // บรรทัดว่าง
            fputcsv($output, ['สรุปยอดรวม']);
            fputcsv($output, ['ยอดรวมรายรับ', number_format($total_income, 2)]);
            fputcsv($output, ['ยอดรวมรายจ่าย', number_format($total_expense, 2)]);
            fputcsv($output, ['ยอดคงเหลือสุทธิ', number_format($balance, 2)]);

            fclose($output);
        }

        if (isset($file_path) && file_exists($file_path)) {
            $files_to_attach[] = ['path' => $file_path, 'name' => $attachment_filename];
        }
    }

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
    $mail->Body    = "รายงานที่คุณร้องขอได้แนบมากับอีเมลนี้แล้ว";

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
    echo json_encode(['status' => 'error', 'message' => "Error: " . $e->getMessage()]);
} finally {
    foreach ($files_to_attach as $file) {
        if (isset($file['path']) && file_exists($file['path'])) {
            unlink($file['path']);
        }
    }
}
?>

