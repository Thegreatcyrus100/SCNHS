<?php
require_once('TCPDF-6.8.2/TCPDF-6.8.2/tcpdf.php');

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 14);
$pdf->Cell(0, 10, 'TCPDF is Working!', 0, 1, 'C');
$pdf->Output();
?>
