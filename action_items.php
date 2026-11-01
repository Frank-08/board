<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/action_item_helpers.php';
outputHeader('Action Items', 'action_items.php');
?>
    <style>
        .action-filters { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; align-items: end; margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 6px; }
        .action-filters label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .action-filters select { width: 100%; padding: 7px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .action-summary { color: #555; margin-bottom: 10px; }
    </style>

        <main>
            <div class="page-header">
                <h2>Action Items</h2>
            </div>
            <p style="color: #555; margin-top: -10px;">Follow-up tasks from every meeting. Add them from a meeting's <strong>Action Items</strong> tab.</p>

            <div class="action-filters">
                <div>
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" onchange="loadActionItems()">
                        <option value="open">Open &amp; in progress</option>
                        <option value="">All</option>
                        <?php foreach (ACTION_ITEM_STATUSES as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filterOwner">Owner</label>
                    <select id="filterOwner" onchange="loadActionItems()">
                        <option value="">Anyone</option>
                        <?php if (!empty($currentUser['board_member_id'])): ?>
                        <option value="mine">Assigned to me</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="filterMeetingType">Meeting type</label>
                    <select id="filterMeetingType" onchange="loadActionItems()">
                        <option value="">All meeting types</option>
                    </select>
                </div>
                <div>
                    <label for="filterOverdue">Due</label>
                    <select id="filterOverdue" onchange="loadActionItems()">
                        <option value="">Any</option>
                        <option value="1">Overdue only</option>
                    </select>
                </div>
            </div>

            <div class="action-summary" id="actionSummary"></div>
            <div id="action-items-list"></div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/action_items.js"></script>
    <script>
        const authData = <?php echo getAuthJsVars(); ?>;
        let loadSeq = 0;

        window.addEventListener('DOMContentLoaded', () => {
            apiCall('meeting_types.php').then(types => {
                const select = document.getElementById('filterMeetingType');
                types.forEach(t => select.add(new Option(t.name, t.id)));
            }).catch(err => console.error('Error loading meeting types:', err));

            apiCall('members.php').then(members => {
                const select = document.getElementById('filterOwner');
                members.forEach(m => select.add(new Option(m.first_name + ' ' + m.last_name, m.id)));
            }).catch(err => console.error('Error loading members:', err));

            loadActionItems();
        });

        async function loadActionItems() {
            const seq = ++loadSeq;
            const params = new URLSearchParams();
            const status = document.getElementById('filterStatus').value;
            const owner = document.getElementById('filterOwner').value;
            const type = document.getElementById('filterMeetingType').value;
            const overdue = document.getElementById('filterOverdue').value;
            if (status) params.set('status', status);
            if (owner === 'mine') params.set('mine', '1');
            else if (owner) params.set('assigned_member_id', owner);
            if (type) params.set('meeting_type_id', type);
            if (overdue) params.set('overdue', '1');

            const list = document.getElementById('action-items-list');
            try {
                const items = await apiCall('action_items.php?' + params.toString());
                if (seq !== loadSeq) return;
                const overdueCount = items.filter(i => i.is_overdue).length;
                document.getElementById('actionSummary').textContent =
                    `${items.length} action item${items.length === 1 ? '' : 's'}` + (overdueCount ? ` · ${overdueCount} overdue` : '');
                ActionItems.renderList(list, items, { showMeeting: true, onChange: loadActionItems });
            } catch (err) {
                if (seq !== loadSeq) return;
                list.innerHTML = '<p>Error loading action items.</p>';
                console.error(err);
            }
        }
    </script>
</body>
</html>
