/**
 * Checkout process JavaScript for Healthy Meal Kit
 * Handles delivery options and order placement
 */

// Global variables for addresses
let savedAddresses = [];

// Define variables
let orderFormData = {};
let transferSlipFile = null;
let currentPaymentMethodId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    
    // Load saved addresses if available and check address count limit
    loadSavedAddresses();
    checkAddressLimit();
    
    // Add event listeners for delivery option radios
    document.querySelectorAll('.delivery-option-radio').forEach(radio => {
        radio.addEventListener('change', updateOrderSummary);
    });
    
    // Add event listener for delivery notes
    const deliveryNotesEl = document.getElementById('deliveryNotes');
    if (deliveryNotesEl) {
        deliveryNotesEl.addEventListener('input', updateDeliveryNotes);
    }
    
    // Add event listeners for address fields to check for duplicates
    const addressFields = ['inputAddress', 'inputCity', 'inputZip'];
    addressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', checkAddressDuplication);
        }
    });
    
    // Initialize order summary
    updateOrderSummary();
    
    // Initialize payment method controls
    initPaymentMethodControls();
    
    // Initialize address saving controls
    initAddressSavingControls();
    
    // Setup place order button with proper event handling
    const placeOrderBtnEl = document.getElementById('placeOrderBtn');
    if (placeOrderBtnEl) {
        // Remove any existing event listeners
        const newPlaceOrderBtn = placeOrderBtnEl.cloneNode(true);
        placeOrderBtnEl.parentNode.replaceChild(newPlaceOrderBtn, placeOrderBtnEl);
        
        // Add single event listener with proper prevention of double submission
        newPlaceOrderBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default form submission
            e.stopPropagation(); // Stop event bubbling
            
            // Check if button is already disabled
            if (this.disabled) {
                console.log('Order submission already in progress');
                return;
            }
            
            // Call submitOrder
            submitOrder();
        });
    }
    
    // Prevent form submission on enter key
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            return false;
        });
    }
});

/**
 * Load saved addresses from API
 */
function loadSavedAddresses() {
    fetch('api/user/get_addresses.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.addresses) {
                savedAddresses = data.addresses;
            }
        })
        .catch(error => {
            console.error('Error loading addresses:', error);
        });
}

/**
 * Check if the current address already exists in saved addresses
 */
function checkAddressDuplication() {
    if (savedAddresses.length === 0) return;
    
    const currentStreet = document.getElementById('inputAddress')?.value?.trim() || '';
    const currentCity = document.getElementById('inputCity')?.value?.trim() || '';
    const currentZip = document.getElementById('inputZip')?.value?.trim() || '';
    
    // Only check if we have enough data to compare
    if (!currentStreet || !currentCity || !currentZip) return;
    
    const saveAddressCheckbox = document.getElementById('saveAddress');
    const addressExistsWarning = document.getElementById('addressExistsWarning');
    const saveAddressDetails = document.getElementById('saveAddressDetails');
    
    // Check if this address already exists
    const addressExists = savedAddresses.some(addr => {
        return (
            currentStreet.toLowerCase() === addr.full_address.toLowerCase() &&
            currentCity.toLowerCase() === addr.city.toLowerCase() &&
            currentZip.toLowerCase() === addr.postal_code.toLowerCase()
        );
    });
    
    // Check if user has reached address limit (6 addresses)
    const hasReachedLimit = savedAddresses.length >= 6;
    
    // First, clear the existing warning to avoid confusion
    if (addressExistsWarning) {
        addressExistsWarning.style.display = 'none';
    }
    
    if (addressExists && saveAddressCheckbox) {
        // Address already exists
        saveAddressCheckbox.checked = false;
        saveAddressCheckbox.disabled = true;
        
        if (addressExistsWarning) {
            addressExistsWarning.style.display = 'block';
            
            // Find the matching address to display its name
            const matchingAddress = savedAddresses.find(addr => 
                currentStreet.toLowerCase() === addr.full_address.toLowerCase() &&
                currentCity.toLowerCase() === addr.city.toLowerCase() &&
                currentZip.toLowerCase() === addr.postal_code.toLowerCase()
            );
            
            if (matchingAddress) {
                addressExistsWarning.innerHTML = `
                    <i class="bi bi-info-circle"></i> This address already exists as "${matchingAddress.address_name}" in your saved addresses.
                `;
            }
        }
        
        // Hide the save address details section
        if (saveAddressDetails) {
            saveAddressDetails.style.display = 'none';
        }
    } else if (hasReachedLimit && saveAddressCheckbox) {
        // Address limit reached
        saveAddressCheckbox.checked = false;
        saveAddressCheckbox.disabled = true;
        
        // Show the limit warning but not the "already exists" warning
        if (addressExistsWarning) {
            addressExistsWarning.style.display = 'block';
            addressExistsWarning.innerHTML = `
                <i class="bi bi-exclamation-triangle-fill"></i> You have reached the maximum limit of 6 addresses.
            `;
        }
        
        // Hide the save address details section
        if (saveAddressDetails) {
            saveAddressDetails.style.display = 'none';
        }
    } else if (saveAddressCheckbox) {
        // Address doesn't exist and user hasn't reached limit
        saveAddressCheckbox.disabled = false;
        if (addressExistsWarning) {
            addressExistsWarning.style.display = 'none';
        }
    }
}

