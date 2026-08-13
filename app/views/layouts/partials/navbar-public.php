<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a
            class="navbar-brand d-flex align-items-center fw-bold"
            href="?page=home"
            title="<?= COMPANY_TAGLINE ?>">

            <img
                src="uploads/assets/logo.png"
                alt="<?= COMPANY_NAME ?>"
                height="40"
                class="me-2">

            <span><?= COMPANY_NAME ?></span>

        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div
            class="collapse navbar-collapse"
            id="navbarNav">

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">
                    <a class="nav-link" href="?page=services">
                        Services
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="?page=contact">
                        Contact
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="?page=customer-register">
                        Register
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="?page=public-login">
                        Login
                    </a>
                </li>

            </ul>

        </div>

    </div>

</nav>