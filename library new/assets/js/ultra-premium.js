// Ultra Premium JS Enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Faster loader
    window.addEventListener('load', function() {
        const loader = document.querySelector('.loader-container');
        setTimeout(function() {
            loader.classList.add('loaded');
        }, 300); // Reduced from 500ms to 300ms
    });

    // Enhanced ripple effect
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const x = e.clientX - e.target.offsetLeft;
            const y = e.clientY - e.target.offsetTop;
            
            const ripple = document.createElement('span');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 500); // Reduced from 600ms to 500ms
        });
    });

    // Form field focus enhancement
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // Clear form on page refresh
    window.onload = function() {
        document.querySelector("form").reset();
    };
});