/**
 * Initializes payment method controls
 */
function initPaymentMethodControls() {
    // Add event listeners to payment method radios
    document.querySelectorAll('.payment-method-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            // Hide all payment info divs
            document.querySelectorAll('.payment-info').forEach(div => {
                div.style.display = 'none';
            });
            
            // If not Cash on Delivery, show the correct payment info div using the payment ID
            if (this.id !== 'cashOnDelivery') {
                const paymentId = this.dataset.paymentId;
                const paymentInfoId = 'info_' + paymentId;
                const infoDiv = document.getElementById(paymentInfoId);
                
                if (infoDiv) {
                    infoDiv.style.display = 'block';
                    
                    // Update the section height
                    setTimeout(() => {
                        updateSectionHeight('paymentMethodSection');
                    }, 100);
                }
            }
        });
    });
    
    // Initialize file upload previews
    document.querySelectorAll('input[type="file"]').forEach(fileInput => {
        fileInput.addEventListener('change', function() {
            const previewId = 'slip_preview_' + this.id.replace('transfer_slip_', '');
            const previewDiv = document.getElementById(previewId);
            
            if (!previewDiv) return;
            
            // Show loading overlay for 3 seconds
            showLoadingOverlay('Processing payment slip...');
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `
                        <div class="mt-2">
                            <img src="${e.target.result}" alt="Transfer Slip Preview" class="img-thumbnail" style="max-height: 150px;">
                        </div>
                    `;
                    
                    // Set up reference ID for scanner
                    const fileId = fileInput.id;
                    const methodId = fileId.replace('transfer_slip_', '');
                    const transactionIdField = document.getElementById('transaction_id_' + methodId);
                    const scanStatusDiv = document.getElementById('scan_status_' + methodId);
                    
                    // Set global variables for the OCR scanner to use without changing IDs
                    window.transfer_slip_element = fileInput;
                    window.transaction_id_element = transactionIdField;
                    window.scan_status_element = scanStatusDiv;
                    
                    // After the preview is loaded, recalculate the section height
                    setTimeout(() => {
                        updateSectionHeight('paymentMethodSection');
                    }, 100);
                };
                reader.readAsDataURL(this.files[0]);
            } else {
                previewDiv.innerHTML = '';
                // Also update height when removing preview
                setTimeout(() => {
                    updateSectionHeight('paymentMethodSection');
                    hideLoadingOverlay();
                }, 100);
            }
        });
    });
}

/**
 * Helper function to update section heights when content changes
 */
function updateSectionHeight(sectionId) {
    const section = document.getElementById(sectionId);
    if (section && !section.classList.contains('collapsed')) {
        section.style.maxHeight = 'none'; // First remove any fixed height
        const scrollHeight = section.scrollHeight;
        section.style.maxHeight = scrollHeight + 'px';
        
        console.log(`Updated section ${sectionId} height to ${scrollHeight}px`);
    }
}

/**
 * Initialize address saving checkbox behavior
 */
function initAddressSavingControls() {
    const saveAddressCheckbox = document.getElementById('saveAddress');
    const saveAddressDetails = document.getElementById('saveAddressDetails');
    
    if (saveAddressCheckbox && saveAddressDetails) {
        saveAddressCheckbox.addEventListener('change', function() {
            saveAddressDetails.style.display = this.checked ? 'block' : 'none';
        });
    }
}

