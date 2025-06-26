// Simple collapsible sidebar script
document.addEventListener('DOMContentLoaded', function() {
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'sidebar-toggle';
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    toggleBtn.className = 'sidebar-toggle-btn';
    
    // Add button to the page
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        document.body.appendChild(toggleBtn);
        
        // Add toggle functionality
        toggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            
            // Save state
            if (document.body.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });
        
        // Check saved state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    }
});