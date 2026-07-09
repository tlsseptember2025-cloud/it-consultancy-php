<?php

require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_ui.php';
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

drawHeader(
    $pdf,
    'Invoice',
    'INV-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
);

drawSection(
    $pdf,
    'Invoice Information'
);

drawInfoTable(
    $pdf,
    [
        'Invoice Number' =>
            'INV-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Request Number' =>
            'REQ-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Issue Date' =>
            date('d M Y'),

        'Payment Date' =>
            !empty($request['payment_date'])
                ? date('d M Y', strtotime($request['payment_date']))
                : '-',

        'Completion Date' =>
            !empty($request['completed_at'])
                ? date('d M Y', strtotime($request['completed_at']))
                : '-'
    ]
);

drawSection(
    $pdf,
    'Customer Information'
);

drawInfoTable(
    $pdf,
    [
        'Customer' =>
            $request['customer_name'],

        'Service' =>
            $request['service_title'],

        'Email' =>
            $request['email']
    ]
);

drawSection(
    $pdf,
    'Payment Summary'
);

drawAmountBox(
    $pdf,
    'Total Paid',
    'AED ' . number_format($request['quoted_price'], 2)
);

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 14);

$pdf->SetTextColor(0, 150, 0);

$pdf->Cell(
    180,
    8,
    'PAID',
    0,
    1,
    'C'
);

$pdf->SetTextColor(0, 0, 0);

drawSection(
    $pdf,
    'Company Contact'
);

drawInfoTable(
    $pdf,
    [
        'Website' => str_replace(
            ['https://','http://'],
            '',
            COMPANY_WEBSITE
        ),

        'Email' => COMPANY_EMAIL,

        'Phone' => COMPANY_PHONE
    ]
);

$pdf->Ln(2);

drawFooter($pdf);

$pdf->Output('F', $outputPath);
return $outputPath;

}