/**
 * Updates the delivery notes in the order summary
 */
function updateDeliveryNotes() {
    const notes = document.getElementById('deliveryNotes').value;
    const notesContainer = document.getElementById('orderDeliveryNotes');
    
    if (notes && notesContainer) {
        notesContainer.innerHTML = `
            <div class="alert p-2 small mb-0" style="background-color: var(--light); border-left: 3px solid var(--primary);">
                <strong>Delivery Instructions:</strong><br>
                <div class="mt-1 p-2 border-start" style="border-color: var(--primary) !important; border-width: 2px !important;">
                    ${notes}
                </div>
            </div>
        `;
    } else if (notesContainer) {
        notesContainer.innerHTML = '';
    }
}

/**
 * Updates the order summary based on selected options
 */
function updateOrderSummary() {
    const subtotalElement = document.getElementById('orderSubtotal');
    const taxElement = document.getElementById('orderTax');
    const deliveryFeeElement = document.getElementById('orderDeliveryFee');
    const totalElement = document.getElementById('orderTotal');
    
    if (!subtotalElement || !taxElement || !deliveryFeeElement || !totalElement) return;
    
    // Get the subtotal (this should be set by the server in a data attribute)
    const subtotal = parseFloat(subtotalElement.dataset.value || 0);
    
    // Calculate tax (5%) - ensure it's a whole number for MMK
    // This matches the server-side calculateTax function
    const tax = Math.round(subtotal * 0.05);
    
    // Get selected delivery option fee
    const selectedRadio = document.querySelector('input[name="delivery_option"]:checked');
    let deliveryFee = 0;
    
    if (selectedRadio && selectedRadio.dataset.fee) {
        deliveryFee = parseFloat(selectedRadio.dataset.fee) || 0;
    }
    
    // Calculate total - ensure it's a whole number for MMK
    // This matches the server-side calculateTotal function
    const total = subtotal + tax + deliveryFee;
    
    // Update the elements - all MMK values as whole numbers
    subtotalElement.textContent = Math.round(subtotal).toLocaleString() + ' MMK';
    taxElement.textContent = tax.toLocaleString() + ' MMK';
    deliveryFeeElement.textContent = Math.round(deliveryFee).toLocaleString() + ' MMK';
    totalElement.textContent = Math.round(total).toLocaleString() + ' MMK';
    
    // Store values as data attributes for the order submission
    subtotalElement.dataset.valueRounded = Math.round(subtotal);
    taxElement.dataset.valueRounded = tax;
    deliveryFeeElement.dataset.valueRounded = Math.round(deliveryFee);
    totalElement.dataset.valueRounded = Math.round(total);
    
    // Update delivery notes
    updateDeliveryNotes();
}

/**
 * Use a saved address for the order
 */
function useAddress(fullAddress, city, postalCode, addressName) {
    const streetAddressInput = document.getElementById('inputAddress');
    const cityInput = document.getElementById('inputCity');
    const zipInput = document.getElementById('inputZip');
    
    if (streetAddressInput && cityInput && zipInput) {
        streetAddressInput.value = fullAddress;
        cityInput.value = city;
        zipInput.value = postalCode;
        
        // Disable the save address checkbox and show warning
        const saveAddressCheckbox = document.getElementById('saveAddress');
        const addressExistsWarning = document.getElementById('addressExistsWarning');
        
        if (saveAddressCheckbox && addressExistsWarning) {
            saveAddressCheckbox.checked = false;
            saveAddressCheckbox.disabled = true;
            addressExistsWarning.style.display = 'block';
            
            // Hide the save address details section
            const saveAddressDetails = document.getElementById('saveAddressDetails');
            if (saveAddressDetails) {
                saveAddressDetails.style.display = 'none';
            }
        }
    }
}

/**
 * Submit order - performs validation and submits the order form
 */
