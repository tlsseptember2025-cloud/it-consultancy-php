<?php

/*
==============================================================================
ROUTES ORGANIZATION
------------------------------------------------------------------------------
1. Public Website
2. Authentication
3. Customer Portal
4. Admin Portal
   - Dashboard
   - Customers
   - Agents
   - Services
   - Requests
   - Payments
   - Refunds
   - Consultation Scheduling
   - Service Scheduling
   - Messages & Notifications
   - Reports / Backup
5. Agent Portal
6. Default (404)
==============================================================================

*/

require_once CONFIG_PATH . '/database.php';

/**
 * Determine application context from the hostname.
 */
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

$isDemoEnvironment = (
    $host === 'demo.wahbibconsultancy.com'
);

if (
    $isDemoEnvironment &&
    !isset($demoPdo)
) {
    require_once CONFIG_PATH . '/demo-database.php';
}

$page = $_GET['page'] ?? 'home';

/**
 * Demo Environment Route Protection
 *
 * Demo is a completely separate application environment.
 * Access is controlled by the Demo session role.
 */
if ($isDemoEnvironment) {

    /**
     * Public Demo routes
     */
    $demoPublicRoutes = [
        'home',
        'demo-login',
        'demo-change-password',
        'customer-forgot-password',
        'customer-reset-password',
        'rules',
    ];

    /**
     * Routes available to Demo Super Admin
     */
    $demoSuperAdminRoutes = [
        'dashboard',
        'demo-super-admin',
        'admin-suspension-chat',
        'logout',
        'suspension-attachment',
        'notifications',
        'open-notification',
        'mark-all-notifications-read',
        'messages',
        'archived-messages',
        'services-admin',
        'add-service',
        'edit-service',
        'delete-service',
        'pricing',
        'add-pricing',
        'edit-pricing',
        'delete-pricing',
        'customers',
        'view-customer',
        'agents',
        'view-agent',
        'requests',
        'view-request',
        'closed-requests',
        'review-closed-request',
        'archived-requests',
        'view-archived-request',
        'retention-review',
        'review-retention',
        'export-retention',
        'refunds',
        'refund-requests',
        'complete-refund',
        'archived-refunds',
        'view-refund',
        'payments',
        'view-payment',
        'edit-payment',
        'delete-payment',
        'consultation-slots',
        'review-consultation',
        'admin-review-consultation',
        'admin-final-approve-consultation',
        'admin-reschedule-consultation',
        'admin-assign-agent',
        'admin-contact-customer',
        'admin-close-request',
        'service-slots',
        'review-service',
        'approve-service',
        'reject-service',
        'approve-service-schedule',
        'complete-service',
        'review-refund',
        'missed-consultation-approvals',
        'review-missed-consultation',
        'visitor-message',
        'close-conversation',
        'create-proposal',
        'view-proposal',
        'admin-view-proposal',
        'send-proposal',
        'contract-leads',
        'update-contract-lead',
        'archive-contract-lead',
        'pending-contract-leads',
        'view-contract-lead',
        'complete-service-form',
    ];

    /**
     * Routes available to Demo Admin
     */
    $demoAdminRoutes = [
        'dashboard',
        'demo-setup',
        'customers',
        'view-customer',
        'suspension-attachment',
        'admin-suspension-chat',
        'agents',
        'logout',
        'notifications',
        'open-notification',
        'mark-all-notifications-read',
        'messages',
        'archived-messages',

        'services-admin',
        'add-service',
        'edit-service',
        'delete-service',

        'pricing',
        'add-pricing',
        'edit-pricing',
        'delete-pricing',

        'customers',
        'view-customer',

        'agents',
        'view-agent',

        'requests',
        'add-request',
        'view-request',
        'edit-request',
        'closed-requests',
        'review-closed-request',
        'archived-requests',
        'view-archived-request',
        'retention-review',
        'review-retention',
        'export-retention',

        'refunds',
        'add-refund',
        'refund-requests',
        'complete-refund',
        'archived-refunds',
        'view-refund',

        'payments',
        'view-payment',
        'edit-payment',
        'delete-payment',

        'consultation-slots',
        'review-consultation',
        'approve-consultation',
        'confirm-consultation-booking',
        'reject-consultation',
        'complete-consultation',
        'admin-review-consultation',
        'admin-final-approve-consultation',
        'admin-reschedule-consultation',
        'admin-assign-agent',
        'admin-contact-customer',
        'admin-close-request',

        'service-slots',
        'approve-service-schedule',
        'review-service',
        'approve-service',
        'reject-service',
        'complete-service',
        'review-refund',

        'missed-consultation-approvals',
        'review-missed-consultation',

        'visitor-message',
        'close-conversation',
        'create-proposal',
        'admin-view-proposal',
        'send-proposal',

        'contract-leads',
        'update-contract-lead',
        'archive-contract-lead',
        'pending-contract-leads',
        'view-contract-lead',

        'complete-service-form',
    ];

    /**
     * Routes available to Demo Agent
     */
    $demoAgentRoutes = [
        'agent-dashboard',
        'agent-logout',
        'agent-change-password',
        'agent-reset-password',
        'agent-jobs',
        'view-service-job',
        'contact-customer',
        'consultation-closure-agreement',
        'approved-closures',
        'complete-consultation-closure',
        'explain-overdue-consultation',
        'explain-missed-service',
        'respond-service-review',
        'agent-consultations',
        'view-consultation',
        'cannot-complete-consultation',
        'explain-missed-consultation',
        'agent-profile',
        'agent-notifications',
        'agent-open-notification',
        'agent-mark-all-notifications-read',
        'view-service-job',
    ];

    /**
     * Routes available to Demo Customer
     */
    $demoCustomerRoutes = [
    'customer-dashboard',
    'customer-suspension-chat',
    'suspension-attachment',
    'customer-logout',
    'customer-profile',
    'customer-requests',
    'customer-view-inactive-request',
    'customer-request-service',
    'customer-request-refund',
    'customer-payments',
    'customer-refunds',
    'customer-upload-slip',
    'customer-notifications',
    'schedule-consultation',
    'confirm-consultation',
    'reschedule-consultation',
    'confirm-reschedule-consultation',
    'refund-history',
    'customer-view-refund',
    'schedule-service',
    'confirm-service',
    'reschedule-service',
    'confirm-reschedule-service',
    'confirm-service-completion',
    'confirm-consultation-completion',
    'customer-rate-agent',
    'view-proposal',
    'accept-proposal-confirm',
    'reject-proposal',
];

    /**
     * Public routes do not require authentication.
     */
    if (in_array($page, $demoPublicRoutes, true)) {

        // Continue normally.

    } else {

        /**
         * Determine the authenticated Demo role.
         */
        $demoRole = null;

        if (isset($_SESSION['demo_super_admin'])) {
            $demoRole = 'super_admin';
        } elseif (isset($_SESSION['demo_user'])) {
            $demoRole = 'admin';
        } elseif (isset($_SESSION['demo_customer'])) {
            $demoRole = 'customer';
        } elseif (isset($_SESSION['demo_agent'])) {
            $demoRole = 'agent';
        }

        /**
         * No Demo authentication.
         */
        if ($demoRole === null) {
            header('Location: ?page=demo-login');
            exit;
        }

        /**
         * Check whether the requested route is allowed
         * for the authenticated Demo role.
         */
        $allowedRoutes = match ($demoRole) {
            'super_admin' => $demoSuperAdminRoutes,
            'admin'       => $demoAdminRoutes,
            'customer'    => $demoCustomerRoutes,
            'agent'       => $demoAgentRoutes,
            default       => [],
        };

        /**
         * Deny unauthorized Demo routes.
         */
        if (!in_array($page, $allowedRoutes, true)) {
            http_response_code(403);
            echo '403 - Access denied';
            exit;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Suspended Customer Route Protection
|--------------------------------------------------------------------------
|
| Suspended customers may access only:
|
| - Suspension Chat
| - Suspension Chat Attachments
| - Customer Logout
| - Payment Slip Upload when "Payment Required" is active
|
| All normal customer portal routes are blocked.
|--------------------------------------------------------------------------
*/

$isSuspendedCustomer = false;
$hasPaymentRequiredSuspension = false;

if (
    isset($_SESSION['customer']) ||
    isset($_SESSION['demo_customer'])
) {

    /*
     * Determine which database and customer ID to use.
     */
    if ($isDemoEnvironment && isset($_SESSION['demo_customer'])) {

        $suspendedCustomerId =
            (int) $_SESSION['demo_customer']['id'];

        $suspensionPdo = $demoPdo;

        $customerStatusStmt = $suspensionPdo->prepare("
            SELECT status
            FROM customers
            WHERE id = ?
              AND is_demo_account = 1
            LIMIT 1
        ");

        $customerStatusStmt->execute([
            $suspendedCustomerId
        ]);

    } elseif (!$isDemoEnvironment && isset($_SESSION['customer'])) {

        $suspendedCustomerId =
            (int) $_SESSION['customer']['id'];

        $suspensionPdo = $pdo;

        $customerStatusStmt = $suspensionPdo->prepare("
            SELECT status
            FROM customers
            WHERE id = ?
            LIMIT 1
        ");

        $customerStatusStmt->execute([
            $suspendedCustomerId
        ]);

    } else {

        $suspendedCustomerId = 0;
        $suspensionPdo = null;
        $customerStatusStmt = null;
    }


    /*
     * Check the customer's current database status.
     *
     * This deliberately does not rely only on the session because
     * an Admin may suspend or reactivate the customer while the
     * customer's browser session is still active.
     */
    if ($customerStatusStmt) {

        $currentCustomerStatus =
            $customerStatusStmt->fetchColumn();

        if ($currentCustomerStatus === 'Suspended') {

            $isSuspendedCustomer = true;


            /*
             * Check whether Payment Required is currently active.
             */
            $paymentRequiredStmt = $suspensionPdo->prepare("
                SELECT COUNT(*)
                FROM customer_suspensions
                WHERE customer_id = ?
                  AND reason = 'Payment Required'
                  AND active = 1
            ");

            $paymentRequiredStmt->execute([
                $suspendedCustomerId
            ]);

            $hasPaymentRequiredSuspension =
                ((int) $paymentRequiredStmt->fetchColumn() > 0);
        }
    }
}


/*
|--------------------------------------------------------------------------
| Enforce Suspended Customer Access
|--------------------------------------------------------------------------
*/

if ($isSuspendedCustomer) {

    $allowedSuspendedCustomerRoutes = [
        'customer-suspension-chat',
        'suspension-attachment',
        'customer-logout'
    ];


    /*
     * Payment slip upload is available only when the active
     * suspension includes Payment Required.
     */
    if ($hasPaymentRequiredSuspension) {
        $allowedSuspendedCustomerRoutes[] =
            'customer-upload-slip';
    }


    if (!in_array($page, $allowedSuspendedCustomerRoutes, true)) {

        if ($isDemoEnvironment) {
            header('Location: ?page=customer-suspension-chat');
        } else {
            header('Location: ?page=customer-suspension-chat');
        }

        exit;
    }
}


switch ($page) {

    /*
    |--------------------------------------------------------------------------
    | Public Pages
    |--------------------------------------------------------------------------
    */

    case 'home':

    if ($isDemoEnvironment) {
        require VIEW_PATH . '/public/demo-login.php';
    } else {
        require VIEW_PATH . '/public/home.php';
    }

    break;



    case 'services':
        require VIEW_PATH . '/public/services.php';
        break;

    case 'contact':
        require VIEW_PATH . '/public/contact.php';
        break;

    case 'demo-request':
        require VIEW_PATH . '/public/demo-request.php';
        break;

    case 'confirm-demo-email':
        require VIEW_PATH . '/public/confirm-demo-email.php';
        break;

    case 'approve-demo-request':
    require VIEW_PATH . '/admin/approve-demo-request.php';
    break;

    case 'view-agent':
    require VIEW_PATH . '/admin/view-agent.php';
    break;

    case 'confirm-demo-customer':
    require VIEW_PATH . '/public/confirm-demo-customer.php';
    break;




    /*
|--------------------------------------------------------------------------
| Demo Requests
|--------------------------------------------------------------------------
*/

case 'demo-requests':
    require VIEW_PATH . '/admin/demo-requests.php';
    break;

case 'view-demo-request':
    require VIEW_PATH . '/admin/view-demo-request.php';
    break;

case 'create-demo-tenant':
    require CONTROLLER_PATH . '/create-demo-tenant.php';
    break;

case 'create-demo-admin':
    require CONTROLLER_PATH . '/create-demo-admin.php';
    break;

case 'demo-super-admin':
    require VIEW_PATH . '/admin/demo-super-admin.php';
    break;

case 'demo-super-admin-login':
    require VIEW_PATH . '/public/demo-super-admin-login.php';
    break;

case 'create-demo':
    require CONTROLLER_PATH . '/create-demo.php';
    break;


    /*
    |--------------------------------------------------------------------------
    | Messages & Notifications
    |--------------------------------------------------------------------------
    */

    case 'messages':
        require VIEW_PATH . '/admin/messages.php';
        break;

    case 'archived-messages':
        require VIEW_PATH . '/admin/archived-messages.php';
        break;

    case 'notifications':
        require VIEW_PATH . '/admin/notifications.php';
        break;

    case 'open-notification':
        require VIEW_PATH . '/admin/open-notification.php';
        break;

    case 'mark-all-notifications-read':
        require VIEW_PATH . '/admin/mark-all-notifications-read.php';
        break;

    case 'awaiting-customer-response':
        require CONTROLLER_PATH . '/awaiting-customer-response.php';
        break;

    case 'view-awaiting-customer-response':
        require CONTROLLER_PATH . '/view-awaiting-customer-response.php';
        break;

    case 'review-cancellation-request':
        require CONTROLLER_PATH . '/review-cancellation-request.php';
        break;

    case 'closure-agreements':
        require CONTROLLER_PATH . '/closure-agreements.php';
        break;

    case 'review-closure-agreement':
        require CONTROLLER_PATH . '/review-closure-agreement.php';
        break;

    case 'demo-setup':
        require VIEW_PATH . '/public/demo-setup.php';
        break;    

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    case 'login':
        require VIEW_PATH . '/admin/login.php';
        break;

    case 'logout':
        require VIEW_PATH . '/admin/logout.php';
        break;

    case 'public-login':
        require VIEW_PATH . '/public/login.php';
        break;
    case 'demo-login':
        require VIEW_PATH . '/public/demo-login.php';
        break;

    case 'customer-register':
        require VIEW_PATH . '/public/customer-register.php';
        break;

    case 'customer-logout':

    if (isset($_SESSION['demo_customer'])) {

        unset($_SESSION['demo_customer']);

        header('Location: ?page=demo-login');
        exit;
    }

    unset($_SESSION['customer']);

    header('Location: ?page=public-login');
    exit;

    case 'review-reschedule-consultation':
        require VIEW_PATH . '/admin/review-reschedule-consultation.php';
        break;

    case 'customer-forgot-password':
        require VIEW_PATH . '/public/customer-forgot-password.php';
        break;

    case 'customer-reset-password':
        require VIEW_PATH . '/public/customer-reset-password.php';
        break;

    case 'agent-dashboard':
        require VIEW_PATH . '/agent/dashboard.php';
        break;

    case 'view-service-job':
        require VIEW_PATH . '/agent/view-service-job.php';
        break;

    case 'contact-customer':
        require CONTROLLER_PATH . '/contact-customer.php';
        break;

    case 'consultation-closure-agreement':
        require CONTROLLER_PATH . '/consultation-closure-agreement.php';
        break;

    case 'agent-logout':
        require VIEW_PATH . '/agent/logout.php';
        break;

    case 'pricing':
    require VIEW_PATH . '/admin/pricing.php';
    break;

    case 'add-pricing':
    require VIEW_PATH . '/admin/add-pricing.php';
    break;

    case 'edit-pricing':
    require VIEW_PATH . '/admin/edit-pricing.php';
    break;

    case 'delete-pricing':
    require VIEW_PATH . '/admin/delete-pricing.php';
    break;

    case 'approved-closures':
    require CONTROLLER_PATH . '/approved-closures.php';
    break;

    case 'complete-consultation-closure':
    require CONTROLLER_PATH . '/complete-consultation-closure.php';
    break;

    case 'agent-change-password':
    require VIEW_PATH . '/agent/agent-change-password.php';
    break;

    case 'agent-reset-password':
    require VIEW_PATH . '/agent/agent-reset-password.php';
    break;

    case 'agent-jobs':
    require VIEW_PATH . '/agent/agent-jobs.php';
    break;

    case 'review-reschedule-service':
    require VIEW_PATH . '/admin/review-reschedule-service.php';

    case 'explain-overdue-consultation':
    require VIEW_PATH . '/agent/explain-overdue-consultation.php';
    break;

    case 'explain-missed-service':
    require VIEW_PATH . '/agent/explain-missed-service.php';
    break;

    case 'admin-review-service-job':
    require VIEW_PATH . '/admin/admin-review-service-job.php';
    break;

    case 'respond-service-review':
    require VIEW_PATH . '/agent/respond-service-review.php';
    break;

    case 'customer-profile':
    require VIEW_PATH . '/customer/customer-profile.php';
    break;

    case 'customer-rate-agent':
    require VIEW_PATH . '/customer/customer-rate-agent.php';
    break;

    case 'confirm-service-completion':
    require VIEW_PATH . '/customer/confirm-service-completion.php';
    break;
    
    case 'confirm-consultation-completion':
    require VIEW_PATH
        . '/customer/confirm-consultation-completion.php';
    break;

    case 'demo-change-password':
    require VIEW_PATH . '/public/demo-change-password.php';
    break;

    /*
    |--------------------------------------------------------------------------
    | Customer Portal
    |--------------------------------------------------------------------------
    */

    case 'customer-dashboard':
        require VIEW_PATH . '/customer/customer-dashboard.php';
        break;

    case 'customer-suspension-chat':
        require VIEW_PATH . '/customer/customer-suspension-chat.php';
        break;

    case 'admin-suspension-chat':
        require VIEW_PATH . '/admin/admin-suspension-chat.php';
        break;

    case 'suspension-attachment':
        require CONTROLLER_PATH . '/suspension-attachment.php';
        break;

    case 'customer-view-inactive-request':
        require VIEW_PATH . '/customer/view-inactive-request.php';
        break;

    case 'customer-requests':
        require VIEW_PATH . '/customer/customer-requests.php';
        break;

    case 'customer-request-service':
        require VIEW_PATH . '/customer/customer-request-service.php';
        break;

    case 'customer-request-refund':
        require VIEW_PATH . '/customer/customer-request-refund.php';
        break;

    case 'customer-payments':
        require VIEW_PATH . '/customer/customer-payments.php';
        break;

    case 'customer-refunds':
        require VIEW_PATH . '/customer/customer-refunds.php';
        break;

    case 'customer-upload-slip':
        require VIEW_PATH . '/customer/customer-upload-slip.php';
        break;

    case 'customer-notifications':
        require VIEW_PATH . '/customer/customer-notifications.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Deposit Slips
    |--------------------------------------------------------------------------
    */

    case 'deposit-slips':
        require VIEW_PATH . '/admin/deposit-slips.php';
        break;

    case 'approve-slip':
        require VIEW_PATH . '/admin/approve-slip.php';
        break;

    case 'reject-slip':
        require VIEW_PATH . '/admin/reject-slip.php';
        break;

    case 'view-slip':
        require VIEW_PATH . '/admin/view-slip.php';
        break;



    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */

    case 'edit':
        require VIEW_PATH . '/admin/edit.php';
        break;

    case 'delete':
        require VIEW_PATH . '/admin/delete.php';
        break;

    case 'view':
        require VIEW_PATH . '/admin/view.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

        case 'dashboard':
        require VIEW_PATH . '/admin/dashboard.php';
        break;

        case 'needs-admin-review':
        require VIEW_PATH . '/admin/needs-admin-review.php';
        break;

        case 'admin-review-consultation':
        require VIEW_PATH . '/admin/admin-review-consultation.php';
        break;

        case 'admin-final-approve-consultation':
        require VIEW_PATH
            . '/admin/admin-final-approve-consultation.php';

        break;

        case 'admin-reschedule-consultation':
        require VIEW_PATH . '/admin/admin-reschedule-consultation.php';
        break;

        case 'admin-assign-agent':
        require VIEW_PATH . '/admin/admin-assign-agent.php';
        break;

        case 'admin-contact-customer':
        require CONTROLLER_PATH  . '/admin-contact-customer.php';
        break;

        case 'admin-close-request':
        require VIEW_PATH . '/admin/admin-close-request.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    case 'services-admin':
        require VIEW_PATH . '/admin/services-admin.php';
        break;

    case 'add-service':
        require VIEW_PATH . '/admin/add-service.php';
        break;

    case 'edit-service':
        require VIEW_PATH . '/admin/edit-service.php';
        break;

    case 'delete-service':
        require VIEW_PATH . '/admin/delete-service.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    case 'customers':
        require VIEW_PATH . '/admin/customers.php';
        break;

    case 'add-customer':
        require VIEW_PATH . '/admin/add-customer.php';
        break;

    case 'edit-customer':
        require VIEW_PATH . '/admin/edit-customer.php';
        break;

    case 'view-customer':
        require VIEW_PATH . '/admin/view-customer.php';
        break;

    case 'delete-customer':
        require VIEW_PATH . '/admin/delete-customer.php';
        break;


    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    */

    case 'agents':
        require VIEW_PATH . '/admin/agents.php';
        break;

    case 'add-agent':
        require VIEW_PATH . '/admin/add-agent.php';
        break;

    case 'edit-agent':
        require VIEW_PATH . '/admin/edit-agent.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    */

    case 'requests':
        require VIEW_PATH . '/admin/requests.php';
        break;

    case 'add-request':
        require VIEW_PATH . '/admin/add-request.php';
        break;

    case 'view-request':
        require VIEW_PATH . '/admin/view-request.php';
        break;

    case 'edit-request':
        require VIEW_PATH . '/admin/edit-request.php';
        break;

    case 'delete-request':
        require VIEW_PATH . '/admin/delete-request.php';
        break;

    case 'closed-requests':
        require VIEW_PATH . '/admin/closed-requests.php';
        break;

    case 'review-closed-request':
    require CONTROLLER_PATH . '/review-closed-request.php';
    break;

    case 'archived-requests':
    require VIEW_PATH . '/admin/archived-requests.php';
    break;

    case 'view-archived-request':
    require VIEW_PATH . '/admin/view-archived-request.php';
    break;

    case 'retention-review':
    require VIEW_PATH . '/admin/retention-review.php';
    break;

    case 'review-retention':
    require VIEW_PATH . '/admin/review-retention.php';
    break;

    case 'export-retention':
    require VIEW_PATH . '/admin/export-retention.php';
    break;

    case 'test':

    require APP_PATH . '/controllers/test.php';

    break;
    
        /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */

    case 'refunds':
        require VIEW_PATH . '/admin/refunds.php';
        break;

    case 'add-refund':
        require VIEW_PATH . '/admin/add-refund.php';
        break;

    case 'refund-requests':
        require VIEW_PATH . '/admin/refund-requests.php';
        break;

    case 'complete-refund':
        require VIEW_PATH . '/admin/complete-refund.php';
        break;

    case 'archived-refunds':
        require VIEW_PATH . '/admin/archived-refunds.php';
        break;

    case 'view-refund':
        require VIEW_PATH . '/admin/view-refund.php';
        break;

    case 'delete-refund':

        $stmt = $pdo->prepare("
            DELETE FROM refunds
            WHERE id = ?
        ");

        $stmt->execute([
            $_GET['id']
        ]);

        header('Location: ?page=refunds');
        exit;

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    case 'payments':
        require VIEW_PATH . '/admin/payments.php';
        break;

    case 'add-payment':
        require VIEW_PATH . '/admin/add-payment.php';
        break;

    case 'view-payment':
        require VIEW_PATH . '/admin/view-payment.php';
        break;

    case 'edit-payment':
        require VIEW_PATH . '/admin/edit-payment.php';
        break;

    case 'delete-payment':
        require VIEW_PATH . '/admin/delete-payment.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Consultation Scheduling
    |--------------------------------------------------------------------------
    */

    case 'consultation-slots':
        require VIEW_PATH . '/admin/consultation-slots.php';
        break;

    case 'schedule-consultation':
        require VIEW_PATH . '/customer/schedule-consultation.php';
        break;

    case 'approve-consultation':
        require VIEW_PATH . '/admin/approve-consultation.php';
        break;

    case 'confirm-consultation':
        require VIEW_PATH . '/customer/confirm-consultation.php';
        break;

    case 'review-consultation':
        require VIEW_PATH . '/admin/review-consultation.php';
        break;

    case 'confirm-consultation-booking':
        require VIEW_PATH . '/admin/confirm-consultation-booking.php';
        break;

    case 'reject-consultation':
        require VIEW_PATH . '/admin/reject-consultation.php';
        break;

    case 'reschedule-consultation':
        require VIEW_PATH . '/customer/reschedule-consultation.php';
        break;

    case 'confirm-reschedule-consultation':
        require VIEW_PATH . '/customer/confirm-reschedule-consultation.php';
        break;

    case 'refund-history':
        require VIEW_PATH . '/customer/refund-history.php';
        break;

    case 'customer-view-refund':
        require VIEW_PATH . '/customer/view-refund.php';
        break;

    case 'complete-consultation':
        require VIEW_PATH . '/admin/complete-consultation.php';
        break;

    case 'agent-consultations':
        require VIEW_PATH . '/agent/my-consultations.php';
        break;

    case 'view-consultation':
        require VIEW_PATH . '/agent/view-consultation.php';
        break;

    case 'cannot-complete-consultation':
        require VIEW_PATH . '/agent/cannot-complete-consultation.php';
        break;

    case 'explain-missed-consultation':
        require VIEW_PATH . '/agent/explain-missed-consultation.php';
        break;

    case 'review-missed-consultation':
        require VIEW_PATH . '/admin/review-missed-consultation.php';
        break;

    case 'missed-consultation-approvals':
    require VIEW_PATH . '/admin/missed-consultation-approvals.php';
    break;

    case 'agent-profile':
    require VIEW_PATH . '/agent/agent-profile.php';
    break;

    case 'agent-notifications':
    require VIEW_PATH . '/agent/agent-notifications.php';
    break;

case 'agent-open-notification':
    require VIEW_PATH . '/agent/open-notification.php';
    break;

case 'agent-mark-all-notifications-read':
    require VIEW_PATH . '/agent/mark-all-notifications-read.php';
    break;

    /*
    |--------------------------------------------------------------------------
    | Service Scheduling
    |--------------------------------------------------------------------------
    */

    case 'service-slots':
        require VIEW_PATH . '/admin/service-slots.php';
        break;

    case 'schedule-service':
        require VIEW_PATH . '/customer/schedule-service.php';
        break;

    case 'approve-service-schedule':
        require VIEW_PATH . '/admin/approve-service-schedule.php';
        break;

    case 'review-service':
        require VIEW_PATH . '/admin/review-service.php';
        break;

    case 'approve-service':
        require VIEW_PATH . '/admin/approve-service.php';
        break;

    case 'reject-service':
        require VIEW_PATH . '/admin/reject-service.php';
        break;

    case 'confirm-service':
        require VIEW_PATH . '/customer/confirm-service.php';
        break;

    case 'reschedule-service':
        require VIEW_PATH . '/customer/reschedule-service.php';
        break;

    case 'confirm-reschedule-service':
        require VIEW_PATH . '/customer/confirm-reschedule-service.php';
        break;

    case 'complete-service':
        require VIEW_PATH . '/admin/complete-service.php';
        break;

    case 'review-refund':
        require VIEW_PATH . '/admin/review-refund.php';
        break;

        /*
    |--------------------------------------------------------------------------
    | Visitor Messages & Proposals
    |--------------------------------------------------------------------------
    */

    case 'visitor-message':
        require VIEW_PATH . '/admin/visitor-message.php';
        break;

    case 'close-conversation':
        require VIEW_PATH . '/admin/close-conversation.php';
        break;

    case 'create-proposal':
        require VIEW_PATH . '/admin/create-proposal.php';
        break;

    case 'view-proposal':
        require VIEW_PATH . '/customer/view-proposal.php';
        break;

    case 'admin-view-proposal':
        require APP_PATH . '/views/admin/view-proposal.php';
        break;

    case 'send-proposal':
        require APP_PATH . '/views/admin/send-proposal.php';
        break;

    case 'accept-proposal-confirm':
        require VIEW_PATH . '/customer/accept-proposal-confirm.php';
        break;

    case 'reject-proposal':
        require VIEW_PATH . '/customer/reject-proposal.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Contract Leads
    |--------------------------------------------------------------------------
    */

    case 'contract-leads':
        require VIEW_PATH . '/admin/contract-leads.php';
        break;

    case 'update-contract-lead':
        require VIEW_PATH . '/admin/update-contract-lead.php';
        break;

    case 'archive-contract-lead':
        require VIEW_PATH . '/admin/archive-contract-lead.php';
        break;

    case 'pending-contract-leads':
        require APP_PATH . '/views/admin/pending-contract-leads.php';
        break;
        
    case 'view-contract-lead':
        require APP_PATH . '/views/admin/view-contract-lead.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Service Completion
    |--------------------------------------------------------------------------
    */

    case 'complete-service-form':
        require VIEW_PATH . '/admin/complete-service-form.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    */

    case 'rules':
        require VIEW_PATH . '/public/rules.php';
        break;

    case 'verify-customer-email':
    require VIEW_PATH . '/public/verify-customer-email.php';
    break;

    case 'review-customer-registration':
    require VIEW_PATH . '/admin/review-customer-registration.php';
    break;

    case 'approve-customer-registration':
    require VIEW_PATH . '/admin/approve-customer-registration.php';
    break;

    case 'reject-customer-registration':
    require VIEW_PATH . '/admin/reject-customer-registration.php';
    break;

    case 'customer-status':
    require VIEW_PATH . '/admin/customer-status.php';
    break;
    
    /*
    |--------------------------------------------------------------------------
    | Default
    |--------------------------------------------------------------------------
    */

    default:
        http_response_code(404);
        echo "404 - Page not found";
        break;

}

?>