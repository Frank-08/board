<?php
require_once __DIR__ . '/includes/header.php';
outputHeader('Dashboard', 'index.php');
?>

        <main>
            <div class="page-header">
                <h2>System Overview</h2>
                <!-- <button onclick="showMeetingTypeModal()" class="btn btn-primary">+ New Meeting Type</button> -->
            </div>

            <div class="organization-selector">
                <label for="meetingTypeSelect">Filter by Meeting Type (optional):</label>
                <select id="meetingTypeSelect" onchange="loadDashboard()">
                    <option value="">---Select Meeting Type---</option>
                </select>
            </div>

            <div id="dashboard">
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

            <div id="no-meeting-type" style="display:none;">
                <p>No meeting type selected. Please create or select a meeting type.</p>
            </div>
        </main>
    </div>

    <!-- Meeting Type Modal
    <div id="meetingTypeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMeetingTypeModal()">&times;</span>
            <h2>New Meeting Type</h2>
            <form id="meetingTypeForm" onsubmit="createMeetingType(event)">
                <div class="form-group">
                    <label for="meetingTypeName">Name *</label>
                    <input type="text" id="meetingTypeName" required>
                </div>
                <div class="form-group">
                    <label for="meetingTypeShortcode">Short Code </label>
                    <input type="text" id="meetingTypeShortcode">
                </div>
                <div class="form-group">
                    <label for="meetingTypeDescription">Description</label>
                    <textarea id="meetingTypeDescription"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Meeting Type</button>
            </form>
        </div>
    </div> -->

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        
        // Load meeting types on page load
        window.addEventListener('DOMContentLoaded', function() {
            loadMeetingTypes();
            loadDashboard();
        });

        function loadMeetingTypes() {
            fetch('api/meeting_types.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('meetingTypeSelect');
                    select.innerHTML = '<option value="">---Select a meeting type---</option>';
                    data.forEach(meetingType => {
                        const option = document.createElement('option');
                        option.value = meetingType.id;
                        option.textContent = meetingType.name;
                        select.appendChild(option);
                    });
                    // if (data.length > 0) {
                    //     select.value = data[0].id;
                    //     loadDashboard();
                    // }
                })
                .catch(error => {
                    console.error('Error loading meeting types:', error);
                });
        }

        function loadDashboard() {
            const meetingTypeId = document.getElementById('meetingTypeSelect').value;
            
            // Always show dashboard, load all data or filtered data
            document.getElementById('dashboard').style.display = 'block';

            // Build API URL - no meeting_type_id parameter means system-wide
            let apiUrl = 'api/dashboard.php';
            if (meetingTypeId) {
                apiUrl += `?meeting_type_id=${meetingTypeId}`;
            }

            // Load dashboard stats
            fetch(apiUrl)
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
                                <p><strong>${meeting.meeting_type_name}</strong> - ${formatDateTime(meeting.scheduled_date)}</p>
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
                                <p><strong>${meeting.meeting_type_name}</strong> - ${formatDateTime(meeting.scheduled_date)}</p>
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

        function showMeetingTypeModal() {
            document.getElementById('meetingTypeModal').style.display = 'block';
        }

        function closeMeetingTypeModal() {
            document.getElementById('meetingTypeModal').style.display = 'none';
            document.getElementById('meetingTypeForm').reset();
        }

        function createMeetingType(event) {
            event.preventDefault();
            const data = {
                name: document.getElementById('meetingTypeName').value,
                shortcode: document.getElementById('meetingTypeShortcode').value,
                description: document.getElementById('meetingTypeDescription').value
            };

            fetch('api/meeting_types.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeMeetingTypeModal();
                loadMeetingTypes();
            })
            .catch(error => {
                console.error('Error creating meeting type:', error);
                alert('Error creating meeting type');
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
            const modal = document.getElementById('meetingTypeModal');
            if (event.target == modal) {
                closeMeetingTypeModal();
            }
        }
    </script>
</body>
</html>

