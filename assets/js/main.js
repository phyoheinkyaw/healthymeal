// Initialize cart count from localStorage
document.addEventListener('DOMContentLoaded', function() {
    updateCartCountFromStorage();
});

// --- Contact number live validation ---
document.addEventListener('DOMContentLoaded', function() {
    var contactInput = document.getElementById('contact_number');
    if (contactInput) {
        contactInput.addEventListener('input', function(e) {
            // Only allow numbers and optional leading +
            let value = contactInput.value;
            // Remove all non-digit characters except leading +
            value = value.replace(/(?!^)[^\d]/g, '');
            if (value[0] === '+') {
                value = '+' + value.slice(1).replace(/[^\d]/g, '');
            }
            contactInput.value = value;
        });
    }
});

// --- Fix Place Order button/modal for radio selection bug ---
document.addEventListener('DOMContentLoaded', function() {
    var showConfirmBtn = document.getElementById('showConfirmModalBtn');
    var confirmModalEl = document.getElementById('confirmOrderModal');
    var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
    var radios = document.querySelectorAll('input[name="payment_method"]');
    if (showConfirmBtn && confirmModal) {
        showConfirmBtn.addEventListener('click', function(e) {
            var selected = document.querySelector('input[name="payment_method"]:checked');
            if (selected) {
                confirmModal.show();
            } else {
                alert('Please select a payment method.');
            }
        });
    }
});

// --- Enhanced reorderItems to show beautiful message for inactive meal kits ---
window.reorderItems = function(orderId) {
    fetch('api/orders/reorder.php?id=' + orderId)
        .then(response => response.json())
        .then(data => {
            console.log('Reorder API response:', data); // Debug log
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.style.position = 'fixed';
                toastContainer.style.top = '1rem';
                toastContainer.style.right = '1rem';
                toastContainer.style.zIndex = 2000;
                document.body.appendChild(toastContainer);
            }
            let toastMsg = document.createElement('div');
            toastMsg.className = 'toast align-items-center text-bg-' + (data.success ? 'success' : 'warning') + ' border-0 show mb-2';
            toastMsg.setAttribute('role', 'alert');
            toastMsg.innerHTML = `
              <div class="d-flex">
                <div class="toast-body">
                  <i class="bi ${data.success ? 'bi-cart-plus' : 'bi-exclamation-triangle'} me-2"></i>
                  ${data.message}
                  ${data.inactive_meal_kits && data.inactive_meal_kits.length > 0 ?
                    `<div class='mt-2 alert alert-warning p-2 rounded small'><i class='bi bi-x-circle'></i> <strong>Some meal kits could not be added:</strong><br>
                    ${data.inactive_meal_kits.map(mk => `<span class='badge bg-secondary me-1 mb-1'>${mk.name}</span>`).join(' ')}
                    </div>` : ''}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
              </div>`;
            toastContainer.appendChild(toastMsg);
            setTimeout(() => toastMsg.remove(), 6000);
            // Optionally update cart count if available
            if (typeof updateCartCountFromStorage === 'function') {
              updateCartCountFromStorage();
            }
        })
        .catch((err) => {
            console.log('Reorder API error:', err);
            alert('An error occurred while adding items to cart');
        });
}

function updateCartCountFromStorage() {
    const savedCartCount = localStorage.getItem('cartCount');
    if (savedCartCount) {
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement) {
            cartCountElement.textContent = savedCartCount;
        }
    }
}