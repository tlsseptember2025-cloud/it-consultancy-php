<?php

require_once APP_PATH . '/helpers/auth.php';


/*
|--------------------------------------------------------------------------
| Suspension Chat Attachment Controller
|--------------------------------------------------------------------------
| Securely serves attachments uploaded through the suspended-customer
| communication system.
|
| Route:
| ?page=suspension-attachment&id=123
|
| The file is never served directly from the storage folder.
|--------------------------------------------------------------------------
*/


$attachmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$attachmentId) {

    http_response_code(400);

    exit('Invalid attachment.');
}


/*
|--------------------------------------------------------------------------
| Detect Logged-In Role and Select Database
|--------------------------------------------------------------------------
*/

$isDemo =
    isset($_SESSION['demo_user']) ||
    isset($_SESSION['demo_super_admin']) ||
    isset($_SESSION['demo_customer']);

$pdoToUse = null;

$currentCustomerId = null;
$currentAdminId = null;

$isCustomer = false;
$isAdmin = false;


/*
|--------------------------------------------------------------------------
| DEMO
|--------------------------------------------------------------------------
*/

if ($isDemo) {

    require_once CONFIG_PATH . '/demo-database.php';


    /*
    |--------------------------------------------------------------------------
    | Demo Customer
    |--------------------------------------------------------------------------
    */

    if (isset($_SESSION['demo_customer'])) {

        requireDemoCustomer();

        $isCustomer = true;

        $currentCustomerId =
            (int) ($_SESSION['demo_customer']['id'] ?? 0);

        $pdoToUse = $demoPdo;
    }


    /*
    |--------------------------------------------------------------------------
    | Demo Admin
    |--------------------------------------------------------------------------
    */

    elseif (isset($_SESSION['demo_user'])) {

        requireDemoAdmin();

        $isAdmin = true;

        $currentAdminId =
            (int) ($_SESSION['demo_user']['id'] ?? 0);

        $pdoToUse = $demoPdo;
    }


    /*
    |--------------------------------------------------------------------------
    | Demo Super Admin
    |--------------------------------------------------------------------------
    */

    elseif (isset($_SESSION['demo_super_admin'])) {

        requireDemoSuperAdmin();

        $isAdmin = true;

        $currentAdminId =
            (int) ($_SESSION['demo_super_admin']['id'] ?? 0);

        $pdoToUse = $demoPdo;
    }
}


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

else {


    /*
    |--------------------------------------------------------------------------
    | Main Customer
    |--------------------------------------------------------------------------
    */

    if (isset($_SESSION['customer'])) {

        requireCustomerLogin();

        require_once CONFIG_PATH . '/database.php';

        $isCustomer = true;

        $currentCustomerId =
            (int) ($_SESSION['customer']['id'] ?? 0);

        $pdoToUse = $pdo;
    }


    /*
    |--------------------------------------------------------------------------
    | Main Admin
    |--------------------------------------------------------------------------
    |
    | Main Admin login currently stores the administrator email in
    | $_SESSION['user'], not the complete users row.
    |
    | Therefore we retrieve the actual users.id using that email.
    |--------------------------------------------------------------------------
    */

    elseif (isset($_SESSION['user'])) {

        requireAdminLogin();

        require_once CONFIG_PATH . '/database.php';

        $isAdmin = true;

        $adminEmail = trim(
            (string) ($_SESSION['user'] ?? '')
        );

        if ($adminEmail === '') {

            header('Location: ?page=login');
            exit;
        }


        $adminStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $adminStmt->execute([
            $adminEmail
        ]);

        $currentAdminId =
            (int) $adminStmt->fetchColumn();


        if ($currentAdminId <= 0) {

            http_response_code(403);

            exit(
                'Main Admin account could not be identified.'
            );
        }


        $pdoToUse = $pdo;
    }
}


/*
|--------------------------------------------------------------------------
| No Valid Authenticated Session
|--------------------------------------------------------------------------
*/

if (
    !$pdoToUse ||
    (!$isCustomer && !$isAdmin)
) {

    http_response_code(403);

    exit('Access denied.');
}


/*
|--------------------------------------------------------------------------
| Load Attachment and Conversation Ownership
|--------------------------------------------------------------------------
*/

$stmt = $pdoToUse->prepare("
    SELECT
        a.id,
        a.original_name,
        a.stored_name,
        a.file_path,
        a.mime_type,
        a.file_size,
        c.id AS conversation_id,
        c.customer_id,
        c.status AS conversation_status
    FROM customer_suspension_attachments a
    INNER JOIN customer_suspension_messages m
        ON m.id = a.message_id
    INNER JOIN customer_suspension_conversations c
        ON c.id = m.conversation_id
    WHERE a.id = ?
    LIMIT 1
");

$stmt->execute([
    $attachmentId
]);

$attachment = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$attachment) {

    http_response_code(404);

    exit('Attachment not found.');
}


