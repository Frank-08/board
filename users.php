<?php
require_once __DIR__ . '/includes/header.php';

// Require admin role for this page
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

outputHeader('User Management', 'users.php');
?>

        <main>
            <div class="page-header">
                <h2>User Management</h2>
                <button onclick="showUserModal()" class="btn btn-primary">+ Add User</button>
            </div>

            <div id="users-list">
                <p>Loading users...</p>
            </div>
        </main>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeUserModal()">&times;</span>
            <h2 id="modalTitle">New User</h2>
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" required>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label for="password">Password *</label>
                    <input type="password" id="password">
                    <small id="passwordHint" style="color: #666; display: none;">Leave blank to keep existing password</small>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" required>
                        <option value="Viewer">Viewer (Read-only)</option>
                        <option value="Member">Member (View + limited edit)</option>
                        <option value="Clerk">Clerk (Manage meetings & agendas)</option>
                        <option value="Admin">Admin (Full access)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="boardMemberId">Link to Member (Optional)</label>
                    <select id="boardMemberId">
                        <option value="">Not linked to a member</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="isActive" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="isActive" checked>
                        Active
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Save User</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        
        let allBoardMembers = [];

        window.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            loadBoardMembers();
        });

        function loadUsers() {
            fetch('api/users.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('users-list');
                    if (data.error) {
                        list.innerHTML = '<p style="color: red;">Error: ' + data.error + '</p>';
                        return;
                    }
                    if (data.length === 0) {
                        list.innerHTML = '<p>No users found.</p>';
                        return;
                    }
                    
                    list.innerHTML = `
                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <thead>
                                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                    <th style="padding: 12px; text-align: left;">Username</th>
                                    <th style="padding: 12px; text-align: left;">Email</th>
                                    <th style="padding: 12px; text-align: left;">Role</th>
                                    <th style="padding: 12px; text-align: left;">Member</th>
                                    <th style="padding: 12px; text-align: left;">Status</th>
                                    <th style="padding: 12px; text-align: left;">Last Login</th>
                                    <th style="padding: 12px; text-align: left;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.map(user => `
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 12px; font-weight: 600;">${escapeHtml(user.username)}</td>
                                        <td style="padding: 12px;">${escapeHtml(user.email)}</td>
                                        <td style="padding: 12px;">
                                            <span class="badge badge-${user.role.toLowerCase()}" style="padding: 4px 8px;">
                                                ${user.role}
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">
                                            ${user.board_member_name || '<span style="color: #999;">—</span>'}
                                        </td>
                                        <td style="padding: 12px;">
                                            <span class="badge badge-${user.is_active ? 'active' : 'inactive'}">
                                                ${user.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td style="padding: 12px; color: #666;">
                                            ${user.last_login ? formatDateTime(user.last_login) : 'Never'}
                                        </td>
                                        <td style="padding: 12px;">
                                            <button onclick="editUser(${user.id})" class="btn btn-sm">Edit</button>
                                            ${user.id != authData.user.id ? `
                                                <button onclick="deleteUser(${user.id})" class="btn btn-sm btn-danger">Delete</button>
                                            ` : ''}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    document.getElementById('users-list').innerHTML = '<p style="color: red;">Error loading users.</p>';
                });
        }

        function loadBoardMembers() {
            fetch('api/members.php')
                .then(response => response.json())
                .then(data => {
                    allBoardMembers = data;
                    const select = document.getElementById('boardMemberId');
                    select.innerHTML = '<option value="">Not linked to a board member</option>';
                    data.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = `${member.first_name} ${member.last_name}`;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading board members:', error);
                });
        }

        function showUserModal(user = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const title = document.getElementById('modalTitle');
            const passwordInput = document.getElementById('password');
            const passwordHint = document.getElementById('passwordHint');
            
            if (user) {
                title.textContent = 'Edit User';
                document.getElementById('userId').value = user.id;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('role').value = user.role;
                document.getElementById('boardMemberId').value = user.board_member_id || '';
                document.getElementById('isActive').checked = user.is_active;
                passwordInput.required = false;
                passwordInput.value = '';
                passwordHint.style.display = 'block';
            } else {
                title.textContent = 'New User';
                form.reset();
                document.getElementById('userId').value = '';
                document.getElementById('isActive').checked = true;
                passwordInput.required = true;
                passwordHint.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }

        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
            document.getElementById('userForm').reset();
        }

        function saveUser(event) {
            event.preventDefault();
            const userId = document.getElementById('userId').value;
            const password = document.getElementById('password').value;
            
            const data = {
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                role: document.getElementById('role').value,
                board_member_id: document.getElementById('boardMemberId').value || null,
                is_active: document.getElementById('isActive').checked
            };
            
            // Only include password if it's set
            if (password) {
                data.password = password;
            }

            const method = userId ? 'PUT' : 'POST';
            if (userId) {
                data.id = userId;
            }

            fetch('api/users.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.error) {
                    alert('Error: ' + result.error);
                } else {
                    closeUserModal();
                    loadUsers();
                }
            })
            .catch(error => {
                console.error('Error saving user:', error);
                alert('Error saving user');
            });
        }

        function editUser(id) {
            fetch(`api/users.php?id=${id}`)
                .then(response => response.json())
                .then(user => {
                    if (user.error) {
                        alert('Error: ' + user.error);
                        return;
                    }
                    showUserModal(user);
                })
                .catch(error => {
                    console.error('Error loading user:', error);
                    alert('Error loading user');
                });
        }

        function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            
            fetch('api/users.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(result => {
                if (result.error) {
                    alert('Error: ' + result.error);
                } else {
                    loadUsers();
                }
            })
            .catch(error => {
                console.error('Error deleting user:', error);
                alert('Error deleting user');
            });
        }

        function formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target == modal) {
                closeUserModal();
            }
        }
    </script>
</body>
</html>

