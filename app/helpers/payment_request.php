<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_layout.php';

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

    drawSectionTitle(
    $pdf,
    'Customer Information'
    );

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, 'Customer:');
    $pdf->Cell(0, 8, $request['customer_name'], 0, 1);

    $pdf->Cell(50, 8, 'Service:');
    $pdf->Cell(0, 8, $request['service_title'], 0, 1);

    $pdf->Ln(5);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Payment Summary', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, 'Total Amount Due:');
    $pdf->Cell(
        0,
        8,
        'AED ' . number_format($request['quoted_price'], 2),
        0,
        1
    );

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Bank Details', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, 'Bank Name:');
    $pdf->Cell(0, 8, BANK_NAME, 0, 1);

    $pdf->Cell(50, 8, 'Account Name:');
    $pdf->Cell(0, 8, BANK_ACCOUNT_NAME, 0, 1);

    $pdf->Cell(50, 8, 'Account Number:');
    $pdf->Cell(0, 8, BANK_ACCOUNT_NUMBER, 0, 1);

    $pdf->Cell(50, 8, 'IBAN:');
    $pdf->Cell(0, 8, BANK_IBAN, 0, 1);

    $pdf->Cell(50, 8, 'SWIFT:');
    $pdf->Cell(0, 8, BANK_SWIFT, 0, 1);

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Payment Reference', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->MultiCell(
        0,
        7,
        'Please use Request No. REQ-' .
        str_pad($request['id'], 6, '0', STR_PAD_LEFT) .
        ' as your payment reference when making the bank transfer.'
    );

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Important Information', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->MultiCell(
        0,
        7,
        "• Please transfer the full payment amount shown above.\n"
        . "• Use the payment reference when making the transfer.\n"
        . "• Upload your payment slip through the Customer Portal after payment.\n"
        . "• Your payment will be verified before your service is scheduled."
    );

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Company Contact Details', 0, 1);

    $pdf->SetFont('Arial', '', 11);

    $pdf->Cell(50, 8, 'Website:');
    $pdf->Cell(0, 8, COMPANY_WEBSITE, 0, 1);

    $pdf->Cell(50, 8, 'Email:');
    $pdf->Cell(0, 8, COMPANY_EMAIL, 0, 1);

    $pdf->Cell(50, 8, 'Phone:');
    $pdf->Cell(0, 8, COMPANY_PHONE, 0, 1);

    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'I', 10);

    $pdf->MultiCell(
        0,
        6,
        "Thank you for choosing " . COMPANY_NAME . ".\n"
        . "We appreciate your business and look forward to serving you."
    );

    $pdf->Output('F', $outputPath);

    return $outputPath;
}
