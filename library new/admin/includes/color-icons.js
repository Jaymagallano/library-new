// Apply colors to icons
document.addEventListener('DOMContentLoaded', function() {
  // Get all button icons and set their color to white
  document.querySelectorAll('.btn-primary i, .btn-warning i, .btn-danger i, .btn-secondary i, .btn-success i').forEach(function(icon) {
    icon.style.color = 'white';
  });
});