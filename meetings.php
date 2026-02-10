<?php
require_once __DIR__ . '/includes/header.php';
outputHeader('Meetings', 'meetings.php');
?>

        <main>
            <div class="page-header">
                <h2>Meetings</h2>
                <button onclick="showMeetingModal()" class="btn btn-primary">+ New Meeting</button>
            </div>

            <div class="organization-selector">
                <label for="meetingTypeSelect">Meeting Type:</label>
                <select id="meetingTypeSelect" onchange="loadMeetings()">
                    <option value="">Select meeting type...</option>
                </select>
                <button onclick="showTemplateModal()" class="btn btn-secondary" id="manageTemplatesBtn" style="margin-left: 10px; display: none;">Manage Agenda Template</button>
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
                <div class="form-group">
                    <label for="agendaItemParent">Parent Item (optional)</label>
                    <select id="agendaItemParent">
                        <option value="">No parent (top-level)</option>
                    </select>
                    <small style="color: #666;">Choose a parent to create a sub-item (a, b, c...)</small>
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
                    <label for="attendeeMember">Member *</label>
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
                <div class="form-group" id="resolutionParentGroup">
                    <label for="resolutionParentAgendaItem">Link Agenda Item (Optional)</label>
                    <select id="resolutionParentAgendaItem">
                        <option value="">No linked agenda item</option>
                    </select>
                    <small style="color: #666;">Select an agenda item or sub-item to link this resolution.</small>
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
                            <option value="Consensus">Consensus</option>
                            <option value="Agreement">Agreement</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resolutionVoteType">Vote Type</label>
                        <select id="resolutionVoteType">
                            <option value="">Select vote type...</option>
                            <option value="Cards">Cards</option>
                            <option value="Formal Procedures">Formal Procedures</option>
                            <option value="Show of Hands">Show of Hands</option>
                        </select>
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
                    <input type="file" id="documentFile" required accept=".pdf,application/pdf">
                    <small style="color: #666;">Max size: 10MB. Only PDF files are allowed for agenda items.</small>
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
                        <label for="meetingTypeId">Meeting Type *</label>
                        <select id="meetingTypeId" required>
                            <option value="">Select meeting type...</option>
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
                <div class="form-group" id="applyTemplateGroup">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="applyTemplate" checked>
                        Apply default agenda template
                    </label>
                    <small style="color: #666;">Pre-populate agenda with standard items for this meeting type</small>
                </div>
                <button type="submit" class="btn btn-primary">Save Meeting</button>
            </form>
        </div>
    </div>

    <!-- Agenda Template Modal -->
    <div id="templateModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeTemplateModal()">&times;</span>
            <h2>Manage Agenda Template</h2>
            <p style="color: #666; margin-bottom: 20px;">Define default agenda items that will be automatically added when creating new meetings of this type.</p>
            
            <div style="margin-bottom: 15px;">
                <button onclick="showTemplateItemModal()" class="btn btn-primary">+ Add Template Item</button>
            </div>
            
            <div id="template-items-list"></div>
        </div>
    </div>

    <!-- Template Item Modal -->
    <div id="templateItemModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTemplateItemModal()">&times;</span>
            <h2 id="modalTemplateItemTitle">New Template Item</h2>
            <form id="templateItemForm" onsubmit="saveTemplateItem(event)">
                <input type="hidden" id="templateItemId">
                <div class="form-group">
                    <label for="templateItemTitle">Title *</label>
                    <input type="text" id="templateItemTitle" required>
                </div>
                <div class="form-group">
                    <label for="templateItemDescription">Description</label>
                    <textarea id="templateItemDescription" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="templateItemType">Item Type *</label>
                        <select id="templateItemType" required>
                            <option value="Discussion">Discussion</option>
                            <option value="Action Item">Action Item</option>
                            <option value="Vote">Vote</option>
                            <option value="Information">Information</option>
                            <option value="Presentation">Presentation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="templateItemDuration">Duration (minutes)</label>
                        <input type="number" id="templateItemDuration" min="0">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Template Item</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        
        let currentMeetingTypeId = null;
        let currentMeetingId = null;
        let allMeetingTypes = [];

        window.addEventListener('DOMContentLoaded', function() {
            loadMeetingTypes();
            
            // Check if meeting ID is in URL
            const urlParams = new URLSearchParams(window.location.search);
            const meetingId = urlParams.get('id');
            if (meetingId) {
                showMeetingDetail(meetingId);
            }
        });

        function loadMeetingTypes() {
            fetch('api/meeting_types.php')
                .then(response => response.json())
                .then(data => {
                    allMeetingTypes = data;
                    const select = document.getElementById('meetingTypeSelect');
                    const meetingTypeSelect = document.getElementById('meetingTypeId');
                    
                    select.innerHTML = '<option value="">Select meeting type...</option>';
                    meetingTypeSelect.innerHTML = '<option value="">Select meeting type...</option>';
                    
                    data.forEach(meetingType => {
                        const option = document.createElement('option');
                        option.value = meetingType.id;
                        option.textContent = meetingType.name;
                        select.appendChild(option);
                        
                        const option2 = option.cloneNode(true);
                        meetingTypeSelect.appendChild(option2);
                    });
                    
                    if (data.length > 0) {
                        select.value = data[0].id;
                        currentMeetingTypeId = data[0].id;
                        loadMeetings();
                    }
                });
        }

        function loadMeetings() {
            currentMeetingTypeId = document.getElementById('meetingTypeSelect').value;
            const manageTemplatesBtn = document.getElementById('manageTemplatesBtn');
            if (manageTemplatesBtn) {
                manageTemplatesBtn.style.display = currentMeetingTypeId ? 'inline-block' : 'none';
            }
            if (!currentMeetingTypeId) return;

            fetch(`api/meetings.php?meeting_type_id=${currentMeetingTypeId}`)
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
                                <a href="export/notice.php?meeting_id=${meeting.id}" target="_blank" class="btn btn-primary" style="text-decoration: none; display: inline-block; margin-right: 5px;">
                                    📋 Generate Notice
                                </a>
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
                                const isChild = item.parent_id && item.parent_id !== null;
                                const indentStyle = isChild ? 'style="margin-left: 22px;"' : '';
                            const documents = documentsArrays[index] || [];
                            const isFirst = index === 0;
                            const isLast = index === items.length - 1;
                            return `
                                      <div class="agenda-item ${item.resolution_id ? 'agenda-item-with-resolution' : ''}" ${indentStyle}
                                          draggable="true" 
                                          data-item-id="${item.id}" 
                                          data-parent-id="${item.parent_id || ''}"
                                          data-position="${item.position}">
                                    <div class="item-header">
                                        <div class="item-drag-handle" title="Drag to reorder">
                                            <span class="drag-icon">☰</span>
                                        </div>
                                            <h4>${item.item_number ? item.item_number + '. ' : ''}${item.title}</h4>
                                        <div class="item-actions">
                                            <div class="reorder-buttons">
                                                <button onclick="moveAgendaItemUp(${item.id})" 
                                                        class="btn btn-sm btn-reorder" 
                                                        title="Move up"
                                                        ${isFirst ? 'disabled' : ''}
                                                        style="padding: 4px 8px; min-width: auto;">
                                                    ↑
                                                </button>
                                                <button onclick="moveAgendaItemDown(${item.id})" 
                                                        class="btn btn-sm btn-reorder" 
                                                        title="Move down"
                                                        ${isLast ? 'disabled' : ''}
                                                        style="padding: 4px 8px; min-width: auto;">
                                                    ↓
                                                </button>
                                            </div>
                                            ${item.resolution_id ? `<a href="#resolutions" onclick="showTab('resolutions'); event.preventDefault();" class="btn btn-sm" style="text-decoration: none; display: inline-block;">View Resolution</a>` : ''}
                                            <button onclick="showDocumentUploadModal(${item.id})" class="btn btn-sm">📎 Attach Document</button>
                                            <button onclick="editAgendaItem(${item.id})" class="btn btn-sm">Edit</button>
                                            <button onclick="deleteAgendaItem(${item.id})" class="btn btn-sm btn-danger">Delete</button>
                                        </div>
                                    </div>
                                    ${item.description ? `<p>${item.description}</p>` : ''}
                                    ${item.resolution_id ? `<div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 3px solid #28a745;">
                                        <div>
                                            <strong>📋 Linked Resolution:</strong> ${item.resolution_title || 'Resolution'}
                                            ${item.resolution_number ? `(#${item.resolution_number})` : ''}
                                            ${item.resolution_status ? `<span class="badge badge-${item.resolution_status.toLowerCase()}" style="margin-left: 8px;">${item.resolution_status}</span>` : ''}
                                        </div>
                                        ${item.resolution_vote_type ? `<div style="margin-top: 4px; color: #2f6f46;">Vote Type: ${item.resolution_vote_type}</div>` : ''}
                                        ${item.resolution_effective_date ? `<div style="margin-top: 4px; color: #2f6f46;">Effective: ${formatDateTime(item.resolution_effective_date)}</div>` : ''}
                                        ${item.resolution_description ? `<div style="margin-top: 6px; color: #2f6f46;">${item.resolution_description}</div>` : ''}
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
                        // Initialize drag-and-drop after items are rendered
                        makeAgendaItemsSortable(meetingId);
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
                                    ${item.resolution_id && minutes.status !== 'Approved' ? `<button onclick="editResolution(${item.resolution_id})" class="btn btn-sm" style="margin-left: 8px;">Edit Resolution</button>` : ''}
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
                            ${res.vote_type ? `<p><strong>Vote Type:</strong> ${res.vote_type}</p>` : ''}
                            <p><strong>Status:</strong> <span class="badge badge-${res.status.toLowerCase()}">${res.status}</span></p>
                        </div>
                    `).join('');
                });
        }

        function showMeetingModal(meeting = null) {
            if (!currentMeetingTypeId) {
                alert('Please select a meeting type first');
                return;
            }

            const modal = document.getElementById('meetingModal');
            const form = document.getElementById('meetingForm');
            const title = document.getElementById('modalTitle');
            
            const applyTemplateGroup = document.getElementById('applyTemplateGroup');
            
            if (meeting) {
                title.textContent = 'Edit Meeting';
                document.getElementById('meetingId').value = meeting.id;
                document.getElementById('meetingTitle').value = meeting.title;
                document.getElementById('meetingTypeId').value = meeting.meeting_type_id || currentMeetingTypeId;
                document.getElementById('scheduledDate').value = meeting.scheduled_date.replace(' ', 'T').substring(0, 16);
                document.getElementById('location').value = meeting.location || '';
                document.getElementById('virtualLink').value = meeting.virtual_link || '';
                document.getElementById('quorumRequired').value = meeting.quorum_required || 0;
                document.getElementById('meetingStatus').value = meeting.status;
                document.getElementById('meetingNotes').value = meeting.notes || '';
                // Hide template option for editing existing meetings
                if (applyTemplateGroup) applyTemplateGroup.style.display = 'none';
            } else {
                title.textContent = 'New Meeting';
                form.reset();
                document.getElementById('meetingId').value = '';
                document.getElementById('meetingTypeId').value = currentMeetingTypeId;
                // Show template option for new meetings
                if (applyTemplateGroup) applyTemplateGroup.style.display = 'block';
                document.getElementById('applyTemplate').checked = true;
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
                meeting_type_id: document.getElementById('meetingTypeId').value || currentMeetingTypeId,
                title: document.getElementById('meetingTitle').value,
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
            } else {
                // Only apply template on new meetings
                data.apply_template = document.getElementById('applyTemplate').checked;
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

                // Populate parent dropdown with top-level items for current meeting
                const parentSelect = document.getElementById('agendaItemParent');
                parentSelect.innerHTML = '<option value="">No parent (top-level)</option>';
                if (currentMeetingId) {
                    fetch(`api/agenda.php?meeting_id=${currentMeetingId}`)
                        .then(r => r.json())
                        .then(allItems => {
                            // Only allow selecting top-level items as parent
                            allItems.filter(i => !i.parent_id).forEach(i => {
                                // Do not allow an item to be parent of itself
                                if (item && item.id && item.id == i.id) return;
                                const opt = document.createElement('option');
                                opt.value = i.id;
                                opt.textContent = (i.item_number ? i.item_number + '. ' : '') + i.title;
                                parentSelect.appendChild(opt);
                            });

                            if (item && item.parent_id) {
                                parentSelect.value = item.parent_id;
                            }
                        }).catch(err => {
                            console.error('Error loading parent items:', err);
                        });
                }
                
                modal.style.display = 'block';
            });
        }

        function closeAgendaItemModal() {
            document.getElementById('agendaItemModal').style.display = 'none';
            document.getElementById('agendaItemForm').reset();
        }

        async function saveAgendaItem(event) {
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

            const parentVal = document.getElementById('agendaItemParent').value;
            if (parentVal) data.parent_id = parentVal;

            // UI validation: prevent selecting a descendant as the parent (would create a cycle)
            if (parentVal && itemId) {
                try {
                    const resp = await fetch(`api/agenda.php?meeting_id=${currentMeetingId}`);
                    const allItems = await resp.json();
                    const parentMap = {};
                    allItems.forEach(i => { parentMap[i.id] = i.parent_id; });

                    // Walk up from the chosen parent; if we encounter the item itself, it's invalid
                    let cur = parseInt(parentVal);
                    const originalId = parseInt(itemId);
                    while (cur) {
                        if (cur === originalId) {
                            alert('Invalid parent selection: an item cannot be a child of its own descendant.');
                            return;
                        }
                        cur = parentMap[cur] ? parseInt(parentMap[cur]) : null;
                    }
                } catch (err) {
                    console.error('Error validating parent selection:', err);
                    alert('Could not validate parent selection. Please try again.');
                    return;
                }
            }

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

        // Agenda Item Reordering
        let draggedElement = null;
        let draggedIndex = null;

        function makeAgendaItemsSortable(meetingId) {
            const list = document.getElementById('agenda-items-list');
            if (!list) return;

            const items = list.querySelectorAll('.agenda-item');
            
            items.forEach((item, index) => {
                // Remove existing listeners to avoid duplicates
                const newItem = item.cloneNode(true);
                item.parentNode.replaceChild(newItem, item);
            });

            // Re-query after cloning
            const updatedItems = list.querySelectorAll('.agenda-item');
            
            updatedItems.forEach((item, index) => {
                item.addEventListener('dragstart', (e) => {
                    // If dragging a parent, include its children in the dragged group
                    const draggedId = parseInt(item.getAttribute('data-item-id'));
                    const draggedParentId = item.getAttribute('data-parent-id');
                    let group = [item];
                    if (!draggedParentId) {
                        // collect immediate child rows that follow this parent
                        let next = item.nextElementSibling;
                        while (next && next.classList.contains('agenda-item') && next.getAttribute('data-parent-id') == draggedId) {
                            group.push(next);
                            next = next.nextElementSibling;
                        }
                    }
                    draggedElement = group;
                    draggedIndex = index;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', item.innerHTML);
                });

                item.addEventListener('dragend', (e) => {
                    // remove dragging class from group
                    if (Array.isArray(draggedElement)) {
                        draggedElement.forEach(el => el.classList.remove('dragging'));
                    } else if (draggedElement) {
                        draggedElement.classList.remove('dragging');
                    }
                    // Remove drop indicator classes
                    updatedItems.forEach(i => i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom'));
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    const rect = item.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    
                    // Remove all drag-over classes
                    updatedItems.forEach(i => i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom'));
                    
                    if (item !== draggedElement) {
                        if (e.clientY < midY) {
                            item.classList.add('drag-over-top');
                        } else {
                            item.classList.add('drag-over-bottom');
                        }
                    }
                });

                item.addEventListener('dragleave', (e) => {
                    item.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom');
                });

                item.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (draggedElement && draggedElement !== item) {
                        const dropIndex = Array.from(updatedItems).indexOf(item);
                        const rect = item.getBoundingClientRect();
                        const midY = rect.top + rect.height / 2;
                        const insertBefore = e.clientY < midY;
                        
                        const finalIndex = insertBefore ? dropIndex : dropIndex + 1;

                        // Reorder in DOM
                        if (Array.isArray(draggedElement)) {
                            // Insert group: detach all and insert in order
                            const parent = item.parentNode;
                            // Determine reference node
                            const refNode = insertBefore ? item : item.nextSibling;
                            draggedElement.forEach(el => parent.removeChild(el));
                            // Insert them preserving order
                            for (let i = 0; i < draggedElement.length; i++) {
                                parent.insertBefore(draggedElement[i], refNode);
                            }
                        } else {
                            if (draggedIndex < finalIndex) {
                                item.parentNode.insertBefore(draggedElement, item.nextSibling);
                            } else {
                                item.parentNode.insertBefore(draggedElement, item);
                            }
                        }
                        
                        // Update positions via API
                        const newOrder = Array.from(list.querySelectorAll('.agenda-item')).map(el => 
                            parseInt(el.getAttribute('data-item-id'))
                        );
                        reorderAgendaItems(meetingId, newOrder);
                    }
                    
                    // Clean up
                    updatedItems.forEach(i => i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom'));
                });
            });
        }

        function moveAgendaItemUp(itemId) {
            if (!currentMeetingId) return;
            
            const list = document.getElementById('agenda-items-list');
            const items = Array.from(list.querySelectorAll('.agenda-item'));
            const currentIndex = items.findIndex(item => 
                parseInt(item.getAttribute('data-item-id')) === itemId
            );
            
            if (currentIndex <= 0) return; // Already at top
            
            // Move group (parent + children) up together
            const currentItem = items[currentIndex];
            // collect group for current
            const group = [currentItem];
            let next = currentItem.nextElementSibling;
            const currentId = currentItem.getAttribute('data-item-id');
            while (next && next.classList.contains('agenda-item') && next.getAttribute('data-parent-id') == currentId) {
                group.push(next);
                next = next.nextElementSibling;
            }

            const previousItem = items[currentIndex - 1];
            const parent = currentItem.parentNode;
            // Insert group before previousItem
            group.forEach(el => parent.removeChild(el));
            parent.insertBefore(group[0], previousItem);
            for (let i = 1; i < group.length; i++) parent.insertBefore(group[i], previousItem);
            
            // Update positions via API
            const newOrder = Array.from(list.querySelectorAll('.agenda-item')).map(el => 
                parseInt(el.getAttribute('data-item-id'))
            );
            reorderAgendaItems(currentMeetingId, newOrder);
        }

        function moveAgendaItemDown(itemId) {
            if (!currentMeetingId) return;
            
            const list = document.getElementById('agenda-items-list');
            const items = Array.from(list.querySelectorAll('.agenda-item'));
            const currentIndex = items.findIndex(item => 
                parseInt(item.getAttribute('data-item-id')) === itemId
            );
            
            if (currentIndex < 0 || currentIndex >= items.length - 1) return; // Already at bottom
            
            // Move group (parent + children) down together
            const currentItem = items[currentIndex];
            const group = [currentItem];
            let next = currentItem.nextElementSibling;
            const currentId = currentItem.getAttribute('data-item-id');
            while (next && next.classList.contains('agenda-item') && next.getAttribute('data-parent-id') == currentId) {
                group.push(next);
                next = next.nextElementSibling;
            }

            const nextItem = items[currentIndex + 1];
            const parent = currentItem.parentNode;
            // Insert group after nextItem
            // Remove group first
            group.forEach(el => parent.removeChild(el));
            const ref = nextItem.nextSibling;
            for (let i = 0; i < group.length; i++) parent.insertBefore(group[i], ref);
            
            // Update positions via API
            const newOrder = Array.from(list.querySelectorAll('.agenda-item')).map(el => 
                parseInt(el.getAttribute('data-item-id'))
            );
            reorderAgendaItems(currentMeetingId, newOrder);
        }

        function reorderAgendaItems(meetingId, newOrder) {
            // newOrder is an array of item IDs in the new order
            fetch('api/agenda.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'reorder',
                    meeting_id: meetingId,
                    order: newOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error reordering items:', data.error);
                    alert('Error reordering agenda items. Reloading...');
                    loadMeetingAgenda(meetingId);
                } else {
                    // Reload to get updated item numbers
                    loadMeetingAgenda(meetingId);
                }
            })
            .catch(error => {
                console.error('Error reordering agenda items:', error);
                alert('Error reordering agenda items. Reloading...');
                loadMeetingAgenda(meetingId);
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
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Delete failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    loadMeetingAttendees(currentMeetingId);
                }
            })
            .catch(error => {
                console.error('Error deleting attendee:', error);
                alert('Error deleting attendee: ' + error.message);
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
            const modal = document.getElementById('resolutionModal');
            const form = document.getElementById('resolutionForm');
            
            // Populate agenda item dropdown (include sub-items)
            const parentSelect = document.getElementById('resolutionParentAgendaItem');
            parentSelect.innerHTML = '<option value="">No linked agenda item</option>';
            
            if (currentMeetingId) {
                fetch(`api/agenda.php?meeting_id=${currentMeetingId}`)
                    .then(r => r.json())
                    .then(allItems => {
                        allItems.forEach(i => {
                            const opt = document.createElement('option');
                            opt.value = i.id;
                            const prefix = i.parent_id ? '— ' : '';
                            opt.textContent = prefix + (i.item_number ? i.item_number + '. ' : '') + i.title;
                            parentSelect.appendChild(opt);
                        });

                        if (resolution && resolution.agenda_item_id) {
                            parentSelect.value = resolution.agenda_item_id;
                        }
                    })
                    .catch(err => {
                        console.error('Error loading parent items:', err);
                    });
            }
            
            if (resolution) {
                document.getElementById('resolutionId').value = resolution.id;
                document.getElementById('resolutionTitle').value = resolution.title;
                document.getElementById('resolutionDescription').value = resolution.description;
                document.getElementById('resolutionNumber').value = resolution.resolution_number || '';
                document.getElementById('resolutionVoteType').value = resolution.vote_type || '';
                document.getElementById('resolutionStatus').value = resolution.status;
                document.getElementById('resolutionEffectiveDate').value = resolution.effective_date || '';
                document.getElementById('modalResolutionTitle').textContent = 'Edit Resolution';
                // Disable parent selection when editing (parent cannot be changed after creation)
                document.getElementById('resolutionParentAgendaItem').disabled = false;
                document.getElementById('resolutionParentGroup').style.opacity = '0.6';
            } else {
                form.reset();
                document.getElementById('resolutionId').value = '';
                document.getElementById('modalResolutionTitle').textContent = 'New Resolution';
                // Enable parent selection for new resolutions
                document.getElementById('resolutionParentAgendaItem').disabled = false;
                document.getElementById('resolutionParentGroup').style.opacity = '1';
            }
            
            modal.style.display = 'block';
        }

        function closeResolutionModal() {
            document.getElementById('resolutionModal').style.display = 'none';
            document.getElementById('resolutionForm').reset();
        }

        function saveResolution(event) {
            event.preventDefault();
            const resolutionId = document.getElementById('resolutionId').value;
            const parentAgendaItemId = document.getElementById('resolutionParentAgendaItem').value;
            const data = {
                meeting_id: currentMeetingId,
                title: document.getElementById('resolutionTitle').value,
                description: document.getElementById('resolutionDescription').value,
                resolution_number: document.getElementById('resolutionNumber').value || null,
                vote_type: document.getElementById('resolutionVoteType').value || null,
                status: document.getElementById('resolutionStatus').value,
                effective_date: document.getElementById('resolutionEffectiveDate').value || null
            };
            
            if (parentAgendaItemId && parentAgendaItemId !== '') {
                const agendaItemId = parseInt(parentAgendaItemId);
                if (!isNaN(agendaItemId)) {
                    data.agenda_item_id = agendaItemId;
                }
            } else {
                data.agenda_item_id = null;
            }

            const method = resolutionId ? 'PUT' : 'POST';
            if (resolutionId) data.id = resolutionId;

            fetch('api/resolutions.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(async response => {
                const text = await response.text();
                let jsonData;
                try {
                    jsonData = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Server returned invalid response. Check console for details.');
                }
                
                if (!response.ok) {
                    throw new Error(jsonData.error || 'Error saving resolution');
                }
                
                if (jsonData.error) {
                    throw new Error(jsonData.error);
                }
                
                return jsonData;
            })
            .then(data => {
                closeResolutionModal();
                loadMeetingResolutions(currentMeetingId);
                // Also reload agenda items to show the new sub-item if created
                if (!resolutionId) {
                    loadMeetingAgenda(currentMeetingId);
                }
            })
            .catch(error => {
                console.error('Error saving resolution:', error);
                alert('Error saving resolution: ' + error.message);
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
            
            // Validate PDF for agenda items
            const file = fileInput.files[0];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (fileExtension !== 'pdf' && file.type !== 'application/pdf') {
                alert('Only PDF files are allowed for agenda items');
                return;
            }
            
            formData.append('file', file);
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

        // Utility function to load board members for current meeting type with their roles
        function loadBoardMembers() {
            if (!currentMeetingTypeId) return Promise.resolve([]);
            // Get meeting type members which includes role for this meeting type
            return fetch(`api/meeting_type_members.php?meeting_type_id=${currentMeetingTypeId}`)
                .then(response => response.json())
                .then(meetingTypeMembers => {
                    // Transform to format expected by other functions
                    return meetingTypeMembers.map(mtm => ({
                        id: mtm.member_id,
                        first_name: mtm.first_name,
                        last_name: mtm.last_name,
                        email: mtm.email,
                        phone: mtm.phone,
                        title: mtm.title,
                        role: mtm.role  // Role in this meeting type
                    }));
                })
                .catch(error => {
                    console.error('Error loading council members:', error);
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

        // Template Management Functions
        function showTemplateModal() {
            if (!currentMeetingTypeId) {
                alert('Please select a meeting type first');
                return;
            }
            document.getElementById('templateModal').style.display = 'block';
            loadTemplateItems();
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        function loadTemplateItems() {
            if (!currentMeetingTypeId) return;
            
            fetch(`api/agenda_templates.php?meeting_type_id=${currentMeetingTypeId}`)
                .then(response => response.json())
                .then(items => {
                    const list = document.getElementById('template-items-list');
                    if (items.length === 0) {
                        list.innerHTML = '<p style="color: #666;">No template items defined. Add items to create a default agenda for new meetings.</p>';
                        return;
                    }
                    
                    list.innerHTML = items.map((item, index) => {
                        const isFirst = index === 0;
                        const isLast = index === items.length - 1;
                        return `
                            <div class="agenda-item" style="margin-bottom: 10px;">
                                <div class="item-header">
                                    <h4>${index + 1}. ${item.title}</h4>
                                    <div class="item-actions">
                                        <button onclick="moveTemplateItemUp(${item.id})" 
                                                class="btn btn-sm" 
                                                title="Move up"
                                                ${isFirst ? 'disabled' : ''}
                                                style="padding: 4px 8px; min-width: auto;">↑</button>
                                        <button onclick="moveTemplateItemDown(${item.id})" 
                                                class="btn btn-sm" 
                                                title="Move down"
                                                ${isLast ? 'disabled' : ''}
                                                style="padding: 4px 8px; min-width: auto;">↓</button>
                                        <button onclick="editTemplateItem(${item.id})" class="btn btn-sm">Edit</button>
                                        <button onclick="deleteTemplateItem(${item.id})" class="btn btn-sm btn-danger">Delete</button>
                                    </div>
                                </div>
                                ${item.description ? `<p style="margin: 5px 0; color: #666;">${item.description}</p>` : ''}
                                <div class="agenda-meta">
                                    <span class="badge badge-${item.item_type.toLowerCase().replace(' ', '-')}">${item.item_type}</span>
                                    ${item.duration_minutes ? `<span>Duration: ${item.duration_minutes} min</span>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error loading template items:', error);
                    document.getElementById('template-items-list').innerHTML = '<p style="color: red;">Error loading template items.</p>';
                });
        }

        function showTemplateItemModal(item = null) {
            const modal = document.getElementById('templateItemModal');
            const form = document.getElementById('templateItemForm');
            
            if (item) {
                document.getElementById('templateItemId').value = item.id;
                document.getElementById('templateItemTitle').value = item.title;
                document.getElementById('templateItemDescription').value = item.description || '';
                document.getElementById('templateItemType').value = item.item_type;
                document.getElementById('templateItemDuration').value = item.duration_minutes || '';
                document.getElementById('modalTemplateItemTitle').textContent = 'Edit Template Item';
            } else {
                form.reset();
                document.getElementById('templateItemId').value = '';
                document.getElementById('modalTemplateItemTitle').textContent = 'New Template Item';
            }
            
            modal.style.display = 'block';
        }

        function closeTemplateItemModal() {
            document.getElementById('templateItemModal').style.display = 'none';
            document.getElementById('templateItemForm').reset();
        }

        function saveTemplateItem(event) {
            event.preventDefault();
            const itemId = document.getElementById('templateItemId').value;
            const data = {
                meeting_type_id: currentMeetingTypeId,
                title: document.getElementById('templateItemTitle').value,
                description: document.getElementById('templateItemDescription').value || null,
                item_type: document.getElementById('templateItemType').value,
                duration_minutes: document.getElementById('templateItemDuration').value || null
            };

            const method = itemId ? 'PUT' : 'POST';
            if (itemId) data.id = itemId;

            fetch('api/agenda_templates.php', {
                method: method,
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                closeTemplateItemModal();
                loadTemplateItems();
            })
            .catch(error => {
                console.error('Error saving template item:', error);
                alert('Error saving template item');
            });
        }

        function editTemplateItem(id) {
            fetch(`api/agenda_templates.php?id=${id}`)
                .then(response => response.json())
                .then(item => showTemplateItemModal(item))
                .catch(error => {
                    console.error('Error loading template item:', error);
                    alert('Error loading template item');
                });
        }

        function deleteTemplateItem(id) {
            if (!confirm('Are you sure you want to delete this template item?')) return;
            
            fetch('api/agenda_templates.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(result => {
                loadTemplateItems();
            })
            .catch(error => {
                console.error('Error deleting template item:', error);
                alert('Error deleting template item');
            });
        }

        function moveTemplateItemUp(itemId) {
            reorderTemplateItem(itemId, 'up');
        }

        function moveTemplateItemDown(itemId) {
            reorderTemplateItem(itemId, 'down');
        }

        function reorderTemplateItem(itemId, direction) {
            // Get current order
            fetch(`api/agenda_templates.php?meeting_type_id=${currentMeetingTypeId}`)
                .then(response => response.json())
                .then(items => {
                    const currentIndex = items.findIndex(item => item.id == itemId);
                    if (currentIndex === -1) return;
                    
                    const newIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
                    if (newIndex < 0 || newIndex >= items.length) return;
                    
                    // Swap items
                    const order = items.map(item => item.id);
                    [order[currentIndex], order[newIndex]] = [order[newIndex], order[currentIndex]];
                    
                    // Save new order
                    fetch('api/agenda_templates.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            action: 'reorder',
                            meeting_type_id: currentMeetingTypeId,
                            order: order
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        loadTemplateItems();
                    })
                    .catch(error => {
                        console.error('Error reordering template items:', error);
                    });
                });
        }

        window.onclick = function(event) {
            const modals = ['meetingModal', 'agendaItemModal', 'attendeeModal', 'resolutionModal', 'minutesModal', 'documentUploadModal', 'templateModal', 'templateItemModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    if (modalId === 'meetingModal') closeMeetingModal();
                    else if (modalId === 'agendaItemModal') closeAgendaItemModal();
                    else if (modalId === 'documentUploadModal') closeDocumentUploadModal();
                    else if (modalId === 'attendeeModal') closeAttendeeModal();
                    else if (modalId === 'resolutionModal') closeResolutionModal();
                    else if (modalId === 'minutesModal') closeMinutesModal();
                    else if (modalId === 'templateModal') closeTemplateModal();
                    else if (modalId === 'templateItemModal') closeTemplateItemModal();
                }
            });
        }
    </script>
</body>
</html>

