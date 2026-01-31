// Admin Dashboard JavaScript - Manual Implementation
console.log("Admin dashboard JS loaded");

// Sidebar Navigation
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar navigation highlighting
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active class from all links
            sidebarLinks.forEach(l => l.parentElement.classList.remove('active'));
            // Add active class to clicked link
            this.parentElement.classList.add('active');
        });
    });

    // Initialize table search
    initTableSearch();

    // Initialize charts
    initCharts();

    // Action buttons functionality
    initActionButtons();

    // Export buttons
    initExportButtons();

    // Add hover effects to stat cards
    initStatCardEffects();

    // Activity list animations
    initActivityAnimations();
});

function initTableSearch() {
    // User table search
    const userSearchInput = document.getElementById('userSearch');
    const usersTable = document.getElementById('usersTable');
    if (userSearchInput && usersTable) {
        userSearchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = usersTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    // Course table search
    const courseSearchInput = document.getElementById('courseSearch');
    const coursesTable = document.getElementById('coursesTable');
    if (courseSearchInput && coursesTable) {
        courseSearchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = coursesTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
}

// Manual Chart Implementation using Canvas
function initCharts() {
    // User Growth Chart (Line Chart)
    const userGrowthCanvas = document.getElementById('userGrowthChart');
    if (userGrowthCanvas) {
        const ctx = userGrowthCanvas.getContext('2d');
        userGrowthCanvas.width = userGrowthCanvas.parentElement.offsetWidth - 40;
        userGrowthCanvas.height = 300;
        drawLineChart(ctx, userGrowthCanvas.width, userGrowthCanvas.height, {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            data: [1200, 1350, 1480, 1620, 1780, 1950],
            color: '#3b82f6',
            backgroundColor: '#f9fafb'
        });
    }

    // Course Enrollment Chart (Bar Chart)
    const courseEnrollmentCanvas = document.getElementById('courseEnrollmentChart');
    if (courseEnrollmentCanvas) {
        const ctx = courseEnrollmentCanvas.getContext('2d');
        courseEnrollmentCanvas.width = courseEnrollmentCanvas.parentElement.offsetWidth - 40;
        courseEnrollmentCanvas.height = 300;
        drawBarChart(ctx, courseEnrollmentCanvas.width, courseEnrollmentCanvas.height, {
            labels: ['CS101', 'Math', 'Physics', 'Chem', 'Bio'],
            data: [45, 38, 52, 29, 41],
            color: '#10b981',
            backgroundColor: '#f9fafb'
        });
    }
}

function drawLineChart(ctx, width, height, config) {
    const padding = 50;
    const chartWidth = width - 2 * padding;
    const chartHeight = height - 2 * padding;

    // Clear canvas
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = config.backgroundColor;
    ctx.fillRect(0, 0, width, height);

    // Find max value
    const maxValue = Math.max(...config.data);

    // Draw grid lines
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();

        // Y-axis labels
        ctx.fillStyle = '#6b7280';
        ctx.font = '12px Arial';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round((maxValue / 5) * (5 - i)).toString(), padding - 10, y + 4);
    }

    // Draw axes
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // Draw line
    ctx.strokeStyle = config.color;
    ctx.lineWidth = 3;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    ctx.beginPath();

    config.data.forEach((value, index) => {
        const x = padding + (chartWidth / (config.data.length - 1)) * index;
        const y = padding + chartHeight - (value / maxValue) * chartHeight;

        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }

        // Draw point
        ctx.fillStyle = config.color;
        ctx.beginPath();
        ctx.arc(x, y, 5, 0, 2 * Math.PI);
        ctx.fill();

        // Point shadow
        ctx.shadowColor = config.color;
        ctx.shadowBlur = 10;
        ctx.shadowOffsetX = 2;
        ctx.shadowOffsetY = 2;
        ctx.fill();
        ctx.shadowColor = 'transparent';
    });

    ctx.stroke();

    // Draw labels
    ctx.fillStyle = '#374151';
    ctx.font = 'bold 12px Arial';
    ctx.textAlign = 'center';
    config.labels.forEach((label, index) => {
        const x = padding + (chartWidth / (config.labels.length - 1)) * index;
        ctx.fillText(label, x, height - 10);
    });
}

