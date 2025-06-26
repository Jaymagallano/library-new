/**
 * Ultra-premium email validation for Gmail addresses
 * This script ensures that only properly formatted Gmail addresses are accepted
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find all email input fields
    const emailInputs = document.querySelectorAll('input[type="email"]');
    
    emailInputs.forEach(input => {
        // Add input event listener for real-time validation
        input.addEventListener('input', function() {
            validateGmailAddress(this);
        });
        
        // Add blur event for final validation
        input.addEventListener('blur', function() {
            validateGmailAddress(this, true);
        });
    });
    
    // Add form submit validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const emailInput = this.querySelector('input[type="email"]');
            if (emailInput && !isValidGmail(emailInput.value)) {
                e.preventDefault();
                showError(emailInput, 'Please enter a valid Gmail address (@gmail.com)');
            }
        });
    });
    
    /**
     * Validates if the input contains a valid Gmail address
     * @param {HTMLElement} input - The email input element
     * @param {boolean} showMessage - Whether to show error message
     */
    function validateGmailAddress(input, showMessage = false) {
        const value = input.value.trim();
        
        // Clear previous validation state
        input.classList.remove('is-invalid');
        input.classList.remove('is-valid');
        
        // Remove any existing feedback elements
        const parent = input.parentElement;
        const existingFeedback = parent.querySelector('.email-feedback');
        if (existingFeedback) {
            parent.removeChild(existingFeedback);
        }
        
        // Skip validation if empty (server-side will handle this)
        if (value === '') return;
        
        if (isValidGmail(value)) {
            input.classList.add('is-valid');
            if (showMessage) {
                showSuccess(input);
            }
        } else {
            input.classList.add('is-invalid');
            if (showMessage) {
                showError(input, 'Please enter a valid Gmail address (@gmail.com)');
            }
        }
    }
    
    /**
     * Checks if the provided string is a valid Gmail address
     * @param {string} email - The email to validate
     * @return {boolean} - Whether the email is a valid Gmail address
     */
    function isValidGmail(email) {
        // Basic email validation
        if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            return false;
        }
        
        // Check specifically for Gmail domain
        const domain = email.split('@')[1];
        return domain && domain.toLowerCase() === 'gmail.com';
    }
    
    /**
     * Shows an error message for the input
     * @param {HTMLElement} input - The input element
     * @param {string} message - The error message
     */
    function showError(input, message) {
        const parent = input.parentElement;
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback email-feedback';
        feedback.textContent = message;
        parent.appendChild(feedback);
    }
    
    /**
     * Shows a success message for the input
     * @param {HTMLElement} input - The input element
     */
    function showSuccess(input) {
        const parent = input.parentElement;
        const feedback = document.createElement('div');
        feedback.className = 'valid-feedback email-feedback';
        feedback.textContent = 'Valid Gmail address';
        feedback.style.color = '#2ecc71';
        feedback.style.fontSize = '12px';
        feedback.style.marginTop = '4px';
        parent.appendChild(feedback);
    }
});