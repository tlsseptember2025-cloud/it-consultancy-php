<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function generateInvoicePdf(
    array $request,
    string $outputPath
): string {

    $pdf = new \FPDF();

    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');

    $pdf->Ln(8);

    // Company
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'IT Consultancy', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(0, 8, 'Invoice #: INV-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT), 0, 1);

    $pdf->Cell(0, 8, 'Customer: ' . $request['customer_name'], 0, 1);

    $pdf->Cell(0, 8, 'Service: ' . $request['service_title'], 0, 1);

    $pdf->Cell(0, 8, 'Amount: $' . number_format($request['quoted_price'], 2), 0, 1);

    $pdf->Cell(0, 8, 'Status: PAID', 0, 1);

    $pdf->Ln(10);

    $pdf->MultiCell(
        0,
        8,
        'Thank you for choosing IT Consultancy. This document serves as your official invoice and payment confirmation.'
    );

    $pdf->Output('F', $outputPath);

    return $outputPath;
}