<hr>

<footer>
    <p>© <?php echo date("Y"); ?> IT Consultancy</p>
</footer>

<script>

const searchInput = document.getElementById('searchInput');

if (searchInput) {

    searchInput.addEventListener('keyup', function () {

        let search = this.value;

        fetch('?page=admin&search=' + encodeURIComponent(search))

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