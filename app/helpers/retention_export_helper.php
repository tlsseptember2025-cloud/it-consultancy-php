<?php

/*
|--------------------------------------------------------------------------
| Retention Export Helper
|--------------------------------------------------------------------------
|
| Creates the permanent PDF record for an archived request
| at final retention disposition.
|
| IMPORTANT:
| - This function does NOT delete the request.
| - Legal Hold blocks final disposition export.
| - The request must be Archived.
| - The request must have reached the 7-year final stage.
|
*/


require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/config/company.php';
require_once __DIR__ . '/pdf_ui.php';


/*
|--------------------------------------------------------------------------
| Validate Retention Export
|--------------------------------------------------------------------------
*/

function canExportRetentionRequest(
    array $request
): bool {

    /*
    |--------------------------------------------------------------------------
    | Must be Archived
    |--------------------------------------------------------------------------
    */

    if (
        ($request['workflow_stage'] ?? '')
        !== 'Archived'
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Active Legal Hold blocks final disposition
    |--------------------------------------------------------------------------
    */

    if (
        (int) (
            $request['legal_hold'] ?? 0
        ) === 1
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Must have completed both one-year extensions
    |--------------------------------------------------------------------------
    |
    | 0 = normal retention
    | 1 = 5-year → 6-year
    | 2 = 6-year → 7-year maximum
    |
    */

    if (
        (int) (
            $request['retention_extension_years'] ?? 0
        ) !== 2
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Retention review must exist
    |--------------------------------------------------------------------------
    */

    if (
        empty($request['retention_review_at'])
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Final review date must have been reached
    |--------------------------------------------------------------------------
    */

    $reviewDate = new DateTime(
        $request['retention_review_at']
    );

    $now = new DateTime();


    if ($now < $reviewDate) {
        return false;
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| Generate Retention Export PDF
|--------------------------------------------------------------------------
*/

function generateRetentionExportPdf(
    array $request,
    array $events,
    string $outputPath
): string {


    /*
    |--------------------------------------------------------------------------
    | Safety Check
    |--------------------------------------------------------------------------
    */

    if (
        !canExportRetentionRequest($request)
    ) {
        throw new RuntimeException(
            'This request is not eligible for retention export.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create PDF
    |--------------------------------------------------------------------------
    */

    $pdf = new \FPDF();

    $pdf->AddPage();


    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    drawHeader(
        $pdf,
        'Retention Archive Record',
        'RET-' . str_pad(
            $request['id'],
            6,
            '0',
            STR_PAD_LEFT
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Export Information
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Export Information'
    );


    drawInfoTable(
        $pdf,
        [

            'Export Number' =>
                'RET-' . str_pad(
                    $request['id'],
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'Request Number' =>
                'REQ-' . str_pad(
                    $request['id'],
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'Export Date' =>
                date('d M Y H:i'),

            'Record Status' =>
                'Archived - Final Retention Review',

            'Legal Hold' =>
                'Not Active'

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Request Information
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Request Information'
    );


    drawInfoTable(
        $pdf,
        [

            'Request ID' =>
                '#' . $request['id'],

            'Service' =>
                $request['service_title'] ?? '-',

            'Status' =>
                $request['status'] ?? '-',

            'Workflow Stage' =>
                $request['workflow_stage'] ?? 'Archived',

            'Request Date' =>
                !empty($request['created_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['created_at']
                        )
                    )
                    : '-',

            'Completed At' =>
                !empty($request['completed_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['completed_at']
                        )
                    )
                    : '-',

            'Archived At' =>
                !empty($request['archived_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['archived_at']
                        )
                    )
                    : '-'

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Customer Information
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Customer Information'
    );


    drawInfoTable(
        $pdf,
        [

            'Customer' =>
                $request['customer_name'] ?? '-',

            'Email' =>
                $request['email'] ?? '-',

            'Phone' =>
                $request['phone'] ?? '-',

            'Company' =>
                !empty($request['company'])
                    ? $request['company']
                    : '-'

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Assigned Agent
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Assigned Agent'
    );


    drawInfoTable(
        $pdf,
        [

            'Agent' =>
                !empty($request['agent_name'])
                    ? $request['agent_name']
                    : 'No agent recorded.'

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Retention Information
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Retention Information'
    );


    drawInfoTable(
        $pdf,
        [

            'Archived At' =>
                !empty($request['archived_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['archived_at']
                        )
                    )
                    : '-',

            'Retention Review At' =>
                !empty($request['retention_review_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['retention_review_at']
                        )
                    )
                    : '-',

            'Retention Expires At' =>
                !empty($request['retention_expires_at'])
                    ? date(
                        'd M Y H:i',
                        strtotime(
                            $request['retention_expires_at']
                        )
                    )
                    : '-',

            'Retention Extensions' =>
                (
                    (int) (
                        $request[
                            'retention_extension_years'
                        ] ?? 0
                    )
                ) . ' year(s)',

            'Final Retention Stage' =>
                '7-Year Maximum'

        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Completion Information
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Completion Information'
    );


    $pdf->SetFont(
        'Arial',
        '',
        10
    );


    $pdf->MultiCell(
        180,
        6,
        !empty(
            $request['completion_notes']
        )
            ? $request['completion_notes']
            : 'No completion notes were recorded.',
        1,
        'L'
    );


    $pdf->Ln(4);


    /*
    |--------------------------------------------------------------------------
    | Request Description
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Request Description'
    );


    $pdf->SetFont(
        'Arial',
        '',
        10
    );


    $pdf->MultiCell(
        180,
        6,
        !empty(
            $request['description']
        )
            ? $request['description']
            : 'No description was recorded.',
        1,
        'L'
    );


    $pdf->Ln(4);


    /*
    |--------------------------------------------------------------------------
    | Retention Timeline
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Retention Timeline'
    );


    $pdf->SetFont(
        'Arial',
        '',
        9
    );


    if (empty($events)) {

        $pdf->MultiCell(
            180,
            6,
            'No request events were recorded.',
            1,
            'L'
        );

    } else {

        foreach ($events as $event) {

            $eventDate = !empty(
                $event['created_at']
            )
                ? date(
                    'd M Y H:i',
                    strtotime(
                        $event['created_at']
                    )
                )
                : '-';


            $eventTitle =
                $event['event_title']
                ?? $event['event_code']
                ?? 'Event';


            $eventDescription =
                $event['event_description']
                ?? '';


            $text =
                $eventDate
                . ' - '
                . $eventTitle;


            if ($eventDescription !== '') {

                $text .=
                    "\n"
                    . $eventDescription;

            }


            $pdf->MultiCell(
                180,
                6,
                $text,
                1,
                'L'
            );


            $pdf->Ln(2);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Final Retention Statement
    |--------------------------------------------------------------------------
    */

    drawSection(
        $pdf,
        'Final Retention Statement'
    );


    $pdf->SetFont(
        'Arial',
        '',
        10
    );


    $pdf->MultiCell(
        180,
        6,
        'This record has reached the 7-year maximum retention period. '
        . 'The request is not currently under Legal Hold. '
        . 'This PDF is an administrative preservation copy and does not '
        . 'delete or modify the original request record.',
        1,
        'L'
    );


    $pdf->Ln(5);


    /*
    |--------------------------------------------------------------------------
    | Export Stamp
    |--------------------------------------------------------------------------
    */

    $pdf->SetFont(
        'Arial',
        'B',
        16
    );


    $pdf->SetTextColor(
        0,
        100,
        0
    );


    $pdf->Cell(
        0,
        10,
        'RETENTION EXPORT',
        0,
        1,
        'R'
    );


    $pdf->SetTextColor(
        0,
        0,
        0
    );


    /*
    |--------------------------------------------------------------------------
    | Footer
    |--------------------------------------------------------------------------
    */

    drawFooter(
        $pdf
    );


    /*
    |--------------------------------------------------------------------------
    | Save PDF
    |--------------------------------------------------------------------------
    */

    $pdf->Output(
        'F',
        $outputPath
    );


    return $outputPath;
}