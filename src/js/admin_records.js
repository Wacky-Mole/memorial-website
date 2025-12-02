// This file contains JavaScript functions for managing admin records, including AJAX calls for dynamic updates.

document.addEventListener('DOMContentLoaded', function() {
    // Function to fetch and display memorial entries
    function fetchEntries() {
        fetch('service/storage.php?action=getEntries')
            .then(response => response.json())
            .then(data => {
                const entriesContainer = document.getElementById('entriesContainer');
                entriesContainer.innerHTML = '';
                data.forEach(entry => {
                    const entryDiv = document.createElement('div');
                    entryDiv.classList.add('entry');
                    entryDiv.innerHTML = `
                        <h3>${entry.name}</h3>
                        <p>Email: ${entry.email}</p>
                        <img src="${entry.photo}" alt="${entry.name}" />
                        <button onclick="editEntry(${entry.id})">Edit</button>
                        <button onclick="deleteEntry(${entry.id})">Delete</button>
                    `;
                    entriesContainer.appendChild(entryDiv);
                });
            })
            .catch(error => console.error('Error fetching entries:', error));
    }

    // Function to edit an entry
    window.editEntry = function(id) {
        // Redirect to edit page with entry ID
        window.location.href = `admin/edit.php?id=${id}`;
    };

    // Function to delete an entry
    window.deleteEntry = function(id) {
        if (confirm('Are you sure you want to delete this entry?')) {
            fetch(`service/storage.php?action=deleteEntry&id=${id}`, { method: 'DELETE' })
                .then(response => {
                    if (response.ok) {
                        fetchEntries(); // Refresh entries after deletion
                    } else {
                        alert('Failed to delete entry.');
                    }
                })
                .catch(error => console.error('Error deleting entry:', error));
        }
    };

    // Initial fetch of entries
    fetchEntries();
});