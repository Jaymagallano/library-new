// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
    // Fix for delete buttons
    const deleteButtons = document.querySelectorAll('.btn-danger[title="Delete"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Prevent the default button behavior
            e.preventDefault();
            
            // Get the parent form
            const form = this.closest('form');
            
            // Show confirmation dialog
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                // Submit the form if confirmed
                form.submit();
            }
        });
    });
    
    // Fix for deactivate buttons
    const deactivateButtons = document.querySelectorAll('.btn-warning[title="Deactivate"]');
    
    deactivateButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Prevent the default button behavior
            e.preventDefault();
            
            // Get the parent form
            const form = this.closest('form');
            
            // Show confirmation dialog
            if (confirm('Are you sure you want to deactivate this user?')) {
                // Submit the form if confirmed
                form.submit();
            }
        });
    });
});