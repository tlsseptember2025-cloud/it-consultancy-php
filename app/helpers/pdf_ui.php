<?php

/*
|--------------------------------------------------------------------------
| PDF Layout Helper
|--------------------------------------------------------------------------
| Shared layout functions for:
| - Proposal
| - Payment Request
| - Invoice
| - Service Report
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2) . '/config/company.php';

function drawHeader(
    FPDF $pdf,
    string $documentTitle,
    string $documentNumber
): void
{
    // Logo
    if (file_exists(COMPANY_LOGO)) {
        $pdf->Image(COMPANY_LOGO, 15, 15, 35);
    }

    // Company Name
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetXY(60, 15);
    $pdf->Cell(135, 8, COMPANY_NAME, 0, 1, 'R');

    $pdf->SetFont('Arial', '', 9);

$pdf->SetX(60);

$pdf->Cell(
    135,
    5,
    'Simplifying Technology',
    0,
    1,
    'R'
);

$pdf->SetX(60);

$pdf->Cell(
    135,
    5,
    'Empowering Business',
    0,
    1,
    'R'
);

    // Tagline
    

    // Website
    $pdf->SetX(60);
    $pdf->Cell(135, 5, str_replace(
    ['https://', 'http://'],
    '',
    COMPANY_WEBSITE
), 0, 1, 'R');

    $pdf->SetX(60);

    $pdf->Cell(
        135,
        5,
        COMPANY_CITY . ', ' . COMPANY_COUNTRY,
        0,
        1,
        'R'
    );

    // Divider
    $pdf->Ln(5);
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->Line(
    15,
    46,
    195,
    46
);
    $pdf->Ln(2);

    // Document Title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, strtoupper($documentTitle), 0, 1, 'C');

    // Document Number
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Document No. ' . $documentNumber, 0, 1, 'C');

    $pdf->Ln(1);
}

function drawSection(
    FPDF $pdf,
    string $title
): void
{
    // Section background
    $pdf->SetFillColor(25, 55, 109);

    // White text
    $pdf->SetTextColor(255, 255, 255);

    // Font
    $pdf->SetFont('Arial', 'B', 11);

    // Blue bar
    $pdf->Cell(
        180,
        8,
        strtoupper($title),
        0,
        1,
        'L',
        true
    );

    // Back to black text
    $pdf->SetTextColor(0, 0, 0);

}

function drawInfoTable(
    FPDF $pdf,
    array $rows
): void
{
    $pdf->SetFont('Arial', '', 10);

    foreach ($rows as $label => $value) {

        // Left column
        $pdf->SetFont('Arial', 'B', 10);

        $pdf->Cell(
            55,
            7,
            $label,
            1,
            0,
            'L'
        );

        // Right column
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(
            125,
            7,
            $value,
            1,
            1,
            'L'
        );

    }

    $pdf->Ln(1);
}

function drawAmountBox(
    FPDF $pdf,
    string $title,
    string $amount
): void
{
    // Blue border
    $pdf->SetDrawColor(25, 55, 109);

    // White background
    $pdf->SetFillColor(255, 255, 255);

    // Outer box
    $pdf->Cell(
        180,
        24,
        '',
        1,
        1,
        'C',
        true
    );

    // Go back inside the box
    $pdf->SetY($pdf->GetY() - 21);

    // Title
    $pdf->SetFont('Arial', 'B', 12);

    $pdf->Cell(
        180,
        8,
        strtoupper($title),
        0,
        1,
        'C'
    );

    // Amount
    $pdf->SetFont('Arial', 'B', 22);

    $pdf->SetTextColor(25, 55, 109);

    $pdf->Cell(
        180,
        10,
        $amount,
        0,
        1,
        'C'
    );

    // Back to normal
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Ln(2);
}

function drawInfoRow(
    FPDF $pdf,
    string $label,
    string $value
): void
{
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(50, 8, $label, 1, 0);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(130, 8, $value, 1, 1);
}

function drawReferenceBox(
    FPDF $pdf,
    string $reference
): void
{
    checkPageBreak($pdf, 35);
    drawSection(
        $pdf,
        'Payment Reference'
    );

    $pdf->SetDrawColor(25,55,109);

    $pdf->Cell(
        180,
        18,
        '',
        1,
        1
    );

    
$pdf->SetY(
    $pdf->GetY()-16
    );
    

    $pdf->SetFont(
        'Arial',
        'B',
        18
    );

    $pdf->Cell(
        180,
        10,
        $reference,
        0,
        1,
        'C'
    );

    $pdf->Ln(2);

    $pdf->SetFont(
        'Arial',
        '',
        9
    );

    $pdf->Ln(1);
}

function checkPageBreak(
    FPDF $pdf,
    float $requiredHeight
): void
{
    if ($pdf->GetY() + $requiredHeight > 270) {
        $pdf->AddPage();
    }
}

function drawFooter(
    FPDF $pdf
): void
{
    $pdf->Ln(1);

$pdf->SetDrawColor(180,180,180);

$pdf->Line(
    15,
    $pdf->GetY(),
    195,
    $pdf->GetY()
);

$pdf->Ln(2);

    $pdf->SetFont(
        'Arial',
        'I',
        9
    );

    $pdf->Cell(
        180,
        4,
        'Thank you for choosing ' . COMPANY_NAME . '  |  ' . COMPANY_TAGLINE,
        0,
        1,
        'C'
    );
}