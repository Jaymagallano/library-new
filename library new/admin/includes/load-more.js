// Load more functionality for activity list
document.addEventListener('DOMContentLoaded', function() {
    // Add load more button to activity list
    const activityList = document.querySelector('.activity-list');
    if (activityList) {
        const activityItems = activityList.querySelectorAll('.activity-item');
        const initialItemsToShow = 5;
        const itemsPerLoad = 5;
        let currentlyShown = initialItemsToShow;
        
        // If list is short, just show all items
        if (activityItems.length <= initialItemsToShow) {
            return;
        }
        
        // Hide items beyond the initial count
        for (let i = initialItemsToShow; i < activityItems.length; i++) {
            activityItems[i].style.display = 'none';
        }
        
        // Create load more container
        const loadMoreContainer = document.createElement('div');
        loadMoreContainer.className = 'load-more-container';
        
        // Create load more button
        const loadMoreBtn = document.createElement('button');
        loadMoreBtn.className = 'load-more-btn';
        loadMoreBtn.innerHTML = 'Load More <i class="fas fa-spinner"></i>';
        
        // Add button to container
        loadMoreContainer.appendChild(loadMoreBtn);
        
        // Add container after activity list
        activityList.parentNode.insertBefore(loadMoreContainer, activityList.nextSibling);
        
        // Add click event to button
        loadMoreBtn.addEventListener('click', function() {
            // Calculate next batch of items to show
            const nextBatch = Math.min(currentlyShown + itemsPerLoad, activityItems.length);
            
            // Show next batch of items
            for (let i = currentlyShown; i < nextBatch; i++) {
                activityItems[i].style.display = '';
            }
            
            // Update counter
            currentlyShown = nextBatch;
            
            // Hide button if all items are shown
            if (currentlyShown >= activityItems.length) {
                loadMoreContainer.style.display = 'none';
            }
            
            // Update button text with count
            const remaining = activityItems.length - currentlyShown;
            if (remaining > 0) {
                loadMoreBtn.innerHTML = `Load More (${remaining}) <i class="fas fa-spinner"></i>`;
            }
        });
        
        // Initialize button text with count
        const remaining = activityItems.length - currentlyShown;
        loadMoreBtn.innerHTML = `Load More (${remaining}) <i class="fas fa-spinner"></i>`;
    }
});