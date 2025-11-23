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
                <label for="meetingTypeSelect">Filter by Meeting Type:</label>
                <select id="meetingTypeSelect" onchange="loadMembers()">
                    <option value="">All Members</option>
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
                    <label>Meeting Types & Roles *</label>
                    <div id="meetingTypesContainer"></div>
                    <button type="button" onclick="addMeetingTypeRow()" class="btn btn-sm" style="margin-top: 10px;">+ Add Meeting Type</button>
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
        let allMeetingTypes = [];
        let currentMeetingTypeId = null;

        window.addEventListener('DOMContentLoaded', function() {
            loadMeetingTypes();
            loadMembers();
        });

        function loadMeetingTypes() {
            fetch('api/meeting_types.php')
                .then(response => response.json())
                .then(data => {
                    allMeetingTypes = data;
                    const select = document.getElementById('meetingTypeSelect');
                    select.innerHTML = '<option value="">All Members</option>';
                    data.forEach(meetingType => {
                        const option = document.createElement('option');
                        option.value = meetingType.id;
                        option.textContent = meetingType.name;
                        select.appendChild(option);
                    });
                });
        }

        function loadMembers() {
            currentMeetingTypeId = document.getElementById('meetingTypeSelect').value;
            
            let url = 'api/members.php';
            if (currentMeetingTypeId) {
                url += `?meeting_type_id=${currentMeetingTypeId}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('members-list');
                    if (data.length === 0) {
                        list.innerHTML = '<p>No members found. Add your first board member.</p>';
                        return;
                    }
                    // Load meeting type memberships for each member
                    Promise.all(data.map(member => 
                        fetch(`api/meeting_type_members.php?member_id=${member.id}`)
                            .then(r => r.json())
                            .then(meetingTypes => ({...member, meetingTypes}))
                            .catch(err => {
                                console.error(`Error loading meeting types for member ${member.id}:`, err);
                                return {...member, meetingTypes: []};
                            })
                    )).then(membersWithMeetingTypes => {
                        list.innerHTML = membersWithMeetingTypes.map(member => {
                            const meetingTypesList = member.meetingTypes && member.meetingTypes.length > 0 
                                ? member.meetingTypes.map(mt => {
                                    const statusClass = mt.status === 'Active' ? 'badge-active' : 
                                                       mt.status === 'Inactive' ? 'badge-inactive' : 
                                                       'badge-resigned';
                                    return `<span class="badge ${statusClass}" style="margin: 2px; display: inline-block;">${mt.meeting_type_name} - ${mt.role}</span>`;
                                }).join('')
                                : '<span style="color: #999;">No meeting type assignments</span>';
                            
                            return `
                                <div class="member-card">
                                    <div class="member-header">
                                        <h3>${member.first_name} ${member.last_name}</h3>
                                    </div>
                                    ${member.title ? `<p class="member-title">${member.title}</p>` : ''}
                                    ${member.email ? `<p class="member-email">${member.email}</p>` : ''}
                                    ${member.phone ? `<p class="member-phone">${member.phone}</p>` : ''}
                                    <div style="margin-top: 10px;">
                                        <strong>Meeting Types:</strong><br>
                                        <div style="margin-top: 5px;">${meetingTypesList}</div>
                                    </div>
                                    <div class="member-actions">
                                        <button onclick="editMember(${member.id})" class="btn btn-sm">Edit</button>
                                        <button onclick="deleteMember(${member.id})" class="btn btn-sm btn-danger">Delete</button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    });
                })
                .catch(error => {
                    console.error('Error loading members:', error);
                });
        }

        function showMemberModal(member = null) {
            const modal = document.getElementById('memberModal');
            const form = document.getElementById('memberForm');
            const title = document.getElementById('modalTitle');
            
            // Clear meeting types container
            document.getElementById('meetingTypesContainer').innerHTML = '';
            
            if (member) {
                title.textContent = 'Edit Board Member';
                document.getElementById('memberId').value = member.id;
                document.getElementById('firstName').value = member.first_name;
                document.getElementById('lastName').value = member.last_name;
                document.getElementById('email').value = member.email || '';
                document.getElementById('phone').value = member.phone || '';
                document.getElementById('title').value = member.title || '';
                document.getElementById('bio').value = member.bio || '';
                
                // Load and display existing meeting type memberships
                fetch(`api/meeting_type_members.php?member_id=${member.id}`)
                    .then(response => response.json())
                    .then(meetingTypes => {
                        if (meetingTypes && meetingTypes.length > 0) {
                            meetingTypes.forEach(mtm => {
                                addMeetingTypeRow(mtm.meeting_type_id, mtm.role, mtm.status, mtm.start_date || '', mtm.id);
                            });
                        } else {
                            addMeetingTypeRow();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading meeting type memberships:', error);
                        addMeetingTypeRow(); // Add empty row if error
                    });
            } else {
                title.textContent = 'New Board Member';
                form.reset();
                document.getElementById('memberId').value = '';
                addMeetingTypeRow(); // Add one empty row
            }
            modal.style.display = 'block';
        }

        function addMeetingTypeRow(meetingTypeId = '', role = 'Member', status = 'Active', startDate = '', membershipId = '') {
            const container = document.getElementById('meetingTypesContainer');
            const rowId = 'meeting-type-row-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            
            const row = document.createElement('div');
            row.id = rowId;
            row.className = 'meeting-type-row';
            row.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; padding: 10px; background: #f9f9f9; border-radius: 4px;';
            
            row.innerHTML = `
                <select class="meeting-type-select" style="flex: 2;" required>
                    <option value="">Select Meeting Type...</option>
                    ${allMeetingTypes.map(mt => `<option value="${mt.id}" ${mt.id == meetingTypeId ? 'selected' : ''}>${mt.name}</option>`).join('')}
                </select>
                <select class="meeting-type-role" style="flex: 1;">
                    <option value="Member" ${role === 'Member' ? 'selected' : ''}>Member</option>
                    <option value="Chair" ${role === 'Chair' ? 'selected' : ''}>Chair</option>
                    <option value="Deputy Chair" ${role === 'Deputy Chair' ? 'selected' : ''}>Deputy Chair</option>
                    <option value="Secretary" ${role === 'Secretary' ? 'selected' : ''}>Secretary</option>
                    <option value="Treasurer" ${role === 'Treasurer' ? 'selected' : ''}>Treasurer</option>
                    <option value="Ex-officio" ${role === 'Ex-officio' ? 'selected' : ''}>Ex-officio</option>
                </select>
                <select class="meeting-type-status" style="flex: 1;">
                    <option value="Active" ${status === 'Active' ? 'selected' : ''}>Active</option>
                    <option value="Inactive" ${status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                    <option value="Resigned" ${status === 'Resigned' ? 'selected' : ''}>Resigned</option>
                    <option value="Terminated" ${status === 'Terminated' ? 'selected' : ''}>Terminated</option>
                </select>
                <input type="date" class="meeting-type-start-date" placeholder="Start Date" value="${startDate || ''}" style="flex: 1;">
                <input type="hidden" class="membership-id" value="${membershipId}">
                <button type="button" onclick="removeMeetingTypeRow('${rowId}')" class="btn btn-sm btn-danger">×</button>
            `;
            
            container.appendChild(row);
        }

        function removeMeetingTypeRow(rowId) {
            document.getElementById(rowId).remove();
        }

        function closeMemberModal() {
            document.getElementById('memberModal').style.display = 'none';
            document.getElementById('memberForm').reset();
        }

        function saveMember(event) {
            event.preventDefault();
            const memberId = document.getElementById('memberId').value;
            
            // Collect meeting type memberships
            const meetingTypeRows = document.querySelectorAll('.meeting-type-row');
            const meetingTypeIds = [];
            
            meetingTypeRows.forEach(row => {
                const meetingTypeId = row.querySelector('.meeting-type-select').value;
                if (meetingTypeId) {
                    const membershipId = row.querySelector('.membership-id').value;
                    meetingTypeIds.push({
                        meeting_type_id: meetingTypeId,
                        role: row.querySelector('.meeting-type-role').value,
                        status: row.querySelector('.meeting-type-status').value,
                        start_date: row.querySelector('.meeting-type-start-date').value || null,
                        membership_id: membershipId || null
                    });
                }
            });
            
            if (meetingTypeIds.length === 0) {
                alert('Please add at least one meeting type membership');
                return;
            }
            
            // Save member basic info
            const memberData = {
                first_name: document.getElementById('firstName').value,
                last_name: document.getElementById('lastName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                title: document.getElementById('title').value,
                bio: document.getElementById('bio').value
            };

            const url = 'api/members.php';
            const method = memberId ? 'PUT' : 'POST';
            
            if (memberId) {
                memberData.id = memberId;
            }

            // Save or update member
            fetch(url, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(memberData)
            })
            .then(response => response.json())
            .then(savedMember => {
                const actualMemberId = memberId || savedMember.id;
                
                // Save meeting type memberships
                const membershipPromises = meetingTypeIds.map(mt => {
                    if (mt.membership_id) {
                        // Update existing membership
                        return fetch('api/meeting_type_members.php', {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                id: mt.membership_id,
                                role: mt.role,
                                status: mt.status,
                                start_date: mt.start_date
                            })
                        });
                    } else {
                        // Create new membership
                        return fetch('api/meeting_type_members.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                meeting_type_id: mt.meeting_type_id,
                                member_id: actualMemberId,
                                role: mt.role,
                                status: mt.status,
                                start_date: mt.start_date
                            })
                        });
                    }
                });
                
                return Promise.all(membershipPromises).then(() => {
                    closeMemberModal();
                    loadMembers();
                });
            })
            .catch(error => {
                console.error('Error saving member:', error);
                alert('Error saving member: ' + error.message);
            });
        }

        function editMember(id) {
            Promise.all([
                fetch(`api/members.php?id=${id}`).then(r => r.json()),
                fetch(`api/meeting_type_members.php?member_id=${id}`).then(r => r.json())
            ]).then(([member, meetingTypes]) => {
                // Merge meeting type data into member object for compatibility
                member.meetingTypes = meetingTypes;
                showMemberModal(member);
            }).catch(error => {
                console.error('Error loading member:', error);
                alert('Error loading member details');
            });
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

