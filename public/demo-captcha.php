<?php

require_once dirname(__DIR__) . '/app/helpers/captcha.php';

if (isset($_GET['refresh'])) {
    clearDemoCaptcha();
    generateDemoCaptcha();
}

if (empty($_SESSION['demo_captcha_code'])) {
    generateDemoCaptcha();
}

outputDemoCaptchaImage();