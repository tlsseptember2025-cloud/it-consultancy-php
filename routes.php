<?php

require __DIR__ . '/config/database.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        require 'app/views/home.php';
        break;

    case 'services':
        require 'app/views/services.php';
        break;

    case 'contact':
        require 'app/views/contact.php';
        break;

    case 'messages':
    require 'app/views/messages.php';
    break;

    case 'archived-messages':
    require 'app/views/archived-messages.php';
    break;

    case 'open-notification':
    require 'app/views/open-notification.php';
    break;

    case 'notifications':
    require 'app/views/notifications.php';
    break;

    case 'mark-all-notifications-read':
    require 'app/views/mark-all-notifications-read.php';
    break;

    case 'login':
    require 'app/views/login.php';
    break;

    case 'customer-login':
    require __DIR__ . '/app/views/customer-login.php';
    break;

    case 'customer-logout':
    unset($_SESSION['customer']);
    header("Location: ?page=home");
    exit;

    case 'deposit-slips':
    require '../app/views/deposit-slips.php';
    break;

    case 'customer-dashboard':
    require __DIR__ . '/app/views/customer-dashboard.php';
    break;

    case 'customer-upload-slip':
    require __DIR__ . '/app/views/customer-upload-slip.php';
    break;

    case 'customer-register':
    require __DIR__ . '/app/views/customer-register.php';
    break;

    case 'logout':
    require 'app/views/logout.php';
    break;

    case 'edit':
    require 'app/views/edit.php';
    break;

    case 'delete':
    require 'app/views/delete.php';
    break;

    case 'view':
    require 'app/views/view.php';
    break;

    case 'services-admin':
    require 'app/views/services-admin.php';
    break;

    case 'add-service':
    require 'app/views/add-service.php';
    break;

    case 'edit-service':
    require 'app/views/edit-service.php';
    break;

    case 'delete-service':
    require 'app/views/delete-service.php';
    break;

    case 'dashboard':
    require 'app/views/dashboard.php';
    break;

    case 'customers':
    require 'app/views/customers.php';
    break;

    case 'add-customer':
        require 'app/views/add-customer.php';
        break;

    case 'edit-customer':
        require 'app/views/edit-customer.php';
        break;

    case 'delete-customer':
        require 'app/views/delete-customer.php';
        break;

    case 'backup':
        require __DIR__ . '/app/views/backup.php';
        break;

    case 'view-customer':
        require 'app/views/view-customer.php';
        break;

    case 'requests':
    require 'app/views/requests.php';
    break;

    case 'add-request':
        require 'app/views/add-request.php';
        break;

    case 'view-request':
        require 'app/views/view-request.php';
        break;

    case 'edit-request':
        require 'app/views/edit-request.php';
        break;

    case 'delete-request':
        require 'app/views/delete-request.php';
        break;

    case 'refunds':
        require __DIR__ . '/app/views/refunds.php';
        break;

    case 'add-refund':
        require __DIR__ . '/app/views/add-refund.php';
        break;

    case 'payments':
        require 'app/views/payments.php';
        break;

    case 'add-payment':
        require 'app/views/add-payment.php';
        break;

    case 'approve-slip':
    require 'app/views/approve-slip.php';
    break;

    case 'reject-slip':
    require 'app/views/reject-slip.php';
    break;

    case 'view-payment':
        require 'app/views/view-payment.php';
        break;

    case 'edit-payment':
        require 'app/views/edit-payment.php';
        break;

    case 'delete-payment':
        require 'app/views/delete-payment.php';
        break;

    case 'customer-requests':
    require __DIR__ . '/app/views/customer-requests.php';
    break;

    case 'customer-payments':
    require __DIR__ . '/app/views/customer-payments.php';
    break;

    case 'customer-request-service':
    require __DIR__ . '/app/views/customer-request-service.php';
    break;

    case 'customer-refunds':
    require __DIR__ . '/app/views/customer-refunds.php';
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

    case 'consultation-slots':
        require __DIR__ . '/app/views/consultation-slots.php';
        break;

    case 'approve-consultation':
        require __DIR__ . '/app/views/approve-consultation.php';
        break;

    case 'schedule-consultation':
    require __DIR__ . '/app/views/schedule-consultation.php';
    break;

    case 'reschedule-consultation':
    require __DIR__ . '/app/views/reschedule-consultation.php';
    break;

    case 'confirm-consultation':
    require __DIR__ . '/app/views/confirm-consultation.php';
    break;

    case 'confirm-consultation-admin':
    require __DIR__ . '/app/views/confirm-consultation-admin.php';
    break;

    case 'confirm-reschedule-consultation':
    require __DIR__ . '/app/views/confirm-reschedule-consultation.php';
    break;

    case 'reschedule-service':
    require __DIR__ . '/app/views/reschedule-service.php';
    break;

    case 'confirm-reschedule-service':
    require __DIR__ . '/app/views/confirm-reschedule-service.php';
    break;

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

    case 'service-slots':
    require __DIR__ . '/app/views/service-slots.php';
    break;

    case 'schedule-service':
    require __DIR__ . '/app/views/schedule-service.php';
    break;

    case 'confirm-service':
    require __DIR__ . '/app/views/confirm-service.php';
    break;

    case 'approve-service-schedule':
    require __DIR__ . '/app/views/approve-service-schedule.php';
    break;

    case 'complete-consultation':
    require __DIR__ . '/app/views/complete-consultation.php';
    break;

    case 'reject-proposal':
    require __DIR__ . '/app/views/reject-proposal.php';
    break;

    case 'view-slip':
    require __DIR__ . '/app/views/view-slip.php';
    break;

    case 'complete-service':
    require __DIR__ . '/app/views/complete-service.php';
    break;

    case 'archived-requests':
    require __DIR__ . '/app/views/archived-requests.php';
    break;

    case 'customer-request-refund':
    require __DIR__ . '/app/views/customer-request-refund.php';
    break;

    case 'contract-leads':
    require __DIR__ . '/app/views/contract-leads.php';
    break;

    case 'edit-contract-lead':
    require __DIR__ . '/app/views/edit-contract-lead.php';
    break;

    case 'customer-forgot-password':
    require __DIR__ . '/app/views/customer-forgot-password.php';
    break;

    case 'customer-reset-password':
    require __DIR__ . '/app/views/customer-reset-password.php';
    break;

    case 'complete-service-form':
    require __DIR__ . '/app/views/complete-service-form.php';
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

    case 'generate-consultation-slots':
    require 'app/views/generate-consultation-slots.php';
    break;

    case 'generate-service-slots':
    require 'app/views/generate-service-slots.php';
    break;

    case 'delete-contract-lead':
    require 'app/views/delete-contract-lead.php';
    break;

    case 'rules':
    require __DIR__ . '/app/views/rules.php';
    break;

    case 'customer-notifications':
    require __DIR__ . '/../app/views/customer-notifications.php';
    break;

    default:
        echo "404 - Page not found";
}
?>