function drawBarChart(ctx, width, height, config) {
    const padding = 50;
    const chartWidth = width - 2 * padding;
    const chartHeight = height - 2 * padding;

    // Clear canvas
    ctx.clearRect(0, 0, width, height);
    ctx.fillStyle = config.backgroundColor;
    ctx.fillRect(0, 0, width, height);

    // Find max value
    const maxValue = Math.max(...config.data);
    const barCount = config.data.length;
    const barWidth = chartWidth / barCount * 0.7;
    const barSpacing = chartWidth / barCount * 0.3;

    // Draw grid lines
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
        const y = padding + (chartHeight / 5) * i;
        ctx.beginPath();
        ctx.moveTo(padding, y);
        ctx.lineTo(width - padding, y);
        ctx.stroke();
    }

    // Draw bars
    config.data.forEach((value, index) => {
        const x = padding + (chartWidth / barCount) * index + barSpacing / 2;
        const barHeight = (value / maxValue) * chartHeight;
        const y = padding + chartHeight - barHeight;

        // Bar gradient
        const gradient = ctx.createLinearGradient(x, y, x + barWidth, y + barHeight);
        gradient.addColorStop(0, config.color);
        gradient.addColorStop(1, config.color + '80');

        ctx.fillStyle = gradient;
        ctx.fillRect(x, y, barWidth, barHeight);

        // Bar border
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 1;
        ctx.strokeRect(x, y, barWidth, barHeight);

        // Value label on top
        ctx.fillStyle = '#374151';
        ctx.font = 'bold 12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(value.toString(), x + barWidth / 2, y - 10);
    });

    // Draw axes
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // Draw labels
    ctx.fillStyle = '#374151';
    ctx.font = 'bold 12px Arial';
    ctx.textAlign = 'center';
    config.labels.forEach((label, index) => {
        const x = padding + (chartWidth / barCount) * index + barWidth / 2;
        ctx.fillText(label, x, height - 15);
    });
}



function initActionButtons() {
    const actionButtons = document.querySelectorAll('button.action-btn:not([data-bs-toggle])');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const action = this.querySelector('span')?.textContent || 'Action';

            // Create modal
            showActionModal(action);
        });
    });
}

function showActionModal(action) {
    // Remove existing modal if any
    const existingModal = document.querySelector('.action-modal');
    if (existingModal) existingModal.remove();

    // Create modal
    const modal = document.createElement('div');
    modal.className = 'action-modal';
    modal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${action}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>This feature is under development. ${action} functionality will be available soon.</p>
                    <div class="form-group">
                        <label>Enter details:</label>
                        <input type="text" placeholder="Type here..." class="modal-input">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary modal-cancel">Cancel</button>
                    <button class="btn-primary modal-confirm">${action}</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Modal functionality
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = modal.querySelector('.modal-cancel');
    const confirmBtn = modal.querySelector('.modal-confirm');
    const overlay = modal.querySelector('.modal-overlay');

    function closeModal() {
        modal.remove();
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    confirmBtn.addEventListener('click', function() {
        alert(`${action} action performed successfully!`);
        closeModal();
    });

    // Animate modal in
    setTimeout(() => {
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
        modal.querySelector('.modal-content').style.opacity = '1';
    }, 10);
}

function initExportButtons() {
    const exportCSVBtn = document.getElementById('exportCSV');
    const exportPDFBtn = document.getElementById('exportPDF');
    
    if (exportCSVBtn) {
        exportCSVBtn.addEventListener('click', function() {
            // Simulate CSV export
            const csvContent = 'data:text/csv;charset=utf-8,' +
                'Metric,Value\n' +
                'Total Users,' + document.querySelector('.stat-info h3').textContent + '\n' +
                'Active Courses,' + document.querySelectorAll('.stat-info h3')[1].textContent + '\n' +
                'Faculty Members,' + document.querySelectorAll('.stat-info h3')[2].textContent + '\n' +
                'Students,' + document.querySelectorAll('.stat-info h3')[3].textContent;
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'reports.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    if (exportPDFBtn) {
        exportPDFBtn.addEventListener('click', function() {
            // Simulate PDF export (in real app, use jsPDF)
            alert('PDF export functionality would generate a report PDF with charts and stats. For now, this is a placeholder.');
            // Example with jsPDF if library is included:
            // const { jsPDF } = window.jspdf;
            // const doc = new jsPDF();
            // doc.text('Reports', 10, 10);
            // doc.save('reports.pdf');
        });
    }
}

// Stat Card Effects
function initStatCardEffects() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });
}

// Activity Animations
function initActivityAnimations() {
    const activityItems = document.querySelectorAll('.activity-item');

    // Stagger animation on load
    activityItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';

        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
    });

    // Add click effect
    activityItems.forEach(item => {
        item.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
}
