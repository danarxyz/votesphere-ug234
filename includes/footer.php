</main> <!-- Main Content Area End -->

<!-- Consistent Footer -->
<footer class="bg-white dark:bg-gray-800 mt-auto py-8 border-t border-gray-200 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. 
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
            Built with PHP, Tailwind CSS, and Alpine.js.
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Ensure Feather icons are replaced after Alpine.js initializes and on DOM content load
    // This might be redundant if called within Alpine's init, but good for safety.
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
    // If Alpine is managing dynamic content that includes icons, you might need to call feather.replace()
    // after those specific Alpine-driven updates if icons don't render.
    // For example, in a component: this.$nextTick(() => feather.replace());
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const resultsBox = document.getElementById('search-results');
        let selectedIdx = -1;
        let lastQuery = '';
        let debounceTimeout = null;

        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderResults(data) {
            if (!data.length) {
                resultsBox.innerHTML = '<li class="px-4 py-2 text-gray-400">No results</li>';
            } else {
                resultsBox.innerHTML = data.map(item =>
                    `<li>
                    <a href="<?= APP_URL ?>/src/vote.php?poll_id=${item.poll_id}" class="block px-4 py-2 hover:bg-violet-50 dark:hover:bg-violet-900/40 text-gray-900 dark:text-gray-100">${escapeHTML(item.title)}</a>
                </li>`
                ).join('');
            }
            resultsBox.classList.remove('hidden');
            selectedIdx = -1;
            updateActive();
        }

        function updateActive() {
            const items = resultsBox.querySelectorAll('li');
            items.forEach((item, idx) => {
                if (idx === selectedIdx) {
                    item.classList.add('bg-violet-100', 'dark:bg-violet-900/40');
                } else {
                    item.classList.remove('bg-violet-100', 'dark:bg-violet-900/40');
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const q = this.value.trim();
                lastQuery = q;
                clearTimeout(debounceTimeout);
                if (q.length < 2) {
                    resultsBox.innerHTML = '';
                    resultsBox.classList.add('hidden');
                    return;
                }
                debounceTimeout = setTimeout(() => {
                    fetch('<?= APP_URL ?>/src/search_api.php?q=' + encodeURIComponent(q))
                        .then(res => res.json())
                        .then(data => {
                            // Only show results for the latest query
                            if (searchInput.value.trim() === lastQuery) {
                                renderResults(data);
                            }
                        });
                }, 200);
            });

            searchInput.addEventListener('keydown', function(e) {
                const items = resultsBox.querySelectorAll('li a');
                if (!resultsBox.classList.contains('hidden') && items.length > 0) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        selectedIdx = (selectedIdx + 1) % items.length;
                        updateActive();
                        items[selectedIdx].parentElement.scrollIntoView({
                            block: 'nearest'
                        });
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        selectedIdx = (selectedIdx - 1 + items.length) % items.length;
                        updateActive();
                        items[selectedIdx].parentElement.scrollIntoView({
                            block: 'nearest'
                        });
                    } else if (e.key === 'Enter' && selectedIdx >= 0) {
                        e.preventDefault();
                        items[selectedIdx].click();
                    }
                }
            });

            // Hide results on click outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                    resultsBox.classList.add('hidden');
                }
            });
        }
    });
</script>
</body>

</html>