document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('form[role="search"]');
    const searchInput = document.getElementById('navbarSearch');
    const searchButton = document.getElementById('navbarSearchBtn');
    const resultsContainer = document.getElementById('liveSearchResults');
    let searchTimeout = null;
    let currentQuery = '';

    // Prevent form submission
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
    });

    // Function to clear results
    function clearResults() {
        resultsContainer.innerHTML = '';
        resultsContainer.classList.remove('show');
        currentQuery = '';
    }

    // Function to perform the search
    async function performSearch(query) {
        if (query === currentQuery) return; // Prevent duplicate searches
        currentQuery = query;

        if (!query.trim()) {
            clearResults();
            return;
        }

        try {
            const response = await fetch(`/hm/api/meal-kits/search.php?q=${encodeURIComponent(query)}&limit=5`);
            const data = await response.json();

            if (data.success) {
                displayResults(data);
            } else {
                console.error('Search failed:', data.message);
                clearResults();
            }
        } catch (error) {
            console.error('Error performing search:', error);
            clearResults();
        }
    }

    // Helper function to get meal kit image URL (mirrors PHP logic)
    function getMealKitImageUrl(imageUrl, mealName) {
        if (!imageUrl || imageUrl.trim() === '') {
            return `https://placehold.co/600x400/FFF3E6/FF6B35?text=${encodeURIComponent(mealName)}`;
        }
        if (/^https?:\/\//i.test(imageUrl)) {
            return imageUrl;
        }
        // Get the base project path from the current URL (e.g. '/hm')
        const parts = window.location.pathname.replace(/^\//, '').split('/');
        const projectBase = '/' + parts[0]; // e.g. '/hm'
        return `${projectBase}/uploads/meal-kits/${imageUrl}`;
    }

    // Function to display search results
    function displayResults(data) {
        if (!data.data || !data.data.length) {
            resultsContainer.innerHTML = `
                <div class="search-result-item">
                    <p class="mb-0 text-muted">No results found</p>
                </div>`;
            resultsContainer.classList.add('show');
            return;
        }

        let html = data.data.map(meal => {
            let imgUrl = getMealKitImageUrl(meal.image_url, meal.name);
            return `
                <a href="/hm/meal-details.php?id=${meal.id}" class="search-result-item">
                    <img src="${imgUrl}" alt="${meal.name}">
                    <div class="search-result-info">
                        <div class="search-result-name">${meal.name}</div>
                        <div class="search-result-price">$${Number(meal.price).toFixed(2)}</div>
                        <p class="search-result-description">${meal.description}</p>
                    </div>
                </a>
            `;
        }).join('');

        if (data.has_more) {
            html += `
                <a href="/hm/search-results.php?q=${encodeURIComponent(currentQuery)}" 
                   class="view-all-results">
                    View all ${data.total_count} results
                </a>`;
        }

        resultsContainer.innerHTML = html;
        resultsContainer.classList.add('show');
    }

    // Event listener for search input
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Hide results if query is empty
        if (!query) {
            clearResults();
            return;
        }

        // Set new timeout for search
        searchTimeout = setTimeout(() => performSearch(query), 300);
    });

    // Event listener for search button
    searchButton.addEventListener('click', function() {
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `/hm/search-results.php?q=${encodeURIComponent(query)}`;
        }
    });

    // Event listener for Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = e.target.value.trim();
            if (query) {
                window.location.href = `/hm/search-results.php?q=${encodeURIComponent(query)}`;
            }
        }
    });

    // Clear results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('form[role="search"]')) {
            clearResults();
        }
    });

    // Clear results when search input loses focus
    searchInput.addEventListener('blur', function(e) {
        // Small delay to allow clicking on results
        setTimeout(() => {
            if (!document.activeElement.closest('form[role="search"]')) {
                clearResults();
            }
        }, 200);
    });
}); 