<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Board Members - Governance Board Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Governance Board Management System</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="members.php" class="active">Board Members</a></li>
                    <li><a href="meetings.php">Meetings</a></li>
                    <li><a href="resolutions.php">Resolutions</a></li>
                    <li><a href="documents.php">Documents</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="page-header">
                <h2>Board Members</h2>
                <button onclick="showMemberModal()" class="btn btn-primary">+ Add Member</button>
            </div>

            <div class="organization-selector">
                <!-- <label for="orgSelect">Committee:</label> -->
                <select id="orgSelect" onchange="loadMembers()">
                    <option value="">Select Committee...</option>
                </select>
            </div>

            <div id="members-list" class="members-grid"></div>
        </main>
    </div>

    <!-- Member Modal -->
    <div id="memberModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMemberModal()">&times;</span>
            <h2 id="modalTitle">New Board Member</h2>
            <form id="memberForm" onsubmit="saveMember(event)">
                <input type="hidden" id="memberId">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone">
                </div>
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title">
                </div>
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" required>
                        <option value="Member">Member</option>
                        <option value="Chair">Chair</option>
                        <option value="Deputy Chair">Deputy Chair</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Treasurer">Treasurer</option>
                        <option value="Ex-officio">Ex-officio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Resigned">Resigned</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="startDate">Start Date</label>
                    <input type="date" id="startDate">
                </div>
                <div class="form-group">
                    <label for="bio">Biography</label>
                    <textarea id="bio" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Member</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let currentOrgId = null;

        window.addEventListener('DOMContentLoaded', function() {
            loadOrganizations();
        });

        function loadOrganizations() {
            fetch('api/committees.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('orgSelect');
                    select.innerHTML = '<option value="">Select organization...</option>';
                    data.forEach(org => {
                        const option = document.createElement('option');
                        option.value = org.id;
                        option.textContent = org.name;
                        select.appendChild(option);
                    });
                    if (data.length > 0) {
                        select.value = data[0].id;
                        currentOrgId = data[0].id;
                        loadMembers();
                    }
                });
        }

        function loadMembers() {
            currentOrgId = document.getElementById('orgSelect').value;
            if (!currentOrgId) return;

            fetch(`api/members.php?committees_id=${currentOrgId}`)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('members-list');
                    if (data.length === 0) {
                        list.innerHTML = '<p>No members found. Add your first board member.</p>';
                        return;
                    }
                    list.innerHTML = data.map(member => `
                        <div class="member-card">
                            <div class="member-header">
                                <h3>${member.first_name} ${member.last_name}</h3>
                            </div>

                            ${member.title ? `<p class="member-title">${member.title}</p>` : ''}
                            ${member.email ? `<p class="member-email">${member.email}</p>` : ''}
                            ${member.phone ? `<p class="member-phone">${member.phone}</p>` : ''}
                            <div class="member-actions">
                                <button onclick="editMember(${member.id})" class="btn btn-sm">Edit</button>
                                <button onclick="deleteMember(${member.id})" class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function showMemberModal(member = null) {
            if (!currentOrgId) {
                alert('Please select an organization first');
                return;
            }

            const modal = document.getElementById('memberModal');
            const form = document.getElementById('memberForm');
            const title = document.getElementById('modalTitle');
            
            if (member) {
                title.textContent = 'Edit Board Member';
                document.getElementById('memberId').value = member.id;
                document.getElementById('firstName').value = member.first_name;
                document.getElementById('lastName').value = member.last_name;
                document.getElementById('email').value = member.email || '';
                document.getElementById('phone').value = member.phone || '';
                document.getElementById('title').value = member.title || '';
                document.getElementById('role').value = member.role;
                document.getElementById('status').value = member.status;
                document.getElementById('startDate').value = member.start_date || '';
                document.getElementById('bio').value = member.bio || '';
            } else {
                title.textContent = 'New Board Member';
                form.reset();
                document.getElementById('memberId').value = '';
            }
            modal.style.display = 'block';
        }

        function closeMemberModal() {
            document.getElementById('memberModal').style.display = 'none';
            document.getElementById('memberForm').reset();
        }

        function saveMember(event) {
            event.preventDefault();
            const memberId = document.getElementById('memberId').value;
            const data = {
                organization_id: currentOrgId,
                first_name: document.getElementById('firstName').value,
                last_name: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                title: document.getElementById('title').value,
                role: document.getElementById('role').value,
                status: document.getElementById('status').value,
                start_date: document.getElementById('startDate').value || null,
                bio: document.getElementById('bio').value
            };

            const url = 'api/members.php';
            const method = memberId ? 'PUT' : 'POST';
            
            if (memberId) {
                data.id = memberId;
            }

            fetch(url, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeMemberModal();
                loadMembers();
            })
            .catch(error => {
                console.error('Error saving member:', error);
                alert('Error saving member');
            });
        }

        function editMember(id) {
            fetch(`api/members.php?id=${id}`)
                .then(response => response.json())
                .then(member => showMemberModal(member));
        }

        function deleteMember(id) {
            if (!confirm('Are you sure you want to delete this member?')) return;
            
            fetch('api/members.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                loadMembers();
            })
            .catch(error => {
                console.error('Error deleting member:', error);
                alert('Error deleting member');
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('memberModal');
            if (event.target == modal) {
                closeMemberModal();
            }
        }
    </script>
</body>
</html>

