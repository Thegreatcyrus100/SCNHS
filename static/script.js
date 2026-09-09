// Create floating bubbles
function createBubbles() {
    const body = document.querySelector('body');
    const bubbleCount = 10;
    
    for(let i = 0; i < bubbleCount; i++) {
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        
        // Random size and position
        const size = Math.random() * 100 + 50;
        bubble.style.width = `${size}px`;
        bubble.style.height = `${size}px`;
        bubble.style.left = `${Math.random() * 100}vw`;
        bubble.style.animationDuration = `${Math.random() * 10 + 10}s`;
        bubble.style.animationDelay = `${Math.random() * 5}s`;
        
        body.appendChild(bubble);
    }
}

// Form validation enhancement
function validateForm() {
    const inputs = document.querySelectorAll('input[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('invalid');
            shakElement(input);
        } else {
            input.classList.remove('invalid');
        }
    });

    return isValid;
}

// Add shake animation to invalid fields
function shakElement(element) {
    element.style.animation = 'shake 0.5s ease-in-out';
    element.addEventListener('animationend', () => {
        element.style.animation = '';
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    createBubbles();
    
    // Add form validation
    const form = document.querySelector('form');
    if(form) {
        form.addEventListener('submit', (e) => {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    }

    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
});
