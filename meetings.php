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
                        <label for="agendaItemDecisionMethod">Decision Method</label>
                        <select id="agendaItemDecisionMethod">
                            <option value="None">None</option>
                            <option value="Consensus">Consensus</option>
                            <option value="Formal Majority">Formal Majority</option>
                            <option value="Referral">Referral</option>
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
                        <option value="Apology">Apology</option>
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
                    <select id="resolutionParentAgendaItem" onchange="onResolutionAgendaItemChange()">
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
                        <label for="resolutionDecisionMethod">Decision Method</label>
                        <select id="resolutionDecisionMethod" onchange="resolutionDecisionMethodTouched = true;">
                            <option value="Consensus">Consensus</option>
                            <option value="Formal Majority">Formal Majority</option>
                            <option value="Referral">Referral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resolutionVoteType">Vote Type</label>
                        <select id="resolutionVoteType">
                            <option value="">Select vote type...</option>
                            <option value="Voices">Voices</option>
                            <option value="Show of Hands">Show of Hands</option>
                            <option value="Cards">Cards</option>
                            <option value="Written Ballot">Written Ballot</option>
                            <option value="Formal Procedures">Formal Procedures</option>
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

    <!-- Procedural Proposal Modal -->
    <div id="proceduralProposalModal" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="closeProceduralProposalModal()">&times;</span>
            <h2 id="modalProceduralProposalTitle">New Procedural Proposal</h2>
            <form id="proceduralProposalForm" onsubmit="saveProceduralProposal(event)">
                <input type="hidden" id="proceduralProposalId">
                <div class="form-group">
                    <label for="proceduralProposalType">Proposal Type *</label>
                    <select id="proceduralProposalType" required>
                        <option value="PointOfOrder">Point of Order</option>
                        <option value="UseOfProcedures">Use of Procedures</option>
                        <option value="OrderOfDay">Order of the Day</option>
                        <option value="Adjournment">Adjournment</option>
                        <option value="PrivateSitting">Private Sitting</option>
                        <option value="Referral">Referral</option>
                        <option value="DecisionNow">Determining the Need for a Decision Now</option>
                        <option value="WithdrawMotion">Withdraw Motion</option>
                        <option value="PreviousQuestion">The Previous Question (that the motion be not put)</option>
                        <option value="Closure">Closure (that the vote be now taken)</option>
                        <option value="Reconsideration">Reconsideration</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="proceduralProposalAgendaItem">Linked Agenda Item (Optional)</label>
                    <select id="proceduralProposalAgendaItem">
                        <option value="">No linked agenda item</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="proceduralProposalResolution">Linked Resolution/Motion (Optional)</label>
                    <select id="proceduralProposalResolution">
                        <option value="">No linked resolution</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proceduralProposalProposedBy">Proposed By</label>
                        <select id="proceduralProposalProposedBy">
                            <option value="">Select member...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="proceduralProposalSecondedBy">Seconded By</label>
                        <select id="proceduralProposalSecondedBy">
                            <option value="">Select member...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proceduralProposalOutcome">Outcome</label>
                        <select id="proceduralProposalOutcome">
                            <option value="Pending">Pending</option>
                            <option value="Carried">Carried</option>
                            <option value="Lost">Lost</option>
                            <option value="Lapsed">Lapsed</option>
                            <option value="RuledOn">Ruled On</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; margin-top: 22px;">
                        <label for="proceduralProposalRequiresLeave" style="margin: 0 8px 0 0;">
                            <input type="checkbox" id="proceduralProposalRequiresLeave" style="width:auto;">
                            Required leave of the council
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="proceduralProposalNotes">Notes</label>
                    <textarea id="proceduralProposalNotes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Procedural Proposal</button>
            </form>
        </div>
    </div>

    <!-- Document Upload Modal -->
    <div id="documentUploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDocumentUploadModal()">&times;</span>
            <h2 id="modalDocumentTitle">Add Document Link</h2>
            <form id="documentUploadForm">
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
                    <label for="documentSharePointUrl">SharePoint URL *</label>
                    <input type="url" id="documentSharePointUrl" required placeholder="https://yourtenant.sharepoint.com/...">
                    <small style="color: #666;">Paste the SharePoint link for this agenda document.</small>
                </div>
                <button type="submit" class="btn btn-primary">Save Document Link</button>
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
        let collapsedAgendaParentIds = new Set();
        let resolutionDecisionMethodTouched = false;
        let currentResolutionAgendaItems = [];

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
            collapsedAgendaParentIds = new Set();
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
                            <div class="minutes-procedural-proposals-section" style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #ddd;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <h3 style="margin: 0;">Procedural Proposals</h3>
                                    <button onclick="addProceduralProposal()" class="btn btn-sm btn-primary" id="addProceduralProposalBtn">+ Add Procedural Proposal</button>
                                </div>
                                <p style="color: #666; margin: 5px 0 10px 0;">Points of order, adjournment, previous question, and other procedural motions raised at this meeting.</p>
                                <div id="procedural-proposals-list"></div>
                            </div>
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
                    loadMeetingProceduralProposals(id);
                });
        }

        function closeMeetingDetail() {
            document.getElementById('meeting-detail').style.display = 'none';
            document.getElementById('meetings-list').style.display = 'block';
            currentMeetingId = null;
            collapsedAgendaParentIds = new Set();
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`tab-${tabName}`).classList.add('active');
            event.target.classList.add('active');
        }

        function getItemResolutions(item) {
            if (item.resolutions && item.resolutions.length > 0) {
                return item.resolutions;
            }
            if (item.resolution_id) {
                return [{
                    id: item.resolution_id,
                    title: item.resolution_title,
                    resolution_number: item.resolution_number,
                    status: item.resolution_status,
                    vote_type: item.resolution_vote_type,
                    effective_date: item.resolution_effective_date,
                    description: item.resolution_description
                }];
            }
            return [];
        }

        function renderAgendaResolutionPanels(item, showEditButtons = true) {
            const resolutions = getItemResolutions(item);
            if (resolutions.length === 0) {
                return '';
            }
            return resolutions.map(res => {
                const statusBadge = res.status
                    ? `<span class="badge badge-${String(res.status).toLowerCase()}" style="margin-left: 8px;">${res.status}</span>`
                    : '';
                let panel = `
                <div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 3px solid #28a745;">
                    <div>
                        <strong>📋 Linked Resolution:</strong> ${res.title || 'Resolution'}
                        ${res.resolution_number ? `(#${res.resolution_number})` : ''}
                        ${statusBadge}
                    </div>`;
                if (res.vote_type) {
                    panel += `<div style="margin-top: 4px; color: #2f6f46;">Vote Type: ${res.vote_type}</div>`;
                }
                if (res.effective_date) {
                    panel += `<div style="margin-top: 4px; color: #2f6f46;">Effective: ${formatDateTime(res.effective_date)}</div>`;
                }
                if (res.description) {
                    panel += `<div style="margin-top: 6px; color: #2f6f46;">${res.description}</div>`;
                }
                if (showEditButtons && res.id) {
                    panel += `<div style="margin-top: 8px;"><button onclick="editResolution(${res.id})" class="btn btn-sm">Edit Resolution</button></div>`;
                }
                panel += '</div>';
                return panel;
            }).join('');
        }

        function renderMinutesResolutionSummary(item, minutesApproved) {
            const resolutions = getItemResolutions(item);
            if (resolutions.length === 0) {
                return '';
            }
            return resolutions.map(res => {
                const numberPart = res.resolution_number
                    ? `<span style="color: #007bff; font-weight: normal; margin-left: 10px;">(Resolution #${res.resolution_number})</span>`
                    : '';
                const statusPart = res.status
                    ? `<span class="badge badge-${res.status.toLowerCase().replace(' ', '-')}" style="margin-left: 8px; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">${res.status}</span>`
                    : '';
                const editPart = (!minutesApproved && res.id)
                    ? `<button onclick="editResolution(${res.id})" class="btn btn-sm" style="margin-left: 8px;">Edit Resolution</button>`
                    : '';
                return `${numberPart}${statusPart}${editPart}`;
            }).join('');
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
                        const childCountByParent = {};
                        items.forEach(item => {
                            if (item.parent_id) {
                                const parentKey = String(item.parent_id);
                                childCountByParent[parentKey] = (childCountByParent[parentKey] || 0) + 1;
                            }
                        });

                        list.innerHTML = items.map((item, index) => {
                            const isChild = item.parent_id && item.parent_id !== null;
                            const hasChildren = !!childCountByParent[String(item.id)];
                            const isCollapsed = hasChildren && collapsedAgendaParentIds.has(String(item.id));
                            const indentStyle = isChild ? 'style="margin-left: 22px;"' : '';
                            const documents = documentsArrays[index] || [];
                            const reorderDisabled = getAgendaReorderDisabledState(items, index);
                            const disableUp = reorderDisabled.disableUp;
                            const disableDown = reorderDisabled.disableDown;
                            const itemResolutions = getItemResolutions(item);
                            const hasResolutions = itemResolutions.length > 0;
                            return `
                                      <div class="agenda-item ${hasResolutions ? 'agenda-item-with-resolution' : ''}" ${indentStyle}
                                          draggable="true" 
                                          data-item-id="${item.id}" 
                                          data-parent-id="${item.parent_id || ''}"
                                          data-position="${item.position}">
                                    <div class="item-header">
                                        <div class="item-drag-handle" title="Drag to reorder">
                                            <span class="drag-icon">☰</span>
                                        </div>
                                            <h4>
                                                ${hasChildren ? `<button type="button" class="agenda-collapse-toggle ${isCollapsed ? 'collapsed' : ''}" onclick="toggleAgendaChildren(${item.id}, event)" aria-expanded="${isCollapsed ? 'false' : 'true'}" title="${isCollapsed ? 'Expand sub-items' : 'Collapse sub-items'}">${isCollapsed ? '▸' : '▾'}</button>` : '<span class="agenda-collapse-spacer" aria-hidden="true"></span>'}
                                                ${item.item_number ? item.item_number + '. ' : ''}${item.title}
                                            </h4>
                                        <div class="item-actions">
                                            <div class="reorder-buttons">
                                                <button onclick="moveAgendaItemUp(${item.id})" 
                                                        class="btn btn-sm btn-reorder" 
                                                        title="Move up"
                                                        ${disableUp ? 'disabled' : ''}
                                                        style="padding: 4px 8px; min-width: auto;">
                                                    ↑
                                                </button>
                                                <button onclick="moveAgendaItemDown(${item.id})" 
                                                        class="btn btn-sm btn-reorder" 
                                                        title="Move down"
                                                        ${disableDown ? 'disabled' : ''}
                                                        style="padding: 4px 8px; min-width: auto;">
                                                    ↓
                                                </button>
                                            </div>
                                            ${hasResolutions ? `<a href="#resolutions" onclick="showTab('resolutions'); event.preventDefault();" class="btn btn-sm" style="text-decoration: none; display: inline-block;">View Resolution${itemResolutions.length > 1 ? 's' : ''}</a>` : ''}
                                            <button onclick="showDocumentUploadModal(${item.id})" class="btn btn-sm">📎 Attach Document</button>
                                            <button onclick="editAgendaItem(${item.id})" class="btn btn-sm">Edit</button>
                                            <button onclick="deleteAgendaItem(${item.id})" class="btn btn-sm btn-danger">Delete</button>
                                        </div>
                                    </div>
                                    ${item.description ? `<p>${item.description}</p>` : ''}
                                                                        ${renderAgendaResolutionPanels(item)}
                                    ${documents.length > 0 ? `
                                        <div style="background: #f0f8ff; padding: 10px; border-radius: 4px; margin: 10px 0; border-left: 3px solid #007bff;">
                                            <strong>📎 Attached Documents:</strong>
                                            <ul style="margin: 5px 0; padding-left: 20px;">
                                                ${documents.map(doc => `
                                                    <li>
                                                        <a href="${doc.sharepoint_url || ('api/download.php?id=' + doc.id)}" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: #007bff;">
                                                            ${doc.title || doc.file_name || extractFileNameFromUrl(doc.sharepoint_url) || 'Document Link'}
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
                        applyAgendaCollapseState();
                        // Initialize drag-and-drop after items are rendered
                        makeAgendaItemsSortable(meetingId);
                    });
                });
        }

        function toggleAgendaChildren(parentId, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            const parentKey = String(parentId);
            if (collapsedAgendaParentIds.has(parentKey)) {
                collapsedAgendaParentIds.delete(parentKey);
            } else {
                collapsedAgendaParentIds.add(parentKey);
            }
            applyAgendaCollapseState();
        }

        function applyAgendaCollapseState() {
            const list = document.getElementById('agenda-items-list');
            if (!list) return;

            const items = list.querySelectorAll('.agenda-item');
            items.forEach(item => {
                const parentId = item.getAttribute('data-parent-id');
                if (!parentId) {
                    return;
                }
                item.style.display = collapsedAgendaParentIds.has(parentId) ? 'none' : '';
            });

            const parentItems = list.querySelectorAll('.agenda-item[data-parent-id=""]');
            parentItems.forEach(parentItem => {
                const parentId = parentItem.getAttribute('data-item-id');
                const toggleButton = parentItem.querySelector('.agenda-collapse-toggle');
                if (!toggleButton) {
                    return;
                }
                const isCollapsed = collapsedAgendaParentIds.has(String(parentId));
                toggleButton.classList.toggle('collapsed', isCollapsed);
                toggleButton.textContent = isCollapsed ? '▸' : '▾';
                toggleButton.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                toggleButton.setAttribute('title', isCollapsed ? 'Expand sub-items' : 'Collapse sub-items');
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
                                    ${renderMinutesResolutionSummary(item, minutes.status === 'Approved')}
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
                    document.getElementById('agendaItemDecisionMethod').value = item.decision_method || 'None';
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
                decision_method: document.getElementById('agendaItemDecisionMethod').value,
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
        let draggedBlock = null;
        let draggedBlockIds = null;

        function isTopLevelAgendaItem(el) {
            return !el.getAttribute('data-parent-id');
        }

        function getAgendaBlockEndIndex(items, startIndex) {
            const start = items[startIndex];
            if (!isTopLevelAgendaItem(start)) {
                return startIndex;
            }
            const parentId = start.getAttribute('data-item-id');
            let end = startIndex;
            for (let i = startIndex + 1; i < items.length; i++) {
                if (items[i].getAttribute('data-parent-id') == parentId) {
                    end = i;
                } else {
                    break;
                }
            }
            return end;
        }

        function getAgendaBlock(items, startIndex) {
            const endIndex = getAgendaBlockEndIndex(items, startIndex);
            return {
                block: items.slice(startIndex, endIndex + 1),
                startIndex,
                endIndex
            };
        }

        function getAgendaBlockStartIndex(items, index) {
            const el = items[index];
            if (isTopLevelAgendaItem(el)) {
                return index;
            }
            const parentId = el.getAttribute('data-parent-id');
            for (let i = index; i >= 0; i--) {
                if (items[i].getAttribute('data-item-id') == parentId) {
                    return i;
                }
            }
            return index;
        }

        function getPreviousBlockStartIndex(items, blockStart) {
            if (blockStart <= 0) {
                return null;
            }
            return getAgendaBlockStartIndex(items, blockStart - 1);
        }

        function getNextBlockStartIndex(items, blockEnd) {
            const next = blockEnd + 1;
            return next < items.length ? next : null;
        }

        function insertAgendaBlockBefore(parentNode, block, refNode) {
            block.forEach(el => parentNode.removeChild(el));
            block.forEach(el => parentNode.insertBefore(el, refNode));
        }

        function insertAgendaBlockAfter(parentNode, block, refNode, allItems) {
            const items = allItems || Array.from(parentNode.querySelectorAll('.agenda-item'));
            const refIndex = items.indexOf(refNode);
            if (refIndex < 0) {
                return;
            }
            const blockStart = getAgendaBlockStartIndex(items, refIndex);
            const { endIndex } = getAgendaBlock(items, blockStart);
            const refAfter = items[endIndex].nextSibling;
            block.forEach(el => parentNode.removeChild(el));
            block.forEach(el => parentNode.insertBefore(el, refAfter));
        }

        function getDataAgendaBlock(items, startIndex) {
            const item = items[startIndex];
            if (item.parent_id) {
                return { startIndex, endIndex: startIndex };
            }
            const parentId = item.id;
            let endIndex = startIndex;
            for (let i = startIndex + 1; i < items.length; i++) {
                if (items[i].parent_id == parentId) {
                    endIndex = i;
                } else {
                    break;
                }
            }
            return { startIndex, endIndex };
        }

        function getDataAgendaBlockStartIndex(items, index) {
            const item = items[index];
            if (!item.parent_id) {
                return index;
            }
            for (let i = index; i >= 0; i--) {
                if (items[i].id == item.parent_id) {
                    return i;
                }
            }
            return index;
        }

        function getAgendaReorderDisabledState(items, index) {
            const item = items[index];
            const isChild = item.parent_id && item.parent_id !== null;

            if (isChild) {
                const parentId = item.parent_id;
                const siblingIndices = [];
                items.forEach((it, i) => {
                    if (it.parent_id == parentId) {
                        siblingIndices.push(i);
                    }
                });
                const posInSiblings = siblingIndices.indexOf(index);
                return {
                    disableUp: posInSiblings <= 0,
                    disableDown: posInSiblings < 0 || posInSiblings >= siblingIndices.length - 1
                };
            }

            const blockStart = getDataAgendaBlockStartIndex(items, index);
            const { endIndex } = getDataAgendaBlock(items, blockStart);
            return {
                disableUp: blockStart === 0,
                disableDown: endIndex >= items.length - 1
            };
        }

        function collectAgendaDragBlock(item, items) {
            const itemIndex = items.indexOf(item);
            if (itemIndex < 0) {
                return [item];
            }
            const blockStart = getAgendaBlockStartIndex(items, itemIndex);
            const { block } = getAgendaBlock(items, blockStart);
            if (isTopLevelAgendaItem(item)) {
                return block;
            }
            // Child drag: sibling reorder only (single row)
            return [item];
        }

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
            
            updatedItems.forEach((item) => {
                item.addEventListener('dragstart', (e) => {
                    const items = Array.from(list.querySelectorAll('.agenda-item'));
                    draggedBlock = collectAgendaDragBlock(item, items);
                    draggedBlockIds = new Set(
                        draggedBlock.map(el => el.getAttribute('data-item-id'))
                    );
                    draggedBlock.forEach(el => el.classList.add('dragging'));
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', item.innerHTML);
                });

                item.addEventListener('dragend', () => {
                    if (draggedBlock) {
                        draggedBlock.forEach(el => el.classList.remove('dragging'));
                    }
                    draggedBlock = null;
                    draggedBlockIds = null;
                    list.querySelectorAll('.agenda-item').forEach(i => {
                        i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom');
                    });
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';

                    const itemId = item.getAttribute('data-item-id');
                    if (draggedBlockIds && draggedBlockIds.has(itemId)) {
                        return;
                    }

                    const rect = item.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;

                    list.querySelectorAll('.agenda-item').forEach(i => {
                        i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom');
                    });

                    if (e.clientY < midY) {
                        item.classList.add('drag-over-top');
                    } else {
                        item.classList.add('drag-over-bottom');
                    }
                });

                item.addEventListener('dragleave', () => {
                    item.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom');
                });

                item.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const itemId = item.getAttribute('data-item-id');
                    if (!draggedBlock || !draggedBlockIds || draggedBlockIds.has(itemId)) {
                        return;
                    }

                    const items = Array.from(list.querySelectorAll('.agenda-item'));
                    const parentNode = item.parentNode;
                    const rect = item.getBoundingClientRect();
                    const insertBefore = e.clientY < rect.top + rect.height / 2;
                    const targetIndex = items.indexOf(item);
                    const targetBlockStart = getAgendaBlockStartIndex(items, targetIndex);
                    const { endIndex: targetBlockEnd } = getAgendaBlock(items, targetBlockStart);

                    const draggingChild = draggedBlock.length === 1 && !isTopLevelAgendaItem(draggedBlock[0]);
                    if (draggingChild) {
                        const dragParentId = draggedBlock[0].getAttribute('data-parent-id');
                        const targetParentId = item.getAttribute('data-parent-id');
                        if (!dragParentId || dragParentId !== targetParentId) {
                            return;
                        }
                        if (insertBefore) {
                            insertAgendaBlockBefore(parentNode, draggedBlock, item);
                        } else {
                            insertAgendaBlockAfter(parentNode, draggedBlock, item, items);
                        }
                    } else if (insertBefore) {
                        insertAgendaBlockBefore(parentNode, draggedBlock, items[targetBlockStart]);
                    } else {
                        insertAgendaBlockAfter(parentNode, draggedBlock, items[targetBlockEnd], items);
                    }

                    const newOrder = Array.from(list.querySelectorAll('.agenda-item')).map(el =>
                        parseInt(el.getAttribute('data-item-id'))
                    );
                    reorderAgendaItems(meetingId, newOrder);

                    list.querySelectorAll('.agenda-item').forEach(i => {
                        i.classList.remove('drag-over', 'drag-over-top', 'drag-over-bottom');
                    });
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
            if (currentIndex <= 0) return;

            const currentItem = items[currentIndex];
            const parentNode = currentItem.parentNode;

            if (isTopLevelAgendaItem(currentItem)) {
                const blockStart = getAgendaBlockStartIndex(items, currentIndex);
                const { block, startIndex } = getAgendaBlock(items, blockStart);
                const prevStart = getPreviousBlockStartIndex(items, startIndex);
                if (prevStart === null) return;
                insertAgendaBlockBefore(parentNode, block, items[prevStart]);
            } else {
                const parentId = currentItem.getAttribute('data-parent-id');
                const prev = items[currentIndex - 1];
                if (!prev || prev.getAttribute('data-parent-id') !== parentId) return;
                insertAgendaBlockBefore(parentNode, [currentItem], prev);
            }

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
            if (currentIndex < 0 || currentIndex >= items.length - 1) return;

            const currentItem = items[currentIndex];
            const parentNode = currentItem.parentNode;

            if (isTopLevelAgendaItem(currentItem)) {
                const blockStart = getAgendaBlockStartIndex(items, currentIndex);
                const { block, endIndex } = getAgendaBlock(items, blockStart);
                const nextStart = getNextBlockStartIndex(items, endIndex);
                if (nextStart === null) return;
                const { endIndex: nextEnd } = getAgendaBlock(items, nextStart);
                insertAgendaBlockAfter(parentNode, block, items[nextEnd], items);
            } else {
                const parentId = currentItem.getAttribute('data-parent-id');
                const next = items[currentIndex + 1];
                if (!next || next.getAttribute('data-parent-id') !== parentId) return;
                insertAgendaBlockAfter(parentNode, [currentItem], next, items);
            }

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
            resolutionDecisionMethodTouched = false;
            currentResolutionAgendaItems = [];

            // Populate agenda item dropdown (include sub-items)
            const parentSelect = document.getElementById('resolutionParentAgendaItem');
            parentSelect.innerHTML = '<option value="">No linked agenda item</option>';

            if (currentMeetingId) {
                fetch(`api/agenda.php?meeting_id=${currentMeetingId}`)
                    .then(r => r.json())
                    .then(allItems => {
                        const seenAgendaIds = new Set();
                        allItems.forEach(i => {
                            if (seenAgendaIds.has(i.id)) {
                                return;
                            }
                            seenAgendaIds.add(i.id);
                            const opt = document.createElement('option');
                            opt.value = i.id;
                            const prefix = i.parent_id ? '— ' : '';
                            opt.textContent = prefix + (i.item_number ? i.item_number + '. ' : '') + i.title;
                            parentSelect.appendChild(opt);
                        });
                        currentResolutionAgendaItems = allItems;

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
                document.getElementById('resolutionDecisionMethod').value = resolution.decision_method || 'Consensus';
                document.getElementById('resolutionVoteType').value = resolution.vote_type || '';
                document.getElementById('resolutionStatus').value = resolution.status;
                document.getElementById('resolutionEffectiveDate').value = resolution.effective_date || '';
                document.getElementById('modalResolutionTitle').textContent = 'Edit Resolution';
                // Allow updating linked agenda item while editing
                document.getElementById('resolutionParentAgendaItem').disabled = false;
                document.getElementById('resolutionParentGroup').style.opacity = '1';
                // Editing an existing resolution counts as "touched" so linking a
                // different agenda item won't silently overwrite a chosen method
                resolutionDecisionMethodTouched = true;
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

        // Pre-fill a new resolution's Decision Method from the linked agenda
        // item's Decision Method, unless the user has already changed it.
        function onResolutionAgendaItemChange() {
            const isNew = !document.getElementById('resolutionId').value;
            if (!isNew || resolutionDecisionMethodTouched) return;
            const agendaItemId = document.getElementById('resolutionParentAgendaItem').value;
            if (!agendaItemId) return;
            const item = currentResolutionAgendaItems.find(i => String(i.id) === String(agendaItemId));
            if (item && item.decision_method && item.decision_method !== 'None') {
                document.getElementById('resolutionDecisionMethod').value = item.decision_method;
            }
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
                decision_method: document.getElementById('resolutionDecisionMethod').value,
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

        // Procedural Proposal Management
        const PROCEDURAL_PROPOSAL_LABELS = {
            UseOfProcedures: 'Use of Procedures',
            OrderOfDay: 'Order of the Day',
            Adjournment: 'Adjournment',
            PrivateSitting: 'Private Sitting',
            Referral: 'Referral',
            DecisionNow: 'Determining the Need for a Decision Now',
            WithdrawMotion: 'Withdraw Motion',
            PreviousQuestion: 'The Previous Question',
            Closure: 'Closure (vote be now taken)',
            Reconsideration: 'Reconsideration',
            PointOfOrder: 'Point of Order'
        };

        function loadMeetingProceduralProposals(meetingId) {
            fetch(`api/procedural_proposals.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(proposals => {
                    const list = document.getElementById('procedural-proposals-list');
                    if (!list) return;
                    if (!Array.isArray(proposals) || proposals.length === 0) {
                        list.innerHTML = '<p>No procedural proposals recorded for this meeting.</p>';
                        return;
                    }
                    list.innerHTML = proposals.map(p => {
                        const typeLabel = PROCEDURAL_PROPOSAL_LABELS[p.proposal_type] || p.proposal_type;
                        const proposer = p.proposed_by_first_name ? `${p.proposed_by_first_name} ${p.proposed_by_last_name}` : null;
                        const seconder = p.seconded_by_first_name ? `${p.seconded_by_first_name} ${p.seconded_by_last_name}` : null;
                        return `
                            <div class="resolution-item">
                                <div class="item-header">
                                    <h4>${typeLabel}</h4>
                                    <div class="item-actions">
                                        <button onclick="editProceduralProposal(${p.id})" class="btn btn-sm">Edit</button>
                                        <button onclick="deleteProceduralProposal(${p.id})" class="btn btn-sm btn-danger">Delete</button>
                                    </div>
                                </div>
                                ${proposer ? `<p><strong>Proposed by:</strong> ${proposer}</p>` : ''}
                                ${seconder ? `<p><strong>Seconded by:</strong> ${seconder}</p>` : ''}
                                ${p.requires_leave ? `<p><em>Required leave of the council.</em></p>` : ''}
                                ${p.notes ? `<p>${p.notes}</p>` : ''}
                                <p><strong>Outcome:</strong> <span class="badge badge-${p.outcome.toLowerCase()}">${p.outcome}</span></p>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error loading procedural proposals:', error);
                });
        }

        function addProceduralProposal() {
            if (!currentMeetingId) return;
            showProceduralProposalModal();
        }

        function editProceduralProposal(id) {
            fetch(`api/procedural_proposals.php?id=${id}`)
                .then(response => response.json())
                .then(proposal => showProceduralProposalModal(proposal));
        }

        function showProceduralProposalModal(proposal = null) {
            const modal = document.getElementById('proceduralProposalModal');
            const form = document.getElementById('proceduralProposalForm');

            // Populate linked agenda item dropdown
            const agendaItemSelect = document.getElementById('proceduralProposalAgendaItem');
            agendaItemSelect.innerHTML = '<option value="">No linked agenda item</option>';
            if (currentMeetingId) {
                fetch(`api/agenda.php?meeting_id=${currentMeetingId}`)
                    .then(r => r.json())
                    .then(allItems => {
                        const seenAgendaIds = new Set();
                        allItems.forEach(i => {
                            if (seenAgendaIds.has(i.id)) return;
                            seenAgendaIds.add(i.id);
                            const opt = document.createElement('option');
                            opt.value = i.id;
                            const prefix = i.parent_id ? '— ' : '';
                            opt.textContent = prefix + (i.item_number ? i.item_number + '. ' : '') + i.title;
                            agendaItemSelect.appendChild(opt);
                        });
                        if (proposal && proposal.agenda_item_id) {
                            agendaItemSelect.value = proposal.agenda_item_id;
                        }
                    })
                    .catch(err => console.error('Error loading agenda items:', err));

                // Populate linked resolution dropdown
                const resolutionSelect = document.getElementById('proceduralProposalResolution');
                resolutionSelect.innerHTML = '<option value="">No linked resolution</option>';
                fetch(`api/resolutions.php?meeting_id=${currentMeetingId}`)
                    .then(r => r.json())
                    .then(resolutions => {
                        resolutions.forEach(r => {
                            const opt = document.createElement('option');
                            opt.value = r.id;
                            opt.textContent = r.title;
                            resolutionSelect.appendChild(opt);
                        });
                        if (proposal && proposal.resolution_id) {
                            resolutionSelect.value = proposal.resolution_id;
                        }
                    })
                    .catch(err => console.error('Error loading resolutions:', err));

                // Populate proposed-by / seconded-by dropdowns from this meeting's attendees
                const proposedBySelect = document.getElementById('proceduralProposalProposedBy');
                const secondedBySelect = document.getElementById('proceduralProposalSecondedBy');
                proposedBySelect.innerHTML = '<option value="">Select member...</option>';
                secondedBySelect.innerHTML = '<option value="">Select member...</option>';
                fetch(`api/attendees.php?meeting_id=${currentMeetingId}`)
                    .then(r => r.json())
                    .then(attendees => {
                        attendees.forEach(a => {
                            const label = `${a.first_name} ${a.last_name}`;
                            const opt1 = document.createElement('option');
                            opt1.value = a.member_id;
                            opt1.textContent = label;
                            proposedBySelect.appendChild(opt1);
                            const opt2 = document.createElement('option');
                            opt2.value = a.member_id;
                            opt2.textContent = label;
                            secondedBySelect.appendChild(opt2);
                        });
                        if (proposal && proposal.proposed_by) proposedBySelect.value = proposal.proposed_by;
                        if (proposal && proposal.seconded_by) secondedBySelect.value = proposal.seconded_by;
                    })
                    .catch(err => console.error('Error loading attendees:', err));
            }

            if (proposal) {
                document.getElementById('proceduralProposalId').value = proposal.id;
                document.getElementById('proceduralProposalType').value = proposal.proposal_type;
                document.getElementById('proceduralProposalOutcome').value = proposal.outcome || 'Pending';
                document.getElementById('proceduralProposalRequiresLeave').checked = !!proposal.requires_leave;
                document.getElementById('proceduralProposalNotes').value = proposal.notes || '';
                document.getElementById('modalProceduralProposalTitle').textContent = 'Edit Procedural Proposal';
            } else {
                form.reset();
                document.getElementById('proceduralProposalId').value = '';
                document.getElementById('modalProceduralProposalTitle').textContent = 'New Procedural Proposal';
            }

            modal.style.display = 'block';
        }

        function closeProceduralProposalModal() {
            document.getElementById('proceduralProposalModal').style.display = 'none';
            document.getElementById('proceduralProposalForm').reset();
        }

        function saveProceduralProposal(event) {
            event.preventDefault();
            const proposalId = document.getElementById('proceduralProposalId').value;
            const agendaItemVal = document.getElementById('proceduralProposalAgendaItem').value;
            const resolutionVal = document.getElementById('proceduralProposalResolution').value;
            const proposedByVal = document.getElementById('proceduralProposalProposedBy').value;
            const secondedByVal = document.getElementById('proceduralProposalSecondedBy').value;

            const data = {
                meeting_id: currentMeetingId,
                proposal_type: document.getElementById('proceduralProposalType').value,
                agenda_item_id: agendaItemVal ? parseInt(agendaItemVal) : null,
                resolution_id: resolutionVal ? parseInt(resolutionVal) : null,
                proposed_by: proposedByVal ? parseInt(proposedByVal) : null,
                seconded_by: secondedByVal ? parseInt(secondedByVal) : null,
                outcome: document.getElementById('proceduralProposalOutcome').value,
                requires_leave: document.getElementById('proceduralProposalRequiresLeave').checked,
                notes: document.getElementById('proceduralProposalNotes').value || null
            };

            const method = proposalId ? 'PUT' : 'POST';
            if (proposalId) data.id = proposalId;

            fetch('api/procedural_proposals.php', {
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
                    throw new Error(jsonData.error || 'Error saving procedural proposal');
                }
                if (jsonData.error) {
                    throw new Error(jsonData.error);
                }
                return jsonData;
            })
            .then(data => {
                closeProceduralProposalModal();
                loadMeetingProceduralProposals(currentMeetingId);
            })
            .catch(error => {
                console.error('Error saving procedural proposal:', error);
                alert('Error saving procedural proposal: ' + error.message);
            });
        }

        function deleteProceduralProposal(id) {
            if (!confirm('Are you sure you want to delete this procedural proposal?')) return;

            fetch('api/procedural_proposals.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    loadMeetingProceduralProposals(currentMeetingId);
                }
            })
            .catch(error => {
                console.error('Error deleting procedural proposal:', error);
                alert('Error deleting procedural proposal');
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
            const agendaItemId = document.getElementById('documentAgendaItemId').value;
            const sharepointUrl = document.getElementById('documentSharePointUrl').value.trim();

            if (!isValidSharePointUrl(sharepointUrl)) {
                alert('Please enter a valid HTTPS SharePoint URL');
                return;
            }

            const payload = {
                title: document.getElementById('documentTitle').value,
                description: document.getElementById('documentDescription').value,
                document_type: document.getElementById('documentType').value,
                meeting_id: currentMeetingId,
                agenda_item_id: agendaItemId,
                sharepoint_url: sharepointUrl
            };
            
            fetch('api/documents.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
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

        function isValidSharePointUrl(url) {
            if (!url) return false;
            try {
                const parsed = new URL(url);
                return parsed.protocol === 'https:' && parsed.hostname.includes('sharepoint.com');
            } catch (error) {
                return false;
            }
        }

        function extractFileNameFromUrl(url) {
            if (!url) return '';
            try {
                const parsed = new URL(url);
                const pathParts = parsed.pathname.split('/').filter(Boolean);
                if (pathParts.length === 0) return '';
                return decodeURIComponent(pathParts[pathParts.length - 1]);
            } catch (error) {
                return '';
            }
        }

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
            const modals = ['meetingModal', 'agendaItemModal', 'attendeeModal', 'resolutionModal', 'proceduralProposalModal', 'minutesModal', 'documentUploadModal', 'templateModal', 'templateItemModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    if (modalId === 'meetingModal') closeMeetingModal();
                    else if (modalId === 'agendaItemModal') closeAgendaItemModal();
                    else if (modalId === 'documentUploadModal') closeDocumentUploadModal();
                    else if (modalId === 'attendeeModal') closeAttendeeModal();
                    else if (modalId === 'resolutionModal') closeResolutionModal();
                    else if (modalId === 'proceduralProposalModal') closeProceduralProposalModal();
                    else if (modalId === 'minutesModal') closeMinutesModal();
                    else if (modalId === 'templateModal') closeTemplateModal();
                    else if (modalId === 'templateItemModal') closeTemplateItemModal();
                }
            });
        }
    </script>
</body>
</html>

