// Financial Summary System - Push Notifications

class FSS_PushNotifications {
    constructor() {
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.isSubscribed = false;
        this.swRegistration = null;
        
        if (this.isSupported) {
            this.init();
        } else {
            console.log('Push notifications are not supported');
        }
    }
    
    async init() {
        try {
            // Register service worker
            this.swRegistration = await navigator.serviceWorker.register(
                fss_push.service_worker_url + '?fss_sw=1',
                { scope: '/' }
            );
            
            console.log('Service Worker registered');
            
            // Check current subscription status
            await this.checkSubscription();
            
            // Add notification request button to page
            this.addNotificationButton();
            
        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    }
    
    async checkSubscription() {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = !(subscription === null);
            
            if (this.isSubscribed) {
                console.log('User is subscribed to push notifications');
            } else {
                console.log('User is not subscribed to push notifications');
            }
        } catch (error) {
            console.error('Error checking subscription:', error);
        }
    }
    
    addNotificationButton() {
        // Add notification permission button to financial summary form
        const container = document.querySelector('.fss-header');
        if (!container) return;
        
        const notificationSection = document.createElement('div');
        notificationSection.className = 'fss-notification-section';
        notificationSection.innerHTML = `
            <div class="fss-notification-controls">
                <button id="fss-enable-notifications" class="fss-notification-btn" style="display: none;">
                    🔔 Enable Notifications
                </button>
                <button id="fss-test-notification" class="fss-notification-btn" style="display: none;">
                    📱 Test Notification
                </button>
                <div id="fss-notification-status" class="fss-notification-status"></div>
            </div>
        `;
        
        container.appendChild(notificationSection);
        
        this.updateNotificationUI();
        this.bindNotificationEvents();
    }
    
    updateNotificationUI() {
        const enableBtn = document.getElementById('fss-enable-notifications');
        const testBtn = document.getElementById('fss-test-notification');
        const status = document.getElementById('fss-notification-status');
        
        if (!enableBtn || !testBtn || !status) return;
        
        if (Notification.permission === 'denied') {
            status.innerHTML = '🚫 Notifications blocked. Please enable in browser settings.';
            status.className = 'fss-notification-status denied';
        } else if (Notification.permission === 'granted' && this.isSubscribed) {
            enableBtn.style.display = 'none';
            testBtn.style.display = 'inline-block';
            status.innerHTML = '✅ Notifications enabled';
            status.className = 'fss-notification-status enabled';
        } else {
            enableBtn.style.display = 'inline-block';
            testBtn.style.display = 'none';
            status.innerHTML = '🔕 Click to enable notifications';
            status.className = 'fss-notification-status disabled';
        }
    }
    
    bindNotificationEvents() {
        const enableBtn = document.getElementById('fss-enable-notifications');
        const testBtn = document.getElementById('fss-test-notification');
        
        if (enableBtn) {
            enableBtn.addEventListener('click', () => this.subscribeUser());
        }
        
        if (testBtn) {
            testBtn.addEventListener('click', () => this.sendTestNotification());
        }
    }
    
    async subscribeUser() {
        try {
            // Request notification permission
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                throw new Error('Notification permission denied');
            }
            
            // Subscribe to push notifications
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(fss_push.vapid_public_key)
            });
            
            // Send subscription to server
            const response = await fetch(fss_push.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fss_register_push_subscription',
                    nonce: fss_push.nonce,
                    subscription: JSON.stringify(subscription)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.isSubscribed = true;
                this.updateNotificationUI();
                this.showMessage('Notifications enabled successfully!', 'success');
            } else {
                throw new Error(result.data || 'Subscription failed');
            }
            
        } catch (error) {
            console.error('Failed to subscribe user:', error);
            this.showMessage('Failed to enable notifications: ' + error.message, 'error');
        }
    }
    
    async sendTestNotification() {
        try {
            const response = await fetch(fss_push.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fss_send_test_notification',
                    nonce: fss_push.nonce
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showMessage('Test notification sent!', 'success');
            } else {
                throw new Error(result.data || 'Test failed');
            }
            
        } catch (error) {
            console.error('Failed to send test notification:', error);
            this.showMessage('Test notification failed: ' + error.message, 'error');
        }
    }
    
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    showMessage(message, type) {
        // Reuse existing notification system
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            alert(message);
        }
    }
}

// Initialize push notifications when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.fssPushNotifications = new FSS_PushNotifications();
});

// Handle notification clicks in background
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', event => {
        if (event.data && event.data.type === 'notification-click') {
            // Handle notification click actions
            const notificationData = event.data.notification;
            
            switch (notificationData.data.type) {
                case 'discrepancy_alert':
                    // Redirect to reconciliation page
                    window.location.href = '#reconciliation';
                    break;
                case 'missing_summary':
                    // Scroll to summary form
                    const form = document.getElementById('fss-summary-form');
                    if (form) form.scrollIntoView({ behavior: 'smooth' });
                    break;
                case 'reconciliation_reminder':
                    // Show reconciliation modal
                    if (typeof showReconciliation === 'function') {
                        showReconciliation();
                    }
                    break;
            }
        }
    });
}