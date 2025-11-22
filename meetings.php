<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meetings - Governance Board Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Governance Board Management System</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="members.php">Board Members</a></li>
                    <li><a href="meetings.php" class="active">Meetings</a></li>
                    <li><a href="resolutions.php">Resolutions</a></li>
                    <li><a href="documents.php">Documents</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="page-header">
                <h2>Board Meetings</h2>
                <button onclick="showMeetingModal()" class="btn btn-primary">+ New Meeting</button>
            </div>

            <div class="organization-selector">
                <label for="orgSelect">Organization:</label>
                <select id="orgSelect" onchange="loadMeetings()">
                    <option value="">Select organization...</option>
                </select>
            </div>

            <div id="meetings-list" class="meetings-list"></div>

            <!-- Meeting Detail View -->
            <div id="meeting-detail" class="meeting-detail" style="display:none;">
                <button onclick="closeMeetingDetail()" class="btn btn-secondary">← Back to List</button>
                <div id="meeting-detail-content"></div>
            </div>
        </main>
    </div>

    <!-- Meeting Modal -->
    <div id="meetingModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeMeetingModal()">&times;</span>
            <h2 id="modalTitle">New Meeting</h2>
            <form id="meetingForm" onsubmit="saveMeeting(event)">
                <input type="hidden" id="meetingId">
                <div class="form-group">
                    <label for="meetingTitle">Title *</label>
                    <input type="text" id="meetingTitle" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="meetingType">Meeting Type *</label>
                        <select id="meetingType" required>
                            <option value="Regular">Regular</option>
                            <option value="Special">Special</option>
                            <option value="Annual">Annual</option>
                            <option value="Emergency">Emergency</option>
                            <option value="Workshop">Workshop</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="scheduledDate">Scheduled Date & Time *</label>
                        <input type="datetime-local" id="scheduledDate" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location">
                </div>
                <div class="form-group">
                    <label for="virtualLink">Virtual Meeting Link</label>
                    <input type="url" id="virtualLink">
                </div>
                <div class="form-group">
                    <label for="quorumRequired">Quorum Required</label>
                    <input type="number" id="quorumRequired" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="meetingStatus">Status</label>
                    <select id="meetingStatus">
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Postponed">Postponed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="meetingNotes">Notes</label>
                    <textarea id="meetingNotes" rows="4"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Meeting</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let currentOrgId = null;
        let currentMeetingId = null;

        window.addEventListener('DOMContentLoaded', function() {
            loadOrganizations();
            
            // Check if meeting ID is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const meetingId = urlParams.get('id');
            if (meetingId) {
                showMeetingDetail(meetingId);
            }
        });

        function loadOrganizations() {
            fetch('api/organizations.php')
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
                        loadMeetings();
                    }
                });
        }

        function loadMeetings() {
            currentOrgId = document.getElementById('orgSelect').value;
            if (!currentOrgId) return;

            fetch(`api/meetings.php?organization_id=${currentOrgId}`)
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('meetings-list');
                    if (data.length === 0) {
                        list.innerHTML = '<p>No meetings found. Schedule your first meeting.</p>';
                        return;
                    }
                    list.innerHTML = data.map(meeting => `
                        <div class="meeting-item" onclick="showMeetingDetail(${meeting.id})">
                            <div class="meeting-header">
                                <h3>${meeting.title}</h3>
                                <span class="badge badge-${meeting.status.toLowerCase().replace(' ', '-')}">${meeting.status}</span>
                            </div>
                            <p class="meeting-type">${meeting.meeting_type} Meeting</p>
                            <p class="meeting-date">${formatDateTime(meeting.scheduled_date)}</p>
                            ${meeting.location ? `<p class="meeting-location">📍 ${meeting.location}</p>` : ''}
                        </div>
                    `).join('');
                });
        }

        function showMeetingDetail(id) {
            currentMeetingId = id;
            document.getElementById('meetings-list').style.display = 'none';
            document.getElementById('meeting-detail').style.display = 'block';

            fetch(`api/meetings.php?id=${id}`)
                .then(response => response.json())
                .then(meeting => {
                    const content = document.getElementById('meeting-detail-content');
                    content.innerHTML = `
                        <div class="meeting-detail-header">
                            <h2>${meeting.title}</h2>
                            <div>
                                <button onclick="editMeetingFromDetail()" class="btn btn-secondary">Edit</button>
                                <button onclick="deleteMeeting(${meeting.id})" class="btn btn-danger">Delete</button>
                            </div>
                        </div>
                        <div class="meeting-info">
                            <p><strong>Type:</strong> ${meeting.meeting_type}</p>
                            <p><strong>Scheduled:</strong> ${formatDateTime(meeting.scheduled_date)}</p>
                            <p><strong>Status:</strong> <span class="badge badge-${meeting.status.toLowerCase().replace(' ', '-')}">${meeting.status}</span></p>
                            ${meeting.location ? `<p><strong>Location:</strong> ${meeting.location}</p>` : ''}
                            ${meeting.virtual_link ? `<p><strong>Virtual Link:</strong> <a href="${meeting.virtual_link}" target="_blank">${meeting.virtual_link}</a></p>` : ''}
                            ${meeting.notes ? `<p><strong>Notes:</strong> ${meeting.notes}</p>` : ''}
                        </div>
                        <div class="meeting-tabs">
                            <button class="tab-btn active" onclick="showTab('agenda')">Agenda</button>
                            <button class="tab-btn" onclick="showTab('attendees')">Attendees</button>
                            <button class="tab-btn" onclick="showTab('minutes')">Minutes</button>
                            <button class="tab-btn" onclick="showTab('resolutions')">Resolutions</button>
                        </div>
                        <div id="tab-agenda" class="tab-content active">
                            <h3>Agenda Items</h3>
                            <button onclick="addAgendaItem()" class="btn btn-sm btn-primary">+ Add Item</button>
                            <div id="agenda-items-list"></div>
                        </div>
                        <div id="tab-attendees" class="tab-content">
                            <h3>Attendees</h3>
                            <button onclick="addAttendee()" class="btn btn-sm btn-primary">+ Add Attendee</button>
                            <div id="attendees-list"></div>
                        </div>
                        <div id="tab-minutes" class="tab-content">
                            <h3>Meeting Minutes</h3>
                            <div id="minutes-content"></div>
                        </div>
                        <div id="tab-resolutions" class="tab-content">
                            <h3>Resolutions</h3>
                            <button onclick="addResolution()" class="btn btn-sm btn-primary">+ Add Resolution</button>
                            <div id="resolutions-list"></div>
                        </div>
                    `;
                    
                    loadMeetingAgenda(id);
                    loadMeetingAttendees(id);
                    loadMeetingMinutes(id);
                    loadMeetingResolutions(id);
                });
        }

        function closeMeetingDetail() {
            document.getElementById('meeting-detail').style.display = 'none';
            document.getElementById('meetings-list').style.display = 'block';
            currentMeetingId = null;
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`tab-${tabName}`).classList.add('active');
            event.target.classList.add('active');
        }

        function loadMeetingAgenda(meetingId) {
            fetch(`api/agenda.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(items => {
                    const list = document.getElementById('agenda-items-list');
                    if (items.length === 0) {
                        list.innerHTML = '<p>No agenda items yet.</p>';
                        return;
                    }
                    list.innerHTML = items.map(item => `
                        <div class="agenda-item">
                            <h4>${item.title}</h4>
                            ${item.description ? `<p>${item.description}</p>` : ''}
                            <div class="agenda-meta">
                                <span class="badge badge-${item.item_type.toLowerCase()}">${item.item_type}</span>
                                ${item.presenter_first_name ? `<span>Presenter: ${item.presenter_first_name} ${item.presenter_last_name}</span>` : ''}
                                ${item.duration_minutes ? `<span>Duration: ${item.duration_minutes} min</span>` : ''}
                            </div>
                        </div>
                    `).join('');
                });
        }

        function loadMeetingAttendees(meetingId) {
            fetch(`api/attendees.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(attendees => {
                    const list = document.getElementById('attendees-list');
                    if (attendees.length === 0) {
                        list.innerHTML = '<p>No attendees added yet.</p>';
                        return;
                    }
                    list.innerHTML = attendees.map(att => `
                        <div class="attendee-item">
                            <strong>${att.first_name} ${att.last_name}</strong> (${att.role})
                            <span class="badge badge-${att.attendance_status.toLowerCase()}">${att.attendance_status}</span>
                        </div>
                    `).join('');
                });
        }

        function loadMeetingMinutes(meetingId) {
            fetch(`api/minutes.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(minutes => {
                    const content = document.getElementById('minutes-content');
                    if (!minutes || minutes === null) {
                        content.innerHTML = '<button onclick="createMinutes()" class="btn btn-primary">Create Minutes</button>';
                        return;
                    }
                    content.innerHTML = `
                        <div class="minutes-display">
                            <p><strong>Status:</strong> <span class="badge badge-${minutes.status.toLowerCase()}">${minutes.status}</span></p>
                            <div class="minutes-text">${minutes.content.replace(/\n/g, '<br>')}</div>
                            ${minutes.action_items ? `<h4>Action Items</h4><div>${minutes.action_items.replace(/\n/g, '<br>')}</div>` : ''}
                        </div>
                    `;
                });
        }

        function loadMeetingResolutions(meetingId) {
            fetch(`api/resolutions.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(resolutions => {
                    const list = document.getElementById('resolutions-list');
                    if (resolutions.length === 0) {
                        list.innerHTML = '<p>No resolutions for this meeting.</p>';
                        return;
                    }
                    list.innerHTML = resolutions.map(res => `
                        <div class="resolution-item">
                            <h4>${res.title}</h4>
                            <p>${res.description}</p>
                            <p><strong>Status:</strong> <span class="badge badge-${res.status.toLowerCase()}">${res.status}</span></p>
                        </div>
                    `).join('');
                });
        }

        function showMeetingModal(meeting = null) {
            if (!currentOrgId) {
                alert('Please select an organization first');
                return;
            }

            const modal = document.getElementById('meetingModal');
            const form = document.getElementById('meetingForm');
            const title = document.getElementById('modalTitle');
            
            if (meeting) {
                title.textContent = 'Edit Meeting';
                document.getElementById('meetingId').value = meeting.id;
                document.getElementById('meetingTitle').value = meeting.title;
                document.getElementById('meetingType').value = meeting.meeting_type;
                document.getElementById('scheduledDate').value = meeting.scheduled_date.replace(' ', 'T').substring(0, 16);
                document.getElementById('location').value = meeting.location || '';
                document.getElementById('virtualLink').value = meeting.virtual_link || '';
                document.getElementById('quorumRequired').value = meeting.quorum_required || 0;
                document.getElementById('meetingStatus').value = meeting.status;
                document.getElementById('meetingNotes').value = meeting.notes || '';
            } else {
                title.textContent = 'New Meeting';
                form.reset();
                document.getElementById('meetingId').value = '';
            }
            modal.style.display = 'block';
        }

        function closeMeetingModal() {
            document.getElementById('meetingModal').style.display = 'none';
            document.getElementById('meetingForm').reset();
        }

        function saveMeeting(event) {
            event.preventDefault();
            const meetingId = document.getElementById('meetingId').value;
            const scheduledDate = document.getElementById('scheduledDate').value;
            
            const data = {
                organization_id: currentOrgId,
                title: document.getElementById('meetingTitle').value,
                meeting_type: document.getElementById('meetingType').value,
                scheduled_date: scheduledDate.replace('T', ' ') + ':00',
                location: document.getElementById('location').value,
                virtual_link: document.getElementById('virtualLink').value,
                quorum_required: parseInt(document.getElementById('quorumRequired').value),
                status: document.getElementById('meetingStatus').value,
                notes: document.getElementById('meetingNotes').value
            };

            const url = 'api/meetings.php';
            const method = meetingId ? 'PUT' : 'POST';
            
            if (meetingId) {
                data.id = meetingId;
            }

            fetch(url, {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeMeetingModal();
                loadMeetings();
                if (currentMeetingId == data.id) {
                    showMeetingDetail(data.id);
                }
            })
            .catch(error => {
                console.error('Error saving meeting:', error);
                alert('Error saving meeting');
            });
        }

        function editMeetingFromDetail() {
            fetch(`api/meetings.php?id=${currentMeetingId}`)
                .then(response => response.json())
                .then(meeting => showMeetingModal(meeting));
        }

        function deleteMeeting(id) {
            if (!confirm('Are you sure you want to delete this meeting?')) return;
            
            fetch('api/meetings.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                closeMeetingDetail();
                loadMeetings();
            })
            .catch(error => {
                console.error('Error deleting meeting:', error);
                alert('Error deleting meeting');
            });
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function addAgendaItem() {
            alert('Agenda item management coming soon');
        }

        function addAttendee() {
            alert('Attendee management coming soon');
        }

        function createMinutes() {
            alert('Minutes creation coming soon');
        }

        function addResolution() {
            alert('Resolution creation coming soon');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('meetingModal');
            if (event.target == modal) {
                closeMeetingModal();
            }
        }
    </script>
</body>
</html>

