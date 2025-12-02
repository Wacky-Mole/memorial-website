// This file handles the submission of new memorial entries via AJAX, providing a seamless user experience.

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('memorialForm');
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(form);
        
        fetch('save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Memorial entry submitted successfully!');
                form.reset();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the form.');
        });
    });
});