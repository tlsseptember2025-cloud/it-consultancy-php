<?php

require_once __DIR__ . '/config/database.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {

    /*
    |--------------------------------------------------------------------------
    | Public Pages
    |--------------------------------------------------------------------------
    */

    case 'home':
        require __DIR__ . '/app/views/home.php';
        break;

    case 'services':
        require __DIR__ . '/app/views/services.php';
        break;

    case 'contact':
        require __DIR__ . '/app/views/contact.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Messages & Notifications
    |--------------------------------------------------------------------------
    */

    case 'messages':
        require __DIR__ . '/app/views/messages.php';
        break;

    case 'archived-messages':
        require __DIR__ . '/app/views/archived-messages.php';
        break;

    case 'notifications':
        require __DIR__ . '/app/views/notifications.php';
        break;

    case 'open-notification':
        require __DIR__ . '/app/views/open-notification.php';
        break;

    case 'mark-all-notifications-read':
        require __DIR__ . '/app/views/mark-all-notifications-read.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    case 'login':
        require __DIR__ . '/app/views/login.php';
        break;

    case 'logout':
        require __DIR__ . '/app/views/logout.php';
        break;

    case 'customer-login':
        require __DIR__ . '/app/views/customer-login.php';
        break;

    case 'customer-register':
        require __DIR__ . '/app/views/customer-register.php';
        break;

    case 'customer-logout':
        unset($_SESSION['customer']);
        header('Location: ?page=home');
        exit;

    case 'customer-forgot-password':
        require __DIR__ . '/app/views/customer-forgot-password.php';
        break;

    case 'customer-reset-password':
        require __DIR__ . '/app/views/customer-reset-password.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Customer Portal
    |--------------------------------------------------------------------------
    */

    case 'customer-dashboard':
        require __DIR__ . '/app/views/customer-dashboard.php';
        break;

    case 'customer-requests':
        require __DIR__ . '/app/views/customer-requests.php';
        break;

    case 'customer-request-service':
        require __DIR__ . '/app/views/customer-request-service.php';
        break;

    case 'customer-request-refund':
        require __DIR__ . '/app/views/customer-request-refund.php';
        break;

    case 'customer-payments':
        require __DIR__ . '/app/views/customer-payments.php';
        break;

    case 'customer-refunds':
        require __DIR__ . '/app/views/customer-refunds.php';
        break;

    case 'customer-upload-slip':
        require __DIR__ . '/app/views/customer-upload-slip.php';
        break;

    case 'customer-notifications':
        require __DIR__ . '/app/views/customer-notifications.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Deposit Slips
    |--------------------------------------------------------------------------
    */

    case 'deposit-slips':
        require __DIR__ . '/app/views/deposit-slips.php';
        break;

    case 'approve-slip':
        require __DIR__ . '/app/views/approve-slip.php';
        break;

    case 'reject-slip':
        require __DIR__ . '/app/views/reject-slip.php';
        break;

    case 'view-slip':
        require __DIR__ . '/app/views/view-slip.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | General
    |--------------------------------------------------------------------------
    */

    case 'edit':
        require __DIR__ . '/app/views/edit.php';
        break;

    case 'delete':
        require __DIR__ . '/app/views/delete.php';
        break;

    case 'view':
        require __DIR__ . '/app/views/view.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */

        case 'dashboard':
        require __DIR__ . '/app/views/dashboard.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    case 'services-admin':
        require __DIR__ . '/app/views/services-admin.php';
        break;

    case 'add-service':
        require __DIR__ . '/app/views/add-service.php';
        break;

    case 'edit-service':
        require __DIR__ . '/app/views/edit-service.php';
        break;

    case 'delete-service':
        require __DIR__ . '/app/views/delete-service.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    case 'customers':
        require __DIR__ . '/app/views/customers.php';
        break;

    case 'add-customer':
        require __DIR__ . '/app/views/add-customer.php';
        break;

    case 'edit-customer':
        require __DIR__ . '/app/views/edit-customer.php';
        break;

    case 'view-customer':
        require __DIR__ . '/app/views/view-customer.php';
        break;

    case 'delete-customer':
        require __DIR__ . '/app/views/delete-customer.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    */

    case 'requests':
        require __DIR__ . '/app/views/requests.php';
        break;

    case 'add-request':
        require __DIR__ . '/app/views/add-request.php';
        break;

    case 'view-request':
        require __DIR__ . '/app/views/view-request.php';
        break;

    case 'edit-request':
        require __DIR__ . '/app/views/edit-request.php';
        break;

    case 'delete-request':
        require __DIR__ . '/app/views/delete-request.php';
        break;

    case 'archived-requests':
        require __DIR__ . '/app/views/archived-requests.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */

    case 'backup':
        require __DIR__ . '/app/views/backup.php';
        break;

    
        /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */

    case 'refunds':
        require __DIR__ . '/app/views/refunds.php';
        break;

    case 'add-refund':
        require __DIR__ . '/app/views/add-refund.php';
        break;

    case 'refund-requests':
        require __DIR__ . '/app/views/refund-requests.php';
        break;

    case 'process-refund-request':
        require __DIR__ . '/app/views/process-refund-request.php';
        break;

    case 'approve-refund-request':
        require __DIR__ . '/app/views/approve-refund-request.php';
        break;

    case 'complete-refund':
        require __DIR__ . '/app/views/complete-refund.php';
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
        require __DIR__ . '/app/views/payments.php';
        break;

    case 'add-payment':
        require __DIR__ . '/app/views/add-payment.php';
        break;

    case 'view-payment':
        require __DIR__ . '/app/views/view-payment.php';
        break;

    case 'edit-payment':
        require __DIR__ . '/app/views/edit-payment.php';
        break;

    case 'delete-payment':
        require __DIR__ . '/app/views/delete-payment.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Consultation Scheduling
    |--------------------------------------------------------------------------
    */

    case 'consultation-slots':
        require __DIR__ . '/app/views/consultation-slots.php';
        break;

    case 'schedule-consultation':
        require __DIR__ . '/app/views/schedule-consultation.php';
        break;

    case 'approve-consultation':
        require __DIR__ . '/app/views/approve-consultation.php';
        break;

    case 'confirm-consultation':
        require __DIR__ . '/app/views/confirm-consultation.php';
        break;

    case 'confirm-consultation-admin':
        require __DIR__ . '/app/views/confirm-consultation-admin.php';
        break;

    case 'reschedule-consultation':
        require __DIR__ . '/app/views/reschedule-consultation.php';
        break;

    case 'confirm-reschedule-consultation':
        require __DIR__ . '/app/views/confirm-reschedule-consultation.php';
        break;

    case 'complete-consultation':
        require __DIR__ . '/app/views/complete-consultation.php';
        break;

    case 'generate-consultation-slots':
        require __DIR__ . '/app/views/generate-consultation-slots.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Service Scheduling
    |--------------------------------------------------------------------------
    */

    case 'service-slots':
        require __DIR__ . '/app/views/service-slots.php';
        break;

    case 'schedule-service':
        require __DIR__ . '/app/views/schedule-service.php';
        break;

    case 'approve-service-schedule':
        require __DIR__ . '/app/views/approve-service-schedule.php';
        break;

    case 'confirm-service':
        require __DIR__ . '/app/views/confirm-service.php';
        break;

    case 'reschedule-service':
        require __DIR__ . '/app/views/reschedule-service.php';
        break;

    case 'confirm-reschedule-service':
        require __DIR__ . '/app/views/confirm-reschedule-service.php';
        break;

    case 'complete-service':
        require __DIR__ . '/app/views/complete-service.php';
        break;

    case 'generate-service-slots':
        require __DIR__ . '/app/views/generate-service-slots.php';
        break;

        /*
    |--------------------------------------------------------------------------
    | Visitor Messages & Proposals
    |--------------------------------------------------------------------------
    */

    case 'visitor-message':
        require __DIR__ . '/app/views/visitor-message.php';
        break;

    case 'close-conversation':
        require __DIR__ . '/app/views/close-conversation.php';
        break;

    case 'create-proposal':
        require __DIR__ . '/app/views/create-proposal.php';
        break;

    case 'view-proposal':
        require __DIR__ . '/app/views/view-proposal.php';
        break;

    case 'accept-proposal-confirm':
        require __DIR__ . '/app/views/accept-proposal-confirm.php';
        break;

    case 'reject-proposal':
        require __DIR__ . '/app/views/reject-proposal.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Contract Leads
    |--------------------------------------------------------------------------
    */

    case 'contract-leads':
        require __DIR__ . '/app/views/contract-leads.php';
        break;

    case 'edit-contract-lead':
        require __DIR__ . '/app/views/edit-contract-lead.php';
        break;

    case 'delete-contract-lead':
        require __DIR__ . '/app/views/delete-contract-lead.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Service Completion
    |--------------------------------------------------------------------------
    */

    case 'complete-service-form':
        require __DIR__ . '/app/views/complete-service-form.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    */

    case 'rules':
        require __DIR__ . '/app/views/rules.php';
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
    