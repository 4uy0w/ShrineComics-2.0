// Simple JavaScript for Admin Management
function openAddPointsModal(userId, username, currentPoints) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUsername').value = username;
    document.getElementById('modalCurrentPoints').value = currentPoints;
    document.getElementById('pointsModal').style.display = 'block';
}

function closeAddPointsModal() {
    document.getElementById('pointsModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('pointsModal')) {
        closeAddPointsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddPointsModal();
    }
});

// Simple dropdown functionality
document.addEventListener('click', function(e) {
    // Close all dropdowns when clicking elsewhere
    if (!e.target.classList.contains('btn-status') && !e.target.closest('.dropdown-menu')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});