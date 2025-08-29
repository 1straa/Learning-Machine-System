document.addEventListener("DOMContentLoaded", () => {
  const API = {
    list: "api/users.php?action=list",
    create: "api/users.php?action=create",
    update: (id) => `api/users.php?action=update&id=${id}`,
    delete: (id) => `api/users.php?action=delete&id=${id}`,
  };

  const searchInput = document.querySelector(".user-search");
  const roleFilter = document.querySelector(".user-filter");
  const statusFilter = document.querySelector(".user-status");
  const tbody = document.querySelector(".users-table tbody");

  // Stats
  const totalUsersEl = document.getElementById("total-users");
  const totalTeachersEl = document.getElementById("total-teachers");
  const totalStudentsEl = document.getElementById("total-students");
  const totalCoursesEl = document.getElementById("total-courses");
  const totalActiveEl = document.getElementById("total-active");
  const pctTeachersEl = document.getElementById("pct-teachers");
  const pctStudentsEl = document.getElementById("pct-students");

  // Modal
  const modal = document.getElementById("createUserModal");
  const openBtn = document.getElementById("openCreateModal");
  const closeBtn = document.getElementById("closeCreateModal");
  const form = document.getElementById("createUserForm");

  openBtn.addEventListener("click", () => { modal.style.display = "flex"; });
  closeBtn.addEventListener("click", () => { modal.style.display = "none"; });
  window.addEventListener("click", (e) => { if (e.target === modal) modal.style.display = "none"; });

  // Helpers
  const fmt = (d) => d ? new Date(d).toLocaleDateString() : "—";
  const initialsOf = (name) => name.split(/\s+/).map(n => n[0]).join("").toUpperCase().slice(0,2);
  const avatarClass = (role) => role === "admin" ? "blue" : (role === "teacher" ? "purple" : "green");

  function renderRow(u) {
    const tr = document.createElement("tr");
    tr.dataset.id = u.id;
    tr.innerHTML = `
      <td>
        <div class="user-info">
          <div class="user-avatar ${avatarClass(u.role)}">${initialsOf(u.name)}</div>
          <div>
            <div class="user-name">${u.name}</div>
            <div class="user-email">${u.email}</div>
          </div>
        </div>
      </td>
      <td><span class="role-badge ${u.role}">${u.role}</span></td>
      <td><span class="status-badge ${u.status}">${u.status}</span></td>
      <td>${fmt(u.last_login)}</td>
      <td>${fmt(u.created_at)}</td>
      <td>
        <button class="action-btn view" title="View" aria-label="View">👁️</button>
        <button class="action-btn reset" title="Reset Password" aria-label="Reset Password">♻️</button>
        <button class="action-btn delete" title="Delete" aria-label="Delete">❌</button>
      </td>
    `;
    return tr;
  }

  function updateStats() {
    const rows = Array.from(tbody.querySelectorAll("tr")).filter(r => r.style.display !== "none");
    const totalUsers = rows.length;
    let teachers = 0, students = 0, active = 0;
    rows.forEach(row => {
      const role = row.querySelector(".role-badge").textContent.toLowerCase();
      const status = row.querySelector(".status-badge").textContent.toLowerCase();
      if (role === "teacher") teachers++;
      if (role === "student") students++;
      if (status === "active") active++;
    });
    totalUsersEl.textContent = totalUsers;
    totalTeachersEl.textContent = teachers;
    totalStudentsEl.textContent = students;
    totalActiveEl.textContent = `${active} active`;
    const pctT = totalUsers ? Math.round((teachers/totalUsers)*100) : 0;
    const pctS = totalUsers ? Math.round((students/totalUsers)*100) : 0;
    pctTeachersEl.textContent = `${pctT}% of users`;
    pctStudentsEl.textContent = `${pctS}% of users`;
    totalCoursesEl.textContent = teachers * 2; // demo logic
  }

  function attachRowEvents(tr) {
    const id = tr.dataset.id;
    tr.querySelector(".action-btn.view").onclick = () => {
      const name = tr.querySelector(".user-name").textContent;
      alert(`Viewing profile of ${name}`);
    };
    tr.querySelector(".action-btn.reset").onclick = async () => {
      const newPass = prompt("Enter new temporary password (leave blank to cancel):", "password123");
      if (!newPass) return;
      await fetch(API.update(id), {
        method: "PUT",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({ password: newPass }),
      });
      alert("Temporary password set.");
    };
    tr.querySelector(".action-btn.delete").onclick = async () => {
      if (!confirm("Delete this user?")) return;
      const res = await fetch(API.delete(id), { method: "DELETE" });
      const json = await res.json();
      if (json.ok) {
        tr.remove();
        updateStats();
      } else {
        alert(json.error || "Delete failed");
      }
    };
  }

  function applyFilters() {
    const search = (searchInput.value || "").toLowerCase();
    const role = roleFilter.value.toLowerCase();
    const status = statusFilter.value.toLowerCase();

    tbody.querySelectorAll("tr").forEach(row => {
      const name = row.querySelector(".user-name").textContent.toLowerCase();
      const email = row.querySelector(".user-email").textContent.toLowerCase();
      const userRole = row.querySelector(".role-badge").textContent.toLowerCase();
      const userStatus = row.querySelector(".status-badge").textContent.toLowerCase();

      const matchesSearch = name.includes(search) || email.includes(search);
      const roleMatch = (role === "all roles" || role === userRole);
      const statusMatch = (status === "all status" || status === userStatus);

      row.style.display = (matchesSearch && roleMatch && statusMatch) ? "" : "none";
    });
    updateStats();
  }
  searchInput.addEventListener("keyup", applyFilters);
  roleFilter.addEventListener("change", applyFilters);
  statusFilter.addEventListener("change", applyFilters);

  async function loadUsers() {
    tbody.innerHTML = "<tr><td colspan='6'>Loading…</td></tr>";
    const res = await fetch(API.list);
    const json = await res.json();
    if (!json.ok) {
      tbody.innerHTML = `<tr><td colspan='6'>Failed to load users.</td></tr>`;
      return;
    }
    tbody.innerHTML = "";
    json.data.forEach(u => {
      const tr = renderRow(u);
      tbody.appendChild(tr);
      attachRowEvents(tr);
    });
    updateStats();
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = document.getElementById("newName").value.trim();
    const email = document.getElementById("newEmail").value.trim();
    const role = document.getElementById("newRole").value;
    const status = document.getElementById("newStatus").value;
    const password = document.getElementById("newPassword").value.trim();

    const res = await fetch(API.create, {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({ name, email, role, status, password }),
    });
    const json = await res.json();
    if (!json.ok) {
      alert(json.error || "Create failed");
      return;
    }
    const tr = renderRow(json.data);
    tbody.prepend(tr);
    attachRowEvents(tr);
    updateStats();
    form.reset();
    modal.style.display = "none";
  });

  // Kick things off
  loadUsers();
});