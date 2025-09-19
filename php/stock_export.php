<?php
require __DIR__ . '/../vendor/autoload.php'; // เรียก library Dompdf + PhpSpreadsheet
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// ================== รับค่าจาก GET ==================
$type   = $_GET['type']   ?? '';
$month  = $_GET['month']  ?? 0;
$year   = $_GET['year']   ?? date('Y');
$format = $_GET['format'] ?? 'excel';

// ================== ฟังก์ชัน ==================
function thai_type($type){
    return strtolower($type)=='import' ? 'รับเข้า' : 'จ่ายออก';
}

// ================== ตั้งชื่อไฟล์ ==================
$type_label   = $type ? thai_type($type) : 'ทั้งหมด';
$month_label  = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : '00';
$filename_base = "stock_report_{$year}_{$month_label}_{$type_label}";

// ================== Query ข้อมูล ==================
$sql = "SELECT s.stock_date,p.product_name,s.stock_type,s.quantity,p.unit
        FROM stock s
        JOIN products p ON s.product_id=p.product_id
        WHERE s.deleted=0";
if($type)     $sql .= " AND s.stock_type='".$conn->real_escape_string($type)."'";
if($month>0)  $sql .= " AND MONTH(s.stock_date)=$month";
if($year)     $sql .= " AND YEAR(s.stock_date)=$year";
$sql .= " ORDER BY s.stock_date DESC";

$result = $conn->query($sql);
$data = [];
while($row = $result->fetch_assoc()) $data[] = $row;

// ================== Export Excel ==================
if($format=='excel'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // หัวตาราง
    $sheet->setCellValue('A1','วันที่');
    $sheet->setCellValue('B1','สินค้า');
    $sheet->setCellValue('C1','ประเภท');
    $sheet->setCellValue('D1','จำนวน');
    $sheet->setCellValue('E1','หน่วย');

    // เติมข้อมูล
    $rowNum = 2;
    foreach($data as $row){
        $sheet->setCellValue("A$rowNum",$row['stock_date']);
        $sheet->setCellValue("B$rowNum",$row['product_name']);
        $sheet->setCellValue("C$rowNum",thai_type($row['stock_type']));
        $sheet->setCellValue("D$rowNum",$row['quantity']);
        $sheet->setCellValue("E$rowNum",$row['unit']);
        $rowNum++;
    }

    // ชิดขวาคอลัมน์จำนวน
    $sheet->getStyle("D2:D$rowNum")
          ->getAlignment()
          ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

    // ส่งออก Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename_base.'.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

// ================== Export PDF ==================
// ================== Export PDF ==================
elseif($format=='pdf'){

    // Path ฟอนต์ THSarabunIT9
    $fontFile = realpath(__DIR__ . '/../font/THSarabunIT9.ttf');
    if(!$fontFile || !file_exists($fontFile)){
        die("ไม่พบไฟล์ฟอนต์: THSarabunIT9.ttf ที่ $fontFile");
    }

    // สร้าง HTML
    $html = '<style>
        @font-face {
            font-family: "THSarabunIT9";
            font-style: normal;
            font-weight: normal;
            src: url("file:///' . str_replace('\\','/',$fontFile) . '") format("truetype");
        }
        body { font-family: "THSarabunIT9", sans-serif; font-size:16px; }
        table { border-collapse: collapse; width:100%; }
        th, td { border:1px solid #000; padding:5px; }
        th { background-color:#ddd; text-align:center; }
        td { text-align:left; }
        .center { text-align:center; }
        .right { text-align:right; }
    </style>';

    $html .= '<h3 class="center">📦 รายงานสต๊อกสินค้า ปี '.$year.' เดือน '.$month.'</h3>';
    $html .= '<table>';
    $html .= '<tr>
                <th>วันที่</th>
                <th>สินค้า</th>
                <th>ประเภท</th>
                <th>จำนวน</th>
                <th>หน่วย</th>
              </tr>';
    foreach($data as $row){
        $html .= '<tr>
                    <td class="center">'.$row['stock_date'].'</td>
                    <td>'.$row['product_name'].'</td>
                    <td class="center">'.thai_type($row['stock_type']).'</td>
                    <td class="right">'.$row['quantity'].'</td>
                    <td class="center">'.$row['unit'].'</td>
                  </tr>';
    }
    $html .= '</table>';

    // ตั้งค่า Dompdf
    $options = new Options();
    $options->set('defaultFont', 'THSarabunIT9');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // ตั้งค่ากระดาษแนวนอน
    $dompdf->render();

    // ดาวน์โหลด PDF หรือแสดงในหน้าเว็บ
    $dompdf->stream($filename_base.'.pdf', ["Attachment"=>1]); // 1=ดาวน์โหลด, 0=แสดงในหน้าเว็บ
    exit;
}

?>
