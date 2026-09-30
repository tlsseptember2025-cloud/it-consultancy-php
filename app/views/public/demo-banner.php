<?php

/*
 * Shared Demo Environment Banner
 *
 * The banner is shown on Demo routes before login and while any Demo
 * account is active. Main-site visitors do not see it.
 */

$demoPageRoutes = [
    'demo-login',
    'demo-request',
    'demo-setup',
    'demo-change-password',
    'demo-password-recovery',
    'demo-super-admin-login',
    'confirm-demo-email',
    'confirm-demo-customer',
];

$currentPage = isset($_GET['page']) && is_string($_GET['page'])
    ? $_GET['page']
    : '';

$isDemoSession =
    isset($_SESSION['demo_user'])
    || isset($_SESSION['demo_customer'])
    || isset($_SESSION['demo_agent'])
    || isset($_SESSION['demo_super_admin']);

$isDemoRoute = in_array($currentPage, $demoPageRoutes, true);

if ($isDemoSession || $isDemoRoute):
?>

<div class="demo-environment-banner" role="status" aria-label="Demo environment">
    <div class="demo-environment-banner__inner">
        <span class="demo-environment-banner__label">DEMO ENVIRONMENT</span>
        <span class="demo-environment-banner__text">
            You are viewing the Demo environment. Data and actions are for demonstration and testing purposes.
        </span>
    </div>
</div>

<style>
.demo-environment-banner {
    width: 100%;
    background: #fff3cd;
    border-bottom: 1px solid #ffecb5;
    color: #664d03;
    position: relative;
    z-index: 1020;
}

.demo-environment-banner__inner {
    max-width: 1320px;
    margin: 0 auto;
    padding: .65rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    text-align: center;
    font-size: .92rem;
}

.demo-environment-banner__label {
    font-weight: 700;
    letter-spacing: .04em;
    white-space: nowrap;
}

.demo-environment-banner__text {
    font-weight: 500;
}

@media (max-width: 767.98px) {
    .demo-environment-banner__inner {
        flex-direction: column;
        gap: .2rem;
        padding: .55rem .75rem;
        font-size: .84rem;
    }
}
</style>

<?php endif; ?>