function submitOrder() {
    console.log('Starting order submission process...');
    
    // Prevent double submission
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (placeOrderBtn.disabled) {
        console.log('Order submission already in progress');
        return;
    }
    
    // Disable the submit button immediately to prevent double submission
    placeOrderBtn.disabled = true;
    
    // Show loading overlay right away
    showLoadingOverlay('Checking your order details...');
    
    // Get form and validate
    const form = document.getElementById('checkoutForm');
    
    if (!form) {
        console.error('Checkout form not found');
        hideLoadingOverlay();
        showAlert('error', 'Form not found');
        placeOrderBtn.disabled = false; // Re-enable button on error
        return;
    }
    
    console.log('Form validation starting...');
    
    // Clear previous error messages
    document.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
    });
    document.querySelectorAll('.invalid-feedback').forEach(feedback => {
        feedback.style.display = 'none';
    });
    document.querySelectorAll('.section-header').forEach(header => {
        header.classList.remove('has-error');
    });
    
    // Get selected payment method first to determine validation rules
    const paymentMethodRadio = document.querySelector('input[name="payment_method"]:checked');
    if (!paymentMethodRadio) {
        hideLoadingOverlay();
        showAlert('danger', 'Please select a payment method');
        placeOrderBtn.disabled = false;
        return;
    }
    
    // Check if this is Cash on Delivery
    const isCashOnDelivery = (paymentMethodRadio.id === 'cashOnDelivery');
    console.log('Is Cash on Delivery payment:', isCashOnDelivery);
    
    // Validate the form based on required fields
    const requiredFields = [];
    
    // Always required fields
    requiredFields.push(
        document.getElementById('inputAddress'),
        document.getElementById('inputCity'),
        document.getElementById('inputZip'),
        document.getElementById('customerPhone'),
        document.getElementById('contactNumber'),
        document.getElementById('deliveryDate')
    );
    
    // Validate required fields
    let hasError = false;
    requiredFields.forEach(field => {
        if (!field || !field.value.trim()) {
            if (field) {
                field.classList.add('is-invalid');
            }
            hasError = true;
        }
    });
    
    if (hasError) {
        hideLoadingOverlay();
        showAlert('danger', 'Please fill in all required fields');
        placeOrderBtn.disabled = false;
        return;
    }
    
    // Validate payment-specific fields
    if (!isCashOnDelivery) {
        // For non-COD payments, check for payment slip
        const paymentId = paymentMethodRadio.dataset.paymentId;
        const transferSlipInput = document.getElementById(`transfer_slip_${paymentId}`);
        
        console.log('Checking payment slip for method:', paymentMethodRadio.id);
        console.log('Transfer slip input found:', !!transferSlipInput);
        
        if (!transferSlipInput || !transferSlipInput.files || transferSlipInput.files.length === 0) {
            hideLoadingOverlay();
            showAlert('danger', 'Please upload a payment slip for ' + paymentMethodRadio.value);
            placeOrderBtn.disabled = false;
            return;
        }
    }
    
    // Get delivery option
    const deliveryOptionRadio = document.querySelector('input[name="delivery_option"]:checked');
    if (!deliveryOptionRadio) {
        hideLoadingOverlay();
        showAlert('danger', 'Please select a delivery option');
        placeOrderBtn.disabled = false;
        return;
    }
    
    // Get delivery date
    const deliveryDate = document.getElementById('deliveryDate').value;
    console.log('Delivery date being sent:', deliveryDate);
    
    // Validate delivery date format (YYYY-MM-DD)
    if (!/^\d{4}-\d{2}-\d{2}$/.test(deliveryDate)) {
        hideLoadingOverlay();
        showAlert('danger', 'Please select a valid delivery date from the calendar.');
        placeOrderBtn.disabled = false;
        return;
    }
    
    // Get address details
    const street = document.getElementById('inputAddress').value;
    const city = document.getElementById('inputCity').value;
    const zip = document.getElementById('inputZip').value;
    
    if (!street || !city || !zip) {
        hideLoadingOverlay();
        showAlert('danger', 'Please enter your delivery address');
        placeOrderBtn.disabled = false;
        return;
    }
    
    const deliveryAddress = `${street}, ${city} ${zip}`;
    
    // Get contact details
    const customerPhone = document.getElementById('customerPhone').value;
    const contactNumber = document.getElementById('contactNumber').value;
    
    if (!customerPhone || !contactNumber) {
        hideLoadingOverlay();
        showAlert('danger', 'Please enter both phone numbers');
        placeOrderBtn.disabled = false;
        return;
    }
    
    // Get optional fields
    const deliveryNotes = document.getElementById('deliveryNotes').value;
    
    // Get account phone if applicable
    let accountPhone = null;
    if (paymentMethodRadio.id !== 'cashOnDelivery') {
        const paymentId = paymentMethodRadio.dataset.paymentId;
        const accountPhoneInput = document.getElementById(`accountPhone_${paymentId}`);
        if (accountPhoneInput) {
            accountPhone = accountPhoneInput.value;
        }
    }
    
    // Save address if checkbox is checked (do this BEFORE submitting order)
    const saveAddressCheckbox = document.getElementById('saveAddress');
    if (saveAddressCheckbox && saveAddressCheckbox.checked) {
        const addressName = document.getElementById('addressName').value;
        const isDefault = document.getElementById('defaultAddress').checked;
        
        if (addressName && street && city && zip) {
            console.log('Saving address before submitting order...');
            
            const addressData = {
                address_name: addressName,
                full_address: street,
                city: city,
                postal_code: zip,
                is_default: isDefault
            };
            
            // Save the address before proceeding with the order
            saveUserAddress(addressData)
                .then((response) => {
                    if (response.success) {
                        console.log('Address saved successfully before order submission');
                    } else if (response.message === 'Address limit reached') {
                        // Special case: Let the user know about the address limit but continue with order
                        console.log('Address limit reached but continuing with order');
                        // The warning is already shown by saveUserAddress function
                    } else {
                        console.error('Error saving address:', response.message);
                    }
                    // In all cases, continue with the order submission
                    continueWithOrderSubmission();
                })
                .catch(error => {
                    console.error('Error saving address:', error);
                    // Continue with order submission even if saving address fails
                    continueWithOrderSubmission();
                });
        } else {
            console.error('Cannot save address - missing required fields');
            // Don't block order submission if address saving fails
            continueWithOrderSubmission();
        }
    } else {
        // No address to save, continue with order submission
        continueWithOrderSubmission();
    }
    
    // Function to continue with order submission after address processing
    function continueWithOrderSubmission() {
        // Prepare order data
        const orderData = {
            delivery_address: deliveryAddress,
            customer_phone: customerPhone,
            contact_number: contactNumber,
            delivery_notes: deliveryNotes,
            payment_method: paymentMethodRadio.value,
            payment_method_id: paymentMethodRadio.dataset.paymentId || null,
            account_phone: accountPhone,
            delivery_option_id: parseInt(deliveryOptionRadio.value),
            delivery_date: deliveryDate,
            delivery_time: deliveryOptionRadio.dataset.time || null,
            subtotal: parseInt(document.getElementById('orderSubtotal').dataset.valueRounded || 0),
            tax: parseInt(document.getElementById('orderTax').dataset.valueRounded || 0),
            delivery_fee: parseInt(document.getElementById('orderDeliveryFee').dataset.valueRounded || 0),
            total_amount: parseInt(document.getElementById('orderTotal').dataset.valueRounded || 0)
        };
        
        // Add transaction ID to order data if available
        if (paymentMethodRadio.id !== 'cashOnDelivery') {
            const paymentId = paymentMethodRadio.dataset.paymentId;
            const transactionIdInput = document.getElementById(`transaction_id_${paymentId}`);
            if (transactionIdInput && transactionIdInput.value) {
                orderData.transaction_id = transactionIdInput.value;
            }
        }
        
        console.log('Order data prepared:', orderData);
        
        // Create form data if payment slip is included
        if (paymentMethodRadio.id !== 'cashOnDelivery') {
            const paymentId = paymentMethodRadio.dataset.paymentId;
            const transferSlipInput = document.getElementById(`transfer_slip_${paymentId}`);
            const transactionIdInput = document.getElementById(`transaction_id_${paymentId}`);
            
            if (transferSlipInput && transferSlipInput.files && transferSlipInput.files.length > 0) {
                console.log('Preparing FormData with payment slip...');
                const formData = new FormData();
                
                // Add the file
                formData.append('transfer_slip', transferSlipInput.files[0]);
                console.log('Added file to FormData:', transferSlipInput.files[0].name, transferSlipInput.files[0].size + ' bytes');
                
                // Add transaction ID
                if (transactionIdInput && transactionIdInput.value) {
                    // Use the standardized transaction_id field name for the backend
                    formData.append('transaction_id', transactionIdInput.value);
                    console.log('Added transaction_id to FormData:', transactionIdInput.value);
                }
                
                // Add order data
                formData.append('order_data', JSON.stringify(orderData));
                console.log('Added order_data to FormData (JSON):', JSON.stringify(orderData));
                
                // Debug log all form data entries
                console.log('FormData contents:');
                for (const pair of formData.entries()) {
                    console.log(pair[0] + ': ' + (pair[0] === 'order_data' ? '[JSON data]' : pair[1]));
                }
                
                console.log('Submitting order with file...');
                submitOrderWithFile(formData);
                return;
            }
        }
        
        console.log('Submitting order as JSON...');
        submitOrderAsJson(orderData);
    }
}

