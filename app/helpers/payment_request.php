<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_ui.php';

function generatePaymentRequestPdf(
    array $request,
    string $outputPath
): string {

    $pdf = new \FPDF();
    $pdf->AddPage();

    drawHeader(
    $pdf,
    'Payment Request',
    'PAY-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, 'Payment Request No.:');
    $pdf->Cell(0, 8, 'PAY-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT), 0, 1);

    $pdf->Cell(50, 8, 'Request No.:');
    $pdf->Cell(0, 8, 'REQ-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT), 0, 1);

    $pdf->Cell(50, 8, 'Issue Date:');
    $pdf->Cell(0, 8, date('d M Y'), 0, 1);

    $pdf->Ln(5);

    drawSection(
    $pdf,
    'Customer Information'
    );

    drawInfoTable(
    $pdf,
    [
        'Customer'       => $request['customer_name'],
        'Service'        => $request['service_title'],
        'Issue Date'     => date('d M Y'),
        'Request Number' => 'REQ-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
    ]
);
    
    drawSection(
    $pdf,
    'Payment Summary'
);

drawAmountBox(
    $pdf,
    'Total Amount Due',
    'AED ' . number_format($request['quoted_price'], 2)
);




    $pdf->Ln(5);

    drawSection(
    $pdf,
    'Bank Details'
);

drawInfoTable(
    $pdf,
    [
        'Bank Name'      => BANK_NAME,
        'Account Name'   => BANK_ACCOUNT_NAME,
        'Account Number' => BANK_ACCOUNT_NUMBER,
        'IBAN'           => BANK_IBAN,
        'SWIFT'          => BANK_SWIFT
    ]
);

    $pdf->Ln(5);

    drawReferenceBox(
    $pdf,
    'REQ-' . str_pad($request['id'],6,'0',STR_PAD_LEFT)
);

    $pdf->Ln(8);

    drawSection(
    $pdf,
    'Important Information'
);

$pdf->SetFont('Arial', '', 10);

$pdf->MultiCell(
    180,
    6,
    "1. Transfer the full payment amount shown above."
);

$pdf->MultiCell(
    180,
    6,
    "2. Use the payment reference when making the transfer."
);

$pdf->MultiCell(
    180,
    6,
    "3. Upload your payment slip through the Customer Portal after payment."
);

$pdf->MultiCell(
    180,
    6,
    "4. Your payment will be verified before your service is scheduled."
);

$pdf->Ln(4);

    $pdf->Ln(5);

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

    $pdf->Ln(10);

   drawFooter($pdf);

    $pdf->Output('F', $outputPath);

    return $outputPath;
}
