// This file manages site-specific JavaScript functionalities, enhancing user interaction.

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('memorialForm');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const email = document.getElementById('email').value;
            const name = document.getElementById('deceasedName').value;
            const photo = document.getElementById('photo').files[0];

            if (validateForm(email, name, photo)) {
                form.submit();
            }
        });
    }

    function validateForm(email, name, photo) {
        if (!email || !name || !photo) {
            alert('Please fill in all fields and upload a photo.');
            return false;
        }
        if (!validateEmail(email)) {
            alert('Please enter a valid email address.');
            return false;
        }
        return true;
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
});