// Metro Dagupan Colleges Dashboard - Responsive JavaScript
// This file handles mobile menu, responsive features, and interactivity

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // MOBILE MENU FUNCTIONALITY
    // ========================================
    
    // Create mobile menu toggle button if it doesn't exist
    if (!document.querySelector('.mobile-menu-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'mobile-menu-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.setAttribute('aria-label', 'Toggle Menu');
        document.body.appendChild(toggleBtn);
    }
    
    // Create sidebar overlay if it doesn't exist
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    // Toggle mobile menu
    function toggleMobileMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    
    // Close mobile menu
    function closeMobileMenu() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Event listeners
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobileMenu);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeMobileMenu);
    }
    
    // Close menu when clicking sidebar links on mobile
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 767) {
                closeMobileMenu();
            }
        });
    });
    
    // Close menu on window resize if switching to desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 767) {
                closeMobileMenu();
            }
        }, 250);
    });
    
    // ========================================
    // SEARCH FUNCTIONALITY
    // ========================================
    
    const searchInputs = document.querySelectorAll('.search-box input, #userSearch, #courseSearch, #messageSearch');
    
    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(function() {
                const searchTerm = this.value.toLowerCase().trim();
                const targetId = this.id;
                
                // Handle different search contexts
                if (targetId === 'userSearch') {
                    filterTable('usersTable', searchTerm);
                } else if (targetId === 'courseSearch') {
                    filterCourses(searchTerm);
                } else if (targetId === 'messageSearch') {
                    filterMessages(searchTerm);
                }
            }, 300));
        }
    });
    
    // Debounce helper function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Filter table rows
    function filterTable(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }
    
    // Filter courses
    function filterCourses(searchTerm) {
        const cards = document.querySelectorAll('.course-card');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }
    
    // Filter messages
    function filterMessages(searchTerm) {
        const messages = document.querySelectorAll('.message-item');
        messages.forEach(message => {
            const text = message.textContent.toLowerCase();
            message.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }
    
    // ========================================
    // ROLE FILTER FOR USER MANAGEMENT
    // ========================================
    
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            const selectedRole = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const role = row.getAttribute('data-role');
                if (!selectedRole || role === selectedRole) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
    
    // ========================================
    // MODAL HANDLING
    // ========================================
    
    // Handle Edit User Modal
    const editUserButtons = document.querySelectorAll('.edit-user-btn');
    editUserButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const role = this.getAttribute('data-role');
            
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
            
            toggleEditCourseSelection(role);
            
            // Fetch student's enrolled courses if student
            if (role === 'student') {
                fetchStudentCourses(id);
            }
        });
    });
    
    // Handle Edit Course Modal
    const editCourseButtons = document.querySelectorAll('.edit-course-btn');
    editCourseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const instructorId = this.getAttribute('data-instructor-id');
            
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_name').value = name;
            document.getElementById('edit_course_description').value = description;
            document.getElementById('edit_instructor_id').value = instructorId;
        });
    });
    
    // Handle Edit Assignment Modal
    const editAssignmentButtons = document.querySelectorAll('.edit-assignment-btn');
    editAssignmentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const description = this.getAttribute('data-description');
            const courseId = this.getAttribute('data-course-id');
            const dueDate = this.getAttribute('data-due-date');
            
            document.getElementById('edit_assignment_id').value = id;
            document.getElementById('edit_assignment_title').value = title;
            document.getElementById('edit_assignment_description').value = description;
            document.getElementById('edit_assignment_course_id').value = courseId;
            document.getElementById('edit_assignment_due_date').value = dueDate;
        });
    });
    
    // ========================================
    // COURSE ENROLLMENT TOGGLE
    // ========================================
    
    window.toggleCourseSelection = function(role) {
        const section = document.getElementById('courseEnrollmentSection');
        if (section) {
            section.style.display = role === 'student' ? 'block' : 'none';
        }
    };
    
    window.toggleEditCourseSelection = function(role) {
        const section = document.getElementById('editCourseEnrollmentSection');
        if (section) {
            section.style.display = role === 'student' ? 'block' : 'none';
        }
    };
    
    // Fetch student courses for edit modal
    async function fetchStudentCourses(studentId) {
        try {
            const response = await fetch(`get_student_courses.php?student_id=${studentId}`);
            const data = await response.json();
            
            // Uncheck all checkboxes first
            document.querySelectorAll('.edit-course-check').forEach(cb => cb.checked = false);
            
            // Check enrolled courses
            data.forEach(courseId => {
                const checkbox = document.getElementById(`edit_course_${courseId}`);
                if (checkbox) checkbox.checked = true;
            });
        } catch (error) {
            console.error('Error fetching student courses:', error);
        }
    }
    
    // ========================================
    // FORM VALIDATION
    // ========================================
    
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'danger');
            }
        });
    });
    
    // ========================================
    // NOTIFICATION SYSTEM
    // ========================================
    
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show success-message`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Auto-dismiss success messages
    const successMessages = document.querySelectorAll('.success-message');
    successMessages.forEach(msg => {
        setTimeout(() => {
            msg.remove();
        }, 3000);
    });
    
    // ========================================
    // RESET FILTERS
    // ========================================
    
    window.resetFilters = function() {
        const userSearch = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const sortBy = document.getElementById('sortBy');
        
        if (userSearch) userSearch.value = '';
        if (roleFilter) roleFilter.value = '';
        if (sortBy) sortBy.value = 'recent';
        
        const rows = document.querySelectorAll('#usersTable tbody tr');
        rows.forEach(row => row.style.display = '');
    };
    
    // ========================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ========================================
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // ========================================
    // TABLE ENHANCEMENTS
    // ========================================
    
    // Add hover tooltips to table actions
    const tableActions = document.querySelectorAll('.table-actions button, .table-actions a');
    tableActions.forEach(action => {
        if (!action.hasAttribute('title')) {
            const icon = action.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-edit')) {
                    action.setAttribute('title', 'Edit');
                } else if (icon.classList.contains('fa-trash')) {
                    action.setAttribute('title', 'Delete');
                } else if (icon.classList.contains('fa-eye')) {
                    action.setAttribute('title', 'View');
                }
            }
        }
    });
    
    // ========================================
    // PROGRESS BAR ANIMATIONS
    // ========================================
    
    const progressBars = document.querySelectorAll('.progress-fill, .progress-bar');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const progressObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const width = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = width;
                }, 100);
                progressObserver.unobserve(progressBar);
            }
        });
    }, observerOptions);
    
    progressBars.forEach(bar => {
        progressObserver.observe(bar);
    });
    
    // ========================================
    // CARD ANIMATIONS ON SCROLL
    // ========================================
    
    const cards = document.querySelectorAll('.stat-card, .course-card, .action-btn');
    
    const cardObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    entry.target.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, 100);
                cardObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    cards.forEach(card => {
        cardObserver.observe(card);
    });
    
    // ========================================
    // KEYBOARD SHORTCUTS
    // ========================================
    
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-box input');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to close modals and mobile menu
        if (e.key === 'Escape') {
            closeMobileMenu();
        }
    });
    
    // ========================================
    // VIEW MESSAGE FUNCTION (for Messages Tab)
    // ========================================
    
    window.viewMessage = function(messageId) {
        const modal = new bootstrap.Modal(document.getElementById('viewMessageModal'));
        const modalTitle = document.getElementById('viewMessageModalLabel');
        const modalBody = document.getElementById('viewMessageModalBody');
        
        modalTitle.textContent = 'Loading...';
        modalBody.textContent = 'Please wait while the message loads.';
        modal.show();
        
        fetch(`get_message.php?id=${messageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalTitle.textContent = data.subject;
                    modalBody.innerHTML = `
                        <p><strong>From:</strong> ${data.sender_name} (${data.sender_role})</p>
                        <p><strong>Sent:</strong> ${data.sent_at}</p>
                        <hr>
                        <p>${data.message.replace(/\n/g, '<br>')}</p>
                    `;
                } else {
                    modalTitle.textContent = 'Error';
                    modalBody.textContent = 'Failed to load message.';
                }
            })
            .catch(() => {
                modalTitle.textContent = 'Error';
                modalBody.textContent = 'Failed to load message.';
            });
    };
    
    // ========================================
    // PRINT FUNCTIONALITY
    // ========================================
    
    window.printSection = function(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print</title>');
            printWindow.document.write('<link rel="stylesheet" href="admin_dashboard.css">');
            printWindow.document.write('</head><body>');
            printWindow.document.write(section.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    };
    
    // ========================================
    // INITIALIZATION COMPLETE
    // ========================================
    
    console.log('Dashboard responsive features initialized successfully');
});