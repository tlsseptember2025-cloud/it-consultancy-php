<?php

require_once HELPER_PATH . '/GuestChatHelper.php';

/*
|--------------------------------------------------------------------------
| Public Navbar
|--------------------------------------------------------------------------
|
| This navbar is for public visitors only.
|
| Admin, Customer, Agent, and Demo users have their own
| dedicated navbar files.
|
*/

$guestChatAvailability = getGuestChatAvailability($pdo);

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand"
            href="?page=home"
        >
            <?= htmlspecialchars(COMPANY_NAME) ?>
        </a>


        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <div
            class="collapse navbar-collapse"
            id="navbarNav"
        >

            <div class="navbar-nav ms-auto">


                <!-- Home -->

                <a
                    class="nav-link"
                    href="?page=home"
                >
                    Home
                </a>


                <!-- Services -->

                <a
                    class="nav-link"
                    href="?page=services"
                >
                    Services
                </a>


                <!-- Live Chat -->

                <?php if ($guestChatAvailability['available']): ?>

                    <a
                        class="nav-link"
                        href="?page=guest-chat"
                    >
                        Live Chat
                    </a>

                <?php endif; ?>


                <!-- Register -->

                <a
                    class="nav-link"
                    href="?page=customer-register"
                >
                    Register
                </a>


                <!-- Login -->

                <a
                    class="nav-link"
                    href="?page=public-login"
                >
                    Login
                </a>


            </div>

        </div>

    </div>

</nav>