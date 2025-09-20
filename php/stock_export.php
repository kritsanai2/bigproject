<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("<h1>Composer dependencies not found.</h1><p>Please run '<code>composer require dompdf/dompdf</code>' in your project directory.</p>");
}

require __DIR__ . '/vendor/autoload.php';
require_once "db.php";

use Dompdf\Dompdf;
use Dompdf\Options;

// ================== Get parameters ==================
$format = $_GET['format'] ?? 'pdf'; // pdf หรือ excel
$type   = $_GET['type'] ?? '';
$month  = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year   = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// ================== Helper Functions ==================
function thai_type($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

function thai_month_full($month) {
    if ($month == 0) return 'ทุกเดือน';
    $months = [1 => 'มกราคม',2 => 'กุมภาพันธ์',3 => 'มีนาคม',4 => 'เมษายน',5 => 'พฤษภาคม',6 => 'มิถุนายน',7 => 'กรกฎาคม',8 => 'สิงหาคม',9 => 'กันยายน',10 => 'ตุลาคม',11 => 'พฤศจิกายน',12 => 'ธันวาคม'];
    return $months[$month] ?? '';
}

// ================== Generate Filename ==================
$type_label  = $type ? thai_type($type) : 'ทั้งหมด';
$month_label = $month > 0 ? thai_month_full($month) : 'ทุกเดือน';
$filename_base = "stock_report_{$year}_{$month_label}_{$type_label}";

// ================== Query Data ==================
$sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit
        FROM stock s
        JOIN products p ON s.product_id = p.product_id
        WHERE s.deleted = 0";

if ($type) $sql .= " AND s.stock_type = '" . $conn->real_escape_string($type) . "'";
if ($month > 0) $sql .= " AND MONTH(s.stock_date) = $month";
if ($year) $sql .= " AND YEAR(s.stock_date) = $year";
$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

$result = $conn->query($sql);
$data = [];
if ($result) while ($row = $result->fetch_assoc()) $data[] = $row;
$conn->close();

// ================== Export ==================
if ($format === 'excel') {
    // ===== Export to Excel =====
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename_base}.xls\"");
    echo "<table border='1'>";
    echo "<tr><th>วันที่</th><th>สินค้า</th><th>ประเภท</th><th>จำนวน</th><th>หน่วย</th></tr>";
    if (empty($data)) {
        echo "<tr><td colspan='5'>ไม่พบข้อมูล</td></tr>";
    } else {
        foreach ($data as $row) {
            echo "<tr>
                    <td>{$row['stock_date']}</td>
                    <td>{$row['product_name']}</td>
                    <td>" . thai_type($row['stock_type']) . "</td>
                    <td>{$row['quantity']}</td>
                    <td>{$row['unit']}</td>
                  </tr>";
        }
    }
    echo "</table>";
    exit;
} else {
    // ===== Export to PDF =====
    $fontDir = __DIR__ . '/font';
    $fontFile = $fontDir . '/THSarabunIT9.ttf';
    if (!is_dir($fontDir) || !file_exists($fontFile)) {
        die("Error: Font file not found. Please create a 'font' directory and place 'THSarabunIT9.ttf' inside it.");
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<style>
        @font-face { font-family: "THSarabun"; src: url("' . $fontFile . '"); }
        body { font-family: "THSarabun", sans-serif; font-size:16px; }
        table { border-collapse: collapse; width:100%; }
        th, td { border:1px solid #333; padding:8px; }
        th { background-color:#3498db; color:#fff; text-align:center; }
        h2 { text-align:center; color:#3498db; }
        p { text-align:center; margin-bottom:20px;}
        .center { text-align:center; }
        .right { text-align:right; }
    </style></head><body>';

    $html .= "<h2>รายงานสต็อกสินค้า</h2>";
    $html .= "<p><strong>ปี:</strong> {$year} &nbsp;&nbsp; <strong>เดือน:</strong> " . thai_month_full($month) . " &nbsp;&nbsp; <strong>ประเภท:</strong> {$type_label}</p>";
    $html .= "<table><thead><tr><th>วันที่</th><th>สินค้า</th><th>ประเภท</th><th>จำนวน</th><th>หน่วย</th></tr></thead><tbody>";
    if (empty($data)) {
        $html .= '<tr><td colspan="5" class="center">ไม่พบข้อมูล</td></tr>';
    } else {
        foreach ($data as $row) {
            $html .= "<tr>
                        <td class='center'>{$row['stock_date']}</td>
                        <td>{$row['product_name']}</td>
                        <td class='center'>" . thai_type($row['stock_type']) . "</td>
                        <td class='right'>" . number_format($row['quantity'],2) . "</td>
                        <td class='center'>{$row['unit']}</td>
                      </tr>";
        }
    }
    $html .= "</tbody></table></body></html>";

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'THSarabun');
    $options->setChroot(__DIR__);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename_base . '.pdf', ["Attachment" => 1]);
    exit;
}
?>
