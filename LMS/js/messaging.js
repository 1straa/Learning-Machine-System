// Real-time notification and messaging system
class MessagingSystem {
    constructor() {
        this.updateInterval = 10000; // Check for new messages every 10 seconds
        this.intervalId = null;
        this.currentMessageId = null;
        this.init();
    }

    init() {
        // Initial load
        this.updateNotificationBadge();
        this.loadRecentNotifications();
        
        // Start polling for new messages
        this.startPolling();
        
        // Setup event listeners
        this.setupEventListeners();
    }

    startPolling() {
        this.intervalId = setInterval(() => {
            this.updateNotificationBadge();
            this.loadRecentNotifications();
            
            // If on messages tab, refresh message list
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'messages') {
                this.loadMessages();
            }
        }, this.updateInterval);
    }

    stopPolling() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    }

    setupEventListeners() {
        // Notification bell click
        const notifBtn = document.querySelector('.notification-btn');
        if (notifBtn) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleNotificationDropdown();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.notification-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Message filter and search
        const filterSelect = document.getElementById('messageFilter');
        const searchInput = document.getElementById('messageSearch');
        
        if (filterSelect) {
            filterSelect.addEventListener('change', () => this.loadMessages());
        }
        
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => this.loadMessages(), 500);
            });
        }
    }

    async updateNotificationBadge() {
        try {
            const response = await fetch('message_api.php?action=get_unread_count');
            const data = await response.json();
            
            if (data.success) {
                const badge = document.querySelector('.notification-btn .badge');
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = data.count > 0 ? 'inline-block' : 'none';
                }
            }
        } catch (error) {
            console.error('Error updating notification badge:', error);
        }
    }

    async loadRecentNotifications() {
        try {
            const response = await fetch('message_api.php?action=get_recent_messages');
            const data = await response.json();
            
            if (data.success) {
                this.renderNotificationDropdown(data.messages);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    renderNotificationDropdown(messages) {
        let dropdown = document.querySelector('.notification-dropdown');
        
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'notification-dropdown';
            document.querySelector('.notification-btn').parentElement.appendChild(dropdown);
        }

        if (messages.length === 0) {
            dropdown.innerHTML = `
                <div class="notification-header">
                    <h6>Notifications</h6>
                </div>
                <div class="notification-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No new messages</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="notification-header">
                <h6>Recent Messages</h6>
                <a href="?tab=messages" class="view-all">View All</a>
            </div>
            <div class="notification-list">
        `;

        messages.forEach(msg => {
            const timeAgo = this.formatTimeAgo(msg.sent_at);
            const unreadClass = msg.is_read == 0 ? 'unread' : '';
            const roleColor = msg.sender_role === 'faculty' ? 'primary' : 'success';
            
            html += `
                <div class="notification-item ${unreadClass}" onclick="messagingSystem.viewMessageFromNotification(${msg.id})">
                    <div class="notification-icon bg-${roleColor}">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="notification-content">
                        <strong>${this.escapeHtml(msg.sender_name)}</strong>
                        <span class="badge badge-sm bg-${roleColor}">${msg.sender_role}</span>
                        <p>${this.escapeHtml(msg.subject)}</p>
                        <small>${timeAgo}</small>
                    </div>
                    ${msg.is_read == 0 ? '<span class="unread-dot"></span>' : ''}
                </div>
            `;
        });

        html += '</div>';
        dropdown.innerHTML = html;
    }

    toggleNotificationDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    async loadMessages() {
        const filter = document.getElementById('messageFilter')?.value || 'all';
        const search = document.getElementById('messageSearch')?.value || '';
        
        try {
            const response = await fetch(`message_api.php?action=get_all_messages&filter=${filter}&search=${encodeURIComponent(search)}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderMessageList(data.messages);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    renderMessageList(messages) {
        const container = document.getElementById('messageListContainer');
        if (!container) return;

        if (messages.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No messages found</p>
                </div>
            `;
            return;
        }

        let html = '<div class="message-list">';
        
        messages.forEach(msg => {
            const timeAgo = this.formatTimeAgo(msg.sent_at);
            const unreadClass = msg.is_read == 0 ? 'unread' : '';
            const roleColor = msg.sender_role === 'faculty' ? 'primary' : 'success';
            const initials = msg.sender_name.charAt(0).toUpperCase();
            
            html += `
                <div class="message-item ${unreadClass}" onclick="messagingSystem.viewMessage(${msg.id})">
                    <div class="message-avatar bg-${roleColor}">${initials}</div>
                    <div class="message-content">
                        <div class="message-header">
                            <div>
                                <strong>${this.escapeHtml(msg.sender_name)}</strong>
                                <span class="badge-custom bg-${roleColor} ms-2">${msg.sender_role}</span>
                            </div>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                        <div class="message-subject"><strong>${this.escapeHtml(msg.subject)}</strong></div>
                        <p class="text-muted mb-0">${this.escapeHtml(msg.message.substring(0, 100))}${msg.message.length > 100 ? '...' : ''}</p>
                    </div>
                    ${msg.is_read == 0 ? '<div class="ms-auto"><span class="badge bg-danger">New</span></div>' : ''}
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }

    async viewMessage(messageId) {
        this.currentMessageId = messageId;
        
        try {
            const response = await fetch(`message_api.php?action=get_message&id=${messageId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderMessageView(data.message);
                this.updateNotificationBadge();
                this.loadMessages(); // Refresh list to update read status
            }
        } catch (error) {
            console.error('Error loading message:', error);
            this.showToast('Failed to load message', 'danger');
        }
    }

    async viewMessageFromNotification(messageId) {
        // Close notification dropdown
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
        
        // Navigate to messages tab if not already there
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') !== 'messages') {
            window.location.href = `?tab=messages&msg=${messageId}`;
            return;
        }
        
        this.viewMessage(messageId);
    }

    renderMessageView(message) {
        const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
        const modalTitle = document.getElementById('viewMessageModalLabel');
        const modalBody = document.getElementById('viewMessageModalBody');
        
        const roleColor = message.sender_role === 'faculty' ? 'primary' : 'success';
        const sentDate = new Date(message.sent_at).toLocaleString();
        
        let html = `
            <div class="message-detail">
                <div class="message-detail-header">
                    <div class="sender-info">
                        <div class="sender-avatar bg-${roleColor}">
                            ${message.sender_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h5>${this.escapeHtml(message.sender_name)}</h5>
                            <span class="badge bg-${roleColor}">${message.sender_role}</span>
                            <p class="text-muted mb-0">${message.sender_email}</p>
                        </div>
                    </div>
                    <small class="text-muted">${sentDate}</small>
                </div>
                <hr>
                <div class="message-detail-body">
                    <p>${this.escapeHtml(message.message).replace(/\n/g, '<br>')}</p>
                </div>
        `;
        
        // Render replies
        if (message.replies && message.replies.length > 0) {
            html += '<hr><div class="message-replies"><h6><i class="fas fa-reply me-2"></i>Conversation</h6>';
            
            message.replies.forEach(reply => {
                const replyDate = new Date(reply.sent_at).toLocaleString();
                const isAdmin = reply.sender_role === 'admin';
                const replyClass = isAdmin ? 'reply-admin' : 'reply-user';
                
                html += `
                    <div class="message-reply ${replyClass}">
                        <div class="reply-header">
                            <strong>${this.escapeHtml(reply.sender_name)}</strong>
                            <small class="text-muted">${replyDate}</small>
                        </div>
                        <p>${this.escapeHtml(reply.reply).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
            });
            
            html += '</div>';
        }
        
        // Reply form
        html += `
            <hr>
            <div class="reply-form">
                <h6><i class="fas fa-reply me-2"></i>Send Reply</h6>
                <form onsubmit="messagingSystem.sendReply(event, ${message.id})">
                    <textarea class="form-control mb-3" id="replyText" rows="4" placeholder="Type your reply..." required></textarea>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Reply
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="messagingSystem.quickReply('Thank you for your message. I will look into this.')">
                            <i class="fas fa-magic me-2"></i>Quick Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
        `;
        
        modalTitle.textContent = message.subject;
        modalBody.innerHTML = html;
        modal.show();
    }

    quickReply(text) {
        const replyTextarea = document.getElementById('replyText');
        if (replyTextarea) {
            replyTextarea.value = text;
            replyTextarea.focus();
        }
    }

    async sendReply(event, messageId) {
        event.preventDefault();
        
        const replyText = document.getElementById('replyText').value.trim();
        if (!replyText) return;
        
        const formData = new FormData();
        formData.append('action', 'send_reply');
        formData.append('message_id', messageId);
        formData.append('reply', replyText);
        
        try {
            const response = await fetch('message_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast('Reply sent successfully!', 'success');
                // Reload message to show new reply
                this.viewMessage(messageId);
            } else {
                this.showToast(data.message || 'Failed to send reply', 'danger');
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            this.showToast('Failed to send reply', 'danger');
        }
    }

    showToast(message, type = 'info') {
        // Create toast notification
        const toastContainer = document.getElementById('toastContainer') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // Open compose modal and load recipients
    ComposeModal() {
        this.loadUsersForCompose();
        const modal = new bootstrap.Modal(document.getElementById('composeMessageModal'));
        modal.show();
    }

    async loadUsersForCompose() {
        try {
            const response = await fetch('message_api.php?action=get_users');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('composeRecipient');
                if (select) {
                    let html = '<option value="">Select recipient...</option>';
                    
                    let students = data.users.filter(u => u.role === 'student');
                    let faculty = data.users.filter(u => u.role === 'faculty');
                    
                    if (faculty.length > 0) {
                        html += '<optgroup label="Faculty">';
                        faculty.forEach(user => {
                            html += `<option value="${user.id}">${this.escapeHtml(user.name)} (${user.email})</option>`;
                        });
                        html += '</optgroup>';
                    }
                    
                    if (students.length > 0) {
                        html += '<optgroup label="Students">';
                        students.forEach(user => {
                            html += `<option value="${user.id}">${this.escapeHtml(user.name)} (${user.email})</option>`;
                        });
                        html += '</optgroup>';
                    }
                    
                    select.innerHTML = html;
                }
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async sendNewMessage(event) {
        event.preventDefault();
        
        const recipient = document.getElementById('composeRecipient').value;
        const subject = document.getElementById('composeSubject').value.trim();
        const message = document.getElementById('composeMessage').value.trim();
        
        if (!recipient || !subject || !message) {
            this.showToast('All fields are required', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('receiver_id', recipient);
        formData.append('subject', subject);
        formData.append('message', message);
        
        try {
            const response = await fetch('message_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast('Message sent successfully!', 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('composeMessageModal'));
                if (modal) modal.hide();
                // Reset form
                event.target.reset();
            } else {
                this.showToast(data.message || 'Failed to send message', 'danger');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showToast('Failed to send message', 'danger');
        }
    }

    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`;
        
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize messaging system
let messagingSystem;
document.addEventListener('DOMContentLoaded', function() {
    messagingSystem = new MessagingSystem();
    
    // Check if we need to open a specific message (from URL)
    const urlParams = new URLSearchParams(window.location.search);
    const msgId = urlParams.get('msg');
    if (msgId && urlParams.get('tab') === 'messages') {
        setTimeout(() => {
            messagingSystem.viewMessage(parseInt(msgId));
        }, 500);
    }
    
    // Load messages if on messages tab
    if (urlParams.get('tab') === 'messages') {
        messagingSystem.loadMessages();
    }
});