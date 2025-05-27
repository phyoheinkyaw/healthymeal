/**
 * Console debugging utilities
 * This file provides visual console output to help debug refund operations
 */

(function() {
    // Original console methods
    const originalConsoleLog = console.log;
    const originalConsoleError = console.error;
    const originalConsoleWarn = console.warn;
    const originalConsoleInfo = console.info;
    
    // Create debugger container if not exists
    function ensureDebugContainer() {
        if (!document.getElementById('debug-container')) {
            const container = document.createElement('div');
            container.id = 'debug-container';
            container.style.position = 'fixed';
            container.style.bottom = '0';
            container.style.right = '0';
            container.style.width = '50%';
            container.style.maxHeight = '300px';
            container.style.overflow = 'auto';
            container.style.backgroundColor = 'rgba(0,0,0,0.8)';
            container.style.color = '#00ff00';
            container.style.fontFamily = 'monospace';
            container.style.fontSize = '12px';
            container.style.padding = '10px';
            container.style.zIndex = '9999';
            container.style.display = 'none';
            
            // Add toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.textContent = '🐞 Debug';
            toggleBtn.style.position = 'fixed';
            toggleBtn.style.bottom = '10px';
            toggleBtn.style.right = '10px';
            toggleBtn.style.zIndex = '10000';
            toggleBtn.style.padding = '5px 10px';
            toggleBtn.style.backgroundColor = '#007bff';
            toggleBtn.style.color = 'white';
            toggleBtn.style.border = 'none';
            toggleBtn.style.borderRadius = '4px';
            toggleBtn.style.cursor = 'pointer';
            
            toggleBtn.onclick = function() {
                const debugContainer = document.getElementById('debug-container');
                if (debugContainer.style.display === 'none') {
                    debugContainer.style.display = 'block';
                    toggleBtn.textContent = '❌ Close';
                } else {
                    debugContainer.style.display = 'none';
                    toggleBtn.textContent = '🐞 Debug';
                }
            };
            
            document.body.appendChild(container);
            document.body.appendChild(toggleBtn);
            
            // Add clear button inside debug container
            const clearBtn = document.createElement('button');
            clearBtn.textContent = 'Clear';
            clearBtn.style.backgroundColor = '#dc3545';
            clearBtn.style.color = 'white';
            clearBtn.style.border = 'none';
            clearBtn.style.borderRadius = '4px';
            clearBtn.style.padding = '2px 5px';
            clearBtn.style.marginBottom = '5px';
            clearBtn.style.cursor = 'pointer';
            
            clearBtn.onclick = function() {
                const logContainer = document.getElementById('debug-logs');
                if (logContainer) {
                    logContainer.innerHTML = '';
                }
            };
            
            // Create logs container
            const logsContainer = document.createElement('div');
            logsContainer.id = 'debug-logs';
            
            container.appendChild(clearBtn);
            container.appendChild(logsContainer);
        }
        
        return document.getElementById('debug-logs');
    }
    
    // Add log to visual console
    function addLogToVisualConsole(level, args) {
        const logsContainer = ensureDebugContainer();
        
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry log-${level}`;
        
        // Style based on level
        switch (level) {
            case 'error':
                logEntry.style.color = '#ff5252';
                break;
            case 'warn':
                logEntry.style.color = '#ffb300';
                break;
            case 'info':
                logEntry.style.color = '#2196f3';
                break;
            default:
                logEntry.style.color = '#00ff00';
        }
        
        // Format timestamp
        const now = new Date();
        const timestamp = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}.${now.getMilliseconds().toString().padStart(3, '0')}`;
        
        // Format message - special handling for objects and arrays
        let formattedMessage = '';
        for (let i = 0; i < args.length; i++) {
            const arg = args[i];
            
            if (typeof arg === 'object' && arg !== null) {
                try {
                    formattedMessage += JSON.stringify(arg, null, 2);
                } catch (e) {
                    formattedMessage += '[Object]';
                }
            } else {
                formattedMessage += String(arg);
            }
            
            if (i < args.length - 1) {
                formattedMessage += ' ';
            }
        }
        
        logEntry.innerHTML = `<span class="log-timestamp">[${timestamp}]</span> ${formattedMessage}`;
        logsContainer.appendChild(logEntry);
        
        // Auto-scroll to bottom
        logsContainer.scrollTop = logsContainer.scrollHeight;
    }
    
    // Override console methods
    console.log = function() {
        addLogToVisualConsole('log', arguments);
        originalConsoleLog.apply(console, arguments);
    };
    
    console.error = function() {
        addLogToVisualConsole('error', arguments);
        originalConsoleError.apply(console, arguments);
    };
    
    console.warn = function() {
        addLogToVisualConsole('warn', arguments);
        originalConsoleWarn.apply(console, arguments);
    };
    
    console.info = function() {
        addLogToVisualConsole('info', arguments);
        originalConsoleInfo.apply(console, arguments);
    };
    
    // Add special testing functions
    window.testRefund = function(orderId) {
        console.log('🧪 [TEST] Running refund test for order #' + orderId);
        // Find amount from the table
        const orderRow = document.querySelector(`tr[data-order-id="${orderId}"]`);
        const amount = orderRow ? orderRow.dataset.amount : null;
        
        if (amount) {
            console.log('🧪 [TEST] Found amount:', amount);
            refundPayment(orderId, amount);
        } else {
            console.error('🧪 [TEST] Could not find amount for order #' + orderId);
        }
    };
    
    // Log that console debugging is enabled
    setTimeout(() => {
        console.log('🐞 Console debugging initialized successfully');
        console.log('👉 Use window.testRefund(orderId) to test refund flow');
    }, 1000);
})(); 