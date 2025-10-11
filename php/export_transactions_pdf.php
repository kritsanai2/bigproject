<?php
// --- เปิดการแสดงข้อผิดพลาด ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่าเวลาสูงสุดในการรันสคริปต์
set_time_limit(300);


// --- FPDF Path และ Autoload ---
define('FPDF_FONTPATH', __DIR__ . '/fonts/');
require '../vendor/autoload.php';
require_once 'db.php';

// --- (2) ฟังก์ชันช่วยเหลือ (เหมือนเดิม) ---
function thai_type($type)
{
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month($month)
{
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[intval($month)] ?? '';
}
// ฟังก์ชันแปลงข้อความไทยสำหรับ FPDF
function utf8_to_tis620($string)
{
    return iconv('UTF-8', 'TIS-620', $string);
}


// --- (3) ส่วนของการดึงข้อมูลและประมวลผล (เหมือนเดิมทุกประการ) ---
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year  = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : intval(date('Y'));

// --- ดึงข้อมูล Transactions ---
$sql = "SELECT t.*, od.product_id, p.product_name, o.order_id
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        LEFT JOIN orders o ON od.order_id = o.order_id";
$params = [];
$types = "";
$where_clauses = [];
if ($type_filter) {
    $where_clauses[] = "t.transaction_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($filter_month > 0) {
    $where_clauses[] = "MONTH(t.transaction_date) = ?";
    $params[] = $filter_month;
    $types .= "i";
}
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(t.transaction_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transaction_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- ดึงข้อมูลเงินเดือน ---
$salary_rows = [];
if ($type_filter === '' || $type_filter === 'expense') {
    $salary_sql = "SELECT total_amount, pay_month FROM salary";
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
    $salary_results = $stmt_salary->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($salary_results as $salary) {
        $salary_rows[] = [
            'transaction_id' => 'S' . date('mY', strtotime($salary['pay_month'])),
            'transaction_type' => 'expense',
            'amount' => $salary['total_amount'],
            'transaction_date' => $salary['pay_month'],
            'expense_type' => 'เงินเดือนพนักงานเดือน ' . thai_month(date('n', strtotime($salary['pay_month']))),
            'order_id' => null,
            'slip_image' => null,
            'product_name' => null
        ];
    }
}

// --- รวม, จัดเรียง, และคำนวณยอดรวม ---
$all_rows = array_merge($transaction_rows, $salary_rows);
usort($all_rows, fn($a, $b) => strtotime($b['transaction_date']) - strtotime($a['transaction_date']));
$total_income = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'income'), 'amount'));
$total_expense = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'expense'), 'amount'));
$balance = $total_income - $total_expense;

// --- สร้างหัวข้อรายงาน ---
$report_title_utf8 = "รายงาน";
if ($type_filter == 'income') $report_title_utf8 .= "รายรับ";
elseif ($type_filter == 'expense') $report_title_utf8 .= "รายจ่าย";
else $report_title_utf8 .= "รายรับ-รายจ่าย";
if ($filter_month > 0) $report_title_utf8 .= " เดือน " . thai_month($filter_month);
if ($filter_year > 0) $report_title_utf8 .= " ปี " . ($filter_year + 543);


// --- (4) สร้างเอกสาร PDF ด้วย FPDF ---

class PDF extends FPDF
{
    function Header()
    {
        global $report_title_utf8;
        $this->SetFont('THSarabunNew', 'B', 18);
        $this->Cell(0, 10, utf8_to_tis620($report_title_utf8), 0, 1, 'C');
        $this->Ln(5);
    }
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// ======================= CHANGE 1: ปรับเป็นแนวตั้ง ('P') =======================
$pdf = new PDF('P', 'mm', 'A4'); // P = Portrait (แนวตั้ง)
$pdf->AliasNbPages();

// --- เพิ่มฟอนต์ภาษาไทย ---
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');

$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 12);

