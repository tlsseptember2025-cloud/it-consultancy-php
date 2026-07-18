</div>

<footer class="bg-dark text-white text-center py-4 mt-5">

    <div class="container">

        <p class="mb-1">
            <?= PRODUCT_COPYRIGHT ?>
        </p>

        <p class="mb-1">
            <?= COMPANY_TAGLINE ?>
        </p>

        <small>

            <a
                href="<?= COMPANY_WEBSITE ?>"
                target="_blank"
                class="text-white text-decoration-none">
                <?= COMPANY_WEBSITE ?>
            </a>

            |

            <a
                href="mailto:<?= COMPANY_EMAIL ?>"
                class="text-white text-decoration-none">
                <?= COMPANY_EMAIL ?>
            </a>

            |

            Version <?= PRODUCT_VERSION ?>

        </small>

        <div class="mt-2">

            <a
                href="?page=rules"
                target="_blank"
                class="text-white text-decoration-none">
                Rules &amp; Regulations
            </a>

        </div>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

const searchInput = document.getElementById('searchInput');

if (searchInput) {

    searchInput.addEventListener('keyup', function () {

        const search = this.value;

        fetch('?page=messages&search=' + encodeURIComponent(search))

            .then(response => response.text())

            .then(data => {

                const parser = new DOMParser();

                const doc = parser.parseFromString(data, 'text/html');

                const newResults = doc.getElementById('results');

                if (newResults && document.getElementById('results')) {

                    document.getElementById('results').innerHTML =
                        newResults.innerHTML;

                }

            });

    });

}

</script>

</body>

</html>