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
                <label for="committeeSelect">Committee:</label>
                <select id="committeeSelect" onchange="loadMeetings()">
                    <option value="">Select committee...</option>
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

    <!-- Agenda Item Modal -->
    <div id="agendaItemModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeAgendaItemModal()">&times;</span>
            <h2 id="modalAgendaTitle">New Agenda Item</h2>
            <form id="agendaItemForm" onsubmit="saveAgendaItem(event)">
                <input type="hidden" id="agendaItemId">
                <div class="form-group">
                    <label for="agendaItemTitle">Title *</label>
                    <input type="text" id="agendaItemTitle" required>
                </div>
                <div class="form-group">
                    <label for="agendaItemDescription">Description</label>
                    <textarea id="agendaItemDescription" rows="4"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="agendaItemType">Item Type *</label>
                        <select id="agendaItemType" required>
                            <option value="Discussion">Discussion</option>
                            <option value="Action Item">Action Item</option>
                            <option value="Vote">Vote</option>
                            <option value="Information">Information</option>
                            <option value="Presentation">Presentation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="agendaItemDuration">Duration (minutes)</label>
                        <input type="number" id="agendaItemDuration" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="agendaItemPresenter">Presenter</label>
                    <select id="agendaItemPresenter">
                        <option value="">Select presenter...</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Agenda Item</button>
            </form>
        </div>
    </div>

    <!-- Attendee Modal -->
    <div id="attendeeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAttendeeModal()">&times;</span>
            <h2 id="modalAttendeeTitle">Add Attendee</h2>
            <form id="attendeeForm" onsubmit="saveAttendee(event)">
                <input type="hidden" id="attendeeId">
                <div class="form-group">
                    <label for="attendeeMember">Board Member *</label>
                    <select id="attendeeMember" required>
                        <option value="">Select member...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="attendeeStatus">Attendance Status *</label>
                    <select id="attendeeStatus" required>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Excused">Excused</option>
                        <option value="Late">Late</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="attendeeArrivalTime">Arrival Time</label>
                    <input type="datetime-local" id="attendeeArrivalTime">
                </div>
                <div class="form-group">
                    <label for="attendeeNotes">Notes</label>
                    <textarea id="attendeeNotes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Attendee</button>
            </form>
        </div>
    </div>

    <!-- Resolution Modal -->
    <div id="resolutionModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeResolutionModal()">&times;</span>
            <h2 id="modalResolutionTitle">New Resolution</h2>
            <form id="resolutionForm" onsubmit="saveResolution(event)">
                <input type="hidden" id="resolutionId">
                <div class="form-group">
                    <label for="resolutionTitle">Title *</label>
                    <input type="text" id="resolutionTitle" required>
                </div>
                <div class="form-group">
                    <label for="resolutionDescription">Description *</label>
                    <textarea id="resolutionDescription" rows="5" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="resolutionNumber">Resolution Number</label>
                        <input type="text" id="resolutionNumber">
                    </div>
                    <div class="form-group">
                        <label for="resolutionStatus">Status</label>
                        <select id="resolutionStatus">
                            <option value="Proposed">Proposed</option>
                            <option value="Passed">Passed</option>
                            <option value="Failed">Failed</option>
                            <option value="Tabled">Tabled</option>
                            <option value="Withdrawn">Withdrawn</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="resolutionMovedBy">Moved By</label>
                        <select id="resolutionMovedBy">
                            <option value="">Select member...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resolutionSecondedBy">Seconded By</label>
                        <select id="resolutionSecondedBy">
                            <option value="">Select member...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="resolutionVoteType">Vote Type</label>
                        <select id="resolutionVoteType">
                            <option value="">Select vote type...</option>
                            <option value="Unanimous">Unanimous</option>
                            <option value="Majority">Majority</option>
                            <option value="Split">Split</option>
                            <option value="Tabled">Tabled</option>
                            <option value="Withdrawn">Withdrawn</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resolutionVotesFor">Votes For</label>
                        <input type="number" id="resolutionVotesFor" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="resolutionVotesAgainst">Votes Against</label>
                        <input type="number" id="resolutionVotesAgainst" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label for="resolutionVotesAbstain">Votes Abstain</label>
                        <input type="number" id="resolutionVotesAbstain" min="0" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label for="resolutionEffectiveDate">Effective Date</label>
                    <input type="date" id="resolutionEffectiveDate">
                </div>
                <button type="submit" class="btn btn-primary">Save Resolution</button>
            </form>
        </div>
    </div>

    <!-- Document Upload Modal -->
    <div id="documentUploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDocumentUploadModal()">&times;</span>
            <h2 id="modalDocumentTitle">Upload Document</h2>
            <form id="documentUploadForm" enctype="multipart/form-data">
                <input type="hidden" id="documentAgendaItemId">
                <div class="form-group">
                    <label for="documentTitle">Document Title *</label>
                    <input type="text" id="documentTitle" required>
                </div>
                <div class="form-group">
                    <label for="documentDescription">Description</label>
                    <textarea id="documentDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="documentType">Document Type</label>
                    <select id="documentType">
                        <option value="Other">Other</option>
                        <option value="Agenda">Agenda</option>
                        <option value="Minutes">Minutes</option>
                        <option value="Resolution">Resolution</option>
                        <option value="Report">Report</option>
                        <option value="Policy">Policy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="documentFile">File *</label>
                    <input type="file" id="documentFile" required accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
                    <small style="color: #666;">Max size: 10MB. Allowed types: PDF, DOC, DOCX, XLS, XLSX, TXT</small>
                </div>
                <button type="submit" class="btn btn-primary">Upload Document</button>
            </form>
        </div>
    </div>

    <!-- Minutes Modal -->
    <div id="minutesModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeMinutesModal()">&times;</span>
            <h2 id="modalMinutesTitle">Create Minutes</h2>
            <form id="minutesForm" onsubmit="saveMinutes(event)">
                <input type="hidden" id="minutesId">
                <div class="form-group">
                    <label for="minutesStatus">Status</label>
                    <select id="minutesStatus">
                        <option value="Draft">Draft</option>
                        <option value="Review">Review</option>
                        <option value="Approved">Approved</option>
                        <option value="Published">Published</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="minutesPreparedBy">Prepared By</label>
                    <select id="minutesPreparedBy">
                        <option value="">Select member...</option>
                    </select>
                </div>
                <div class="form-group" style="display: none;">
                    <label for="minutesContent">Minutes Content</label>
                    <textarea id="minutesContent" rows="15"></textarea>
                </div>
                <div class="form-group">
                    <label for="minutesActionItems">Action Items</label>
                    <textarea id="minutesActionItems" rows="5"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="minutesNextMeetingDate">Next Meeting Date</label>
                        <input type="datetime-local" id="minutesNextMeetingDate">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Minutes</button>
            </form>
        </div>
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
                            <option value="Standing Committee">Standing Committee</option>
                            <option value="PiC">Prebytery in Council</option>
                            <option value="PRC">Pastoral Relations Commitee</option>
                            <option value="RPC">Property Board</option>
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
        let currentCommitteeId = null;
        let currentMeetingId = null;

        window.addEventListener('DOMContentLoaded', function() {
            loadCommittees();
            
            // Check if meeting ID is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const meetingId = urlParams.get('id');
            if (meetingId) {
                showMeetingDetail(meetingId);
            }
        });

        function loadCommittees() {
            fetch('api/committees.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('committeeSelect');
                    select.innerHTML = '<option value="">Select committee...</option>';
                    data.forEach(committee => {
                        const option = document.createElement('option');
                        option.value = committee.id;
                        option.textContent = committee.name;
                        select.appendChild(option);
                    });
                    if (data.length > 0) {
                        select.value = data[0].id;
                        currentCommitteeId = data[0].id;
                        loadMeetings();
                    }
                });
        }

        function loadMeetings() {
            currentCommitteeId = document.getElementById('committeeSelect').value;
            if (!currentCommitteeId) return;

            fetch(`api/meetings.php?committee_id=${currentCommitteeId}`)
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
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="margin: 0;">Agenda Items</h3>
                                <div>
                                    <a href="export/agenda.php?meeting_id=${meeting.id}" target="_blank" class="btn btn-sm btn-primary" style="text-decoration: none; display: inline-block;">
                                        📄 Export to PDF
                                    </a>
                                    <button onclick="addAgendaItem()" class="btn btn-sm btn-primary">+ Add Item</button>
                                </div>
                            </div>
                            <div id="agenda-items-list"></div>
                        </div>
                        <div id="tab-attendees" class="tab-content">
                            <h3>Attendees</h3>
                            <button onclick="addAttendee()" class="btn btn-sm btn-primary">+ Add Attendee</button>
                            <div id="attendees-list"></div>
                        </div>
                        <div id="tab-minutes" class="tab-content">
                            <h3>Meeting Minutes</h3>
                            <button onclick="createMinutes()" class="btn btn-sm btn-primary" id="createMinutesBtn" style="display:none;">Create Minutes</button>
                            <button onclick="editMinutes()" class="btn btn-sm btn-primary" id="editMinutesBtn" style="display:none;">Edit Minutes</button>
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
                    // Load documents for each agenda item
                    Promise.all(items.map(item => 
                        fetch(`api/documents.php?agenda_item_id=${item.id}`).then(r => r.json())
                    )).then(documentsArrays => {
                        list.innerHTML = items.map((item, index) => {
                            const documents = documentsArrays[index] || [];
                            return `
                                <div class="agenda-item ${item.resolution_id ? 'agenda-item-with-resolution' : ''}">
                                    <div class="item-header">
                                        <h4>${item.item_number ? item.item_number + '. ' : ''}${item.title}</h4>
                                        <div class="item-actions">
                                            ${item.resolution_id ? `<a href="#resolutions" onclick="showTab('resolutions'); event.preventDefault();" class="btn btn-sm" style="text-decoration: none; display: inline-block;">View Resolution</a>` : ''}
                                            <button onclick="showDocumentUploadModal(${item.id})" class="btn btn-sm">📎 Attach Document</button>
                                            <button onclick="editAgendaItem(${item.id})" class="btn btn-sm">Edit</button>
                                            <button onclick="deleteAgendaItem(${item.id})" class="btn btn-sm btn-danger">Delete</button>
                                        </div>
                                    </div>
                                    ${item.description ? `<p>${item.description}</p>` : ''}
                                    ${item.resolution_id ? `<div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 3px solid #28a745;">
                                        <strong>📋 Linked Resolution:</strong> ${item.resolution_title || 'Resolution'} 
                                        ${item.resolution_number ? `(#${item.resolution_number})` : ''}
                                        ${item.resolution_status ? `<span class="badge badge-${item.resolution_status.toLowerCase()}" style="margin-left: 8px;">${item.resolution_status}</span>` : ''}
                                    </div>` : ''}
                                    ${documents.length > 0 ? `
                                        <div style="background: #f0f8ff; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 3px solid #007bff;">
                                            <strong>📎 Attached Documents:</strong>
                                            <ul style="margin: 5px 0; padding-left: 20px;">
                                                ${documents.map(doc => `
                                                    <li>
                                                        <a href="api/download.php?id=${doc.id}" target="_blank" style="text-decoration: none; color: #007bff;">
                                                            ${doc.title || doc.file_name}
                                                        </a>
                                                        <button onclick="deleteDocument(${doc.id}, ${item.id})" class="btn btn-sm btn-danger" style="margin-left: 10px; padding: 2px 8px; font-size: 11px;">Delete</button>
                                                    </li>
                                                `).join('')}
                                            </ul>
                                        </div>
                                    ` : ''}
                                    <div class="agenda-meta">
                                        <span class="badge badge-${item.item_type.toLowerCase().replace(' ', '-')}">${item.item_type}</span>
                                        ${item.presenter_first_name ? `<span>Presenter: ${item.presenter_first_name} ${item.presenter_last_name}</span>` : ''}
                                        ${item.duration_minutes ? `<span>Duration: ${item.duration_minutes} min</span>` : ''}
                                        ${item.status ? `<span class="badge badge-${item.status.toLowerCase()}">${item.status}</span>` : ''}
                                    </div>
                                </div>
                            `;
                        }).join('');
                    });
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
                            <div>
                                <strong>${att.first_name} ${att.last_name}</strong>
                                ${att.role ? `(${att.role})` : ''}
                                ${att.title ? `<br><span style="font-size: 12px; color: #666;">${att.title}</span>` : ''}
                                ${att.attendance_status ? `<span class="badge badge-${att.attendance_status.toLowerCase()}" style="margin-left: 8px;">${att.attendance_status}</span>` : ''}
                                ${att.arrival_time ? `<br><span style="font-size: 12px; color: #666;">Arrived: ${formatDateTime(att.arrival_time)}</span>` : ''}
                            </div>
                            <div class="item-actions">
                                <button onclick="editAttendee(${att.id})" class="btn btn-sm">Edit</button>
                                <button onclick="deleteAttendee(${att.id})" class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </div>
                    `).join('');
                });
        }

        function loadMeetingMinutes(meetingId) {
            Promise.all([
                fetch(`api/minutes.php?meeting_id=${meetingId}`).then(r => r.json()),
                fetch(`api/agenda.php?meeting_id=${meetingId}`).then(r => r.json())
            ]).then(([minutes, agendaItems]) => {
                const content = document.getElementById('minutes-content');
                const createBtn = document.getElementById('createMinutesBtn');
                const editBtn = document.getElementById('editMinutesBtn');
                
                if (!minutes || minutes === null) {
                    content.innerHTML = '';
                    if (createBtn) createBtn.style.display = 'inline-block';
                    if (editBtn) editBtn.style.display = 'none';
                    return;
                }
                
                if (createBtn) createBtn.style.display = 'none';
                if (editBtn) editBtn.style.display = 'inline-block';
                
                // Create a map of agenda item comments
                const commentsMap = {};
                if (minutes.agenda_comments) {
                    minutes.agenda_comments.forEach(comment => {
                        commentsMap[comment.agenda_item_id] = comment.comment;
                    });
                }
                
                // Build agenda items with comments section
                let agendaItemsHtml = '';
                if (agendaItems && agendaItems.length > 0) {
                    agendaItemsHtml = '<div class="minutes-agenda-section"><h3>Agenda Items Discussion</h3>';
                    agendaItems.forEach(item => {
                        const comment = commentsMap[item.id] || '';
                        agendaItemsHtml += `
                            <div class="agenda-comment-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                <h4 style="margin: 0 0 10px 0; color: #333;">
                                    ${item.item_number ? item.item_number + '. ' : ''}${item.title}
                                    ${item.resolution_number ? `<span style="color: #007bff; font-weight: normal; margin-left: 10px;">(Resolution #${item.resolution_number})</span>` : ''}
                                    ${item.resolution_status ? `<span class="badge badge-${item.resolution_status.toLowerCase().replace(' ', '-')}" style="margin-left: 8px; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">${item.resolution_status}</span>` : ''}
                                </h4>
                                ${item.description ? `<p style="color: #666; margin: 5px 0 10px 0;">${item.description}</p>` : ''}
                                <div style="margin-top: 10px;">
                                    <strong>Discussion/Comments:</strong>
                                    ${minutes.status !== 'Approved' ? `
                                        <textarea class="agenda-comment-textarea" 
                                            data-agenda-item-id="${item.id}" 
                                            data-minutes-id="${minutes.id}"
                                            style="width: 100%; min-height: 60px; margin-top: 5px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"
                                            onblur="saveAgendaComment(${item.id}, ${minutes.id}, this.value)">${comment}</textarea>
                                    ` : `
                                        <div style="margin-top: 5px; padding: 10px; background: #f9f9f9; border-radius: 4px; white-space: pre-wrap;">${comment || '<em style="color: #999;">No comments recorded</em>'}</div>
                                    `}
                                </div>
                            </div>
                        `;
                    });
                    agendaItemsHtml += '</div>';
                }
                
                content.innerHTML = `
                    <div class="minutes-display">
                        <div class="minutes-header">
                            <p><strong>Status:</strong> <span class="badge badge-${minutes.status.toLowerCase()}">${minutes.status}</span></p>
                            <div>
                                ${minutes.status !== 'Approved' ? `<button onclick="editMinutes()" class="btn btn-sm">Edit</button>` : ''}
                                ${minutes.status === 'Draft' || minutes.status === 'Review' ? `<button onclick="approveMinutes()" class="btn btn-sm btn-primary">Approve</button>` : ''}
                                <button onclick="window.open('export/minutes.php?meeting_id=${meetingId}', '_blank')" class="btn btn-sm btn-primary">Export to PDF</button>
                            </div>
                        </div>
                        ${minutes.prepared_first_name ? `<p><strong>Prepared by:</strong> ${minutes.prepared_first_name} ${minutes.prepared_last_name}</p>` : ''}
                        ${minutes.approved_first_name ? `<p><strong>Approved by:</strong> ${minutes.approved_first_name} ${minutes.approved_last_name}</p>` : ''}
                        ${minutes.approved_at ? `<p><strong>Approved on:</strong> ${formatDateTime(minutes.approved_at)}</p>` : ''}
                        ${agendaItemsHtml}
                        ${minutes.action_items ? `<h4>Action Items</h4><div class="minutes-text">${minutes.action_items.replace(/\n/g, '<br>')}</div>` : ''}
                        ${minutes.next_meeting_date ? `<p><strong>Next Meeting:</strong> ${formatDateTime(minutes.next_meeting_date)}</p>` : ''}
                    </div>
                `;
            }).catch(error => {
                console.error('Error loading minutes:', error);
            });
        }
        
        function saveAgendaComment(agendaItemId, minutesId, comment) {
            if (!comment || comment.trim() === '') {
                // If empty, delete the comment
                fetch('api/minutes_comments.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: agendaItemId})
                }).catch(err => console.error('Error deleting comment:', err));
                return;
            }
            
            fetch('api/minutes_comments.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    minutes_id: minutesId,
                    agenda_item_id: agendaItemId,
                    comment: comment.trim()
                })
            }).catch(error => {
                console.error('Error saving agenda comment:', error);
                alert('Error saving comment');
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
                            <div class="item-header">
                                <h4>${res.title}</h4>
                                <div class="item-actions">
                                    <button onclick="editResolution(${res.id})" class="btn btn-sm">Edit</button>
                                    <button onclick="deleteResolution(${res.id})" class="btn btn-sm btn-danger">Delete</button>
                                </div>
                            </div>
                            <p>${res.description}</p>
                            ${res.resolution_number ? `<p><strong>Resolution #:</strong> ${res.resolution_number}</p>` : ''}
                            ${res.moved_first_name ? `<p><strong>Moved by:</strong> ${res.moved_first_name} ${res.moved_last_name}</p>` : ''}
                            ${res.seconded_first_name ? `<p><strong>Seconded by:</strong> ${res.seconded_first_name} ${res.seconded_last_name}</p>` : ''}
                            ${res.vote_type ? `<p><strong>Vote:</strong> ${res.votes_for} for, ${res.votes_against} against, ${res.votes_abstain} abstain (${res.vote_type})</p>` : ''}
                            <p><strong>Status:</strong> <span class="badge badge-${res.status.toLowerCase()}">${res.status}</span></p>
                        </div>
                    `).join('');
                });
        }

        function showMeetingModal(meeting = null) {
            if (!currentCommitteeId) {
                alert('Please select a committee first');
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
                committee_id: currentCommitteeId,
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

        // Agenda Item Management
        function addAgendaItem() {
            if (!currentMeetingId) return;
            showAgendaItemModal();
        }

        function editAgendaItem(id) {
            fetch(`api/agenda.php?id=${id}`)
                .then(response => response.json())
                .then(item => showAgendaItemModal(item));
        }

        function showAgendaItemModal(item = null) {
            loadBoardMembers().then(members => {
                const modal = document.getElementById('agendaItemModal');
                const form = document.getElementById('agendaItemForm');
                
                if (item) {
                    document.getElementById('agendaItemId').value = item.id;
                    document.getElementById('agendaItemTitle').value = item.title;
                    document.getElementById('agendaItemDescription').value = item.description || '';
                    document.getElementById('agendaItemType').value = item.item_type;
                    document.getElementById('agendaItemDuration').value = item.duration_minutes || '';
                    document.getElementById('agendaItemPresenter').value = item.presenter_id || '';
                    document.getElementById('modalAgendaTitle').textContent = 'Edit Agenda Item';
                } else {
                    form.reset();
                    document.getElementById('agendaItemId').value = '';
                    document.getElementById('modalAgendaTitle').textContent = 'New Agenda Item';
                }
                
                // Populate presenter dropdown
                const presenterSelect = document.getElementById('agendaItemPresenter');
                presenterSelect.innerHTML = '<option value="">Select presenter...</option>';
                members.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.id;
                    option.textContent = `${member.first_name} ${member.last_name} (${member.role})`;
                    presenterSelect.appendChild(option);
                });
                
                if (item && item.presenter_id) {
                    presenterSelect.value = item.presenter_id;
                }
                
                modal.style.display = 'block';
            });
        }

        function closeAgendaItemModal() {
            document.getElementById('agendaItemModal').style.display = 'none';
            document.getElementById('agendaItemForm').reset();
        }

        function saveAgendaItem(event) {
            event.preventDefault();
            const itemId = document.getElementById('agendaItemId').value;
            const data = {
                meeting_id: currentMeetingId,
                title: document.getElementById('agendaItemTitle').value,
                description: document.getElementById('agendaItemDescription').value,
                item_type: document.getElementById('agendaItemType').value,
                duration_minutes: document.getElementById('agendaItemDuration').value || null,
                presenter_id: document.getElementById('agendaItemPresenter').value || null
            };

            const method = itemId ? 'PUT' : 'POST';
            if (itemId) data.id = itemId;

            fetch('api/agenda.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeAgendaItemModal();
                loadMeetingAgenda(currentMeetingId);
            })
            .catch(error => {
                console.error('Error saving agenda item:', error);
                alert('Error saving agenda item');
            });
        }

        function deleteAgendaItem(id) {
            if (!confirm('Are you sure you want to delete this agenda item?')) return;
            
            fetch('api/agenda.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                loadMeetingAgenda(currentMeetingId);
            })
            .catch(error => {
                console.error('Error deleting agenda item:', error);
                alert('Error deleting agenda item');
            });
        }

        // Attendee Management
        function addAttendee() {
            if (!currentMeetingId) return;
            showAttendeeModal();
        }

        function editAttendee(id) {
            // Get the attendee from the current list or fetch individually
            fetch(`api/attendees.php?meeting_id=${currentMeetingId}`)
                .then(response => response.json())
                .then(attendees => {
                    const attendee = attendees.find(a => a.id == id);
                    if (attendee) {
                        // Ensure member_id is set correctly
                        showAttendeeModal(attendee);
                    }
                });
        }

        function showAttendeeModal(attendee = null) {
            loadBoardMembers().then(members => {
                const modal = document.getElementById('attendeeModal');
                const form = document.getElementById('attendeeForm');
                
                if (attendee) {
                    document.getElementById('attendeeId').value = attendee.id;
                    document.getElementById('attendeeMember').value = attendee.member_id;
                    document.getElementById('attendeeStatus').value = attendee.attendance_status;
                    document.getElementById('attendeeArrivalTime').value = attendee.arrival_time ? formatDateTimeInput(attendee.arrival_time) : '';
                    document.getElementById('attendeeNotes').value = attendee.notes || '';
                    document.getElementById('modalAttendeeTitle').textContent = 'Edit Attendee';
                } else {
                    form.reset();
                    document.getElementById('attendeeId').value = '';
                    document.getElementById('modalAttendeeTitle').textContent = 'Add Attendee';
                }
                
                // Populate member dropdown
                const memberSelect = document.getElementById('attendeeMember');
                memberSelect.innerHTML = '<option value="">Select member...</option>';
                members.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.id;
                    option.textContent = `${member.first_name} ${member.last_name} (${member.role})`;
                    memberSelect.appendChild(option);
                });
                
                if (attendee && attendee.member_id) {
                    memberSelect.value = attendee.member_id;
                }
                
                modal.style.display = 'block';
            });
        }

        function closeAttendeeModal() {
            document.getElementById('attendeeModal').style.display = 'none';
            document.getElementById('attendeeForm').reset();
        }

        function saveAttendee(event) {
            event.preventDefault();
            const attendeeId = document.getElementById('attendeeId').value;
            const arrivalTime = document.getElementById('attendeeArrivalTime').value;
            
            const data = {
                meeting_id: currentMeetingId,
                member_id: document.getElementById('attendeeMember').value,
                attendance_status: document.getElementById('attendeeStatus').value,
                arrival_time: arrivalTime ? arrivalTime.replace('T', ' ') + ':00' : null,
                notes: document.getElementById('attendeeNotes').value || null
            };

            const method = attendeeId ? 'PUT' : 'POST';
            if (attendeeId) {
                data.id = attendeeId;
            }

            fetch('api/attendees.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeAttendeeModal();
                loadMeetingAttendees(currentMeetingId);
            })
            .catch(error => {
                console.error('Error saving attendee:', error);
                alert('Error saving attendee');
            });
        }

        function deleteAttendee(id) {
            if (!confirm('Are you sure you want to remove this attendee?')) return;
            
            fetch('api/attendees.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                loadMeetingAttendees(currentMeetingId);
            })
            .catch(error => {
                console.error('Error deleting attendee:', error);
                alert('Error deleting attendee');
            });
        }

        // Resolution Management
        function addResolution() {
            if (!currentMeetingId) return;
            showResolutionModal();
        }

        function editResolution(id) {
            fetch(`api/resolutions.php?id=${id}`)
                .then(response => response.json())
                .then(resolution => showResolutionModal(resolution));
        }

        function showResolutionModal(resolution = null) {
            loadBoardMembers().then(members => {
                const modal = document.getElementById('resolutionModal');
                const form = document.getElementById('resolutionForm');
                
                if (resolution) {
                    document.getElementById('resolutionId').value = resolution.id;
                    document.getElementById('resolutionTitle').value = resolution.title;
                    document.getElementById('resolutionDescription').value = resolution.description;
                    document.getElementById('resolutionNumber').value = resolution.resolution_number || '';
                    document.getElementById('resolutionMovedBy').value = resolution.motion_moved_by || '';
                    document.getElementById('resolutionSecondedBy').value = resolution.motion_seconded_by || '';
                    document.getElementById('resolutionVoteType').value = resolution.vote_type || '';
                    document.getElementById('resolutionVotesFor').value = resolution.votes_for || 0;
                    document.getElementById('resolutionVotesAgainst').value = resolution.votes_against || 0;
                    document.getElementById('resolutionVotesAbstain').value = resolution.votes_abstain || 0;
                    document.getElementById('resolutionStatus').value = resolution.status;
                    document.getElementById('resolutionEffectiveDate').value = resolution.effective_date || '';
                    document.getElementById('modalResolutionTitle').textContent = 'Edit Resolution';
                } else {
                    form.reset();
                    document.getElementById('resolutionId').value = '';
                    document.getElementById('modalResolutionTitle').textContent = 'New Resolution';
                }
                
                // Populate member dropdowns
                const movedBySelect = document.getElementById('resolutionMovedBy');
                const secondedBySelect = document.getElementById('resolutionSecondedBy');
                
                movedBySelect.innerHTML = '<option value="">Select member...</option>';
                secondedBySelect.innerHTML = '<option value="">Select member...</option>';
                
                members.forEach(member => {
                    const option1 = document.createElement('option');
                    option1.value = member.id;
                    option1.textContent = `${member.first_name} ${member.last_name} (${member.role})`;
                    movedBySelect.appendChild(option1.cloneNode(true));
                    secondedBySelect.appendChild(option1);
                });
                
                if (resolution) {
                    if (resolution.motion_moved_by) movedBySelect.value = resolution.motion_moved_by;
                    if (resolution.motion_seconded_by) secondedBySelect.value = resolution.motion_seconded_by;
                }
                
                modal.style.display = 'block';
            });
        }

        function closeResolutionModal() {
            document.getElementById('resolutionModal').style.display = 'none';
            document.getElementById('resolutionForm').reset();
        }

        function saveResolution(event) {
            event.preventDefault();
            const resolutionId = document.getElementById('resolutionId').value;
            const data = {
                meeting_id: currentMeetingId,
                title: document.getElementById('resolutionTitle').value,
                description: document.getElementById('resolutionDescription').value,
                resolution_number: document.getElementById('resolutionNumber').value || null,
                motion_moved_by: document.getElementById('resolutionMovedBy').value || null,
                motion_seconded_by: document.getElementById('resolutionSecondedBy').value || null,
                vote_type: document.getElementById('resolutionVoteType').value || null,
                votes_for: parseInt(document.getElementById('resolutionVotesFor').value) || 0,
                votes_against: parseInt(document.getElementById('resolutionVotesAgainst').value) || 0,
                votes_abstain: parseInt(document.getElementById('resolutionVotesAbstain').value) || 0,
                status: document.getElementById('resolutionStatus').value,
                effective_date: document.getElementById('resolutionEffectiveDate').value || null
            };

            const method = resolutionId ? 'PUT' : 'POST';
            if (resolutionId) data.id = resolutionId;

            fetch('api/resolutions.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeResolutionModal();
                loadMeetingResolutions(currentMeetingId);
            })
            .catch(error => {
                console.error('Error saving resolution:', error);
                alert('Error saving resolution');
            });
        }

        function deleteResolution(id) {
            if (!confirm('Are you sure you want to delete this resolution?')) return;
            
            fetch('api/resolutions.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                loadMeetingResolutions(currentMeetingId);
            })
            .catch(error => {
                console.error('Error deleting resolution:', error);
                alert('Error deleting resolution');
            });
        }

        // Minutes Management
        function createMinutes() {
            if (!currentMeetingId) return;
            showMinutesModal();
        }

        function editMinutes() {
            fetch(`api/minutes.php?meeting_id=${currentMeetingId}`)
                .then(response => response.json())
                .then(minutes => {
                    if (minutes && minutes !== null) {
                        showMinutesModal(minutes);
                    } else {
                        showMinutesModal();
                    }
                });
        }

        function showMinutesModal(minutes = null) {
            loadBoardMembers().then(members => {
                const modal = document.getElementById('minutesModal');
                const form = document.getElementById('minutesForm');
                
                if (minutes) {
                    document.getElementById('minutesId').value = minutes.id;
                    document.getElementById('minutesContent').value = minutes.content;
                    document.getElementById('minutesActionItems').value = minutes.action_items || '';
                    document.getElementById('minutesNextMeetingDate').value = minutes.next_meeting_date ? formatDateTimeInput(minutes.next_meeting_date) : '';
                    document.getElementById('minutesStatus').value = minutes.status;
                    document.getElementById('minutesPreparedBy').value = minutes.prepared_by || '';
                    document.getElementById('modalMinutesTitle').textContent = 'Edit Minutes';
                } else {
                    form.reset();
                    document.getElementById('minutesId').value = '';
                    document.getElementById('modalMinutesTitle').textContent = 'Create Minutes';
                }
                
                // Populate prepared by dropdown
                const preparedBySelect = document.getElementById('minutesPreparedBy');
                preparedBySelect.innerHTML = '<option value="">Select member...</option>';
                members.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.id;
                    option.textContent = `${member.first_name} ${member.last_name} (${member.role})`;
                    preparedBySelect.appendChild(option);
                });
                
                if (minutes && minutes.prepared_by) {
                    preparedBySelect.value = minutes.prepared_by;
                }
                
                modal.style.display = 'block';
            });
        }

        function closeMinutesModal() {
            document.getElementById('minutesModal').style.display = 'none';
            document.getElementById('minutesForm').reset();
        }

        function saveMinutes(event) {
            event.preventDefault();
            const minutesId = document.getElementById('minutesId').value;
            const nextMeetingDate = document.getElementById('minutesNextMeetingDate').value;
            
            const data = {
                meeting_id: currentMeetingId,
                content: document.getElementById('minutesContent').value,
                action_items: document.getElementById('minutesActionItems').value || null,
                next_meeting_date: nextMeetingDate ? nextMeetingDate.replace('T', ' ') + ':00' : null,
                status: document.getElementById('minutesStatus').value,
                prepared_by: document.getElementById('minutesPreparedBy').value || null
            };

            const method = minutesId ? 'PUT' : 'POST';
            if (minutesId) data.id = minutesId;

            fetch('api/minutes.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                closeMinutesModal();
                loadMeetingMinutes(currentMeetingId);
            })
            .catch(error => {
                console.error('Error saving minutes:', error);
                alert('Error saving minutes');
            });
        }

        function approveMinutes() {
            if (!confirm('Are you sure you want to approve these minutes?')) return;
            
            fetch(`api/minutes.php?meeting_id=${currentMeetingId}`)
                .then(response => response.json())
                .then(minutes => {
                    if (!minutes || minutes === null) {
                        alert('Minutes not found');
                        return;
                    }
                    
                    loadBoardMembers().then(members => {
                        const approverId = prompt('Enter the ID of the approving member, or select from:\n' + 
                            members.map(m => `${m.id}: ${m.first_name} ${m.last_name}`).join('\n'));
                        if (!approverId) return;
                        
                        fetch('api/minutes.php', {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                id: minutes.id,
                                approve: true,
                                approved_by: approverId,
                                status: 'Approved'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            loadMeetingMinutes(currentMeetingId);
                        })
                        .catch(error => {
                            console.error('Error approving minutes:', error);
                            alert('Error approving minutes');
                        });
                    });
                });
        }

        function showDocumentUploadModal(agendaItemId) {
            const modal = document.getElementById('documentUploadModal');
            const form = document.getElementById('documentUploadForm');
            document.getElementById('documentAgendaItemId').value = agendaItemId;
            form.reset();
            modal.style.display = 'block';
        }

        function closeDocumentUploadModal() {
            document.getElementById('documentUploadModal').style.display = 'none';
            document.getElementById('documentUploadForm').reset();
        }

        document.getElementById('documentUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            const agendaItemId = document.getElementById('documentAgendaItemId').value;
            const fileInput = document.getElementById('documentFile');
            
            if (!fileInput.files[0]) {
                alert('Please select a file');
                return;
            }
            
            formData.append('file', fileInput.files[0]);
            formData.append('title', document.getElementById('documentTitle').value);
            formData.append('description', document.getElementById('documentDescription').value);
            formData.append('document_type', document.getElementById('documentType').value);
            formData.append('meeting_id', currentMeetingId);
            formData.append('agenda_item_id', agendaItemId);
            
            fetch('api/documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Upload failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    closeDocumentUploadModal();
                    loadMeetingAgenda(currentMeetingId);
                }
            })
            .catch(error => {
                console.error('Error uploading document:', error);
                alert('Error uploading document: ' + error.message);
            });
        });

        function deleteDocument(documentId, agendaItemId) {
            if (!confirm('Are you sure you want to delete this document?')) return;
            
            fetch('api/documents.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: documentId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    loadMeetingAgenda(currentMeetingId);
                }
            })
            .catch(error => {
                console.error('Error deleting document:', error);
                alert('Error deleting document');
            });
        }

        // Utility function to load board members for current committee with their roles
        function loadBoardMembers() {
            if (!currentCommitteeId) return Promise.resolve([]);
            // Get committee members which includes role for this committee
            return fetch(`api/committee_members.php?committee_id=${currentCommitteeId}`)
                .then(response => response.json())
                .then(committeeMembers => {
                    // Transform to format expected by other functions
                    return committeeMembers.map(cm => ({
                        id: cm.member_id,
                        first_name: cm.first_name,
                        last_name: cm.last_name,
                        email: cm.email,
                        phone: cm.phone,
                        title: cm.title,
                        role: cm.role  // Role in this committee
                    }));
                })
                .catch(error => {
                    console.error('Error loading board members:', error);
                    return [];
                });
        }

        function formatDateTimeInput(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        window.onclick = function(event) {
            const modals = ['meetingModal', 'agendaItemModal', 'attendeeModal', 'resolutionModal', 'minutesModal', 'documentUploadModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    if (modalId === 'meetingModal') closeMeetingModal();
                    else if (modalId === 'agendaItemModal') closeAgendaItemModal();
                    else if (modalId === 'documentUploadModal') closeDocumentUploadModal();
                    else if (modalId === 'attendeeModal') closeAttendeeModal();
                    else if (modalId === 'resolutionModal') closeResolutionModal();
                    else if (modalId === 'minutesModal') closeMinutesModal();
                }
            });
        }
    </script>
</body>
</html>

