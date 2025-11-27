// Notification Bell Functionality
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');

if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('hidden');
        if (!notificationDropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationDropdown.classList.add('hidden');
        }
    });

    // Mark all as read functionality
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }

    // Load notifications function
    function loadNotifications() {
        fetch('get-teacher-notifications.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const notificationList = document.getElementById('notificationList');
                const notificationCount = document.getElementById('notificationCount');
                
                if (data.notifications && data.notifications.length > 0) {
                    notificationList.innerHTML = data.notifications.map(notification => `
                        <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer notification-item ${notification.is_read ? '' : 'bg-blue-50'}" 
                             data-notification-id="${notification.id}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-medium text-sm text-gray-800">${notification.title || 'Notification'}</p>
                                    <p class="text-xs text-gray-600 mt-1">${notification.message || 'New update'}</p>
                                    <p class="text-xs text-gray-400 mt-1">${notification.time || 'Recently'}</p>
                                </div>
                                ${!notification.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full ml-2 mt-1 flex-shrink-0"></span>' : ''}
                            </div>
                        </div>
                    `).join('');
                    
                    // Add click handlers for notifications
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const notificationId = this.getAttribute('data-notification-id');
                            // Mark as read if unread
                            if (this.classList.contains('bg-blue-50')) {
                                markNotificationAsRead(notificationId);
                                this.classList.remove('bg-blue-50');
                                const unreadDot = this.querySelector('.bg-blue-500');
                                if (unreadDot) {
                                    unreadDot.remove();
                                }
                                // Update counter
                                loadNotificationCount();
                            }
                            // Close dropdown
                            notificationDropdown.classList.add('hidden');
                        });
                    });
                    
                    // Update notification count
                    if (data.unread_count > 0) {
                        notificationCount.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        notificationCount.classList.remove('hidden');
                    } else {
                        notificationCount.classList.add('hidden');
                    }
                } else {
                    notificationList.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-bell-slash text-2xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500">No notifications</p>
                            <p class="text-sm text-gray-400 mt-1">You're all caught up!</p>
                        </div>
                    `;
                    notificationCount.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                const notificationList = document.getElementById('notificationList');
                notificationList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-400 mb-2"></i>
                        <p class="text-gray-500">Failed to load notifications</p>
                        <p class="text-sm text-gray-400 mt-1">Please try again later</p>
                    </div>
                `;
            });
    }

    // Mark single notification as read
    function markNotificationAsRead(notificationId) {
        const formData = new FormData();
        formData.append('notification_id', notificationId);

        fetch('mark-notification-read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to mark notification as read');
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Mark all notifications as read
    function markAllNotificationsAsRead() {
        fetch('mark-all-notifications-read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('bg-blue-50');
                    const unreadDot = item.querySelector('.bg-blue-500');
                    if (unreadDot) {
                        unreadDot.remove();
                    }
                });
                
                // Hide notification counter
                document.getElementById('notificationCount').classList.add('hidden');
                
                // Show success message
                const notificationList = document.getElementById('notificationList');
                const successMessage = document.createElement('div');
                successMessage.className = 'p-3 bg-green-50 text-green-700 text-sm text-center border-b border-green-200';
                successMessage.textContent = 'All notifications marked as read';
                notificationList.insertBefore(successMessage, notificationList.firstChild);
                
                setTimeout(() => {
                    successMessage.remove();
                }, 3000);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Load just the notification count
    function loadNotificationCount() {
        fetch('get-teacher-notifications.php')
            .then(response => response.json())
            .then(data => {
                const notificationCount = document.getElementById('notificationCount');
                if (data.unread_count > 0) {
                    notificationCount.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                    notificationCount.classList.remove('hidden');
                } else {
                    notificationCount.classList.add('hidden');
                }
            })
            .catch(error => console.error('Error loading notification count:', error));
    }

    // Load initial notification count
    loadNotificationCount();
    
    // Refresh notification count every 30 seconds
    setInterval(loadNotificationCount, 30000);
}