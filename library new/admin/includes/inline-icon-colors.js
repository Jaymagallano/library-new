// Apply colors directly to icons using JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Color action button icons
    const editIcons = document.querySelectorAll('.btn-primary .fa-edit');
    editIcons.forEach(icon => {
        icon.style.color = '#ffffff';
    });
    
    const deactivateIcons = document.querySelectorAll('.btn-warning .fa-user-slash');
    deactivateIcons.forEach(icon => {
        icon.style.color = '#ffffff';
    });
    
    const deleteIcons = document.querySelectorAll('.btn-danger .fa-trash');
    deleteIcons.forEach(icon => {
        icon.style.color = '#ffffff';
    });
    
    // Color dashboard icons
    const userIcons = document.querySelectorAll('.fa-users');
    userIcons.forEach(icon => {
        if (!icon.closest('.btn-primary')) {
            icon.style.color = '#4361ee';
        }
    });
    
    const bookIcons = document.querySelectorAll('.fa-book');
    bookIcons.forEach(icon => {
        if (!icon.closest('.btn-primary')) {
            icon.style.color = '#3a86ff';
        }
    });
    
    const exchangeIcons = document.querySelectorAll('.fa-exchange-alt');
    exchangeIcons.forEach(icon => {
        if (!icon.closest('.btn-primary')) {
            icon.style.color = '#7209b7';
        }
    });
});