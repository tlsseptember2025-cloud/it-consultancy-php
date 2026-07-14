<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="?page=agent-dashboard">
            <?= PRODUCT_NAME ?>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <div class="navbar-nav ms-auto">

                <a class="nav-link" href="?page=agent-dashboard">
    Dashboard
                </a>

                <a class="nav-link" href="?page=agent-consultations">
                    My Consultations
                </a>

                <a class="nav-link" href="?page=agent-jobs">
                    My Service Jobs
                </a>

                <a class="nav-link" href="?page=agent-profile">
                    Profile
                </a>

                <a class="nav-link text-danger" href="?page=agent-logout">
                    Logout
                </a>
            </div>

        </div>

    </div>

</nav>