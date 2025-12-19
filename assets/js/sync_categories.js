// sync_categories.js - Sync categories between admin pages
class CategorySync {
    constructor() {
        this.checkInterval = 5000; // Check every 5 seconds
        this.init();
    }
    
    init() {
        // Check for category updates on page load
        this.checkForUpdates();
        
        // Set up periodic checking
        setInterval(() => this.checkForUpdates(), this.checkInterval);
        
        // Listen for storage events (from other tabs/windows)
        window.addEventListener('storage', (e) => {
            if (e.key === 'categories_updated') {
                this.handleCategoryUpdate(e.newValue);
            }
        });
    }
    
    checkForUpdates() {
        const updateData = localStorage.getItem('categories_updated');
        if (updateData) {
            this.handleCategoryUpdate(updateData);
        }
    }
    
    handleCategoryUpdate(updateData) {
        try {
            const data = JSON.parse(updateData);
            const now = Date.now();
            const fiveMinutesAgo = now - (5 * 60 * 1000);
            
            // Only handle updates from the last 5 minutes
            if (data.timestamp && data.timestamp > fiveMinutesAgo) {
                this.refreshCategoryDropdowns(data.categories);
                
                // Clear the flag after processing
                localStorage.removeItem('categories_updated');
            }
        } catch (error) {
            console.error('Error handling category update:', error);
        }
    }
    
    refreshCategoryDropdowns(categories) {
        // Update all category dropdowns on the page
        const categorySelects = document.querySelectorAll('select[name="category"], select#category_id');
        
        categorySelects.forEach(select => {
            if (select.id === 'category_id' || select.name === 'category') {
                this.updateSelectOptions(select, categories);
            }
        });
    }
    
    updateSelectOptions(selectElement, categories) {
        const currentValue = selectElement.value;
        
        // Clear existing options (except the first "All Categories" or "Select Category" option)
        while (selectElement.options.length > 1) {
            selectElement.remove(1);
        }
        
        // Add updated categories
        categories.forEach(category => {
            const option = new Option(category.name, category.category_id);
            selectElement.appendChild(option);
        });
        
        // Restore selected value if it still exists
        if (currentValue) {
            const optionExists = Array.from(selectElement.options).some(
                option => option.value === currentValue
            );
            if (optionExists) {
                selectElement.value = currentValue;
            }
        }
        
        // Trigger change event if this is a filter dropdown
        if (selectElement.name === 'category') {
            selectElement.dispatchEvent(new Event('change'));
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new CategorySync();
});