/**
 * Submit order as JSON
 */
function submitOrderAsJson(orderData) {
    console.log('Starting JSON order submission...');
    // Show loading overlay
    showLoadingOverlay('Submitting your order...');
    
    // Start time for ensuring minimum loading time
    const startTime = new Date().getTime();
    
    // Extra validation to ensure order type is correct for COD
    const isCashOnDelivery = orderData.payment_method === 'Cash on Delivery';
    
    console.log('Is Cash on Delivery:', isCashOnDelivery);
    
    // For Cash on Delivery, make sure we don't send unnecessary fields
    if (isCashOnDelivery) {
        // Remove any slip-related fields for COD orders
        delete orderData.transfer_slip;
        delete orderData.transaction_id;
    }
    
    console.log('Sending POST request to place_order.php...');
    console.log('Order data being sent:', JSON.stringify(orderData));
    
    fetch('api/orders/place_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    })
    .then(response => {
        console.log('Received response from server:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Server response data:', data);
        // Calculate elapsed time
        const elapsedTime = new Date().getTime() - startTime;
        const remainingTime = Math.max(0, 3000 - elapsedTime); // Ensure at least 3 seconds of loading
        
        // Add delay if needed
        setTimeout(() => {
            hideLoadingOverlay();
            handleOrderResponse(data);
        }, remainingTime);
    })
    .catch(error => {
        console.error('Error during order submission:', error);
        // Calculate elapsed time
        const elapsedTime = new Date().getTime() - startTime;
        const remainingTime = Math.max(0, 3000 - elapsedTime); // Ensure at least 3 seconds of loading
        
        // Add delay if needed
        setTimeout(() => {
            hideLoadingOverlay();
            handleOrderError(error);
        }, remainingTime);
    });
}

/**
 * Submit order with file upload
 */
function submitOrderWithFile(formData) {
    console.log('Starting file upload order submission...');
    // Show loading overlay
    showLoadingOverlay('Submitting your order...');
    
    // Start time for ensuring minimum loading time
    const startTime = new Date().getTime();
    
    console.log('Sending POST request with file to place_order.php...');
    fetch('api/orders/place_order.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Received response from server:', response.status);
        
        // Check if response is ok
        if (!response.ok) {
            // Try to extract error message from the response
            return response.text().then(text => {
                console.error('Server error response:', text);
                throw new Error('Server error: ' + (response.statusText || 'Unknown error'));
            });
        }
        
        // Parse response as JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parsing JSON response:', text);
                throw new Error('Invalid server response. Please try again.');
            }
        });
    })
    .then(data => {
        console.log('Server response data:', data);
        // Calculate elapsed time
        const elapsedTime = new Date().getTime() - startTime;
        const remainingTime = Math.max(0, 3000 - elapsedTime); // Ensure at least 3 seconds of loading
        
        // Add delay if needed
        setTimeout(() => {
            hideLoadingOverlay();
            handleOrderResponse(data);
        }, remainingTime);
    })
    .catch(error => {
        console.error('Error during file upload order submission:', error);
        // Calculate elapsed time
        const elapsedTime = new Date().getTime() - startTime;
        const remainingTime = Math.max(0, 3000 - elapsedTime); // Ensure at least 3 seconds of loading
        
        // Add delay if needed
        setTimeout(() => {
            hideLoadingOverlay();
            handleOrderError(error);
        }, remainingTime);
    });
}

