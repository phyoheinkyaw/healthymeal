// Initialize DataTable
$(document).ready(function() {
    $('#usersTable').DataTable({
        responsive: true,
        order: [[4, 'desc']], // Sort by joined date by default
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ users per page",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            emptyTable: "No users available"
        }
    });
});

// View User Details
function viewUserDetails(userId) {
    $.ajax({
        url: 'api/users/get_user.php',
        type: 'GET',
        data: { user_id: userId },
        success: function(response) {
            if (response.success) {
                const user = response.data;
                const joinedDate = new Date(user.created_at);
                const formattedDate = joinedDate.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: '2-digit', 
                    year: 'numeric'
                });

                let html = `
                    <div class="user-profile p-3">
                        <div class="d-flex align-items-center mb-4">
                            <div class="avatar-circle">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="ms-3">
                                <h4 class="mb-1">${user.full_name}</h4>
                                <p class="text-muted mb-0">@${user.username}</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="text-uppercase fw-bold mb-3">Account Information</h6>
                                <p><strong>Email:</strong> ${user.email}</p>
                                <p><strong>Role:</strong> ${user.role}</p>
                                <p><strong>Joined:</strong> ${formattedDate}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-uppercase fw-bold mb-3">Preferences</h6>
                                <p><strong>Dietary Restrictions:</strong> ${user.dietary_restrictions}</p>
                                <p><strong>Allergies:</strong> ${user.allergies}</p>
                                <p><strong>Cooking Experience:</strong> ${user.cooking_experience}</p>
                                <p><strong>Household Size:</strong> ${user.household_size}</p>
                            </div>
                        </div>
                    </div>
                `;
                $('#userDetails').html(html);
                $('#viewUserModal').modal('show');
            } else {
                alert('Error loading user details');
            }
        },
        error: function() {
            alert('Error loading user details');
        }
    });
}

// Edit User
function editUser(userId) {
    $.ajax({
        url: 'api/users/get_user.php',
        type: 'GET',
        data: { user_id: userId },
        success: function(response) {
            if (response.success) {
                const user = response.data;
                $('#editUserForm input[name="user_id"]').val(user.user_id);
                $('#editUserForm input[name="username"]').val(user.username);
                $('#editUserForm input[name="full_name"]').val(user.full_name);
                $('#editUserForm input[name="email"]').val(user.email);
                $('#editUserForm select[name="role"]').val(user.role);
                $('#editUserForm input[name="dietary_restrictions"]').val(user.dietary_restrictions);
                $('#editUserForm input[name="allergies"]').val(user.allergies);
                $('#editUserForm select[name="cooking_experience"]').val(user.cooking_experience);
                $('#editUserForm input[name="household_size"]').val(user.household_size);
                $('#editUserForm input[name="password"]').val(''); // Clear password field
                $('#editUserModal').modal('show');
            } else {
                alert('Error loading user data');
            }
        },
        error: function() {
            alert('Error loading user data');
        }
    });
}

// Save New User
function saveUser() {
    const formData = new FormData($('#addUserForm')[0]);
    
    $.ajax({
        url: 'api/users/add_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#addUserModal').modal('hide');
                location.reload();
            } else {
                alert(response.message || 'Error adding user');
            }
        },
        error: function() {
            alert('Error adding user');
        }
    });
}

// Update User
function updateUser() {
    const formData = new FormData($('#editUserForm')[0]);
    
    // Log the form data for debugging
    console.log('Updating user with data:', Object.fromEntries(formData));
    
    $.ajax({
        url: 'api/users/update_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('Update response:', response);
            if (response.success) {
                $('#editUserModal').modal('hide');
                location.reload();
            } else {
                alert(response.message || 'Error updating user');
            }
        },
        error: function(xhr, status, error) {
            console.error('Update error:', {xhr, status, error});
            alert('Error updating user: ' + (xhr.responseJSON?.message || error));
        }
    });
}

// Delete User
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        $.ajax({
            url: 'api/users/delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error deleting user');
                }
            },
            error: function() {
                alert('Error deleting user');
            }
        });
    }
} 