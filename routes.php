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

    case 'customer-register':
        require VIEW_PATH . '/public/customer-register.php';
        break;

    case 'customer-logout':
        unset($_SESSION['customer']);
        header('Location: ?page=public-login');
        exit;

    case 'customer-forgot-password':
        require VIEW_PATH . '/public/customer-forgot-password.php';
        break;

    case 'customer-reset-password':
        require VIEW_PATH . '/public/customer-reset-password.php';
        break;

    case 'agent-dashboard':
        require VIEW_PATH . '/agent/dashboard.php';
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

        case 'needs-admin-review':
        require VIEW_PATH . '/admin/needs-admin-review.php';
        break;

        case 'admin-review-consultation':
        require VIEW_PATH . '/admin/admin-review-consultation.php';
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

    case 'archived-requests':
        require VIEW_PATH . '/admin/archived-requests.php';
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

?>