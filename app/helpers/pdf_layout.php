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
        $pdf->Image(COMPANY_LOGO, 15, 15, 28);
    }

    // Company Name
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetXY(60, 15);
    $pdf->Cell(135, 8, COMPANY_NAME, 0, 1, 'R');

    // Tagline
    $pdf->Cell(
    135,
    6,
    COMPANY_TAGLINE,
    0,
    1,
    'R'
);

    // Website
    $pdf->SetX(60);
    $pdf->Cell(135, 6, str_replace(
    ['https://', 'http://'],
    '',
    COMPANY_WEBSITE
), 0, 1, 'R');

    $pdf->SetX(60);

    $pdf->Cell(
        135,
        6,
        COMPANY_CITY . ', ' . COMPANY_COUNTRY,
        0,
        1,
        'R'
    );

    // Divider
    $pdf->Ln(5);
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->Line(15, 40, 195, 40);
    $pdf->Ln(8);

    // Document Title
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, strtoupper($documentTitle), 0, 1, 'C');

    // Document Number
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Document No. ' . $documentNumber, 0, 1, 'C');

    $pdf->Ln(6);
}

function drawSectionTitle(
    FPDF $pdf,
    string $title
): void
{
    $pdf->SetFillColor(235, 235, 235);
    $pdf->SetDrawColor(200, 200, 200);

    $pdf->SetFont('Arial', 'B', 11);

    $pdf->Cell(180, 8, strtoupper($title), 1, 1, 'L', true);

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