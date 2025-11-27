// Notification Bell Functionality
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBell && notificationDropdown) {
        let notificationsLoaded = false;
        
        // Toggle dropdown on bell click
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle dropdown visibility
            if (notificationDropdown.classList.contains('hidden')) {
                notificationDropdown.classList.remove('hidden');
                if (!notificationsLoaded) {
                    loadNotifications();
                    notificationsLoaded = true;
                }
            } else {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });

        // Prevent dropdown from closing when clicking inside it
        notificationDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Mark all as read functionality
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                markAllAsRead();
            });
        }

        // Load notifications function
        function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    
    // Show loading state
    notificationList.innerHTML = `
        <div class="text-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto mb-2"></div>
            <p class="text-gray-500 text-sm">Loading notifications...</p>
        </div>
    `;

    // Fetch notifications from server
    fetch('get-student-notifications.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Notifications API Response:', data);
            
            // Check if we have valid notifications array
            if (data.notifications && Array.isArray(data.notifications)) {
                
                if (data.notifications.length > 0) {
                    notificationList.innerHTML = data.notifications.map(notification => {
                        // Safely handle all properties with fallbacks
                        const notificationId = notification.id || 0;
                        const assignmentId = notification.assignment_id || 0;
                        const notificationType = notification.type || 'system';
                        const isRead = notification.is_read || false;
                        const title = notification.title || 'Notification';
                        const message = notification.message || 'No message';
                        const time = notification.time || 'Recently';
                        
                        return `
                            <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer notification-item ${isRead ? '' : 'bg-blue-50'}" 
                                 data-notification-id="${notificationId}" 
                                 data-assignment-id="${assignmentId}" 
                                 data-notification-type="${notificationType}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <p class="font-medium text-sm text-gray-800">${title}</p>
                                        <p class="text-xs text-gray-600 mt-1">${message}</p>
                                        <p class="text-xs text-gray-400 mt-1">${time}</p>
                                    </div>
                                    ${!isRead ? '<span class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-1 flex-shrink-0"></span>' : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    // Add click event to notifications
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const notificationId = this.getAttribute('data-notification-id');
                            const assignmentId = this.getAttribute('data-assignment-id');
                            const type = this.getAttribute('data-notification-type');
                            
                            // Mark as read if unread
                            if (this.classList.contains('bg-blue-50')) {
                                markAsRead(notificationId);
                                this.classList.remove('bg-blue-50');
                                const unreadDot = this.querySelector('.bg-blue-500');
                                if (unreadDot) {
                                    unreadDot.remove();
                                }
                                // Update counter
                                updateNotificationCounter(data.unread_count - 1);
                            }
                            
                            // Redirect if it's an assignment notification and has assignment ID
                            if (assignmentId && assignmentId !== '0' && assignmentId !== 'null' && type === 'assignment') {
                                window.location.href = `view-detail.php?id=${assignmentId}&type=assignment`;
                            }
                            
                            // Close dropdown
                            notificationDropdown.classList.add('hidden');
                        });
                    });
                    
                    updateNotificationCounter(data.unread_count || 0);
                } else {
                    // No notifications
                    notificationList.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-bell-slash text-2xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500">No notifications</p>
                            <p class="text-sm text-gray-400 mt-1">You're all caught up!</p>
                        </div>
                    `;
                    updateNotificationCounter(0);
                }
            } else {
                // Invalid response format
                throw new Error('Invalid response format from server');
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            notificationList.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-2"></i>
                    <p class="text-gray-500">Failed to load notifications</p>
                    <p class="text-sm text-gray-400 mt-1">Please try again later</p>
                    <button class="mt-2 text-blue-600 text-sm hover:text-blue-800 retry-btn">
                        Retry
                    </button>
                    <div class="mt-2 text-xs text-gray-500">
                        Error: ${error.message}
                    </div>
                </div>
            `;
            
            // Add retry functionality
            document.querySelector('.retry-btn').addEventListener('click', function() {
                loadNotifications();
            });
            
            updateNotificationCounter(0);
        });
}

        // Helper function to format time
        function formatTime(timestamp) {
            if (!timestamp) return 'Recently';
            
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString();
        }

        function markAsRead(notificationId) {
            if (!notificationId) return;
            
            const formData = new URLSearchParams();
            formData.append('notification_id', notificationId);

            fetch('mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to mark as read:', data);
                }
            })
            .catch(error => console.error('Error marking as read:', error));
        }

        function markAllAsRead() {
            fetch('mark-all-notifications-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI - Remove all unread indicators
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.classList.remove('bg-blue-50');
                        const unreadDot = item.querySelector('.bg-blue-500');
                        if (unreadDot) {
                            unreadDot.remove();
                        }
                    });
                    
                    // Hide the notification counter
                    updateNotificationCounter(0);
                    
                    // Show success message
                    const notificationList = document.getElementById('notificationList');
                    const successMessage = document.createElement('div');
                    successMessage.className = 'p-3 bg-green-50 text-green-700 text-sm text-center border-b border-green-200';
                    successMessage.textContent = 'All notifications marked as read';
                    
                    // Insert success message at the top
                    notificationList.insertBefore(successMessage, notificationList.firstChild);
                    
                    // Remove success message after 3 seconds
                    setTimeout(() => {
                        if (successMessage.parentNode) {
                            successMessage.remove();
                        }
                    }, 3000);
                } else {
                    console.error('Failed to mark all notifications as read');
                    alert('Failed to mark all notifications as read. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error marking all as read:', error);
                alert('Error marking notifications as read. Please try again.');
            });
        }

        function updateNotificationCounter(count) {
            const notificationCount = document.getElementById('notificationCount');
            
            if (count > 0) {
                notificationCount.textContent = count > 9 ? '9+' : count;
                notificationCount.classList.remove('hidden');
            } else {
                notificationCount.classList.add('hidden');
                notificationCount.textContent = '0';
            }
        }

        // Load initial notification count on page load
        function loadInitialNotificationCount() {
            fetch('get-student-notifications.php')
                .then(response => response.json())
                .then(data => {
                    updateNotificationCounter(data.unread_count);
                })
                .catch(error => {
                    console.error('Error loading notification count:', error);
                    // Set to 0 if there's an error
                    updateNotificationCounter(0);
                });
        }

        // Initialize notification count
        loadInitialNotificationCount();
        
        // Refresh notification count every 30 seconds
        setInterval(loadInitialNotificationCount, 30000);
    } else {
        console.error('Notification elements not found');
    }
});