document.addEventListener('DOMContentLoaded', function() {
    // Format time inputs to display in 12-hour format
    const formatTimeInputs = function() {
        const timeInputs = document.querySelectorAll('input[type="time"]');
        timeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value) {
                    const timeValue = new Date(`2000-01-01T${this.value}`);
                    const formattedTime = timeValue.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    const formText = this.parentElement.querySelector('.form-text');
                    if (formText) {
                        if (this.id === 'time_slot') {
                            formText.innerHTML = `The time when delivery occurs (${formattedTime}).`;
                        } else if (this.id === 'cutoff_time') {
                            formText.innerHTML = `Latest time customers can place orders for this slot (${formattedTime}).`;
                        }
                    }
                }
            });
        });
    };

    // Handle toggle buttons asynchronously
    const setupToggleButtons = function() {
        const toggleForms = document.querySelectorAll('.toggle-status-form');
        toggleForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!confirm('Toggle active status for this delivery option?')) {
                    return;
                }
                
                const toggleId = this.querySelector('input[name="toggle_id"]').value;
                const toggleBtn = this.querySelector('button[type="submit"]');
                const card = toggleBtn.closest('.delivery-card');
                
                // Show loading state
                toggleBtn.disabled = true;
                toggleBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                fetch('/hm/admin/api/delivery-options/toggle_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: toggleId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        const isActive = data.data.is_active;
                        const statusBadge = card.querySelector('.status-badge');
                        
                        if (statusBadge) {
                            statusBadge.className = `badge ${isActive ? 'bg-success' : 'bg-secondary'} status-badge`;
                            statusBadge.textContent = data.data.status_text;
                        }
                        
                        // Update button text and class
                        toggleBtn.innerHTML = `<i class="bi bi-toggle-${isActive ? 'on' : 'off'}"></i> ${isActive ? 'Deactivate' : 'Activate'}`;
                        toggleBtn.className = `btn btn-sm btn-outline-${isActive ? 'warning' : 'success'}`;
                        
                        // Update card opacity
                        if (card) {
                            if (isActive) {
                                card.classList.remove('inactive-card');
                            } else {
                                card.classList.add('inactive-card');
                            }
                        }
                        
                        // Show success message
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message || 'An error occurred.');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'An error occurred. Please try again.');
                    console.error('Error:', error);
                })
                .finally(() => {
                    toggleBtn.disabled = false;
                });
            });
        });
    };
    
    // Display alert messages
    const showAlert = function(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        alertContainer.appendChild(alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const bootstrapAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            bootstrapAlert.close();
        }, 5000);
    };
    
    // Initialize functions
    formatTimeInputs();
    setupToggleButtons();
}); 