/*
|--------------------------------------------------------------------------
| CUSTOMER AUTHORIZATION
|--------------------------------------------------------------------------
|
| A customer may only access attachments belonging to their own
| suspension conversation.
|--------------------------------------------------------------------------
*/

if ($isCustomer) {

    if (
        (int) $attachment['customer_id']
        !== $currentCustomerId
    ) {

        http_response_code(403);

        exit('Access denied.');
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN AUTHORIZATION
|--------------------------------------------------------------------------
|
| Main Admin:
|   Can access Main suspension-chat attachments.
|
| Demo Admin:
|   Can access only attachments belonging to their Demo tenant.
|
| Demo Super Admin:
|   Can access Demo attachments.
|--------------------------------------------------------------------------
*/

if (
    $isAdmin &&
    $isDemo &&
    isset($_SESSION['demo_user'])
) {

    $demoTenantId = (int) (
        $_SESSION['demo_user']['demo_tenant_id']
        ?? 0
    );


    if ($demoTenantId <= 0) {

        http_response_code(403);

        exit('Access denied.');
    }


    $tenantCheck = $pdoToUse->prepare("
        SELECT id
        FROM customers
        WHERE id = ?
          AND demo_tenant_id = ?
          AND is_demo_account = 1
        LIMIT 1
    ");

    $tenantCheck->execute([
        (int) $attachment['customer_id'],
        $demoTenantId
    ]);


    if (!$tenantCheck->fetchColumn()) {

        http_response_code(403);

        exit('Access denied.');
    }
}


/*
|--------------------------------------------------------------------------
| Resolve Physical File Path
|--------------------------------------------------------------------------
*/

$storedName = basename(
    $attachment['stored_name']
);

$storageRoot =
    dirname(__DIR__, 2)
    . '/storage/suspension-attachments';

$filePath =
    $storageRoot
    . '/'
    . $storedName;


/*
|--------------------------------------------------------------------------
| Verify Physical File Exists
|--------------------------------------------------------------------------
*/

if (!is_file($filePath)) {

    http_response_code(404);

    exit('Attachment file not found.');
}


/*
|--------------------------------------------------------------------------
| Security Check
|--------------------------------------------------------------------------
|
| Make sure the resolved file remains inside the intended storage folder.
|--------------------------------------------------------------------------
*/

$realStorageRoot =
    realpath($storageRoot);

$realFilePath =
    realpath($filePath);


if (
    $realStorageRoot === false ||
    $realFilePath === false ||
    strpos(
        $realFilePath,
        $realStorageRoot . DIRECTORY_SEPARATOR
    ) !== 0
) {

    http_response_code(403);

    exit('Access denied.');
}


/*
|--------------------------------------------------------------------------
| Determine MIME Type from Actual File
|--------------------------------------------------------------------------
*/

$mimeType =
    'application/octet-stream';


if (function_exists('finfo_open')) {

    $finfo = finfo_open(
        FILEINFO_MIME_TYPE
    );


    if ($finfo) {

        $detectedMime =
            finfo_file(
                $finfo,
                $realFilePath
            );


        if ($detectedMime) {

            $mimeType =
                $detectedMime;
        }


        finfo_close($finfo);
    }
}


/*
|--------------------------------------------------------------------------
| Allow Only Supported Attachment Types
|--------------------------------------------------------------------------
*/

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'application/pdf'
];


if (
    !in_array(
        $mimeType,
        $allowedMimeTypes,
        true
    )
) {

    http_response_code(403);

    exit('File type not allowed.');
}


/*
|--------------------------------------------------------------------------
| Headers
|--------------------------------------------------------------------------
*/

$downloadName =
    basename(
        $attachment['original_name']
    );


if ($downloadName === '') {

    $downloadName =
        'attachment';
}


$downloadName = str_replace(
    [
        "\r",
        "\n",
        '"'
    ],
    '',
    $downloadName
);


header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Content-Type: ' . $mimeType
);

header(
    'Content-Length: '
    . filesize($realFilePath)
);


/*
|--------------------------------------------------------------------------
| Display Supported Images/PDFs in Browser
|--------------------------------------------------------------------------
*/

header(
    'Content-Disposition: inline; filename="'
    . $downloadName
    . '"'
);


/*
|--------------------------------------------------------------------------
| Output File
|--------------------------------------------------------------------------
*/

readfile(
    $realFilePath
);

exit;