// Update Profile
function updateProfile() {
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);
    
    fetch('api/profile/update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Profile updated successfully');
        } else {
            showAlert('error', data.message || 'Failed to update profile');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'An error occurred while updating profile');
    });
}

// Update Password
function updatePassword() {
    const form = document.getElementById('passwordForm');
    const formData = new FormData(form);
    
    // Validate passwords match
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    
    if (newPassword !== confirmPassword) {
        showAlert('error', 'New passwords do not match');
        return;
    }
    
    fetch('api/profile/change-password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Password changed successfully');
            form.reset();
        } else {
            showAlert('error', data.message || 'Failed to change password');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'An error occurred while changing password');
    });
}

// Helper function to show alerts
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
} 