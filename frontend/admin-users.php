<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin access
requireAdmin();

// Generate CSRF token for POST requests
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/v/bs5/dt-2.1.8/r-3.0.3/datatables.min.css" rel="stylesheet">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    
    <style>
        .topbar .btn-icon { color: #555; }
        .topbar .btn-icon:hover { color: var(--primary); }

        /* Table tweaks */
        table.dataTable tbody tr:hover { background-color: #f2f6ff; }
        .badge-active { background-color: #28a745; }
        .badge-suspended { background-color: #dc3545; }

        /* Mobile sidebar */
        @media (max-width: 992px) {
            .sidebar { position: fixed; transform: translateX(-100%); transition: transform .25s ease; z-index: 1040; }
            .sidebar.show { transform: translateX(0); }
            .content { margin-left: 0; }
        }

        #loadingOverlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body class="bg-light">
    <div id="loadingOverlay" class="d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div class="page-header py-3 bg-white shadow-sm mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-users fs-3 text-primary me-3"></i>
                    <h1 class="m-0 fs-4">User Management</h1>
                </div>
                <div>
                    <a href="admin-dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button onclick="logout()" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Top nav for legacy admin if needed -->
        <nav class="nav nav-pills mb-4 admin-nav">
            <a class="nav-link" href="admin-dashboard.php">Dashboard</a>
            <a class="nav-link" href="owner-list.php">Manage Owners</a>
            <a class="nav-link" href="vehicle-list.php">Manage Vehicles</a>
            <a class="nav-link" href="manage-vehicle-status.php">Vehicle Status</a>
            <a class="nav-link" href="manage-disk-numbers.php">Disk Numbers</a>
            <a class="nav-link" href="admin_reports.php">Reports</a>
            <a class="nav-link active" href="admin-users.php">User View</a>
        </nav>

        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value" id="totalUsers">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Users</div>
                <div class="stat-value" id="activeUsers">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Students</div>
                <div class="stat-value" id="studentUsers">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Staff</div>
                <div class="stat-value" id="staffUsers">0</div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h3 class="card-title m-0 fs-5"><i class="fas fa-list me-2"></i> User Directory</h3>
                <div class="d-flex gap-3">
                    <select id="filterType" class="form-select" style="min-width: 180px">
                        <option value="">All Registrant Types</option>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                    </select>
                    <select id="filterStatus" class="form-select" style="min-width: 160px">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button id="btnRefresh" class="btn btn-primary"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover w-100">
                        <thead class="table-light">
                            <tr>
                                <th>User ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registrant Type</th>
                                <th>Vehicles</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" id="editFullName" name="fullName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="editEmail" name="Email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" id="editPhone" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Registrant Type</label>
                            <select id="editRegistrantType" name="registrantType" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="staff">Staff</option>
                                <option value="guest">Guest</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS, DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-2.1.8/r-3.0.3/datatables.min.js"></script>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let usersTable;

        const showLoading = (show) => {
            document.getElementById('loadingOverlay').classList.toggle('d-none', !show);
        }

        function logout() { 
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php'; 
            }
        }

        function initTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        }

        const apiPath = (path) => `/backend/${String(path).replace(/^\/+/, '')}`;

        async function fetchUsers() {
            showLoading(true);
            const type = document.getElementById('filterType').value;
            const status = document.getElementById('filterStatus').value;
            const url = new URL(apiPath('get_users.php'), window.location.origin);
            if (type) url.searchParams.set('type', type);
            if (status) url.searchParams.set('status', status);
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            showLoading(false);
            
            const usersList = data.users || [];
            document.getElementById('totalUsers').textContent = usersList.length;
            document.getElementById('activeUsers').textContent = usersList.filter(u => (u.status || 'active').toLowerCase() !== 'suspended').length;
            document.getElementById('studentUsers').textContent = usersList.filter(u => (u.registrantType || '').toLowerCase() === 'student').length;
            document.getElementById('staffUsers').textContent = usersList.filter(u => (u.registrantType || '').toLowerCase() === 'staff').length;
            
            return usersList;
        }

        function statusBadge(status) {
            const s = (status || 'active').toLowerCase();
            return s === 'suspended'
                ? '<span class="badge bg-danger badge-suspended">Suspended</span>'
                : '<span class="badge bg-success badge-active">Active</span>'
        }

        function actionButtons(row) {
            const suspendAction = row.status?.toLowerCase() === 'suspended' ? 'Activate' : 'Suspend';
            return `
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Details" onclick="onView(${row.applicant_id})"><i class="fa fa-eye"></i></button>
                    <button class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit User" onclick="onEdit(${row.applicant_id})"><i class="fa fa-pen"></i></button>
                    <button class="btn btn-outline-warning" data-bs-toggle="tooltip" title="${suspendAction}" onclick="onToggleSuspend(${row.applicant_id})"><i class="fa fa-user-slash"></i></button>
                    <button class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Delete" onclick="onDelete(${row.applicant_id})"><i class="fa fa-trash"></i></button>
                </div>
            `;
        }

        async function loadTable() {
            const users = await fetchUsers();
            if (usersTable) {
                usersTable.clear();
                usersTable.rows.add(users);
                usersTable.draw();
                initTooltips();
                return;
            }
            usersTable = new DataTable('#usersTable', {
                data: users,
                responsive: true,
                columns: [
                    { data: 'applicant_id' },
                    { data: 'fullName', render: d => d || '<span class="text-muted">N/A</span>' },
                    { data: 'Email', render: d => d || '<span class="text-muted">N/A</span>' },
                    { data: 'phone', render: d => d || '<span class="text-muted">N/A</span>' },
                    { data: 'registrantType', render: (d) => (d||'').toString().charAt(0).toUpperCase() + (d||'').toString().slice(1) },
                    { data: 'vehicles_count', render: d => d || '0' },
                    { data: 'registration_date', render: d => d || '<span class="text-muted">N/A</span>' },
                    { data: null, render: (row) => statusBadge(row.status) },
                    { data: null, orderable: false, searchable: false, render: (row) => actionButtons(row) },
                ],
                order: [[6, 'desc']],
                pageLength: 10,
                language: {
                    emptyTable: "No users found matching your query."
                }
            });
            initTooltips();
        }

        async function onView(userId) {
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            document.getElementById('viewContent').innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary"></div></div>';
            modal.show();
            const res = await fetch(apiPath('view_user.php') + '?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) {
                document.getElementById('viewContent').innerHTML = '<div class="alert alert-danger">Failed to load user details.</div>';
                return;
            }
            const u = data.user;
            const vehiclesHtml = (u.vehicles || []).map(v => `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>${v.plate || v.regNumber}</strong> — ${v.make || ''}</span>
                    <span class="badge ${v.status?.toLowerCase() === 'active' ? 'bg-success' : 'bg-secondary'}">${(v.status||'').toString().toUpperCase()}</span>
                </li>`).join('');
            document.getElementById('viewContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2"><strong class="text-muted">Full Name:</strong><br/> ${u.fullName || 'N/A'}</div>
                        <div class="mb-2"><strong class="text-muted">Email:</strong><br/> ${u.Email || 'N/A'}</div>
                        <div class="mb-2"><strong class="text-muted">Phone:</strong><br/> ${u.phone || 'N/A'}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><strong class="text-muted">Registrant Type:</strong><br/> ${(u.registrantType||'').toString().toUpperCase()}</div>
                        <div class="mb-2"><strong class="text-muted">Status:</strong><br/> ${statusBadge(u.status)}</div>
                        <div class="mb-2"><strong class="text-muted">Registered Vehicles:</strong><br/> ${u.vehicles_count || (u.vehicles ? u.vehicles.length : 0)}</div>
                    </div>
                </div>
                <hr/>
                <h6 class="text-primary mb-3">Vehicles</h6>
                <ul class="list-group mb-3">${vehiclesHtml || '<li class="list-group-item text-muted text-center py-3">No vehicles registered</li>'}</ul>
                <h6 class="text-primary mb-2">Registration Info</h6>
                <div class="text-muted small">First registration: ${u.registration_date || 'N/A'}</div>
            `;
        }

        async function onEdit(userId) {
            showLoading(true);
            const res = await fetch(apiPath('view_user.php') + '?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            showLoading(false);
            if (!data.success) return alert('Failed to load user');
            const u = data.user;
            document.getElementById('editUserId').value = u.applicant_id;
            document.getElementById('editFullName').value = u.fullName || '';
            document.getElementById('editEmail').value = u.Email || '';
            document.getElementById('editPhone').value = u.phone || '';
            document.getElementById('editRegistrantType').value = (u.registrantType || '').toLowerCase();
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const body = new URLSearchParams(new FormData(form));
            showLoading(true);
            const res = await fetch(apiPath('update_user.php'), {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
                body
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Update failed');
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            await loadTable();
        });

        async function onToggleSuspend(userId) {
            const confirmText = 'Toggle user status (Suspend/Activate)?';
            if (!confirm(confirmText)) return;
            showLoading(true);
            const res = await fetch(apiPath('update_user.php'), {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&user_id=${encodeURIComponent(userId)}`
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Operation failed');
            await loadTable();
        }

        async function onDelete(userId) {
            if (!confirm('Delete this user? This cannot be undone.')) return;
            showLoading(true);
            const res = await fetch(apiPath('delete_user.php'), {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete&user_id=${encodeURIComponent(userId)}`
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Delete failed');
            await loadTable();
        }

        document.getElementById('btnRefresh').addEventListener('click', loadTable);
        document.getElementById('filterType').addEventListener('change', loadTable);
        document.getElementById('filterStatus').addEventListener('change', loadTable);

        document.addEventListener('DOMContentLoaded', loadTable);
    </script>
</body>
</html>
