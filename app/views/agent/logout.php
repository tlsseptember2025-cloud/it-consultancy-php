<?php

if (isset($_SESSION['demo_agent'])) {

    unset($_SESSION['demo_agent']);

    header('Location: ?page=demo-login');
    exit;
}

unset($_SESSION['agent']);

header('Location: ?page=public-login');
exit;