/**
 * Handle order response
 * @param {Object} data - Response data
 */
function handleOrderResponse(data) {
    document.getElementById('placeOrderBtn').disabled = false;
    
    if (data.success) {
        redirectAfterOrder(data);
    } else {
        // Handle error responses
        if (data.error_code === 'SLOT_FULL') {
            handleOrderError(data); // This will handle the specific SLOT_FULL error
        } else {
            showErrorAlert(data.message || 'An error occurred while placing your order.');
        }
    }
}

/**
 * Redirects to the orders page after successful order
 */
function redirectAfterOrder(data) {
    // Set a success message to display in the orders page
    localStorage.setItem('checkout_success', 'true');
    localStorage.setItem('new_order_id', data.order_id);
    // Redirect to my orders page instead of confirmation page
    window.location.href = 'orders.php?checkout_success=true&order_id=' + data.order_id;
}

/**
 * Handles order error
 */
function handleOrderError(error) {
    console.error('Order Error:', error);
    
    hideLoadingOverlay();
    
    // Check if error has specific error_code for slot availability
    if (error && error.error_code === 'SLOT_FULL') {
        // Show error about slot being full
        showErrorAlert(error.message || 'This delivery slot is now fully booked. Please select another time.');
        
        // Force refresh the delivery slots to show updated availability
        const deliveryDateInput = document.getElementById('deliveryDate');
        if (deliveryDateInput) {
            updateDeliverySlotAvailability(deliveryDateInput.value);
        }
        
        // Expand the delivery section and scroll to it
        expandSectionAndFocus('deliveryDateSection');
    } else {
        // Show generic error
        showErrorAlert(error.message || 'An error occurred processing your order. Please try again.');
    }
}

