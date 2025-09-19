<?php
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

// กำหนด default font เป็นฟอนต์ไทย
$options->set('defaultFont', 'THSarabunNew');

$dompdf = new Dompdf($options);

// **ไม่ต้อง registerFont() แบบเก่าแล้ว**

?>