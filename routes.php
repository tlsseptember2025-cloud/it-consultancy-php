<?php

require_once CONFIG_PATH . '/database.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {

    /*
    |--------------------------------------------------------------------------
    | Public Pages
    |--------------------------------------------------------------------------
    */

    case 'home':
        require VIEW_PATH . '/public/home.php';
        break;

    case 'services':
        require VIEW_PATH . '/public/services.php';
        break;

    case 'contact':
        require VIEW_PATH . '/public/contact.php';
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

    case 'customer-login':
        require VIEW_PATH . '/public/customer-login.php';
        break;

    case 'customer-register':
        require VIEW_PATH . '/public/customer-register.php';
        break;

    case 'customer-logout':
        unset($_SESSION['customer']);
        header('Location: ?page=home');
        exit;

    case 'customer-forgot-password':
        require VIEW_PATH . '/public/customer-forgot-password.php';
        break;

    case 'customer-reset-password':
        require VIEW_PATH . '/public/customer-reset-password.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Customer Portal
    |--------------------------------------------------------------------------
    */

    case 'customer-dashboard':
        require VIEW_PATH . '/customer/customer-dashboard.php';
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

    case 'archived-requests':
        require VIEW_PATH . '/admin/archived-requests.php';
        break;

    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    */

    case 'backup':
        require VIEW_PATH . '/admin/backup.php';
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

    case 'process-refund-request':
        require VIEW_PATH . '/admin/process-refund-request.php';
        break;

    case 'approve-refund-request':
        require VIEW_PATH . '/admin/approve-refund-request.php';
        break;

    case 'complete-refund':
        require VIEW_PATH . '/admin/complete-refund.php';
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

    case 'confirm-consultation-admin':
        require VIEW_PATH . '/admin/confirm-consultation-admin.php';
        break;

    case 'reschedule-consultation':
        require VIEW_PATH . '/customer/reschedule-consultation.php';
        break;

    case 'confirm-reschedule-consultation':
        require VIEW_PATH . '/customer/confirm-reschedule-consultation.php';
        break;

    case 'complete-consultation':
        require VIEW_PATH . '/admin/complete-consultation.php';
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
        require VIEW_PATH . '/admin/schedule-service.php';
        break;

    case 'approve-service-schedule':
        require VIEW_PATH . '/admin/approve-service-schedule.php';
        break;

    case 'confirm-service':
        require VIEW_PATH . '/admin/confirm-service.php';
        break;

    case 'reschedule-service':
        require VIEW_PATH . '/admin/reschedule-service.php';
        break;

    case 'confirm-reschedule-service':
        require VIEW_PATH . '/admin/confirm-reschedule-service.php';
        break;

    case 'complete-service':
        require VIEW_PATH . '/admin/complete-service.php';
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

    case 'edit-contract-lead':
        require VIEW_PATH . '/admin/edit-contract-lead.php';
        break;

    case 'delete-contract-lead':
        require VIEW_PATH . '/admin/delete-contract-lead.php';
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
    