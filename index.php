<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Governance Board Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Governance Board Management System</h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="members.php">Board Members</a></li>
                    <li><a href="meetings.php">Meetings</a></li>
                    <li><a href="resolutions.php">Resolutions</a></li>
                    <li><a href="documents.php">Documents</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="organization-selector">
                <label for="orgSelect">Select Organization:</label>
                <select id="orgSelect" onchange="loadDashboard()">
                    <option value="">Loading...</option>
                </select>
                <button onclick="showOrgModal()">+ New Organization</button>
            </div>

            <div id="dashboard" style="display:none;">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Active Members</h3>
                        <p class="stat-number" id="stat-members">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>Upcoming Meetings</h3>
                        <p class="stat-number" id="stat-upcoming">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>Recent Meetings</h3>
                        <p class="stat-number" id="stat-recent">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Resolutions</h3>
                        <p class="stat-number" id="stat-resolutions">0</p>
                    </div>
                    <div class="stat-card">
                        <h3>Draft Minutes</h3>
                        <p class="stat-number" id="stat-minutes">0</p>
                    </div>
                </div>

                <div class="dashboard-sections">
                    <section class="dashboard-section">
                        <h2>Upcoming Meetings</h2>
                        <div id="upcoming-meetings-list" class="meeting-list"></div>
                    </section>

                    <section class="dashboard-section">
                        <h2>Recent Meetings</h2>
                        <div id="recent-meetings-list" class="meeting-list"></div>
                    </section>
                </div>
            </div>

            <div id="no-org" style="display:none;">
                <p>No organization selected. Please create or select an organization.</p>
            </div>
        </main>
    </div>

    <!-- Organization Modal -->
    <div id="orgModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeOrgModal()">&times;</span>
            <h2>New Organization</h2>
            <form id="orgForm" onsubmit="createOrganization(event)">
                <div class="form-group">
                    <label for="orgName">Name *</label>
                    <input type="text" id="orgName" required>
                </div>
                <div class="form-group">
                    <label for="orgDescription">Description</label>
                    <textarea id="orgDescription"></textarea>
                </div>
                <div class="form-group">
                    <label for="orgEmail">Email</label>
                    <input type="email" id="orgEmail">
                </div>
                <div class="form-group">
                    <label for="orgPhone">Phone</label>
                    <input type="tel" id="orgPhone">
                </div>
                <div class="form-group">
                    <label for="orgWebsite">Website</label>
                    <input type="url" id="orgWebsite">
                </div>
                <div class="form-group">
                    <label for="orgAddress">Address</label>
                    <textarea id="orgAddress"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Organization</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Load organizations on page load
        window.addEventListener('DOMContentLoaded', function() {
            loadOrganizations();
        });

        function loadOrganizations() {
            fetch('api/organizations.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('orgSelect');
                    select.innerHTML = '<option value="">Select an organization...</option>';
                    data.forEach(org => {
                        const option = document.createElement('option');
                        option.value = org.id;
                        option.textContent = org.name;
                        select.appendChild(option);
                    });
                    if (data.length > 0) {
                        select.value = data[0].id;
                        loadDashboard();
                    }
                })
                .catch(error => {
                    console.error('Error loading organizations:', error);
                });
        }

        function loadDashboard() {
            const orgId = document.getElementById('orgSelect').value;
            if (!orgId) {
                document.getElementById('dashboard').style.display = 'none';
                document.getElementById('no-org').style.display = 'block';
                return;
            }

            document.getElementById('dashboard').style.display = 'block';
            document.getElementById('no-org').style.display = 'none';

            // Load dashboard stats
            fetch(`api/dashboard.php?organization_id=${orgId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('stat-members').textContent = data.active_members || 0;
                    document.getElementById('stat-upcoming').textContent = data.upcoming_meetings || 0;
                    document.getElementById('stat-recent').textContent = data.recent_meetings || 0;
                    document.getElementById('stat-resolutions').textContent = data.pending_resolutions || 0;
                    document.getElementById('stat-minutes').textContent = data.draft_minutes || 0;

                    // Display upcoming meetings
                    const upcomingList = document.getElementById('upcoming-meetings-list');
                    if (data.upcoming_meetings_list && data.upcoming_meetings_list.length > 0) {
                        upcomingList.innerHTML = data.upcoming_meetings_list.map(meeting => 
                            `<div class="meeting-item">
                                <h4><a href="meetings.php?id=${meeting.id}">${meeting.title}</a></h4>
                                <p>${formatDateTime(meeting.scheduled_date)}</p>
                                <span class="badge badge-${meeting.status.toLowerCase()}">${meeting.status}</span>
                            </div>`
                        ).join('');
                    } else {
                        upcomingList.innerHTML = '<p>No upcoming meetings</p>';
                    }

                    // Display recent meetings
                    const recentList = document.getElementById('recent-meetings-list');
                    if (data.recent_meetings_list && data.recent_meetings_list.length > 0) {
                        recentList.innerHTML = data.recent_meetings_list.map(meeting => 
                            `<div class="meeting-item">
                                <h4><a href="meetings.php?id=${meeting.id}">${meeting.title}</a></h4>
                                <p>${formatDateTime(meeting.scheduled_date)}</p>
                                <span class="badge badge-${meeting.status.toLowerCase()}">${meeting.status}</span>
                            </div>`
                        ).join('');
                    } else {
                        recentList.innerHTML = '<p>No recent meetings</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard:', error);
                });
        }

        function showOrgModal() {
            document.getElementById('orgModal').style.display = 'block';
        }

        function closeOrgModal() {
            document.getElementById('orgModal').style.display = 'none';
            document.getElementById('orgForm').reset();
        }

        function createOrganization(event) {
            event.preventDefault();
            const data = {
                name: document.getElementById('orgName').value,
                description: document.getElementById('orgDescription').value,
                email: document.getElementById('orgEmail').value,
                phone: document.getElementById('orgPhone').value,
                website: document.getElementById('orgWebsite').value,
                address: document.getElementById('orgAddress').value
            };

            fetch('api/committees.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeOrgModal();
                loadOrganizations();
            })
            .catch(error => {
                console.error('Error creating organization:', error);
                alert('Error creating organization');
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orgModal');
            if (event.target == modal) {
                closeOrgModal();
            }
        }
    </script>
</body>
</html>

