<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_ui.php';

function generateServiceReportPdf(
    array $request,
    string $outputPath
): string {

    $pdf = new \FPDF();

    $pdf->AddPage();


    drawHeader(
    $pdf,
    'Service Completion Report',
    'RPT-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT)
    );



    drawSection(
    $pdf,
    'Report Information'
);

drawInfoTable(
    $pdf,
    [
        'Report Number' =>
            'RPT-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Request Number' =>
            'REQ-' . str_pad($request['id'], 6, '0', STR_PAD_LEFT),

        'Completion Date' =>
            !empty($request['completed_at'])
                ? date(
                    'd M Y',
                    strtotime($request['completed_at'])
                )
                : date('d M Y'),

        'Status' =>
            'Completed Successfully'
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

    drawSection(
    $pdf,
    'Completion Notes'
);

$pdf->SetFont('Arial', '', 10);

$pdf->MultiCell(
    180,
    6,
    !empty($request['completion_notes'])
        ? $request['completion_notes']
        : 'No completion notes were recorded.',
    1,
    'L'
);

$pdf->Ln(4);

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

    drawFooter($pdf);

    $pdf->Output('F', $outputPath);

    return $outputPath;
}