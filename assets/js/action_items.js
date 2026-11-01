/**
 * Action items UI shared by meetings.php (Action Items tab) and
 * action_items.php (all meetings). Needs app.js (apiCall, hasPermission)
 * and the page's authData. Talks to api/action_items.php, and to
 * api/claude.php for "Suggest with Claude" (only shown when canUseClaude).
 */
const ActionItems = (() => {
    const STATUSES = ['Open', 'In Progress', 'Done', 'Cancelled'];
    let membersCache = null;

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatDate(value) {
        if (!value) return '';
        const d = new Date(String(value).slice(0, 10) + 'T00:00:00');
        return isNaN(d) ? value : d.toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    async function members() {
        if (!membersCache) {
            membersCache = apiCall('members.php').catch(err => {
                membersCache = null;
                throw err;
            });
        }
        return membersCache;
    }

    function memberOptions(list, selectedId) {
        return '<option value="">Unassigned / other</option>' + list.map(m =>
            `<option value="${m.id}" ${String(m.id) === String(selectedId) ? 'selected' : ''}>${esc(m.first_name + ' ' + m.last_name)}</option>`
        ).join('');
    }

    function canEdit(item) {
        if (hasPermission('canManageActionItems')) return true;
        const myMemberId = authData && authData.user && authData.user.board_member_id;
        return hasPermission('canUpdateOwnActionItems') && myMemberId && String(item.assigned_member_id) === String(myMemberId);
    }

    /**
     * Render items into container.
     * options: { showMeeting: bool, onChange: fn() - called after any change }
     */
    function renderList(container, items, options = {}) {
        if (!items.length) {
            container.innerHTML = '<p style="color: #666;">No action items.</p>';
            return;
        }
        container.innerHTML = `
            <table class="action-items-table">
                <thead><tr>
                    <th>Action</th>
                    ${options.showMeeting ? '<th>Meeting</th>' : ''}
                    <th>Owner</th><th>Due</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>${items.map(item => renderRow(item, options)).join('')}</tbody>
            </table>`;
        container.querySelectorAll('[data-status-for]').forEach(select => {
            select.addEventListener('change', () => updateStatus(select.dataset.statusFor, select.value, options.onChange));
        });
        container.querySelectorAll('[data-edit]').forEach(btn => {
            const item = items.find(i => String(i.id) === btn.dataset.edit);
            btn.addEventListener('click', () => openModal(item, item.meeting_id, options.onChange));
        });
        container.querySelectorAll('[data-delete]').forEach(btn => {
            btn.addEventListener('click', () => remove(btn.dataset.delete, options.onChange));
        });
    }

    function renderRow(item, options) {
        const links = [
            item.agenda_item_number ? `Item ${esc(item.agenda_item_number)}` : '',
            item.resolution_number ? `Res. ${esc(item.resolution_number)}` : ''
        ].filter(Boolean).join(' · ');
        const editable = canEdit(item);
        const status = editable
            ? `<select data-status-for="${item.id}">${STATUSES.map(s => `<option ${s === item.status ? 'selected' : ''}>${s}</option>`).join('')}</select>`
            : `<span class="badge badge-${esc(item.status.toLowerCase().replace(' ', '-'))}">${esc(item.status)}</span>`;
        return `
            <tr class="${item.is_overdue ? 'overdue' : ''} ${['Done', 'Cancelled'].includes(item.status) ? 'closed' : ''}">
                <td>
                    ${esc(item.description)}
                    ${links ? `<div class="action-sub">${links}</div>` : ''}
                    ${item.notes ? `<div class="action-sub">Notes: ${esc(item.notes)}</div>` : ''}
                </td>
                ${options.showMeeting ? `<td><a href="meetings.php?id=${encodeURIComponent(item.meeting_id)}">${esc(item.meeting_type_name + ': ' + item.meeting_title)}</a><div class="action-sub">${esc(formatDate(item.meeting_date))}</div></td>` : ''}
                <td>${item.assigned_display ? esc(item.assigned_display) : '<span class="action-sub">Unassigned</span>'}</td>
                <td>${item.due_date ? esc(formatDate(item.due_date)) : '<span class="action-sub">—</span>'}${item.is_overdue ? '<div class="overdue-label">Overdue</div>' : ''}</td>
                <td>${status}</td>
                <td style="white-space: nowrap;">
                    ${editable ? `<button type="button" class="btn btn-sm" data-edit="${item.id}">Edit</button>` : ''}
                    ${hasPermission('canDeleteActionItems') ? `<button type="button" class="btn btn-sm btn-danger" data-delete="${item.id}">Delete</button>` : ''}
                </td>
            </tr>`;
    }

    async function updateStatus(id, status, onChange) {
        try {
            await apiCall('action_items.php', 'PUT', { id: Number(id), status });
            if (onChange) onChange();
        } catch (err) {
            alert('Error updating action item: ' + err.message);
            if (onChange) onChange();
        }
    }

    async function remove(id, onChange) {
        if (!confirm('Delete this action item?')) return;
        try {
            await apiCall('action_items.php', 'DELETE', { id: Number(id) });
            if (onChange) onChange();
        } catch (err) {
            alert('Error deleting action item: ' + err.message);
        }
    }

    function modalShell(id, title, body) {
        let modal = document.getElementById(id);
        if (!modal) {
            modal = document.createElement('div');
            modal.id = id;
            modal.className = 'modal';
            document.body.appendChild(modal);
            modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
        }
        modal.innerHTML = `
            <div class="modal-content modal-large">
                <span class="close" data-close>&times;</span>
                <h2>${esc(title)}</h2>
                ${body}
            </div>`;
        modal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => { modal.style.display = 'none'; }));
        modal.style.display = 'block';
        return modal;
    }

    /** Add (item null) or edit an action item for a meeting. */
    async function openModal(item, meetingId, onSaved) {
        const ownOnly = !hasPermission('canManageActionItems');
        let memberList = [], agenda = [], resolutions = [];
        if (!ownOnly) {
            [memberList, agenda, resolutions] = await Promise.all([
                members().catch(() => []),
                apiCall(`agenda.php?meeting_id=${meetingId}`).catch(() => []),
                apiCall(`resolutions.php?meeting_id=${meetingId}`).catch(() => [])
            ]);
        }
        const seen = new Set();
        agenda = agenda.filter(a => !seen.has(a.id) && seen.add(a.id));
        const v = item || { status: 'Open' };

        const modal = modalShell('actionItemModal', item ? 'Edit Action Item' : 'New Action Item', `
            <form id="actionItemForm">
                ${ownOnly ? `<p>${esc(v.description)}</p>` : `
                <div class="form-group">
                    <label for="aiDescription">Action *</label>
                    <textarea id="aiDescription" rows="3" required placeholder="e.g. Write to Smithtown congregation confirming the manse sale">${esc(v.description || '')}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiMember">Owner (member)</label>
                        <select id="aiMember">${memberOptions(memberList, v.assigned_member_id)}</select>
                    </div>
                    <div class="form-group">
                        <label for="aiOwnerName">…or other owner</label>
                        <input type="text" id="aiOwnerName" maxlength="150" placeholder="e.g. Presbytery Office" value="${esc(v.assigned_to_name || '')}">
                    </div>
                    <div class="form-group">
                        <label for="aiDue">Due date</label>
                        <input type="date" id="aiDue" value="${esc(v.due_date || '')}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiAgenda">From agenda item</label>
                        <select id="aiAgenda"><option value="">—</option>${agenda.map(a =>
                            `<option value="${a.id}" ${String(a.id) === String(v.agenda_item_id) ? 'selected' : ''}>${esc((a.item_number ? a.item_number + ' ' : '') + a.title)}</option>`).join('')}</select>
                    </div>
                    <div class="form-group">
                        <label for="aiResolution">From resolution</label>
                        <select id="aiResolution"><option value="">—</option>${resolutions.map(r =>
                            `<option value="${r.id}" ${String(r.id) === String(v.resolution_id) ? 'selected' : ''}>${esc(r.resolution_number || r.title || String(r.description).slice(0, 50))}</option>`).join('')}</select>
                    </div>
                </div>`}
                <div class="form-row">
                    <div class="form-group">
                        <label for="aiStatus">Status</label>
                        <select id="aiStatus">${STATUSES.map(s => `<option ${s === v.status ? 'selected' : ''}>${s}</option>`).join('')}</select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="aiNotes">Notes</label>
                    <textarea id="aiNotes" rows="2">${esc(v.notes || '')}</textarea>
                </div>
                <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" data-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>`);

        modal.querySelector('#actionItemForm').addEventListener('submit', async e => {
            e.preventDefault();
            const val = id => modal.querySelector('#' + id).value.trim();
            const data = { status: val('aiStatus'), notes: val('aiNotes') || null };
            if (!ownOnly) {
                Object.assign(data, {
                    description: val('aiDescription'),
                    assigned_member_id: val('aiMember') ? Number(val('aiMember')) : null,
                    assigned_to_name: val('aiOwnerName') || null,
                    due_date: val('aiDue') || null,
                    agenda_item_id: val('aiAgenda') ? Number(val('aiAgenda')) : null,
                    resolution_id: val('aiResolution') ? Number(val('aiResolution')) : null
                });
            }
            try {
                if (item) {
                    await apiCall('action_items.php', 'PUT', { id: item.id, ...data });
                } else {
                    await apiCall('action_items.php', 'POST', { meeting_id: meetingId, ...data });
                }
                modal.style.display = 'none';
                if (onSaved) onSaved();
            } catch (err) {
                alert('Error saving action item: ' + err.message);
            }
        });
    }

    /**
     * Ask Claude for action items from the meeting's minutes/resolutions,
     * then let the user edit, untick, and save the ones they want.
     */
    async function suggestWithClaude(meetingId, button, onSaved) {
        const label = button ? button.textContent : '';
        if (button) { button.disabled = true; button.textContent = 'Asking Claude…'; }
        let result, memberList;
        try {
            [result, memberList] = await Promise.all([
                apiCall('claude.php', 'POST', { action: 'suggest_action_items', meeting_id: meetingId }),
                members().catch(() => [])
            ]);
        } catch (err) {
            alert('Claude could not suggest action items: ' + err.message);
            return;
        } finally {
            if (button) { button.disabled = false; button.textContent = label; }
        }

        const items = result.items || [];
        if (!items.length) {
            alert('Claude found no new action items in this meeting\'s minutes and resolutions.');
            return;
        }

        const modal = modalShell('actionItemSuggestModal', 'Suggested action items', `
            <p style="color: #555;">Suggested by Claude from this meeting's minutes and resolutions. Check each one: untick any you don't want, fix owners and dates, then save.</p>
            <table class="action-items-table suggest-table">
                <thead><tr><th></th><th>Action</th><th>Owner</th><th>Due</th></tr></thead>
                <tbody>${items.map((s, i) => `
                    <tr data-row="${i}">
                        <td><input type="checkbox" checked data-keep></td>
                        <td>
                            <textarea rows="2" data-description style="width: 100%;">${esc(s.description)}</textarea>
                            ${s.source ? `<div class="action-sub">From: “${esc(s.source)}”</div>` : ''}
                        </td>
                        <td>
                            <select data-member>${memberOptions(memberList, s.assigned_member_id)}</select>
                            <input type="text" data-owner-name maxlength="150" placeholder="or other owner" value="${esc(s.assigned_to_name || '')}" style="margin-top: 4px; width: 100%;">
                        </td>
                        <td><input type="date" data-due value="${esc(s.due_date || '')}"></td>
                    </tr>`).join('')}
                </tbody>
            </table>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="button" class="btn btn-secondary" data-close>Cancel</button>
                <button type="button" class="btn btn-primary" data-save>Save ticked items</button>
            </div>`);

        modal.querySelector('[data-save]').addEventListener('click', async () => {
            const toSave = [];
            modal.querySelectorAll('tr[data-row]').forEach(row => {
                if (!row.querySelector('[data-keep]').checked) return;
                const s = items[Number(row.dataset.row)];
                const description = row.querySelector('[data-description]').value.trim();
                if (!description) return;
                const member = row.querySelector('[data-member]').value;
                toSave.push({
                    description,
                    assigned_member_id: member ? Number(member) : null,
                    assigned_to_name: row.querySelector('[data-owner-name]').value.trim() || null,
                    due_date: row.querySelector('[data-due]').value || null,
                    agenda_item_id: s.agenda_item_id,
                    resolution_id: s.resolution_id
                });
            });
            if (!toSave.length) {
                modal.style.display = 'none';
                return;
            }
            try {
                await apiCall('action_items.php', 'POST', { meeting_id: meetingId, items: toSave });
                modal.style.display = 'none';
                if (onSaved) onSaved();
            } catch (err) {
                alert('Error saving action items: ' + err.message);
            }
        });
    }

    return { renderList, openModal, suggestWithClaude, STATUSES };
})();
