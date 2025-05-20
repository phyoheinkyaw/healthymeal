/**
 * Payment Slip Scanner using Tesseract OCR
 * This script automatically scans uploaded payment slips for transaction IDs
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Payment Slip Scanner initialized');
    
    // Function to initialize scanner for a specific payment method
    function initializeScanner(paymentId) {
        console.log('Initializing scanner for payment method:', paymentId);
        
        // Get elements with dynamic IDs
        const paymentSlipInput = document.getElementById(`transfer_slip_${paymentId}`);
        const transactionIdInput = document.getElementById(`transaction_id_${paymentId}`);
        const scanStatusElement = document.getElementById(`scan_status_${paymentId}`);
        
        console.log('Found elements for payment method', paymentId, ':', {
            paymentSlipInput: !!paymentSlipInput,
            transactionIdInput: !!transactionIdInput,
            scanStatusElement: !!scanStatusElement
        });
        
        if (!paymentSlipInput || !transactionIdInput || !scanStatusElement) {
            console.log('Required elements not found for payment method:', paymentId);
            return;
        }
        
        // Add event listener for file upload
        paymentSlipInput.addEventListener('change', function(e) {
            console.log('File selected for payment method', paymentId, ':', this.files[0]?.name);
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Store references to these elements for later use
                // This ensures we always refer to the correct elements even if IDs change
                window.currentPaymentId = paymentId;
                window.currentTransactionIdInput = transactionIdInput;
                window.currentScanStatusElement = scanStatusElement;
                
                // Show loading overlay
                showLoadingOverlay('Uploading payment slip...');
                console.log('Loading overlay shown');
                
                // Create a preview div if it doesn't exist
                const previewId = `slip_preview_${paymentId}`;
                let previewDiv = document.getElementById(previewId);
                if (!previewDiv) {
                    previewDiv = document.createElement('div');
                    previewDiv.id = previewId;
                    paymentSlipInput.parentNode.appendChild(previewDiv);
                }
                
                // Handle PDF files differently
                if (file.type === 'application/pdf') {
                    console.log('PDF file detected, showing PDF preview');
                    previewDiv.innerHTML = `
                        <div class="mt-2">
                            <div class="alert alert-info">
                                <i class="bi bi-file-pdf me-2"></i>
                                PDF file uploaded: ${file.name}
                            </div>
                        </div>
                    `;
                    hideLoadingOverlay();
                    return;
                }
                
                // For image files, create preview and scan
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('File read complete, creating preview');
                    
                    // Create a hidden image for Tesseract to scan
                    const img = new Image();
                    img.src = e.target.result;
                    img.style.display = 'none';
                    img.id = `payment_slip_preview_${paymentId}`;
                    document.body.appendChild(img);
                    
                    // Show preview
                    previewDiv.innerHTML = `
                        <div class="mt-2">
                            <img src="${e.target.result}" alt="Transfer Slip Preview" class="img-thumbnail" style="max-height: 150px;">
                        </div>
                    `;
                    
                    // Wait for image to load before scanning
                    img.onload = function() {
                        console.log('Preview image loaded, starting scan immediately');
                        // Start scanning immediately with the stored references
                        scanTransactionId(img.src);
                    };
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Function to scan payment slip for transaction ID using OCR
    function scanTransactionId(imgSrc) {
        const paymentId = window.currentPaymentId;
        const transactionIdInput = window.currentTransactionIdInput;
        const scanStatusElement = window.currentScanStatusElement;
        
        console.log('Starting OCR scan for payment method:', paymentId);
        
        // Update status to show scanning is in progress
        if (scanStatusElement) {
            scanStatusElement.innerHTML = '<span class="text-info"><i class="spinner-border spinner-border-sm"></i> Scanning image for transaction ID...</span>';
            console.log('Updated scan status element');
        }
        
        // Check if Tesseract is loaded
        if (typeof Tesseract === 'undefined') {
            console.log('Tesseract not loaded, loading dynamically');
            
            // Load Tesseract.js dynamically
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js';
            script.onload = function() {
                console.log('Tesseract loaded successfully');
                performOCR(imgSrc, transactionIdInput, scanStatusElement);
            };
            script.onerror = function() {
                console.error('Failed to load Tesseract');
                hideLoadingOverlay();
                if (scanStatusElement) {
                    scanStatusElement.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Failed to load OCR library</span>';
                }
            };
            document.head.appendChild(script);
        } else {
            console.log('Tesseract already loaded, proceeding with OCR');
            performOCR(imgSrc, transactionIdInput, scanStatusElement);
        }
    }
    
    // Function to perform OCR on the image
    function performOCR(imageData, transactionIdInput, scanStatusElement) {
        console.log('Starting OCR processing');
        
        console.log('Using transaction ID element:', !!transactionIdInput);
        console.log('Using scan status element:', !!scanStatusElement);
        
        if (!transactionIdInput || !scanStatusElement) {
            console.error('Required elements not available for OCR processing');
            hideLoadingOverlay();
            return;
        }
        
        // Show loading overlay
        showLoadingOverlay('Processing payment slip...');
        
        // Load Tesseract if not already loaded
        if (!window.Tesseract) {
            console.log('Tesseract not loaded, loading dynamically');
            loadTesseract().then(() => {
                console.log('Tesseract loaded successfully');
                startOCR();
            });
        } else {
            startOCR();
        }
        
        function startOCR() {
            window.Tesseract.recognize(
                imageData,
                'eng',
                {
                    logger: m => {
                        console.log('Tesseract progress:', m);
                        if (m.status === 'recognizing text') {
                            updateLoadingMessage(`Processing image... ${Math.round(m.progress * 100)}%`);
                        }
                    }
                }
            ).then(({ data: { text } }) => {
                console.log('OCR Result:', text);
                
                // Extract transaction ID and account number
                const extractedData = extractDataFromText(text);
                console.log('Extracted data:', extractedData);
                
                // Update UI with results
                if (extractedData.transactionId) {
                    transactionIdInput.value = extractedData.transactionId;
                    scanStatusElement.innerHTML = `
                        <div class="alert alert-success mt-2">
                            <i class="bi bi-check-circle me-2"></i>
                            Transaction ID found!
                        </div>
                    `;
                    console.log('Transaction ID set:', extractedData.transactionId);
                } else {
                    scanStatusElement.innerHTML = `
                       
                    `;
                    console.log('No transaction ID found in OCR result');
                }

            //     <div class="alert alert-warning mt-2">
            //     <i class="bi bi-exclamation-triangle me-2"></i>
            //     No transaction ID found. Please enter it manually.
            // </div>
                
                // Hide loading overlay
                hideLoadingOverlay();
                console.log('OCR process complete');
            }).catch(error => {
                console.error('OCR Error:', error);
                scanStatusElement.innerHTML = `
                    <div class="alert alert-danger mt-2">
                        <i class="bi bi-x-circle me-2"></i>
                        Error processing image. Please try again or enter details manually.
                    </div>
                `;
                hideLoadingOverlay();
            });
        }
    }
    
    function extractDataFromText(text) {
        console.log('Extracting transaction ID from text');
        
        // Try different patterns for transaction ID
        const patterns = [
            /([a-z0-9]{8,})/i,  // Any 8+ alphanumeric characters
            /([0-9]{10,})/,     // Any 10+ digits
            /TRANSACTION ID:?\s*([A-Z0-9]+)/i,
            /REF:?\s*([A-Z0-9]+)/i
        ];
        
        let transactionId = null;
        
        for (const pattern of patterns) {
            const match = text.match(pattern);
            if (match && match[1]) {
                console.log('Found transaction ID using pattern:', pattern);
                transactionId = match[1].trim();
                break;
            }
        }
        
        console.log('Extracting account number from text');
        const accountNumberPattern = new RegExp('(?:ACCOUNT|ACC|A/C)[\\s#:]+([0-9]+)', 'i');
        const accountMatch = text.match(accountNumberPattern);
        const accountNumber = accountMatch ? accountMatch[1].trim() : null;
        
        if (!accountNumber) {
            console.log('No account number found in text');
        }
        
        return {
            transactionId,
            accountNumber
        };
    }

    // Function to show loading overlay
    function showLoadingOverlay(message = 'Processing...') {
        console.log('Showing loading overlay:', message);
        
        // Create overlay if it doesn't exist
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            console.log('Creating new loading overlay');
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            overlay.style.zIndex = '9999';
            overlay.style.display = 'flex';
            overlay.style.justifyContent = 'center';
            overlay.style.alignItems = 'center';
            overlay.style.flexDirection = 'column';
            overlay.style.color = 'white';
            
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
            console.log('Loading overlay created and added to DOM');
        } else {
            console.log('Updating existing loading overlay message');
            document.getElementById('loadingMessage').textContent = message;
            overlay.style.display = 'flex';
        }
    }
    
    // Function to hide loading overlay
    function hideLoadingOverlay() {
        console.log('Hiding loading overlay');
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto';
            console.log('Loading overlay hidden');
        } else {
            console.log('No loading overlay found to hide');
        }
    }
    
    // Function to load Tesseract dynamically
    function loadTesseract() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load Tesseract'));
            document.head.appendChild(script);
        });
    }
    
    // Function to update loading message
    function updateLoadingMessage(message) {
        const messageEl = document.getElementById('loadingMessage');
        if (messageEl) {
            messageEl.textContent = message;
        }
    }
    
    // Initialize scanner for each payment method
    document.querySelectorAll('.payment-method-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.id !== 'cashOnDelivery') {
                // Get the payment ID from the data attribute
                const paymentId = this.getAttribute('data-payment-id');
                console.log('Payment method changed to:', paymentId);
                if (paymentId) {
                    initializeScanner(paymentId);
                }
            }
        });
    });
    
    // Initialize scanner for the initially selected payment method
    const initialPaymentMethod = document.querySelector('.payment-method-radio:checked');
    if (initialPaymentMethod && initialPaymentMethod.id !== 'cashOnDelivery') {
        // Get the payment ID from the data attribute
        const paymentId = initialPaymentMethod.getAttribute('data-payment-id');
        console.log('Initial payment method:', paymentId);
        if (paymentId) {
            initializeScanner(paymentId);
        }
    }
}); 