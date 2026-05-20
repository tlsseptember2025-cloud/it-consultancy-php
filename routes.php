<?php

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

    case 'login':
    require 'app/views/login.php';
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

    default:
        echo "404 - Page not found";
}
?>