/**
 * Shows an alert message
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    
    // First, check if the same message already exists to prevent duplicates
    const existingAlerts = alertContainer.querySelectorAll('.alert');
    for (let i = 0; i < existingAlerts.length; i++) {
        if (existingAlerts[i].textContent.trim().includes(message)) {
            // Message already exists, don't show it again
            return;
        }
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${type === 'danger' ? '<i class="bi bi-exclamation-triangle me-1"></i>' : '<i class="bi bi-check-circle me-1"></i>'}
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {  // Check if the alert is still in the DOM
            try {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            } catch (e) {
                // Fallback if bootstrap Alert is not available
                alertDiv.remove();
            }
        }
    }, 5000);
}

/**
 * Save user address to the database
 */
function saveUserAddress(addressData) {
    // First check if the user has already reached the address limit
    return fetch('api/user/get_addresses.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.addresses && data.addresses.length >= 6) {
                // User has reached the limit
                showAlert('warning', 'You have reached the maximum limit of 6 addresses. Please delete an existing address before adding a new one.');
                return { success: false, message: 'Address limit reached' };
            }
            
            // Continue with saving the address if limit not reached
            return fetch('api/user/save_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(addressData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Address saved successfully');
                    // Add the new address to the savedAddresses array
                    savedAddresses.push(addressData);
                } else {
                    console.error('Error saving address:', data.message);
                    showAlert('warning', data.message || 'Failed to save address. Please try again.');
                }
                return data;
            });
        })
        .catch(error => {
            console.error('Error:', error);
            throw error;
        });
}

/**
 * Function to show loading overlay
 * @param {string} message - Message to display in the overlay
 */
function showLoadingOverlay(message = 'Processing...') {
    // Create overlay if it doesn't exist
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        
        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-light mb-3';
        spinner.style.width = '3rem';
        spinner.style.height = '3rem';
        spinner.setAttribute('role', 'status');
        
        const spinnerText = document.createElement('span');
        spinnerText.className = 'visually-hidden';
        spinnerText.textContent = 'Loading...';
        spinner.appendChild(spinnerText);
        
        const messageEl = document.createElement('div');
        messageEl.id = 'loadingMessage';
        messageEl.textContent = message;
        messageEl.className = 'mt-3';
        messageEl.style.fontSize = '1.2rem';
        
        overlay.appendChild(spinner);
        overlay.appendChild(messageEl);
        document.body.appendChild(overlay);
        
        // Prevent interaction with page elements while loading
        document.body.style.overflow = 'hidden';
    } else {
        document.getElementById('loadingMessage').textContent = message;
        overlay.style.display = 'flex';
    }
}

/**
 * Function to hide loading overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

/**
 * Check if user has reached the address limit and disable save option if needed
 */
