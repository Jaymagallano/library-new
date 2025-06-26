        </div><!-- End of main-content -->
    </div><!-- End of dashboard-container -->
    
    <!-- Performance optimizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/library%20new/admin/includes/form-submit.js"></script>
    <script src="/library%20new/admin/includes/activity-chart.js"></script>
    <script src="/library%20new/admin/includes/load-more.js"></script>
    <script defer>
    // Add active class to current menu item
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (currentPath.includes(href) && href !== '#') {
                item.classList.add('active');
            }
        });
        
        // Initialize any tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (typeof bootstrap !== 'undefined') {
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Add confirmation to delete buttons
        const deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>