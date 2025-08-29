<?php
// admin.php — Admin dashboard (frontend) that talks to api/users.php
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="Admin.css" />
</head>
<body>
  <header class="admin-header">
    <div class="header-left">
      <div class="logo">
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24">
          <rect x="2" y="7" width="20" height="10" rx="5" fill="#217a2b" />
          <path d="M2 7l10 6 10-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <div>
          <div class="logo-title">I, acad sikatayo Learning Management System</div>
          <div class="logo-subtitle">Admin Portal</div>
        </div>
      </div>
    </div>
    <div class="header-right">
      <button class="icon-btn" title="Notifications" id="btnNotifications" aria-label="Notifications">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24">
          <path stroke="#888" stroke-width="2" d="M12 22a2 2 0 0 0 2-2H10a2 2 0 0 0 2 2Zm6-6V9a6 6 0 1 0-12 0v7l-2 2v1h16v-1l-2-2Z" />
        </svg>
      </button>
      <div class="profile-info">
        <div class="profile-avatar">
          <svg width="32" height="32" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4" fill="#217a2b" />
            <path d="M4 20c0-2.21 3.58-4 8-4s8 1.79 8 4" stroke="#217a2b" stroke-width="2" />
          </svg>
        </div>
        <div>
          <div class="profile-name">System Administrator</div>
          <div class="profile-email">admin@demo.com</div>
        </div>
      </div>
      <button class="icon-btn" title="Settings" aria-label="Settings">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3" stroke="#888" stroke-width="2" />
          <path stroke="#888" stroke-width="2" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 8.6 15a1.65 1.65 0 0 0-1.82-.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 15 8.6a1.65 1.65 0 0 0 1.82.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 15Z" />
        </svg>
      </button>
      <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout">
  <svg width="22" height="22" fill="none" viewBox="0 0 24 24">
    <path stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
      d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1" />
  </svg>
</a>
    </div>
  </header>

  <main class="admin-main">
    <div class="admin-title-row">
      <div>
        <h1 class="admin-title">Admin Dashboard</h1>
        <div class="admin-desc">Manage users, courses, and system settings</div>
      </div>
      <button class="create-user-btn" id="openCreateModal">+ Create User</button>
    </div>

    <div class="admin-stats">
      <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value" id="total-users">0</div>
        <div class="stat-sub green" id="total-active">0 active</div>
        <div class="stat-icon stat-users">
          <svg width="28" height="28" fill="none" viewBox="0 0 24 24">
            <circle cx="8" cy="8" r="4" fill="#2563eb" />
            <circle cx="16" cy="8" r="4" fill="#2563eb" fill-opacity="0.5" />
            <path d="M2 20c0-2.21 3.58-4 8-4s8 1.79 8 4" stroke="#2563eb" stroke-width="2" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Teachers</div>
        <div class="stat-value" id="total-teachers">0</div>
        <div class="stat-sub red" id="pct-teachers">0% of users</div>
        <div class="stat-icon stat-teachers">
          <svg width="28" height="28" fill="none" viewBox="0 0 24 24">
            <rect x="4" y="4" width="16" height="16" rx="4" fill="#6366f1" />
            <path d="M8 12h8M8 16h8" stroke="#fff" stroke-width="2" stroke-linecap="round" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Students</div>
        <div class="stat-value" id="total-students">0</div>
        <div class="stat-sub red" id="pct-students">0% of users</div>
        <div class="stat-icon stat-students">
          <svg width="28" height="28" fill="none" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4" fill="#22c55e" />
            <path d="M4 20c0-2.21 3.58-4 8-4s8 1.79 8 4" stroke="#22c55e" stroke-width="2" />
          </svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Courses</div>
        <div class="stat-value" id="total-courses">0</div>
        <div class="stat-sub orange">Active courses</div>
        <div class="stat-icon stat-courses">
          <svg width="28" height="28" fill="none" viewBox="0 0 24 24">
            <rect x="4" y="4" width="16" height="16" rx="4" fill="#f59e42" />
            <path d="M8 8h8v8H8z" stroke="#fff" stroke-width="2" />
          </svg>
        </div>
      </div>
    </div>

    <section class="admin-users">
      <div class="users-header">
        <h2>User Management</h2>
        <div class="users-controls">
          <input type="text" class="user-search" placeholder="Search users..." />
          <select class="user-filter">
            <option>All Roles</option>
            <option>Admin</option>
            <option>Teacher</option>
            <option>Student</option>
          </select>
          <select class="user-status">
            <option>All Status</option>
            <option>Active</option>
            <option>Inactive</option>
          </select>
        </div>
      </div>
      <div class="users-table-wrap">
        <table class="users-table">
          <thead>
            <tr>
              <th>USER</th>
              <th>ROLE</th>
              <th>STATUS</th>
              <th>LAST LOGIN</th>
              <th>CREATED</th>
              <th>ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <!-- Rows injected by admin.js -->
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="admin.js"></script>

  <div class="modal" id="createUserModal">
    <div class="modal-content">
      <span class="close-btn" id="closeCreateModal">&times;</span>
      <h2>Create New User</h2>
      <form id="createUserForm">
        <label>Name:</label>
        <input type="text" id="newName" required />

        <label>Email:</label>
        <input type="email" id="newEmail" required />

        <label>Role:</label>
        <select id="newRole" required>
          <option value="admin">Admin</option>
          <option value="teacher">Teacher</option>
          <option value="student">Student</option>
        </select>

        <label>Status:</label>
        <select id="newStatus" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>

        <label>Temp Password (optional):</label>
        <input type="text" id="newPassword" placeholder="password123" />

        <button type="submit" class="create-user-btn">Create</button>
      </form>
    </div>
  </div>
</body>
</html>
