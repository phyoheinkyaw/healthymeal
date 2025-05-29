/**
 * Admin Panel Notification System
 */
const NotificationSystem = {
    init: function() {
        // Desktop elements
        this.notificationBell = document.getElementById('notificationBell');
        this.notificationDropdown = document.getElementById('notificationDropdown');
        this.notificationBadge = document.getElementById('notificationBadge');
        this.closeNotificationsBtn = document.getElementById('closeNotifications');
        this.notificationTabs = document.querySelectorAll('.notification-tab');
        this.notificationSections = document.querySelectorAll('.notification-section');
        this.ordersContainer = document.getElementById('pendingOrdersContainer');
        this.paymentsContainer = document.getElementById('pendingPaymentsContainer');
        
        // Mobile elements
        this.mobileBell = document.getElementById('mobileBell');
        this.mobileNotificationDropdown = document.getElementById('mobileNotificationDropdown');
        this.mobileNotificationBadge = document.getElementById('mobileNotificationBadge');
        this.closeMobileNotificationsBtn = document.getElementById('closeMobileNotifications');
        this.mobileOrdersContainer = document.getElementById('mobilePendingOrdersContainer');
        this.mobilePaymentsContainer = document.getElementById('mobilePendingPaymentsContainer');
        
        // Debug flag - set to false for production
        this.isDebug = false;
        
        // Track dropdown state
        this.isDropdownOpen = false;
        this.isMobileDropdownOpen = false;
        
        // Throttle variables for scroll events
        this.lastScrollTime = 0;
        this.scrollThrottle = 200; // ms
        
        // Initialize event listeners
        this.bindEvents();
        
        // Fetch notifications on page load
        this.fetchNotifications();
        
        // Set up auto-refresh interval (every 60 seconds)
        setInterval(() => this.fetchNotifications(), 60000);
    },
    
    bindEvents: function() {
        // Desktop notification bell click
        if (this.notificationBell) {
            this.notificationBell.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleDropdown();
            });
        }
        
        // Mobile notification bell click
        if (this.mobileBell) {
            this.mobileBell.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleMobileDropdown();
            });
        }
        
        // Close desktop dropdown
        if (this.closeNotificationsBtn) {
            this.closeNotificationsBtn.addEventListener('click', () => {
                this.hideDropdown();
            });
        }
        
        // Close mobile dropdown
        if (this.closeMobileNotificationsBtn) {
            this.closeMobileNotificationsBtn.addEventListener('click', () => {
                this.hideMobileDropdown();
            });
        }
        
        // Tab switching functionality
        if (this.notificationTabs) {
            this.notificationTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetSection = tab.getAttribute('data-target');
                    
                    // Find the group of tabs this belongs to
                    const tabContainer = tab.closest('.notification-tabs');
                    const contentContainer = tabContainer.nextElementSibling;
                    
                    // Get all tabs and sections in this container
                    const tabs = tabContainer.querySelectorAll('.notification-tab');
                    const sections = contentContainer.querySelectorAll('.notification-section');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show selected section
                    sections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === targetSection) {
                            section.classList.add('active');
                        }
                    });
                });
            });
        }
        
        // Auto-close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            // Desktop dropdown close
            if (this.isDropdownOpen && 
                this.notificationDropdown && 
                !this.notificationDropdown.contains(e.target) && 
                e.target !== this.notificationBell &&
                !e.target.closest('#notificationBell')) {
                this.hideDropdown();
            }
            
            // Mobile dropdown close 
            if (this.isMobileDropdownOpen && 
                this.mobileNotificationDropdown && 
                !this.mobileNotificationDropdown.contains(e.target) && 
                e.target !== this.mobileBell &&
                !e.target.closest('#mobileBell')) {
                this.hideMobileDropdown();
            }
        });
        
        // Auto-close dropdown when scrolling (desktop only) - throttled
        window.addEventListener('scroll', () => {
            const now = Date.now();
            if (now - this.lastScrollTime > this.scrollThrottle) {
                this.lastScrollTime = now;
                
                if (this.isDropdownOpen) {
                    this.hideDropdown();
                }
            }
        });
        
        // Position the dropdown correctly on window resize
        window.addEventListener('resize', () => {
            if (this.isDropdownOpen) {
                this.hideDropdown();
            }
            if (this.isMobileDropdownOpen) {
                this.hideMobileDropdown();
            }
        });
        
        // Auto-close dropdown when changing page
        window.addEventListener('beforeunload', () => {
            if (this.isDropdownOpen) {
                this.hideDropdown();
            }
            if (this.isMobileDropdownOpen) {
                this.hideMobileDropdown();
            }
        });
    },
    
    toggleDropdown: function() {
        if (this.notificationDropdown) {
            if (this.isDropdownOpen) {
                this.hideDropdown();
            } else {
                this.showDropdown();
            }
        }
    },
    
    showDropdown: function() {
        if (this.notificationDropdown) {
            this.notificationDropdown.classList.add('show');
            this.isDropdownOpen = true;
            
            // Animate entrance
            if (this.notificationBell) {
                this.notificationBell.classList.add('active');
            }
        }
    },
    
    hideDropdown: function() {
        if (this.notificationDropdown) {
            this.notificationDropdown.classList.remove('show');
            this.isDropdownOpen = false;
            
            // Remove active state
            if (this.notificationBell) {
                this.notificationBell.classList.remove('active');
            }
        }
    },
    
    toggleMobileDropdown: function() {
        if (this.mobileNotificationDropdown) {
            if (this.isMobileDropdownOpen) {
                this.hideMobileDropdown();
            } else {
                this.showMobileDropdown();
            }
        }
    },
    
    showMobileDropdown: function() {
        if (this.mobileNotificationDropdown) {
            this.mobileNotificationDropdown.style.display = 'block';
            this.mobileNotificationDropdown.classList.add('show');
            this.isMobileDropdownOpen = true;
            
            // Prevent body scrolling when mobile dropdown is open
            document.body.style.overflow = 'hidden';
            
            // Animate entrance
            if (this.mobileBell) {
                this.mobileBell.classList.add('active');
            }
        }
    },
    
    hideMobileDropdown: function() {
        if (this.mobileNotificationDropdown) {
            this.mobileNotificationDropdown.style.display = 'none';
            this.mobileNotificationDropdown.classList.remove('show');
            this.isMobileDropdownOpen = false;
            
            // Restore body scrolling
            document.body.style.overflow = '';
            
            // Remove active state
            if (this.mobileBell) {
                this.mobileBell.classList.remove('active');
            }
        }
    },
    
    fetchNotifications: function() {
        fetch('/hm/admin/api/get_notifications.php')
            .then(response => {
                // First check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                
                // Debug the raw response in console if needed
                if (this.isDebug) {
                    response.clone().text().then(text => {
                        console.log('Raw API response:', text);
                    });
                }
                
                return response.json();
            })
            .then(data => {
                // Debug the parsed data
                if (this.isDebug) {
                    console.log('Parsed notification data:', data);
                }
                
                if (data.success) {
                    // Update desktop notifications
                    this.updateNotificationBadge(data.total_count);
                    this.renderPendingOrders(data.pending_orders);
                    this.renderPendingPayments(data.pending_payments);
                    
                    // Update mobile notifications
                    this.updateMobileNotificationBadge(data.total_count);
                    this.renderMobilePendingOrders(data.pending_orders);
                    this.renderMobilePendingPayments(data.pending_payments);
                    
                    // Flash animation for new notifications (if count changed)
                    if (data.total_count > 0 && this.lastCount !== data.total_count) {
                        this.flashNotificationButton();
                    }
                    
                    // Update last count
                    this.lastCount = data.total_count;
                    
                } else if (data.error) {
                    console.error('API error:', data.error);
                    if (data.debug_info) console.log('Debug info:', data.debug_info);
                    this.showErrorMessage('Error fetching notifications: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                this.showErrorMessage('Failed to load notifications.');
            });
    },
    
    flashNotificationButton: function() {
        // Flash animation for desktop button
        if (this.notificationBell) {
            this.notificationBell.classList.add('pulse');
            setTimeout(() => {
                this.notificationBell.classList.remove('pulse');
            }, 1000);
        }
        
        // Flash animation for mobile button
        if (this.mobileBell) {
            this.mobileBell.classList.add('pulse');
            setTimeout(() => {
                this.mobileBell.classList.remove('pulse');
            }, 1000);
        }
    },
    
    showErrorMessage: function(message) {
        const errorContent = `
            <div class="alert alert-danger m-3">
                <i class="bi bi-exclamation-triangle me-2"></i> 
                ${message}
            </div>
        `;
        
        // Update containers with error message
        const containers = [
            this.ordersContainer,
            this.paymentsContainer,
            this.mobileOrdersContainer,
            this.mobilePaymentsContainer
        ];
        
        containers.forEach(container => {
            if (container) {
                container.innerHTML = errorContent;
            }
        });
    },
    
    updateNotificationBadge: function(count) {
        if (this.notificationBadge) {
            if (count > 0) {
                this.notificationBadge.textContent = count > 99 ? '99+' : count;
                this.notificationBadge.style.display = 'block';
            } else {
                this.notificationBadge.style.display = 'none';
            }
        }
    },
    
    updateMobileNotificationBadge: function(count) {
        if (this.mobileNotificationBadge) {
            if (count > 0) {
                this.mobileNotificationBadge.textContent = count > 99 ? '99+' : count;
                this.mobileNotificationBadge.style.display = 'block';
            } else {
                this.mobileNotificationBadge.style.display = 'none';
            }
        }
    },
    
    renderPendingOrders: function(orders) {
        if (!this.ordersContainer) return;
        
        if (orders.length === 0) {
            this.ordersContainer.innerHTML = `
                <div class="no-notifications">
                    <i class="bi bi-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>No pending orders at the moment</p>
                </div>
            `;
            return;
        }
        
        this.ordersContainer.innerHTML = '';
        
        orders.forEach(order => {
            const orderItem = document.createElement('div');
            orderItem.className = 'notification-item';
            orderItem.innerHTML = `
                <div class="notification-item-header">
                    <div class="notification-item-title">Order #${order.order_id}</div>
                    <div class="notification-item-time">${order.created_at}</div>
                </div>
                <div class="notification-item-content">
                    <div>${order.full_name} (${order.username})</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="badge bg-warning text-dark">Pending</span>
                        <span class="fw-bold">${this.formatCurrency(order.total_amount)} MMK</span>
                    </div>
                </div>
            `;
            orderItem.addEventListener('click', () => {
                window.location.href = `/hm/admin/order-details.php?id=${order.order_id}`;
                this.hideDropdown();
            });
            this.ordersContainer.appendChild(orderItem);
        });
    },
    
    renderPendingPayments: function(payments) {
        if (!this.paymentsContainer) return;
        
        if (payments.length === 0) {
            this.paymentsContainer.innerHTML = `
                <div class="no-notifications">
                    <i class="bi bi-credit-card text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>No pending payments at the moment</p>
                </div>
            `;
            return;
        }
        
        this.paymentsContainer.innerHTML = '';
        
        payments.forEach(payment => {
            const paymentItem = document.createElement('div');
            paymentItem.className = 'notification-item';
            paymentItem.innerHTML = `
                <div class="notification-item-header">
                    <div class="notification-item-title">Payment #${payment.verification_id}</div>
                    <div class="notification-item-time">${payment.created_at}</div>
                </div>
                <div class="notification-item-content">
                    <div>Order #${payment.order_id} - ${payment.full_name}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="badge bg-info">Verification Needed</span>
                        <span class="fw-bold">${this.formatCurrency(payment.amount_verified)} MMK</span>
                    </div>
                </div>
            `;
            paymentItem.addEventListener('click', () => {
                window.location.href = `/hm/admin/order-details.php?id=${payment.order_id}`;
                this.hideDropdown();
            });
            this.paymentsContainer.appendChild(paymentItem);
        });
    },
    
    renderMobilePendingOrders: function(orders) {
        if (!this.mobileOrdersContainer) return;
        
        if (orders.length === 0) {
            this.mobileOrdersContainer.innerHTML = `
                <div class="no-notifications">
                    <i class="bi bi-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>No pending orders at the moment</p>
                </div>
            `;
            return;
        }
        
        this.mobileOrdersContainer.innerHTML = '';
        
        orders.forEach(order => {
            const orderItem = document.createElement('div');
            orderItem.className = 'notification-item';
            orderItem.innerHTML = `
                <div class="notification-item-header">
                    <div class="notification-item-title">Order #${order.order_id}</div>
                    <div class="notification-item-time">${order.created_at}</div>
                </div>
                <div class="notification-item-content">
                    <div>${order.full_name} (${order.username})</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="badge bg-warning text-dark">Pending</span>
                        <span class="fw-bold">${this.formatCurrency(order.total_amount)} MMK</span>
                    </div>
                </div>
            `;
            orderItem.addEventListener('click', () => {
                window.location.href = `/hm/admin/order-details.php?id=${order.order_id}`;
                this.hideMobileDropdown();
            });
            this.mobileOrdersContainer.appendChild(orderItem);
        });
    },
    
    renderMobilePendingPayments: function(payments) {
        if (!this.mobilePaymentsContainer) return;
        
        if (payments.length === 0) {
            this.mobilePaymentsContainer.innerHTML = `
                <div class="no-notifications">
                    <i class="bi bi-credit-card text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>No pending payments at the moment</p>
                </div>
            `;
            return;
        }
        
        this.mobilePaymentsContainer.innerHTML = '';
        
        payments.forEach(payment => {
            const paymentItem = document.createElement('div');
            paymentItem.className = 'notification-item';
            paymentItem.innerHTML = `
                <div class="notification-item-header">
                    <div class="notification-item-title">Payment #${payment.verification_id}</div>
                    <div class="notification-item-time">${payment.created_at}</div>
                </div>
                <div class="notification-item-content">
                    <div>Order #${payment.order_id} - ${payment.full_name}</div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="badge bg-info">Verification Needed</span>
                        <span class="fw-bold">${this.formatCurrency(payment.amount_verified)} MMK</span>
                    </div>
                </div>
            `;
            paymentItem.addEventListener('click', () => {
                window.location.href = `/hm/admin/order-details.php?id=${payment.order_id}`;
                this.hideMobileDropdown();
            });
            this.mobilePaymentsContainer.appendChild(paymentItem);
        });
    },
    
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('en-US').format(amount);
    }
};

// Initialize notification system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    NotificationSystem.init();
}); 