/**
 * Collapsible Sidebar Functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-sidebar');
    const mobileToggleBtn = document.getElementById('mobile-toggle');
    const toggleIcon = toggleBtn.querySelector('i');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // Check if sidebar state is saved in localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    const isMobile = window.innerWidth <= 768;
    
    // Apply saved state on page load
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
        toggleBtn.classList.add('collapsed');
        toggleIcon.classList.remove('fa-chevron-left');
        toggleIcon.classList.add('fa-chevron-right');
    } else if (isMobile) {
        // On mobile, start with sidebar collapsed
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Function to toggle sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Toggle icon for desktop button
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        } else {
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
        }
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
    
    // Toggle sidebar when desktop button is clicked
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        toggleSidebar();
    });
    
    // Toggle sidebar when mobile button is clicked
    mobileToggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        toggleSidebar();
    });
});