/**
 * Simple Location Search Module
 * Works like resource search - simple dropdown with 5 results max
 */

class LocationMatcher {
    constructor(options = {}) {
        this.baseUrl = options.baseUrl || '';
        this.serviceUrl = `${this.baseUrl}config/location_service.php`;
    }

    /**
     * Search for locations - simple and fast
     * @param {string} query - Search query
     * @returns {Promise<Array>} Array of matching locations (max 5)
     */
    async search(query) {
        if (!query || query.trim().length < 2) {
            return [];
        }

        try {
            const params = new URLSearchParams({
                action: 'search',
                q: query.trim(),
                limit: 5  // Always limit to 5 results
            });

            const response = await fetch(`${this.serviceUrl}?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const results = await response.json();
            return results.slice(0, 5); // Ensure max 5 results
            
        } catch (error) {
            console.error('Search error:', error);
            return [];
        }
    }

    /**
     * Setup simple autocomplete like resource search
     * @param {HTMLElement} inputElement - The input field
     * @param {Object} options - Options including onSelect callback
     */
    setupAutocomplete(inputElement, options = {}) {
        const onSelect = options.onSelect || (() => {});
        
        // Create dropdown container
        const dropdown = document.createElement('div');
        dropdown.className = 'location-dropdown';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        `;
        
        // Make parent container relative
        inputElement.parentElement.style.position = 'relative';
        inputElement.parentElement.appendChild(dropdown);

        let searchTimeout;

        // Handle typing in input
        inputElement.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            // Debounce search
            searchTimeout = setTimeout(async () => {
                const results = await this.search(query);
                this.showDropdown(dropdown, results, onSelect);
            }, 250);
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!inputElement.parentElement.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Clear input when focused for new search
        inputElement.addEventListener('focus', () => {
            if (inputElement.value) {
                inputElement.select();
            }
        });
    }

    /**
     * Show dropdown with search results
     * @param {HTMLElement} dropdown - Dropdown container
     * @param {Array} results - Search results
     * @param {Function} onSelect - Selection callback
     */
    showDropdown(dropdown, results, onSelect) {
        dropdown.innerHTML = '';

        if (results.length === 0) {
            dropdown.innerHTML = `
                <div style="padding: 16px; color: #6c757d; text-align: center; font-size: 0.9rem;">
                    <span class="material-icons" style="font-size: 20px; margin-bottom: 4px; display: block; opacity: 0.5;">search_off</span>
                    No locations found
                </div>
            `;
            dropdown.style.display = 'block';
            return;
        }

        results.forEach(result => {
            const item = document.createElement('div');
            item.style.cssText = `
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                padding: 12px 16px;
                cursor: pointer;
                border-bottom: 1px solid #f1f5f9;
                transition: all 0.2s ease;
                background: white;
            `;

            const icon = result.type === 'barangay' ? '📍' : '🏛️';
            const population = result.population > 0 ? 
                ` (${result.population.toLocaleString()} people)` : '';

            item.innerHTML = `
                <div style="font-size: 1.1rem;">${icon}</div>
                <div style="flex: 1;">
                    <div style="font-weight: 500; color: #212529; margin-bottom: 2px;">
                        ${result.display_name}
                    </div>
                    <div style="font-size: 0.8rem; color: #6c757d;">
                        ${result.type}${population}
                    </div>
                </div>
            `;

            // Simple hover effect
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f8fafc';
                item.style.borderLeft = '3px solid #3b82f6';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'white';
                item.style.borderLeft = '3px solid transparent';
            });

            // Click to select
            item.addEventListener('click', () => {
                dropdown.style.display = 'none';
                onSelect(result);
            });

            dropdown.appendChild(item);
        });

        // Remove border from last item
        const lastItem = dropdown.lastElementChild;
        if (lastItem) {
            lastItem.style.borderBottom = 'none';
        }

        dropdown.style.display = 'block';
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LocationMatcher;
} else if (typeof window !== 'undefined') {
    window.LocationMatcher = LocationMatcher;
}