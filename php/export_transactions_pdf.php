<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
require_once 'db.php';

define('FPDF_FONTPATH', 'fonts/');

// ======================= ฟังก์ชันช่วย =======================
function thai_type($type) {
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month) {
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[intval($month)] ?? '';
}

// ======================= คลาส PDF ขยาย =======================
class tFPDF_Extended extends tFPDF {
    function CheckPageBreak($h) {
        if($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb-1] == "\n")
            $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c == ' ') $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

// ======================= รับค่าจาก URL =======================
$type_filter  = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year  = isset($_GET['year']) ? intval($_GET['year']) : 0;

$report_title = "รายงาน";
if ($type_filter == 'income') $report_title .= "รายรับ";
elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
else $report_title .= "รายรับ-รายจ่าย";

if ($filter_month > 0) $report_title .= " เดือน " . thai_month_name($filter_month);
if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
if (empty($type_filter) && $filter_month == 0 && $filter_year == 0)
    $report_title = "รายงานรายรับ-รายจ่ายทั้งหมด";

// ======================= ดึงข้อมูลจากฐานข้อมูล =======================
$sql = "
    SELECT t.*, od.product_id, p.product_name, o.order_id
    FROM transactions t
    LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
    LEFT JOIN products p ON od.product_id = p.product_id
    LEFT JOIN orders o ON od.order_id = o.order_id
";
$where = [];
$params = [];
$types  = "";
if ($type_filter) { $where[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
if ($filter_month){ $where[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year) { $where[] = "YEAR(t.transaction_date) = ?";  $params[] = $filter_year;  $types .= "i"; }
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// ======================= สร้าง PDF =======================
$pdf = new tFPDF_Extended('P', 'mm', 'A4');
$pdf->AddPage();

$pdf->AddFont('sarabun', '', 'Sarabun-Regular.ttf', true);
$pdf->AddFont('sarabun', 'B', 'Sarabun-Bold.ttf', true);

// หัวรายงาน
$pdf->SetFont('sarabun', 'B', 18);
$pdf->Cell(0, 10, $report_title, 0, 1, 'C');
$pdf->Ln(5);

// หัวตาราง
$pdf->SetFont('sarabun', 'B', 11);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);

$header = ['ลำดับ', 'วันที่', 'ประเภท', 'จำนวนเงิน', 'รายละเอียด', 'รูปภาพ'];
$widths = [12, 25, 22, 25, 76, 30];

foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 10, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// ======================= เนื้อหาตาราง =======================
$pdf->SetFont('sarabun', '', 11);
$total_income = 0;
$total_expense = 0;

if (!empty($rows)) {
    foreach ($rows as $index => $row) {
        $fill_color = ($index % 2 == 0) ? [255, 255, 255] : [248, 248, 248];
        $pdf->SetFillColor(...$fill_color);

        $desc = $row['transaction_type'] == 'expense'
            ? ($row['expense_type'] ?? '-')
            : ($row['product_name'] ?? '-');
        $desc = $desc ?: '-';
        $desc = iconv('UTF-8', 'cp874//IGNORE', $desc);

        $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
        if ($row['transaction_type'] == 'income') $total_income += $row['amount'];
        else $total_expense += $row['amount'];

        $lineHeight = 6;
        $nb = $pdf->NbLines($widths[4], $desc);
        $h = $lineHeight * $nb;

        $pdf->CheckPageBreak($h);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->Cell($widths[0], $h, $index + 1, 1, 0, 'C', true);
        $pdf->Cell($widths[1], $h, $date_be, 1, 0, 'C', true);
        $pdf->Cell($widths[2], $h, thai_type($row['transaction_type']), 1, 0, 'C', true);
        $pdf->Cell($widths[3], $h, number_format($row['amount'], 2), 1, 0, 'R', true);

        // รายละเอียด
        $pdf->SetXY($x + $widths[0] + $widths[1] + $widths[2] + $widths[3], $y);
        $pdf->MultiCell($widths[4], $lineHeight, $desc, 1, 'L', true);

        // รูปภาพ
        $pdf->SetXY($x + array_sum(array_slice($widths, 0, 5)), $y);
        $image_path = $row['slip_image'];
        if ($row['transaction_type'] == 'expense' && !empty($image_path) && file_exists($image_path)) {
            $pdf->Cell($widths[5], $h, '', 1, 0, 'C', true);
            $pdf->Image($image_path, $pdf->GetX() - $widths[5] + 2, $y + 2, $widths[5] - 4, $h - 4);
        } else {
            $pdf->Cell($widths[5], $h, '-', 1, 0, 'C', true);
        }

        $pdf->Ln($h);
    }
} else {
    $pdf->Cell(array_sum($widths), 10, 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก', 1, 1, 'C');
}

// ======================= สรุปผลท้ายรายงาน =======================
if (!empty($rows)) {
    $pdf->Ln(8);
    $pdf->SetFont('sarabun', 'B', 12);

    $label_w = 40;
    $value_w = 50;
    $start_x = 100;

    $pdf->SetX($start_x);
    $pdf->Cell($label_w, 8, 'รายรับรวม', 1, 0, 'L');
    $pdf->SetTextColor(28, 138, 62);
    $pdf->Cell($value_w, 8, number_format($total_income, 2) . ' บาท', 1, 1, 'R');

    $pdf->SetX($start_x);
    $pdf->SetTextColor(203, 50, 52);
    $pdf->Cell($label_w, 8, 'รายจ่ายรวม', 1, 0, 'L');
    $pdf->Cell($value_w, 8, number_format($total_expense, 2) . ' บาท', 1, 1, 'R');

    $net_total = $total_income - $total_expense;
    $pdf->SetX($start_x);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetTextColor($net_total >= 0 ? 28 : 203, $net_total >= 0 ? 138 : 50, $net_total >= 0 ? 62 : 52);
    $pdf->Cell($label_w, 10, 'คงเหลือสุทธิ', 1, 0, 'L', true);
    $pdf->Cell($value_w, 10, number_format($net_total, 2) . ' บาท', 1, 1, 'R', true);

    $pdf->SetTextColor(0);
}

// ======================= ออกไฟล์ PDF =======================
if (ob_get_length()) ob_end_clean();
$pdf->Output('D', 'transactions_report_' . date('Ymd') . '.pdf');
exit();
?>