// --- หัวตาราง ---
$pdf->SetFillColor(220, 220, 220);
$header = ['ลำดับ', 'ประเภท', 'จำนวนเงิน', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ', 'หลักฐานการชำระเงิน'];

// ======================= CHANGE 2: ปรับความกว้างคอลัมน์สำหรับแนวตั้ง =======================
// ความกว้างหน้า A4 แนวตั้งคือ 210mm (พื้นที่ใช้งานได้ประมาณ 190mm)
$w = [10, 20, 25, 20, 65, 25, 25]; // ผลรวม = 190
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 10, utf8_to_tis620($header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// --- เนื้อหาตาราง ---
$pdf->SetFont('THSarabunNew', '', 11);
$pdf->SetFillColor(255, 255, 255);
$i = 1;

$images_to_append = [];
$image_counter = 1;

if (!empty($all_rows)) {
    foreach ($all_rows as $row) {

        // --- คำนวณความสูงของแถวตามข้อความ ---
        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
        $lineCount = 1;

        // ======================= CHANGE 3: ปรับค่าการตัดคำให้เหมาะสมกับคอลัมน์ที่แคบลง =======================
        $wrap_at = 35;
        if (mb_strlen($desc, 'UTF-8') > $wrap_at) {
             $lineCount = ceil(mb_strlen($desc, 'UTF-8') / $wrap_at);
        }
        $cellHeight = max(8, $lineCount * 5);

        // --- ตรวจสอบการขึ้นหน้าใหม่ ---
        if ($pdf->GetY() + $cellHeight > ($pdf->GetPageHeight() - 20)) {
            $pdf->AddPage();

            // *** วาดหัวตารางอีกครั้งในหน้าใหม่ ***
            $pdf->SetFont('THSarabunNew', 'B', 12);
            $pdf->SetFillColor(220, 220, 220);
            for ($h = 0; $h < count($header); $h++) {
                $pdf->Cell($w[$h], 10, utf8_to_tis620($header[$h]), 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('THSarabunNew', '', 11);
        }

        // --- เตรียมข้อความอ้างอิงรูปภาพ และเก็บ path รูปไว้ ---
        $image_reference_text = '-';
        $imagePath = '../' . ltrim($row['slip_image'] ?? '', '/');
        if (!empty($row['slip_image']) && file_exists($imagePath)) {
            $image_reference_text = 'รูปภาพที่ ' . $image_counter;
            $images_to_append[] = [
                'path' => $imagePath,
                'ref_text' => $image_reference_text
            ];
            $image_counter++;
        }

        // --- วาด Cell ข้อมูล ---
        $startY = $pdf->GetY();
        $pdf->Cell($w[0], $cellHeight, $i++, 'LR', 0, 'C');
        $pdf->Cell($w[1], $cellHeight, utf8_to_tis620(thai_type($row['transaction_type'])), 'LR', 0, 'C');
        $pdf->Cell($w[2], $cellHeight, number_format($row['amount'], 2), 'LR', 0, 'R');
        $pdf->Cell($w[3], $cellHeight, date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543), 'LR', 0, 'C');

        $descX = $pdf->GetX();
        $pdf->MultiCell($w[4], 5, utf8_to_tis620($desc), 0, 'L');
        $pdf->Rect($descX, $startY, $w[4], $cellHeight);
        $pdf->SetXY($descX + $w[4], $startY);

        $pdf->Cell($w[5], $cellHeight, $row['order_id'] ?? '-', 'LR', 0, 'C');
        $pdf->Cell($w[6], $cellHeight, utf8_to_tis620($image_reference_text), 'LR', 0, 'C');

        $pdf->Ln($cellHeight);
        $pdf->Cell(array_sum($w), 0, '', 'T', 1);
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_to_tis620('ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

// --- ส่วนสรุปยอด ---
// (ส่วนนี้ปรับตามความกว้างใหม่โดยอัตโนมัติ)
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->Ln(5);
$summary_label_width = $w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5];
$summary_value_width = $w[6];
$pdf->Cell($summary_label_width, 10, utf8_to_tis620('ยอดรวมรายรับ'), 1, 0, 'R');
$pdf->Cell($summary_value_width, 10, number_format($total_income, 2), 1, 1, 'R');
$pdf->Cell($summary_label_width, 10, utf8_to_tis620('ยอดรวมรายจ่าย'), 1, 0, 'R');
$pdf->Cell($summary_value_width, 10, number_format($total_expense, 2), 1, 1, 'R');
$pdf->Cell($summary_label_width, 10, utf8_to_tis620('ยอดคงเหลือสุทธิ'), 1, 0, 'R');
$pdf->Cell($summary_value_width, 10, number_format($balance, 2), 1, 1, 'R');


// --- ** UPGRADED & FINAL: (ภาคผนวก) ส่วนแสดงรูปภาพท้ายรายงาน ** ---
if (!empty($images_to_append)) {
    // ตรวจสอบก่อนว่ามีฟังก์ชันจัดการรูปภาพ (GD Library) หรือไม่
    if (!function_exists('imagecreatefromjpeg')) {
        // ถ้าไม่มี ให้แจ้งข้อผิดพลาดใน PDF แล้วหยุดการทำงานส่วนนี้
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('THSarabunNew', 'B', 12);
        $pdf->SetTextColor(255, 0, 0); // สีแดง
        $pdf->MultiCell(0, 10, utf8_to_tis620('ข้อผิดพลาด: ไม่สามารถแสดงรูปภาพได้ เนื่องจากส่วนเสริม GD Library ไม่ได้ถูกเปิดใช้งานบน Server กรุณาติดต่อผู้ดูแลระบบ'), 1, 'C');
    } else {
        $pdf->AddPage('P', 'A4');
        $pdf->SetFont('THSarabunNew', 'B', 16);
        $pdf->Cell(0, 10, utf8_to_tis620('หลักฐานการชำระเงินใบเสร็จ'), 0, 1, 'C');
        $pdf->Ln(5);

        foreach ($images_to_append as $image_data) {
            $imagePath = $image_data['path'];

            try {
                // --- START: แปลงรูปภาพ on-the-fly ---
                // 1. สร้าง object รูปภาพจากไฟล์ต้นฉบับ (รองรับ jpg, jpeg, jfif)
                $source_image = imagecreatefromjpeg($imagePath);
                if (!$source_image) {
                     throw new Exception('Cannot read source image.');
                }
                
                // 2. สร้างไฟล์ชั่วคราวสำหรับรูปภาพที่แปลงแล้ว
                $temp_filename = tempnam(sys_get_temp_dir(), 'pdfimg') . '.jpg';

                // 3. บันทึก object รูปภาพเป็นไฟล์ JPG คุณภาพ 85%
                imagejpeg($source_image, $temp_filename, 85);
                
                // 4. ทำลาย object รูปภาพในหน่วยความจำ
                imagedestroy($source_image);
                // --- END: แปลงรูปภาพ on-the-fly ---

                // คำนวณขนาด (ใช้ path ของไฟล์ชั่วคราวที่แปลงแล้ว)
                list($width_orig, $height_orig) = getimagesize($temp_filename);
                if ($width_orig == 0 || $height_orig == 0) continue;

                $maxWidth = 190;
                $maxHeight = 100;
                $ratio = $width_orig / $height_orig;
                $new_w = $maxWidth;
                $new_h = $new_w / $ratio;
                if ($new_h > $maxHeight) {
                    $new_h = $maxHeight;
                    $new_w = $new_h * $ratio;
                }

                if ($pdf->GetY() + $new_h + 10 > $pdf->GetPageHeight() - 20) {
                    $pdf->AddPage('P', 'A4');
                }

                $pdf->SetFont('THSarabunNew', 'B', 12);
                $pdf->Cell(0, 10, utf8_to_tis620($image_data['ref_text']), 0, 1, 'L');
                
                // **ใช้ไฟล์ชั่วคราวที่แปลงแล้วในการแสดงผล**
                $pdf->Image($temp_filename, $pdf->GetX(), $pdf->GetY(), $new_w, $new_h);
                $pdf->Ln($new_h + 10);

                // **สำคัญมาก: ลบไฟล์ชั่วคราวทิ้งหลังใช้งานเสร็จ**
                unlink($temp_filename);

            } catch (Exception $e) {
                // ถ้าเกิดข้อผิดพลาดในการแปลงหรือแสดงผล
                if ($pdf->GetY() + 20 > $pdf->GetPageHeight() - 20) { $pdf->AddPage('P', 'A4'); }
                $pdf->SetFont('THSarabunNew', '', 12);
                $pdf->Cell(0, 10, utf8_to_tis620($image_data['ref_text'] . ': [ไม่สามารถแสดงรูปภาพได้ ' . basename($imagePath) . ']'), 0, 1, 'L');
                $pdf->Ln(10);
                // ถ้ามีไฟล์ชั่วคราวค้างอยู่ ให้ลบทิ้ง
                if (isset($temp_filename) && file_exists($temp_filename)) {
                    unlink($temp_filename);
                }
            }
        }
    }
}


// --- (5) ส่งออกไฟล์ PDF ---
$filename = "transaction_report_" . date('Ymd') . ".pdf";
$pdf->Output('D', $filename);
exit();