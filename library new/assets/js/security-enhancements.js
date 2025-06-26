/**
 * Ultra-premium security enhancements for login and registration forms
 * Provides advanced security features for a premium user experience
 */
document.addEventListener('DOMContentLoaded', function() {
    // Security badge display
    addSecurityBadge();
    
    // Add input monitoring for suspicious patterns
    monitorInputs();
    
    // Add form submission security checks
    enhanceFormSecurity();
    
    /**
     * Adds a security badge to the form
     */
    function addSecurityBadge() {
        const formFooters = document.querySelectorAll('.form-footer');
        
        formFooters.forEach(footer => {
            const securityBadge = document.createElement('div');
            securityBadge.className = 'security-badge';
            securityBadge.innerHTML = `
                <div style="margin-top: 15px; font-size: 12px; color: #6e8efb; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-shield-alt" style="margin-right: 5px;"></i> 
                    Secured with advanced encryption
                </div>
            `;
            footer.appendChild(securityBadge);
        });
    }
    
    /**
     * Monitors inputs for suspicious patterns
     */
    function monitorInputs() {
        const inputs = document.querySelectorAll('input');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                // Check for SQL injection patterns
                const value = this.value;
                const sqlPatterns = [
                    /(\%27)|(\')|(\-\-)|(\%23)|(#)/i,
                    /((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i,
                    /\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i,
                    /((\%27)|(\'))union/i
                ];
                
                for (let pattern of sqlPatterns) {
                    if (pattern.test(value)) {
                        this.value = value.replace(/[^\w\s@.-]/gi, '');
                        showSecurityAlert('Suspicious input detected and sanitized');
                        break;
                    }
                }
            });
        });
    }
    
    /**
     * Enhances form security with additional checks
     */
    function enhanceFormSecurity() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            // Add a hidden honeypot field to detect bots
            const honeypot = document.createElement('div');
            honeypot.style.opacity = '0';
            honeypot.style.position = 'absolute';
            honeypot.style.top = '-9999px';
            honeypot.style.left = '-9999px';
            honeypot.innerHTML = `<input type="text" name="website" tabindex="-1">`;
            form.appendChild(honeypot);
            
            // Add submission timestamp to prevent automated submissions
            const timestamp = document.createElement('input');
            timestamp.type = 'hidden';
            timestamp.name = 'timestamp';
            timestamp.value = Date.now();
            form.appendChild(timestamp);
            
            // Add form submission handler
            form.addEventListener('submit', function(e) {
                const honeypotField = this.querySelector('input[name="website"]');
                const timestampField = this.querySelector('input[name="timestamp"]');
                
                // Check if honeypot is filled (bot detection)
                if (honeypotField && honeypotField.value) {
                    e.preventDefault();
                    console.log('Bot submission detected');
                    return;
                }
                
                // Check submission timing (too fast = likely automated)
                if (timestampField) {
                    const elapsed = Date.now() - parseInt(timestampField.value);
                    if (elapsed < 1500) { // Less than 1.5 seconds
                        e.preventDefault();
                        console.log('Submission too fast, possible automation');
                        return;
                    }
                }
            });
        });
    }
    
    /**
     * Shows a security alert to the user
     * @param {string} message - The alert message
     */
    function showSecurityAlert(message) {
        // Check if an alert already exists
        let alertElement = document.querySelector('.security-alert');
        
        if (!alertElement) {
            alertElement = document.createElement('div');
            alertElement.className = 'security-alert';
            alertElement.style.position = 'fixed';
            alertElement.style.bottom = '20px';
            alertElement.style.right = '20px';
            alertElement.style.backgroundColor = '#fff';
            alertElement.style.color = '#e74c3c';
            alertElement.style.padding = '10px 15px';
            alertElement.style.borderRadius = '5px';
            alertElement.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            alertElement.style.zIndex = '9999';
            alertElement.style.fontSize = '13px';
            alertElement.style.display = 'flex';
            alertElement.style.alignItems = 'center';
            alertElement.style.transition = 'opacity 0.3s ease-out';
            
            document.body.appendChild(alertElement);
        }
        
        alertElement.innerHTML = `<i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i> ${message}`;
        alertElement.style.opacity = '1';
        
        // Hide after 3 seconds
        setTimeout(() => {
            alertElement.style.opacity = '0';
            setTimeout(() => {
                if (alertElement.parentNode) {
                    alertElement.parentNode.removeChild(alertElement);
                }
            }, 300);
        }, 3000);
    }
});