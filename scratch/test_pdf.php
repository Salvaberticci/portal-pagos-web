<?php
require '../dompdf/vendor/autoload.php';
use Dompdf\Dompdf;
$dompdf = new Dompdf();
$dompdf->loadHtml('<h1>Test PDF</h1>');
$dompdf->render();
$dompdf->stream("test.pdf", ["Attachment" => false]);
?>
