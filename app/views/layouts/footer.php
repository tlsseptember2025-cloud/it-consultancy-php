</div>

<footer class="bg-dark text-white text-center py-4 mt-5">

    <div class="container">

        <p class="mb-1">
            © 2026 IT Consultancy
        </p>

        <small>
            Professional IT Solutions & Web Development
        </small>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

const searchInput = document.getElementById('searchInput');

if (searchInput) {

    searchInput.addEventListener('keyup', function () {

        let search = this.value;

        fetch('?page=messages&search=' + encodeURIComponent(search))

            .then(response => response.text())

            .then(data => {

                let parser = new DOMParser();

                let doc = parser.parseFromString(data, 'text/html');

                let newResults = doc.getElementById('results');

                document.getElementById('results').innerHTML =
                    newResults.innerHTML;
            });
    });
}

</script>

</body>
</html>