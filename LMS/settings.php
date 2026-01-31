<?php
session_start();
include 'config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$errors = [];
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$current_password || !$new_password || !$confirm_password) {
        $errors[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_new_password, $_SESSION['user_id']);
            if ($update_stmt->execute()) {
                $success = "Password changed successfully.";
            } else {
                $errors[] = "Failed to change password.";
            }
            $update_stmt->close();
        } else {
            $errors[] = "Current password is incorrect.";
        }
        $stmt->close();
    }
}

// Handle system settings (placeholder for now)
$system_settings = [
    'site_name' => 'I-Acadsikatayo LMS',
    'admin_email' => 'admin@lms.com',
    'allow_registration' => true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $site_name = trim($_POST['site_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $allow_registration = isset($_POST['allow_registration']);

    if (!$site_name) {
        $errors[] = "Site name is required.";
    }
    if (!$admin_email || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid admin email is required.";
    }

    if (empty($errors)) {
        // In a real system, save to DB or config file
        $system_settings['site_name'] = $site_name;
        $system_settings['admin_email'] = $admin_email;
        $system_settings['allow_registration'] = $allow_registration;
        $success = "System settings updated successfully.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>System Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    />
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <h3>Admin Panel</h3>
                        <p>I-Acadsikatayo: Learning Management System</p>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php?tab=overview"><i class="fas fa-chart-bar"></i><span>Overview</span></a></li>
                    <li><a href="admin_dashboard.php?tab=users"><i class="fas fa-users"></i><span>User Management</span></a></li>
                    <li><a href="admin_dashboard.php?tab=courses"><i class="fas fa-book"></i><span>Course Management</span></a></li>
                    <li><a href="admin_dashboard.php?tab=reports"><i class="fas fa-file-text"></i><span>Reports</span></a></li>
                    <li class="active"><a href="settings.php"><i class="fas fa-cog"></i><span>System Settings</span></a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>System Settings</h1>
                </div>
                <div class="header-right">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </header>
            <div class="content">
                <div class="settings-container">
                    <!-- General Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-cogs"></i> General Settings</h3>
                        <form method="POST" id="generalSettingsForm">
                            <input type="hidden" name="update_settings" value="1" />
                            <input type="hidden" id="hidden_site_name" name="site_name" value="<?= htmlspecialchars($system_settings['site_name']) ?>" />
                            <input type="hidden" id="hidden_admin_email" name="admin_email" value="<?= htmlspecialchars($system_settings['admin_email']) ?>" />
                            <div class="form-group">
                                <label>Site Name:</label>
                                <span class="editable-field" data-field="site_name" data-value="<?= htmlspecialchars($system_settings['site_name']) ?>"><?= htmlspecialchars($system_settings['site_name']) ?></span>
                            </div>
                            <div class="form-group">
                                <label>Admin Email:</label>
                                <span class="editable-field" data-field="admin_email" data-value="<?= htmlspecialchars($system_settings['admin_email']) ?>"><?= htmlspecialchars($system_settings['admin_email']) ?></span>
                            </div>
                            <div class="form-group">
                                <label>Allow User Registration:</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="allow_registration" name="allow_registration" <?= $system_settings['allow_registration'] ? 'checked' : '' ?> />
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="btn-primary">Save General Settings</button>
                        </form>
                    </div>

                    <!-- Security Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                        <div class="form-group">
                            <label>Change Password:</label>
                            <button type="button" class="btn-change-password" id="changePasswordBtn">Change Password</button>
                        </div>
                    </div>
                </div>

                <!-- Password Change Modal -->
                <div class="password-modal" id="passwordModal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" id="closeModal">&times;</span>
                        <h3>Change Password</h3>
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="change_password" value="1" />
                            <div class="form-group">
                                <label for="current_password">Current Password:</label>
                                <input type="password" id="current_password" name="current_password" required />
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password:</label>
                                <input type="password" id="new_password" name="new_password" required />
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password:</label>
                                <input type="password" id="confirm_password" name="confirm_password" required />
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" id="cancelBtn">Cancel</button>
                                <button type="submit" class="btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($errors): ?>
                    <div class="error-messages" style="color: red; margin-top: 2rem;">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message" style="color: green; margin-top: 2rem;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Editable fields functionality
            const editableFields = document.querySelectorAll('.editable-field');
            editableFields.forEach(field => {
                field.addEventListener('click', function() {
                    const originalValue = this.textContent;
                    const fieldName = this.dataset.field;
                    const input = document.createElement('input');
                    input.type = fieldName === 'admin_email' ? 'email' : 'text';
                    input.value = originalValue;
                    input.className = 'editable-input';
                    input.style.width = '100%';
                    input.style.padding = '0.5rem';
                    input.style.border = '1px solid #dc2626';
                    input.style.borderRadius = '0.375rem';
                    input.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';

                    this.classList.add('editing');
                    this.innerHTML = '';
                    this.appendChild(input);
                    input.focus();

                    const saveEdit = () => {
                        const newValue = input.value.trim();
                        if (newValue && newValue !== originalValue) {
                            // Here you would send an AJAX request to save the value
                            // For now, just update the display
                            this.textContent = newValue;
                            this.dataset.value = newValue;
                            // Simulate save
                            console.log(`Saving ${fieldName}: ${newValue}`);
                        } else {
                            this.textContent = originalValue;
                        }
                        this.classList.remove('editing');
                    };

                    input.addEventListener('blur', saveEdit);
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            saveEdit();
                        } else if (e.key === 'Escape') {
                            this.value = originalValue;
                            saveEdit();
                        }
                    });
                });
            });

            // Toggle switch functionality
            const toggleSwitch = document.getElementById('allow_registration');
            toggleSwitch.addEventListener('change', function() {
                const isChecked = this.checked;
                // Here you would send an AJAX request to save the setting
                console.log(`Allow registration: ${isChecked}`);
            });

            // Password modal functionality
            const changePasswordBtn = document.getElementById('changePasswordBtn');
            const passwordModal = document.getElementById('passwordModal');
            const closeModal = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');

            changePasswordBtn.addEventListener('click', function() {
                passwordModal.style.display = 'flex';
            });

            closeModal.addEventListener('click', function() {
                passwordModal.style.display = 'none';
            });

            cancelBtn.addEventListener('click', function() {
                passwordModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target === passwordModal) {
                    passwordModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
