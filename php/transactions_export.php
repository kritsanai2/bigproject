<?php
require_once "db.php";
require __DIR__ . '/../vendor/autoload.php'; // Dompdf + PhpSpreadsheet
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$type_filter = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'pdf';

$sql = "SELECT * FROM transactions WHERE deleted=0";
if ($type_filter) {
    $sql .= " AND transaction_type='".$conn->real_escape_string($type_filter)."'";
}
$sql .= " ORDER BY transaction_date DESC";

$result = $conn->query($sql);
$rows = [];
while($row = $result->fetch_assoc()){
    $rows[] = $row;
}

function thai_type($type){
    return strtolower($type)=='income' ? 'รายรับ' : 'รายจ่าย';
}

if($format=='excel'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1','ลำดับ');
    $sheet->setCellValue('B1','รหัสรายรับ-รายจ่าย');
    $sheet->setCellValue('C1','วันที่');
    $sheet->setCellValue('D1','ประเภท');
    $sheet->setCellValue('E1','จำนวนเงิน (บาท)');
    $sheet->setCellValue('F1','รายละเอียด');

    $i = 2; $num = count($rows);
    foreach($rows as $row){
        $desc = $row['transaction_type']=='expense' ? ($row['expense_type'] ?? '-') : 'จาก order_detail_id: '.$row['order_detail_id'];
        $sheet->setCellValue('A'.$i,$num--);
        $sheet->setCellValue('B'.$i,$row['transaction_id']);
        $sheet->setCellValue('C'.$i,$row['transaction_date']);
        $sheet->setCellValue('D'.$i,thai_type($row['transaction_type']));
        $sheet->setCellValue('E'.$i,$row['amount']);
        $sheet->setCellValue('F'.$i,$desc);
        $i++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="transactions.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if($format=='pdf'){
    $html = '<h2 style="text-align:center;">รายการธุรกรรม</h2>';
    $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
    $html .= '<tr>
        <th>ลำดับ</th>
        <th>รหัสรายรับ-รายจ่าย</th>
        <th>วันที่</th>
        <th>ประเภท</th>
        <th>จำนวนเงิน (บาท)</th>
        <th>รายละเอียด</th>
    </tr>';

    $num = count($rows);
    foreach($rows as $row){
        $desc = $row['transaction_type']=='expense' ? ($row['expense_type'] ?? '-') : 'จาก order_detail_id: '.$row['order_detail_id'];
        $html .= '<tr>
            <td>'.$num--.'</td>
            <td>'.$row['transaction_id'].'</td>
            <td>'.$row['transaction_date'].'</td>
            <td>'.thai_type($row['transaction_type']).'</td>
            <td>'.number_format($row['amount'],2).'</td>
            <td>'.$desc.'</td>
        </tr>';
    }
    $html .= '</table>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream("transactions.pdf", ["Attachment" => true]);
    exit;
}
