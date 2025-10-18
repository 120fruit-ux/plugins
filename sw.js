// Financial Summary System - Service Worker

const CACHE_NAME = 'fss-cache-v1';
const urlsToCache = [
    // Add any static assets you want to cache
];

// Install event
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

// Fetch event
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            })
    );
});

// Push event - Handle incoming push notifications
self.addEventListener('push', event => {
    console.log('Push event received');
    
    if (!event.data) {
        console.log('Push event but no data');
        return;
    }
    
    let data;
    try {
        data = event.data.json();
    } catch (e) {
        console.log('Error parsing push data:', e);
        return;
    }
    
    const options = {
        body: data.body,
        icon: data.icon || '/wp-content/plugins/financial-summary-system/assets/images/default-icon.png',
        badge: data.badge || '/wp-content/plugins/financial-summary-system/assets/images/badge-icon.png',
        vibrate: data.vibrate || [200, 100, 200],
        data: data.data || {},
        actions: data.actions || [],
        requireInteraction: data.requireInteraction || false,
        tag: 'fss-notification',
        renotify: true
    };
    
    // Customize notification based on type
    if (data.data && data.data.type) {
        switch (data.data.type) {
            case 'discrepancy_alert':
                options.requireInteraction = true;
                options.tag = 'fss-discrepancy';
                options.vibrate = [200, 100, 200, 100, 200];
                break;
            case 'missing_summary':
                options.tag = 'fss-reminder';
                break;
            case 'reconciliation_reminder':
                options.tag = 'fss-reconciliation';
                options.requireInteraction = true;
                break;
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked');
    
    event.notification.close();
    
    const notification = event.notification;
    const action = event.action;
    const data = notification.data;
    
    if (action === 'dismiss') {
        return;
    }
    
    // Default action or 'view' action
    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(clientList => {
            // Check if there's already a window/tab open
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    // Send message to existing window
                    client.postMessage({
                        type: 'notification-click',
                        notification: {
                            title: notification.title,
                            data: data,
                            action: action
                        }
                    });
                    return client.focus();
                }
            }
            
            // No existing window, open new one
            if (clients.openWindow) {
                let url = data.url || self.location.origin;
                
                // Add specific URL fragments based on notification type
                switch (data.type) {
                    case 'discrepancy_alert':
                        url += '#reconciliation';
                        break;
                    case 'missing_summary':
                        url += '#fss-summary-form';
                        break;
                    case 'reconciliation_reminder':
                        url += '#reconciliation';
                        break;
                }
                
                return clients.openWindow(url);
            }
        })
    );
});

// Background sync for offline functionality (optional)
self.addEventListener('sync', event => {
    if (event.tag === 'fss-sync') {
        event.waitUntil(
            // Sync any pending financial data when back online
            syncPendingData()
        );
    }
});

async function syncPendingData() {
    // Implementation for syncing pending financial summaries when back online
    console.log('Syncing pending financial data...');
}

// Notification close event
self.addEventListener('notificationclose', event => {
    console.log('Notification closed:', event.notification.tag);
    
    // Track notification engagement
    event.waitUntil(
        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'fss_track_notification',
                type: 'close',
                notification_data: JSON.stringify(event.notification.data)
            })
        }).catch(err => console.log('Failed to track notification close:', err))
    );
});