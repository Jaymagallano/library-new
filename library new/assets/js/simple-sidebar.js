document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggle-sidebar');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const icon = toggleBtn.querySelector('i');
    
    toggleBtn.addEventListener('click', function() {
        if (sidebar.style.width === '70px') {
            // Expand
            sidebar.style.width = '280px';
            sidebar.style.padding = '20px';
            mainContent.style.marginLeft = '280px';
            icon.className = 'fas fa-chevron-left';
            
            // Show text
            document.querySelectorAll('.sidebar-header h2, .sidebar-header p, .sidebar-menu a span').forEach(el => {
                el.style.display = '';
            });
            
            // Reset icons
            document.querySelectorAll('.sidebar-menu i').forEach(el => {
                el.style.marginRight = '10px';
                el.style.fontSize = '';
            });
            
        } else {
            // Collapse
            sidebar.style.width = '70px';
            sidebar.style.padding = '15px 10px';
            mainContent.style.marginLeft = '70px';
            icon.className = 'fas fa-chevron-right';
            
            // Hide text
            document.querySelectorAll('.sidebar-header h2, .sidebar-header p, .sidebar-menu a span').forEach(el => {
                el.style.display = 'none';
            });
            
            // Adjust icons
            document.querySelectorAll('.sidebar-menu i').forEach(el => {
                el.style.marginRight = '0';
                el.style.fontSize = '18px';
            });
        }
    });
});