function checkAddressLimit() {
    fetch('api/user/get_addresses.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.addresses) {
                const addressCount = data.addresses.length;
                
                // If user has 6 or more addresses, disable the save address option
                if (addressCount >= 6) {
                    const saveAddressCheckbox = document.getElementById('saveAddress');
                    
                    if (saveAddressCheckbox) {
                        // Disable the checkbox
                        saveAddressCheckbox.checked = false;
                        saveAddressCheckbox.disabled = true;
                        
                        // Add warning message
                        const warningMessage = document.createElement('div');
                        warningMessage.className = 'text-warning small mt-1';
                        warningMessage.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> You have reached the maximum limit of 6 addresses.';
                        
                        // Insert after checkbox label - find the parent element (likely label or div)
                        const parentElement = saveAddressCheckbox.closest('.form-check') || saveAddressCheckbox.parentNode;
                        if (parentElement) {
                            parentElement.appendChild(warningMessage);
                        }
                        
                        // Hide the save address details section
                        const saveAddressDetails = document.getElementById('saveAddressDetails');
                        if (saveAddressDetails) {
                            saveAddressDetails.style.display = 'none';
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error checking address limit:', error);
        });
}

/**
 * Function to show error alerts
 * @param {string} message - Error message to display
 */
function showErrorAlert(message) {
    const alertContainer = document.getElementById('alertContainer');
    if (alertContainer) {
        alertContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Scroll to the alert
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

/**
 * Helper function for development/testing only
 */
function testSlotAvailability(overrideData) {
    // Sample data with different slot statuses
    const testData = overrideData || {
        success: true,
        delivery_date: document.getElementById('deliveryDate').value,
        slot_availability: [
            {
                delivery_option_id: 1,
                order_count: 10,
                max_orders: 10,
                is_available: false
            },
            {
                delivery_option_id: 2,
                order_count: 8,
                max_orders: 10,
                is_available: true
            },
            {
                delivery_option_id: 3,
                order_count: 2,
                max_orders: 10,
                is_available: true
            }
        ]
    };
    
    // Process this test data as if it came from the server
    const slotBadges = document.querySelectorAll('.slots-badge');
    slotBadges.forEach(badge => {
        badge.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Testing...';
    });
    
    // Remove any existing full overlays
    document.querySelectorAll('.delivery-full-overlay').forEach(el => el.remove());
    
    // Re-enable all options
    document.querySelectorAll('.delivery-option-radio').forEach(radio => {
        radio.disabled = false;
        radio.closest('.delivery-option').classList.remove('full');
    });
    
    // Simulate processing the data from server
    setTimeout(() => {
        testData.slot_availability.forEach(slot => {
            const slotId = 'slots_' + slot.delivery_option_id;
            const badgeElement = document.getElementById(slotId);
            const radioElement = document.getElementById('delivery_' + slot.delivery_option_id);
            
            if (badgeElement && radioElement) {
                const availableSlots = slot.max_orders - slot.order_count;
                const deliveryOption = radioElement.closest('.delivery-option');
                
                // Update badge content
                let badgeClass = 'slots-badge ';
                let badgeContent = '';
                
                if (availableSlots <= 0) {
                    // No slots available
                    badgeClass += 'slots-full';
                    badgeContent = '<i class="bi bi-x-circle me-1"></i> Full';
                    
                    // Disable the radio button
                    radioElement.disabled = true;
                    deliveryOption.classList.add('full');
                    
                    // Add full overlay
                    const overlay = document.createElement('div');
                    overlay.className = 'delivery-full-overlay';
                    overlay.innerHTML = '<span class="full-badge">FULLY BOOKED</span>';
                    deliveryOption.appendChild(overlay);
                } else if (availableSlots <= 3) {
                    // Limited slots
                    badgeClass += 'slots-limited';
                    badgeContent = '<i class="bi bi-exclamation-triangle me-1"></i> ' + availableSlots + '/' + slot.max_orders + ' left';
                } else {
                    // Plenty of slots
                    badgeClass += 'slots-available';
                    badgeContent = '<i class="bi bi-check-circle me-1"></i> ' + availableSlots + '/' + slot.max_orders + ' available';
                }
                
                badgeElement.className = badgeClass;
                badgeElement.innerHTML = badgeContent;
            }
        });
        
        // Log test completion
        console.log('Test slot availability display completed');
    }, 500);
} 