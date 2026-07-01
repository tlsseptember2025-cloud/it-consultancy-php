<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function generateServiceReportPdf(
    array $request,
    string $outputPath
): string {

    $pdf = new \FPDF();

    $pdf->AddPage();

    // ======================================
    // Company Header
    // ======================================

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
    // Report Title
    // ======================================

    $pdf->SetFont('Arial', 'B', 14);

    $pdf->Cell(
        0,
        10,
        'SERVICE COMPLETION REPORT',
        0,
        1,
        'C'
    );

    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(
        0,
        8,
        'Report #: RPT-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),
        0,
        1
    );

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
    'Completion Date: ' .
    (
        !empty($request['completed_at'])
            ? date(
                'M d, Y',
                strtotime($request['completed_at'])
            )
            : date('M d, Y')
    ),
    0,
    1
);

$pdf->Cell(
    0,
    8,
    'Status: Completed Successfully',
    0,
    1
);

    $pdf->Ln(3);

    $pdf->Cell(
        190,
        0,
        '',
        'T'
    );

    $pdf->Ln(6);

    // ======================================
    // Customer
    // ======================================

    $pdf->SetFont('Arial', 'B', 13);

    $pdf->Cell(
        0,
        8,
        'Customer',
        0,
        1
    );

    $pdf->SetFont('Arial', '', 11);

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

    $pdf->Cell(
        0,
        8,
        'Service: ' . $request['service_title'],
        0,
        1
    );

    $pdf->Ln(5);

    // ======================================
    // Completion Notes
    // ======================================

    $pdf->SetFont('Arial', 'B', 12);

    $pdf->Cell(
        0,
        8,
        'Completion Notes',
        0,
        1
    );

    $pdf->SetFont('Arial', '', 11);

    $pdf->MultiCell(
        0,
        6,
        !empty($request['completion_notes'])
            ? $request['completion_notes']
            : 'No completion notes were recorded.'
    );

    // ======================================
    // Completed Stamp
    // ======================================

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 18);

    $pdf->SetTextColor(0, 150, 0);

    $pdf->Cell(
        0,
        10,
        'SERVICE COMPLETED',
        0,
        1,
        'R'
    );

    $pdf->SetTextColor(0, 0, 0);

    // ======================================
    // Footer
    // ======================================

    $pdf->Ln(10);

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
        'This service completion report was generated electronically and is valid without a signature.'
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