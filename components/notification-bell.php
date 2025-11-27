<!-- Notification Bell Component -->
<style>
.notification-bell {
    position: relative;
    cursor: pointer;
}
.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-center;
    font-weight: bold;
}
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}
.notification-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background-color 0.2s;
}
.notification-item:hover {
    background-color: #f9fafb;
}
.notification-item.unread {
    background-color: #eff6ff;
}
.notification-item:last-child {
    border-bottom: none;
}
</style>

<div class="notification-bell relative" onclick="toggleNotifications()">
    <i class="fas fa-bell text-2xl text-gray-600 hover:text-blue-600 transition-colors"></i>
    <span id="notificationBadge" class="notification-badge hidden">0</span>
    
    <div id="notificationDropdown" class="notification-dropdown hidden">
        <div class="p-3 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-800">Notifications</h3>
        </div>
        <div id="notificationList" class="p-2">
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-bell-slash text-3xl mb-2"></i>
                <p>No notifications</p>
            </div>
        </div>
    </div>
</div>

<script>
let notificationDropdownOpen = false;

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.querySelector('.notification-bell');
    
    if (!bell.contains(event.target) && notificationDropdownOpen) {
        dropdown.classList.add('hidden');
        notificationDropdownOpen = false;
    }
});

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    notificationDropdownOpen = !notificationDropdownOpen;
    
    if (notificationDropdownOpen) {
        dropdown.classList.remove('hidden');
        loadNotifications();
    } else {
        dropdown.classList.add('hidden');
    }
}

function loadNotifications() {
    fetch('api/get-notifications.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                renderNotifications(data.notifications);
            }
        })
        .catch(err => console.error('Error loading notifications:', err));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function renderNotifications(notifications) {
    const list = document.getElementById('notificationList');
    
    if (notifications.length === 0) {
        list.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-bell-slash text-3xl mb-2"></i>
                <p>No notifications</p>
            </div>
        `;
        return;
    }
    
    list.innerHTML = notifications.map(notif => `
        <div class="notification-item ${!notif.is_read ? 'unread' : ''}" 
             onclick="handleNotificationClick(${notif.id}, '${notif.link || ''}')">
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-1">
                    <i class="fas fa-${getNotificationIcon(notif.type)} text-blue-600"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p class="font-medium text-gray-900 text-sm">${escapeHtml(notif.title)}</p>
                    <p class="text-gray-600 text-sm mt-1">${escapeHtml(notif.message)}</p>
                    <p class="text-gray-400 text-xs mt-1">${formatTime(notif.created_at)}</p>
                </div>
                ${!notif.is_read ? '<span class="w-2 h-2 bg-blue-600 rounded-full ml-2 mt-2"></span>' : ''}
            </div>
        </div>
    `).join('');
}

function getNotificationIcon(type) {
    const icons = {
        'assignment_deployed': 'paper-plane',
        'assignment_received': 'file-alt',
        'assignment_graded': 'check-circle',
        'default': 'bell'
    };
    return icons[type] || icons.default;
}

function handleNotificationClick(notifId, link) {
    // Mark as read
    fetch('api/mark-notification-read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'notification_id=' + notifId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadNotifications(); // Refresh notifications
            if (link) {
                window.location.href = link;
            }
        }
    });
}

function formatTime(datetime) {
    const date = new Date(datetime);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // seconds
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    if (!notificationDropdownOpen) {
        fetch('api/get-notifications.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                }
            });
    }
}, 30000);

// Initial load
loadNotifications();
</script>
