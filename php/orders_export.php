<?php
require __DIR__ . '/../vendor/autoload.php';
require_once "db.php";

// ตั้ง charset ฐานข้อมูล
$conn->set_charset("utf8mb4");

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$month  = $_GET['month']  ?? 0;
$year   = $_GET['year']   ?? date('Y');
$format = $_GET['format'] ?? 'excel';

function thai_month($m){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
               5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
               9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($m)] ?? '';
}

$month_label = $month ? str_pad($month,2,'0',STR_PAD_LEFT) : '00';
$filename_base = "orders_report_{$year}_{$month_label}";

// Query ข้อมูล
$sql = "SELECT o.order_id, o.order_date, c.full_name AS customer_name,
               SUM(od.quantity*od.price) AS total_amount
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN order_details od ON o.order_id = od.order_id
        WHERE o.deleted=0";

if($month>0) $sql .= " AND MONTH(o.order_date)=$month";
if($year)    $sql .= " AND YEAR(o.order_date)=$year";

$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

$result = $conn->query($sql);
$data = [];
while($row = $result->fetch_assoc()) $data[] = $row;

// Export Excel
if($format=='excel'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1','ลำดับ');
    $sheet->setCellValue('B1','วันที่สั่งซื้อ');
    $sheet->setCellValue('C1','ชื่อลูกค้า');
    $sheet->setCellValue('D1','ยอดรวม (บาท)');

    $rowNum = 2; $i=1;
    foreach($data as $row){
        $sheet->setCellValue("A$rowNum",$i++);
        $sheet->setCellValue("B$rowNum",$row['order_date']);
        $sheet->setCellValueExplicit("C$rowNum", $row['customer_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue("D$rowNum",$row['total_amount']);
        $rowNum++;
    }

    $sheet->getStyle("D2:D".($rowNum-1))
          ->getNumberFormat()
          ->setFormatCode('#,##0.00');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename_base.'.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

// Export PDF
elseif($format=='pdf'){
    $fontFile = realpath(__DIR__ . '/../font/THSarabunIT9.ttf');
    if(!$fontFile || !file_exists($fontFile)) die("ไม่พบฟอนต์ THSarabunIT9.ttf");

    $html = '<style>
        @font-face {
            font-family: "THSarabunIT9";
            src: url("file:///' . str_replace('\\','/',$fontFile) . '") format("truetype");
        }
        body { font-family: "THSarabunIT9"; font-size:16px; }
        table { border-collapse: collapse; width:100%; }
        th, td { border:1px solid #000; padding:5px; }
        th { background:#ddd; text-align:center; }
        .center { text-align:center; }
        .right { text-align:right; }
    </style>';

    $html .= '<h3 class="center">🛒 รายงานคำสั่งซื้อ เดือน '.($month?thai_month($month):'ทั้งหมด').' '.$year.'</h3>';
    $html .= '<table>
                <tr>
                  <th>ลำดับ</th>
                  <th>วันที่สั่งซื้อ</th>
                  <th>ชื่อลูกค้า</th>
                  <th>ยอดรวม (บาท)</th>
                </tr>';
    $i=1;
    foreach($data as $row){
        $html .= '<tr>
                    <td class="center">'.$i++.'</td>
                    <td class="center">'.$row['order_date'].'</td>
                    <td>'.$row['customer_name'].'</td>
                    <td class="right">'.number_format($row['total_amount'],2).'</td>
                  </tr>';
    }
    $html .= '</table>';

    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

    $options = new Options();
    $options->set('defaultFont','THSarabunIT9');
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream($filename_base.'.pdf',["Attachment"=>1]);
    exit;
}
?>
