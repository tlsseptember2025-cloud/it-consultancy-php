<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function generateInvoicePdf(
    array $request,
    string $outputPath
): string {

    $pdf = new \FPDF();

    $pdf->AddPage();

    // ======================================
// Company Header
// ======================================

// Optional logo
$logo = dirname(__DIR__, 2) . '/public/uploads/assets/logo.png';

if (file_exists($logo)) {

    $pdf->Image($logo, 15, 15, 50);

}

$pdf->SetFont('Arial', 'B', 20);

$pdf->Cell(
    0,
    8,
    'IT Consultancy',
    0,
    1,
    'R'
);

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(
    0,
    5,
    'Abu Dhabi, UAE',
    0,
    1,
    'R'
);

$pdf->Cell(
    0,
    5,
    'Email: ramiwahdan2023@gmail.com',
    0,
    1,
    'R'
);

$pdf->Cell(
    0,
    5,
    'Phone: +971 50 122 8293',
    0,
    1,
    'R'
);

$pdf->Ln(10);

// ======================================
// Invoice Title
// ======================================

$pdf->SetFont('Arial', 'B', 14);

$pdf->Cell(
    0,
    10,
    'INVOICE',
    0,
    1,
    'C'
);

$pdf->Ln(5);

    $pdf->Cell(0, 8, 'Invoice #: INV-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT), 0, 1);

    $pdf->Cell(
    0,
    8,
    'Request ID: #' . $request['id'],
    0,
    1
);

$pdf->Cell(
    0,
    8,
    'Issue Date: ' . date('M d, Y'),
    0,
    1
);

if (!empty($request['payment_date'])) {

    $pdf->Cell(
        0,
        8,
        'Payment Date: ' .
        date(
            'M d, Y',
            strtotime($request['payment_date'])
        ),
        0,
        1
    );

}

if (!empty($request['completed_at'])) {

    $pdf->Cell(
        0,
        8,
        'Completion Date: ' .
        date(
            'M d, Y',
            strtotime($request['completed_at'])
        ),
        0,
        1
    );

}

// Separator before customer information
$pdf->Ln(3);

$pdf->Cell(
    190,
    0,
    '',
    'T'
);

$pdf->Ln(6);

    // ======================================
// Customer Information
// ======================================

$pdf->SetFont('Arial', 'B', 13);

$pdf->Cell(0, 8, 'Bill To', 0, 1);

$pdf->SetFont('Arial', '', 12);

$pdf->Cell(
    0,
    8,
    $request['customer_name'],
    0,
    1
);

if (!empty($request['email'])) {

    $pdf->Cell(
        0,
        8,
        'Email: ' . $request['email'],
        0,
        1
    );

}

$pdf->Ln(6);

// ======================================
// Invoice Table
// ======================================

$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(140, 10, 'Description', 1);

$pdf->Cell(50, 10, 'Amount', 1, 1, 'R');

$pdf->SetFont('Arial', '', 11);

$pdf->Cell(
    140,
    10,
    $request['service_title'],
    1
);

$pdf->Cell(
    50,
    10,
    'AED' . number_format($request['quoted_price'], 2),
    1,
    1,
    'R'
);

$pdf->SetFont('Arial', 'B', 11);

$pdf->Cell(
    140,
    10,
    'Total',
    1
);

$pdf->Cell(
    50,
    10,
    'AED' . number_format($request['quoted_price'], 2),
    1,
    1,
    'R'
);

// ======================================
// Payment Status
// ======================================

$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(0, 150, 0);

$pdf->Cell(
    0,
    10,
    'PAID',
    0,
    1,
    'R'
);

// Reset text color to black
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(15);

$pdf->Cell(
    190,
    0,
    '',
    'T'
);

$pdf->Ln(8);

$pdf->SetFont('Arial', 'I', 9);

$pdf->MultiCell(
    0,
    5,
    'This invoice was generated electronically and is valid without a signature.'
);

$pdf->Ln(3);

$pdf->MultiCell(
    0,
    5,
    'Thank you for choosing IT Consultancy. We appreciate your business and look forward to serving you again.'
);

    $pdf->Output('F', $outputPath);
    return $outputPath;
}