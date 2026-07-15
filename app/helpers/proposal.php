<?php

require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_ui.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function generateProposalPdf(
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
    'Proposal',
    'PRO-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
);

drawSection(
    $pdf,
    'Proposal Information'
);

drawInfoTable(
    $pdf,
    [

        'Proposal Number' =>
            'PRO-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Request Number' =>
            'REQ-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Proposal Date' =>
            date('d M Y'),

        'Workflow Stage' =>
            $request['workflow_stage']

    ]
);

drawSection(
    $pdf,
    'Customer Information'
);

$customerInfo = [
    'Customer' => $request['name'],
    'Email'    => $request['email']
];

if (!empty($request['company'])) {
    $customerInfo['Company'] = $request['company'];
}

if (!empty($request['phone'])) {
    $customerInfo['Phone'] = $request['phone'];
}

drawInfoTable($pdf, $customerInfo);

drawSection(
    $pdf,
    'Service Information'
);

drawInfoTable(
    $pdf,
    [

        'Service' =>
            $request['service_title'],

        'Status' =>
            $request['status']

    ]
);

drawSection(
    $pdf,
    'Request Description'
);

$pdf->SetFont('Arial', '', 10);

$pdf->MultiCell(
    180,
    7,
    $request['description'],
    1
);

$pdf->Ln(1);

drawSection(
    $pdf,
    'Quotation'
);

drawAmountBox(
    $pdf,
    'Quoted Price',
    'AED ' . number_format($request['quoted_price'], 2)
);

drawSection(
    $pdf,
    'Proposal'
);

$pdf->SetFont('Arial', '', 10);

$pdf->MultiCell(
    180,
    7,
    $request['proposal'],
    1
);

$pdf->Ln(1);

$paymentInfo = [

    'Bank Name' =>
        BANK_NAME,

    'Account Name' =>
        BANK_ACCOUNT_NAME

];

if (!empty(BANK_ACCOUNT_NUMBER)) {
    $paymentInfo['Account Number'] = BANK_ACCOUNT_NUMBER;
}

if (!empty(BANK_IBAN)) {
    $paymentInfo['IBAN'] = BANK_IBAN;
}

if (!empty(BANK_SWIFT)) {
    $paymentInfo['SWIFT Code'] = BANK_SWIFT;
}

drawInfoTable(
    $pdf,
    $paymentInfo
);

drawSection(
    $pdf,
    'Company Contact'
);

drawInfoTable(
    $pdf,
    [

        'Website' => str_replace(
            ['https://', 'http://'],
            '',
            COMPANY_WEBSITE
        ),

        'Email' => COMPANY_EMAIL,

        'Phone' => COMPANY_PHONE

    ]
);$pdf->Ln(1);

drawFooter($pdf);

$pdf->Output('F', $outputPath);

return $outputPath;

}