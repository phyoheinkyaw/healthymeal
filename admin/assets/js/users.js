// Document ready function
$(document).ready(function() {
    // Show alert from localStorage if present (after reload)
    const storedMsg = localStorage.getItem('userMessage');
    const storedType = localStorage.getItem('userMessageType');
    if (storedMsg && storedType) {
        showAlert(storedType, storedMsg);
        localStorage.removeItem('userMessage');
        localStorage.removeItem('userMessageType');
    }
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
                
                // Convert numeric cooking experience to display text
                let cookingExperience = 'Not specified';
                switch(parseInt(user.cooking_experience)) {
                    case 0:
                        cookingExperience = 'Beginner';
                        break;
                    case 1:
                        cookingExperience = 'Intermediate';
                        break;
                    case 2:
                        cookingExperience = 'Advanced';
                        break;
                }

                let html = `
                    <div class="user-profile-card p-4 rounded-4 shadow-lg bg-gradient" style="background: linear-gradient(135deg, #f8fafc 65%, #cfe2ff 100%);">
                        <div class="d-flex align-items-center mb-4 gap-3">
                            <div class="avatar-circle-lg bg-primary text-white fw-bold d-flex align-items-center justify-content-center shadow" style="width: 64px; height: 64px; border-radius: 50%; font-size: 2.25rem;">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold text-primary-emphasis">${user.full_name}</h4>
                                <p class="text-muted mb-0">@${user.username} <span class="badge bg-${user.role == 1 ? 'danger' : 'info'} ms-2">${user.role == 1 ? 'Admin' : 'User'}</span></p>
                                <p class="small text-secondary mb-0"><i class="bi bi-calendar-event me-1"></i> Joined: ${formattedDate}</p>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card border-0 bg-white shadow-sm h-100">
                                    <div class="card-body">
                                        <h6 class="text-uppercase fw-semibold text-secondary mb-3"><i class="bi bi-person-badge me-2"></i>Account</h6>
                                        <p class="mb-2"><strong>Email:</strong> <span class="text-dark">${user.email}</span></p>
                                        <p class="mb-2"><strong>Role:</strong> <span class="text-dark">${user.role == 1 ? 'Admin' : 'User'}</span></p>
                                        <p class="mb-0"><strong>Household Size:</strong> <span class="text-dark">${user.household_size}</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 bg-white shadow-sm h-100">
                                    <div class="card-body">
                                        <h6 class="text-uppercase fw-semibold text-secondary mb-3"><i class="bi bi-heart-pulse me-2"></i>Preferences</h6>
                                        <p class="mb-2"><strong>Dietary Restrictions:</strong> <span class="text-dark">${user.dietary_restrictions}</span></p>
                                        <p class="mb-2"><strong>Allergies:</strong> <span class="text-dark">${user.allergies}</span></p>
                                        <p class="mb-0"><strong>Cooking Experience:</strong> <span class="text-dark">${cookingExperience}</span></p>
                                    </div>
                                </div>
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
                localStorage.setItem('userMessage', response.message);
                localStorage.setItem('userMessageType', 'success');
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
                localStorage.setItem('userMessage', response.message);
                localStorage.setItem('userMessageType', 'success');
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

// Custom confirm modal for delete (dynamic, orders style)
function showDeleteConfirmModal(onConfirm, options = {}) {
    $('#deleteConfirmModal').remove();
    const title = options.title || 'Delete Confirmation';
    const message = options.message || 'Are you sure you want to delete this item?';
    const icon = options.icon || '<i class="bi bi-trash-fill text-danger me-2"></i>';
    const modalHtml = `
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger-subtle">
            <h5 class="modal-title" id="deleteConfirmLabel">${icon}${title}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">${message}</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Yes, Delete</button>
          </div>
        </div>
      </div>
    </div>`;
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
    $('#deleteConfirmBtn').on('click', function() {
        modal.hide();
        if (onConfirm) onConfirm();
    });
    $('#deleteConfirmModal').on('hidden.bs.modal', function() {
        $('#deleteConfirmModal').remove();
    });
}

// Delete User
function deleteUser(userId) {
    showDeleteConfirmModal(function() {
        $.ajax({
            url: 'api/users/delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            success: function(response) {
                if (response.success) {
                    localStorage.setItem('userMessage', response.message);
                    localStorage.setItem('userMessageType', 'success');
                    location.reload();
                } else {
                    localStorage.setItem('userMessage', response.message || 'Error deleting user');
                    localStorage.setItem('userMessageType', 'danger');
                    location.reload();
                }
            },
            error: function(xhr) {
                let msg = 'Error deleting user';
                if (xhr && xhr.responseText) {
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        if (resp.message) msg = resp.message;
                    } catch (e) {
                        // Not JSON, fallback to default
                    }
                }
                localStorage.setItem('userMessage', msg);
                localStorage.setItem('userMessageType', 'danger');
                location.reload();
            }
        });
    }, {
        title: 'Delete User',
        message: 'Are you sure you want to delete this user? This action cannot be undone.',
        icon: '<i class="bi bi-trash-fill text-danger me-2"></i>'
    });
}