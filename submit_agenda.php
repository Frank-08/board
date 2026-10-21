<?php
require_once __DIR__ . '/includes/header.php';

if (!hasPermission('submit_agenda_item') && !hasPermission('manage_agenda')) {
    http_response_code(403);
    outputHeader('Submit Agenda Item', 'submit_agenda.php');
    echo '<main><p>You do not have permission to submit agenda items. Please contact the Secretary.</p></main></div>';
    outputFooter();
    exit;
}

outputHeader('Submit Agenda Item', 'submit_agenda.php');
?>

        <main>
            <div class="page-header">
                <h2>Submit an Agenda Item</h2>
                <a href="assets/templates/Agenda_Item_Report_Template.dotx" class="btn btn-secondary" download>⬇ Download Word Report Template</a>
            </div>

            <div class="submission-steps">
                <ol>
                    <li>Download the <a href="assets/templates/Agenda_Item_Report_Template.dotx" download>Word report template</a> and write your report in it (about two pages; attachments as appendices).</li>
                    <li>Save the report to SharePoint and copy its link (<em>Share → Copy link</em>), making sure the council's members can open it.</li>
                    <li>Complete the form below. The Secretary will review your submission and either add it to the agenda or get back to you.</li>
                </ol>
            </div>

            <section id="submission-form-section" style="display: none;">
                <h3 id="submissionFormHeading">New Submission</h3>
                <form id="submissionForm" onsubmit="saveSubmission(event)">
                    <input type="hidden" id="submissionId">
                    <div class="form-group">
                        <label for="submissionMeeting">Meeting *</label>
                        <select id="submissionMeeting" required>
                            <option value="">Select meeting...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="submissionTitle">Agenda Item Title *</label>
                        <input type="text" id="submissionTitle" maxlength="255" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="submissionItemType">Item Type</label>
                            <select id="submissionItemType">
                                <option value="Discussion">Discussion</option>
                                <option value="Action Item">Action Item</option>
                                <option value="Vote">Vote</option>
                                <option value="Information">Information</option>
                                <option value="Presentation">Presentation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="submissionDecisionMethod">Decision Sought</label>
                            <select id="submissionDecisionMethod" onchange="updateProposalRequired()">
                                <option value="None">None (for information)</option>
                                <option value="Consensus">Consensus</option>
                                <option value="Formal Majority">Formal Majority</option>
                                <option value="Referral">Referral</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="submissionReportType">Report Type</label>
                            <select id="submissionReportType" onchange="updateUrlRequired()">
                                <option value="Written">Written</option>
                                <option value="Verbal">Verbal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="submissionDuration">Time Requested (minutes)</label>
                            <input type="number" id="submissionDuration" min="1" max="600">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="submissionSummary">Purpose / Summary</label>
                        <textarea id="submissionSummary" rows="3" placeholder="One or two sentences on what this item is about and why it is coming to the council."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="submissionProposal" id="submissionProposalLabel">Proposal</label>
                        <textarea id="submissionProposal" rows="3" placeholder="That the council ..."></textarea>
                        <small style="color: #666;">Copy the proposal from section 2 of your report. Required when a decision is sought.</small>
                    </div>
                    <div class="form-group">
                        <label for="submissionSharePointUrl" id="submissionSharePointUrlLabel">SharePoint Link to Report *</label>
                        <input type="url" id="submissionSharePointUrl" placeholder="https://yourtenant.sharepoint.com/...">
                    </div>
                    <button type="submit" class="btn btn-primary" id="submissionSaveBtn">Submit for Review</button>
                    <button type="button" class="btn btn-secondary" onclick="hideSubmissionForm()">Cancel</button>
                </form>
            </section>

            <section id="review-section" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
                    <h3 style="margin: 0;">Review Queue</h3>
                    <div>
                        <select id="reviewMeetingFilter" onchange="loadReviewQueue()">
                            <option value="">All meetings</option>
                        </select>
                        <select id="reviewStatusFilter" onchange="loadReviewQueue()">
                            <option value="Pending">Pending</option>
                            <option value="">All statuses</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Declined">Declined</option>
                            <option value="Withdrawn">Withdrawn</option>
                        </select>
                    </div>
                </div>
                <div id="review-list"><p>Loading...</p></div>
            </section>

            <section id="my-submissions-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
                    <h3 style="margin: 0;">My Submissions</h3>
                    <button onclick="showSubmissionForm()" class="btn btn-primary" id="newSubmissionBtn">+ New Submission</button>
                </div>
                <div id="my-submissions-list"><p>Loading...</p></div>
            </section>
        </main>
    </div>

    <style>
        .submission-steps {
            background: #f5f8fb;
            border-left: 4px solid #2c5282;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        .submission-steps ol { margin: 0; padding-left: 20px; }
        .submission-steps li { margin: 4px 0; }
        #submission-form-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .submissions-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .submissions-table th { padding: 10px; text-align: left; background: #f5f5f5; border-bottom: 2px solid #ddd; }
        .submissions-table td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        .submissions-table .muted { color: #666; font-size: 13px; }
        .submissions-table .proposal { white-space: pre-wrap; margin-top: 4px; }
    </style>

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        const canSubmit = hasPermission('canSubmitAgendaItem');
        const canReview = hasPermission('canManageAgenda');

        const STATUS_BADGES = {
            'Pending': 'badge-pending',
            'Accepted': 'badge-approved',
            'Declined': 'badge-failed',
            'Withdrawn': 'badge-inactive'
        };

        let openMeetings = [];
        let mySubmissions = [];

        window.addEventListener('DOMContentLoaded', async function() {
            if (!canSubmit) {
                document.getElementById('my-submissions-section').style.display = 'none';
            }
            await loadOpenMeetings();
            if (canSubmit) {
                loadMySubmissions();
            }
            if (canReview) {
                document.getElementById('review-section').style.display = 'block';
                const params = new URLSearchParams(window.location.search);
                if (params.get('meeting_id')) {
                    document.getElementById('reviewMeetingFilter').value = params.get('meeting_id');
                }
                loadReviewQueue();
            }
        });

        async function loadOpenMeetings() {
            try {
                openMeetings = await apiCall('agenda_submissions.php?open_meetings=1');
            } catch (error) {
                console.error('Error loading meetings:', error);
                openMeetings = [];
            }
            const select = document.getElementById('submissionMeeting');
            select.innerHTML = '<option value="">Select meeting...</option>' + openMeetings.map(m =>
                `<option value="${m.id}">${escapeHtml(m.meeting_type_name)} — ${escapeHtml(m.title)} (${formatDate(m.scheduled_date)})</option>`
            ).join('');

            const filter = document.getElementById('reviewMeetingFilter');
            filter.innerHTML = '<option value="">All meetings</option>' + openMeetings.map(m =>
                `<option value="${m.id}">${escapeHtml(m.title)} (${formatDate(m.scheduled_date)})</option>`
            ).join('');

            if (openMeetings.length === 0) {
                document.getElementById('newSubmissionBtn').disabled = true;
                document.getElementById('newSubmissionBtn').title = 'No upcoming meetings are open for submissions';
            }
        }

        function showSubmissionForm(submission = null) {
            const form = document.getElementById('submissionForm');
            form.reset();
            document.getElementById('submissionId').value = submission ? submission.id : '';
            document.getElementById('submissionMeeting').disabled = !!submission;
            document.getElementById('submissionFormHeading').textContent = submission ? 'Edit Submission' : 'New Submission';
            document.getElementById('submissionSaveBtn').textContent = submission ? 'Save Changes' : 'Submit for Review';

            if (submission) {
                document.getElementById('submissionMeeting').value = submission.meeting_id;
                document.getElementById('submissionTitle').value = submission.title;
                document.getElementById('submissionItemType').value = submission.item_type;
                document.getElementById('submissionDecisionMethod').value = submission.decision_method;
                document.getElementById('submissionReportType').value = submission.report_type;
                document.getElementById('submissionDuration').value = submission.duration_minutes || '';
                document.getElementById('submissionSummary').value = submission.summary || '';
                document.getElementById('submissionProposal').value = submission.proposal || '';
                document.getElementById('submissionSharePointUrl').value = submission.sharepoint_url || '';
            }
            updateProposalRequired();
            updateUrlRequired();

            const section = document.getElementById('submission-form-section');
            section.style.display = 'block';
            section.scrollIntoView({ behavior: 'smooth' });
        }

        function hideSubmissionForm() {
            document.getElementById('submission-form-section').style.display = 'none';
            document.getElementById('submissionForm').reset();
        }

        function updateProposalRequired() {
            const required = document.getElementById('submissionDecisionMethod').value !== 'None';
            document.getElementById('submissionProposal').required = required;
            document.getElementById('submissionProposalLabel').textContent = required ? 'Proposal *' : 'Proposal';
        }

        function updateUrlRequired() {
            const required = document.getElementById('submissionReportType').value === 'Written';
            document.getElementById('submissionSharePointUrl').required = required;
            document.getElementById('submissionSharePointUrlLabel').textContent = required ? 'SharePoint Link to Report *' : 'SharePoint Link to Supporting Papers (optional)';
        }

        async function saveSubmission(event) {
            event.preventDefault();
            const id = document.getElementById('submissionId').value;
            const data = {
                meeting_id: document.getElementById('submissionMeeting').value,
                title: document.getElementById('submissionTitle').value,
                item_type: document.getElementById('submissionItemType').value,
                decision_method: document.getElementById('submissionDecisionMethod').value,
                report_type: document.getElementById('submissionReportType').value,
                duration_minutes: document.getElementById('submissionDuration').value || null,
                summary: document.getElementById('submissionSummary').value,
                proposal: document.getElementById('submissionProposal').value,
                sharepoint_url: document.getElementById('submissionSharePointUrl').value
            };

            try {
                if (id) {
                    await apiCall('agenda_submissions.php', 'PUT', { ...data, id: parseInt(id), action: 'update' });
                    showNotification('Submission updated.');
                } else {
                    await apiCall('agenda_submissions.php', 'POST', data);
                    showNotification('Thank you - your agenda item has been submitted for review.');
                }
                hideSubmissionForm();
                loadMySubmissions();
                if (canReview) loadReviewQueue();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        async function loadMySubmissions() {
            const list = document.getElementById('my-submissions-list');
            try {
                mySubmissions = await apiCall('agenda_submissions.php?mine=1');
            } catch (error) {
                list.innerHTML = '<p>Error loading submissions.</p>';
                return;
            }
            if (mySubmissions.length === 0) {
                list.innerHTML = '<p>You have not submitted any agenda items yet.</p>';
                return;
            }
            list.innerHTML = renderSubmissionsTable(mySubmissions, false);
        }

        async function loadReviewQueue() {
            const list = document.getElementById('review-list');
            const params = new URLSearchParams();
            const meetingId = document.getElementById('reviewMeetingFilter').value;
            const status = document.getElementById('reviewStatusFilter').value;
            if (meetingId) params.set('meeting_id', meetingId);
            if (status) params.set('status', status);

            let submissions;
            try {
                submissions = await apiCall('agenda_submissions.php?' + params.toString());
            } catch (error) {
                list.innerHTML = '<p>Error loading submissions.</p>';
                return;
            }
            if (submissions.length === 0) {
                list.innerHTML = '<p>No submissions to show.</p>';
                return;
            }
            list.innerHTML = renderSubmissionsTable(submissions, true);
        }

        function renderSubmissionsTable(submissions, reviewMode) {
            return `
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Meeting</th>
                            ${reviewMode ? '<th>Submitted By</th>' : ''}
                            <th>Decision / Report</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${submissions.map(s => `
                            <tr>
                                <td>
                                    <strong>${escapeHtml(s.title)}</strong>
                                    ${s.summary ? `<div class="muted">${escapeHtml(s.summary)}</div>` : ''}
                                    ${s.proposal ? `<div class="muted proposal"><strong>Proposal:</strong> ${escapeHtml(s.proposal)}</div>` : ''}
                                </td>
                                <td>
                                    ${escapeHtml(s.meeting_title)}
                                    <div class="muted">${formatDate(s.meeting_date)}</div>
                                </td>
                                ${reviewMode ? `<td>
                                    ${s.submitter_first_name ? escapeHtml(s.submitter_first_name + ' ' + s.submitter_last_name) : escapeHtml(s.submitted_by_username || '—')}
                                    <div class="muted">${formatDateTime(s.created_at)}</div>
                                </td>` : ''}
                                <td>
                                    ${escapeHtml(s.decision_method === 'None' ? 'For information' : s.decision_method)}
                                    <div class="muted">${escapeHtml(s.report_type)}${s.duration_minutes ? ` · ${parseInt(s.duration_minutes)} min` : ''}</div>
                                </td>
                                <td>
                                    <span class="badge ${STATUS_BADGES[s.status] || ''}">${escapeHtml(s.status)}</span>
                                    ${s.status === 'Accepted' && s.agenda_item_number ? `<div class="muted">Item ${escapeHtml(s.agenda_item_number)}</div>` : ''}
                                    ${s.review_notes ? `<div class="muted">${escapeHtml(s.review_notes)}</div>` : ''}
                                </td>
                                <td>
                                    ${s.sharepoint_url ? `<a href="${escapeHtml(s.sharepoint_url)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm">Open Report</a>` : ''}
                                    ${s.status === 'Pending' && !reviewMode ? `
                                        <button onclick="editSubmission(${s.id})" class="btn btn-sm">Edit</button>
                                        <button onclick="withdrawSubmission(${s.id})" class="btn btn-sm btn-danger">Withdraw</button>
                                    ` : ''}
                                    ${s.status === 'Pending' && reviewMode ? `
                                        <button onclick="acceptSubmission(${s.id})" class="btn btn-sm btn-primary">Add to Agenda</button>
                                        <button onclick="declineSubmission(${s.id})" class="btn btn-sm btn-danger">Decline</button>
                                    ` : ''}
                                    ${s.status === 'Accepted' && reviewMode ? `<a href="meetings.php" class="btn btn-sm">Meetings</a>` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function editSubmission(id) {
            const submission = mySubmissions.find(s => s.id == id);
            if (submission) showSubmissionForm(submission);
        }

        async function withdrawSubmission(id) {
            if (!confirmAction('Withdraw this submission? The Secretary will no longer consider it.')) return;
            await updateSubmissionStatus({ id, action: 'withdraw' });
        }

        async function acceptSubmission(id) {
            const notes = prompt('Add this item to the end of the meeting agenda? You can reorder it from the Meetings page.\n\nOptional note to the submitter:', '');
            if (notes === null) return;
            await updateSubmissionStatus({ id, action: 'accept', review_notes: notes });
        }

        async function declineSubmission(id) {
            const notes = prompt('Reason for declining (shown to the submitter):', '');
            if (notes === null) return;
            if (!notes.trim()) {
                showNotification('Please give the submitter a reason.', 'error');
                return;
            }
            await updateSubmissionStatus({ id, action: 'decline', review_notes: notes });
        }

        async function updateSubmissionStatus(data) {
            try {
                await apiCall('agenda_submissions.php', 'PUT', data);
                if (canSubmit) loadMySubmissions();
                if (canReview) loadReviewQueue();
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        }

        // Escapes text for safe interpolation into HTML built via template literals.
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }
    </script>
</body>
</html>
