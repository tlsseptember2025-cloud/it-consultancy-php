<?php

if (isset($_SESSION['user'])) {

    header('Location: ?page=dashboard');
    exit;

}

if (isset($_SESSION['customer'])) {

    header('Location: ?page=customer-dashboard');
    exit;

}

if (isset($_SESSION['agent'])) {

    header('Location: ?page=agent-dashboard');
    exit;

}

require dirname(__DIR__) . '/layouts/header-public.php';

?>

<div class="card border-primary shadow-sm mb-5">

    <div class="card-body">

        <h3 class="text-primary mb-3">
            🔐 Request Demo Access
        </h3>

        <p>
            Create a temporary demo access request and explore the
            IT Consultancy Management System.
        </p>

        <div class="alert alert-info">

            <strong>Demo Access Information</strong>

            <br><br>

            Your request will be reviewed before access is provided.

            <br><br>

            If approved, you will receive a unique demo username and
            password.

            <br><br>

            Demo access is temporary and will expire after 5 days from
            your first successful login.

        </div>


        <form method="POST" action="?page=demo-register">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label for="name" class="form-label">
                        Full Name
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        maxlength="255"
                        required
                    >

                </div>


                <div class="col-md-6 mb-3">

                    <label for="email" class="form-label">
                        Email Address
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        maxlength="255"
                        required
                    >

                </div>

            </div>


            <div class="mb-3">

                <label for="company_name" class="form-label">
                    Company Name
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="company_name"
                    name="company_name"
                    maxlength="255"
                >

            </div>


            <div class="mb-3">

                <label for="reason" class="form-label">
                    What would you like to explore?
                </label>

                <textarea
                    class="form-control"
                    id="reason"
                    name="reason"
                    rows="4"
                    maxlength="1000"
                    placeholder="Tell us briefly what you would like to explore in the demo."
                ></textarea>

            </div>


            <div class="d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary">

                    Request Demo Access

                </button>


                <a
                    href="?page=demo"
                    class="btn btn-secondary">

                    Back to Demo

                </a>

            </div>

        </form>

    </div>

</div>


<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>