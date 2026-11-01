<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/resolution_helpers.php';
outputHeader('Resolutions', 'resolutions.php');
?>
    <style>
        .register-filters { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; align-items: end; margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 6px; }
        .register-filters label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .register-filters select, .register-filters input { width: 100%; padding: 7px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        .register-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .register-toolbar .summary { color: #555; }
        .register-bulk { display: none; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 15px; margin-bottom: 15px; background: #eef0fd; border-radius: 6px; }
        .register-bulk.visible { display: flex; }
        .register-bulk input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 220px; }
        .register-group h3 { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #667eea; padding-bottom: 6px; margin: 25px 0 12px; }
        .register-group h3 .count { font-size: 13px; font-weight: normal; color: #666; }
        .register-group h3 input[type=checkbox] { margin: 0; }
        .register-item { display: flex; gap: 12px; align-items: flex-start; }
        .badge-consensus, .badge-agreement { background: #d4edda; color: #155724; }
        .badge-withdrawn, .badge-lapsed { background: #e2e3e5; color: #41464b; }
        .register-item > input[type=checkbox] { margin-top: 4px; flex-shrink: 0; }
        .register-item .body { flex: 1; min-width: 0; }
        .register-item .context { font-size: 13px; color: #666; margin: 2px 0 6px; }
        .register-item .context a { color: #667eea; }
        .category-tag { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef0fd; color: #444; font-size: 12px; }
        .category-tag.none { background: #f1f1f1; color: #888; font-style: italic; }
        .category-edit { margin-left: 6px; font-size: 12px; background: none; border: none; color: #667eea; cursor: pointer; text-decoration: underline; padding: 0; }
    </style>

        <main>
            <div class="page-header">
                <h2>Resolutions Register</h2>
            </div>

            <form class="register-filters" id="registerFilters" onsubmit="event.preventDefault(); loadRegister();">
                <div>
                    <label for="filterMeetingType">Meeting type</label>
                    <select id="filterMeetingType" onchange="loadRegister()">
                        <option value="">All meeting types</option>
                    </select>
                </div>
                <div>
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" onchange="loadRegister()">
                        <option value="">All statuses</option>
                        <option value="adopted">Adopted (Consensus or Agreement)</option>
                        <option value="Proposed">Proposed</option>
                        <option value="Consensus">Consensus</option>
                        <option value="Agreement">Agreement</option>
                        <option value="Failed">Failed</option>
                        <option value="Withdrawn">Withdrawn</option>
                        <option value="Lapsed">Lapsed</option>
                    </select>
                </div>
                <div>
                    <label for="filterCategory">Category</label>
                    <select id="filterCategory" onchange="loadRegister()">
                        <option value="">All categories</option>
                    </select>
                </div>
                <div>
                    <label for="filterFrom">Meetings from</label>
                    <input type="date" id="filterFrom" onchange="loadRegister()">
                </div>
                <div>
                    <label for="filterTo">Meetings to</label>
                    <input type="date" id="filterTo" onchange="loadRegister()">
                </div>
                <div>
                    <label for="filterSearch">Search</label>
                    <input type="search" id="filterSearch" placeholder="Number, title or text" oninput="debouncedLoadRegister()">
                </div>
                <div>
                    <label for="groupBy">Group by</label>
                    <select id="groupBy" onchange="renderRegister()">
                        <?php foreach (RESOLUTION_REGISTER_GROUPINGS as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="register-toolbar">
                <span class="summary" id="registerSummary"></span>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="openExport('html')">Printable document / PDF</button>
                    <button type="button" class="btn btn-primary" onclick="openExport('word')">Download Word document</button>
                </div>
            </div>

            <div class="register-bulk" id="registerBulk">
                <strong id="bulkCount">0 selected</strong>
                <input type="text" id="bulkCategory" list="categoryOptions" maxlength="100" placeholder="Category, e.g. Finance">
                <button type="button" class="btn btn-sm btn-primary" onclick="applyBulkCategory(false)">Set category</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="applyBulkCategory(true)">Clear category</button>
                <button type="button" class="btn btn-sm" onclick="clearSelection()">Deselect</button>
            </div>
            <datalist id="categoryOptions"></datalist>

            <div id="resolutions-list"></div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        const UNCATEGORISED_FILTER = <?php echo json_encode(RESOLUTION_UNCATEGORISED_FILTER); ?>;
        const UNCATEGORISED_LABEL = <?php echo json_encode(RESOLUTION_UNCATEGORISED_LABEL); ?>;
        const canCategorise = hasPermission('canCategoriseResolutions');

        let registerRows = [];
        let selectedIds = new Set();
        let loadSeq = 0;
        let searchTimer = null;

        window.addEventListener('DOMContentLoaded', function() {
            apiCall('meeting_types.php').then(types => {
                const select = document.getElementById('filterMeetingType');
                types.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name;
                    select.appendChild(opt);
                });
            }).catch(err => console.error('Error loading meeting types:', err));

            loadCategories().then(loadRegister);
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        // Mirrors formatResolutionOutcomeText() in includes/resolution_helpers.php
        function formatOutcome(res) {
            const desc = res.description || '';
            switch (res.status) {
                case 'Consensus': return 'RESOLVED by consensus that ' + desc;
                case 'Agreement': return 'AGREEMENT recorded that ' + desc;
                case 'Failed': return 'The motion was LOST: ' + desc;
                case 'Withdrawn': return 'The motion was withdrawn.';
                case 'Lapsed': return 'The motion lapsed (not seconded) and was not recorded.';
                default: return desc;
            }
        }

        function formatMeetingDate(value) {
            if (!value) return '';
            const d = new Date(String(value).replace(' ', 'T'));
            return isNaN(d) ? value : d.toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric' });
        }

        async function loadCategories() {
            try {
                const categories = await apiCall('resolutions.php?categories=1');
                const filter = document.getElementById('filterCategory');
                const current = filter.value;
                filter.innerHTML = '<option value="">All categories</option>'
                    + `<option value="${escapeHtml(UNCATEGORISED_FILTER)}">${escapeHtml(UNCATEGORISED_LABEL)} only</option>`
                    + categories.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
                // Keep the current filter if it still exists
                filter.value = Array.from(filter.options).some(o => o.value === current) ? current : '';
                document.getElementById('categoryOptions').innerHTML =
                    categories.map(c => `<option value="${escapeHtml(c)}"></option>`).join('');
            } catch (err) {
                console.error('Error loading categories:', err);
            }
        }

        function currentFilters() {
            return {
                meeting_type_id: document.getElementById('filterMeetingType').value,
                status: document.getElementById('filterStatus').value,
                category: document.getElementById('filterCategory').value,
                from: document.getElementById('filterFrom').value,
                to: document.getElementById('filterTo').value,
                q: document.getElementById('filterSearch').value.trim()
            };
        }

        function filterQuery(extra = {}) {
            const params = new URLSearchParams();
            Object.entries({ ...currentFilters(), ...extra }).forEach(([k, v]) => {
                if (v !== '' && v != null) params.set(k, v);
            });
            return params.toString();
        }

        function debouncedLoadRegister() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadRegister, 300);
        }

        async function loadRegister() {
            const seq = ++loadSeq;
            document.getElementById('registerSummary').textContent = 'Loading…';
            try {
                const rows = await apiCall('resolutions.php?register=1&' + filterQuery());
                if (seq !== loadSeq) return; // a newer request superseded this one
                registerRows = rows;
                // Drop selections that are no longer visible
                const visible = new Set(rows.map(r => String(r.id)));
                selectedIds = new Set([...selectedIds].filter(id => visible.has(id)));
                renderRegister();
            } catch (err) {
                if (seq !== loadSeq) return;
                document.getElementById('registerSummary').textContent = '';
                document.getElementById('resolutions-list').innerHTML = `<p>Error loading resolutions: ${escapeHtml(err.message)}</p>`;
            }
        }

        // Same grouping rules as groupResolutionRegister() in includes/resolution_helpers.php
        function groupRows(rows, groupBy) {
            if (groupBy === 'none') return rows.length ? [{ label: 'All resolutions', rows }] : [];
            const groups = new Map();
            rows.forEach(r => {
                let label;
                switch (groupBy) {
                    case 'category': label = (r.category || '').trim() || UNCATEGORISED_LABEL; break;
                    case 'meeting_type': label = r.meeting_type_name || ''; break;
                    case 'year': label = String(r.meeting_date || '').slice(0, 4); break;
                    default: label = r[groupBy] || '';
                }
                // Case-insensitive, matching the DB collation; first spelling wins
                const key = label.toLowerCase();
                if (!groups.has(key)) groups.set(key, { label, rows: [] });
                groups.get(key).rows.push(r);
            });
            let list = [...groups.values()];
            if (groupBy === 'category') {
                list.sort((a, b) => {
                    if (a.label === UNCATEGORISED_LABEL) return 1;
                    if (b.label === UNCATEGORISED_LABEL) return -1;
                    return a.label.localeCompare(b.label, undefined, { sensitivity: 'base' });
                });
            } else if (groupBy === 'year') {
                list.sort((a, b) => a.label.localeCompare(b.label));
            }
            return list;
        }

        function renderRegister() {
            const groupBy = document.getElementById('groupBy').value;
            const list = document.getElementById('resolutions-list');
            const n = registerRows.length;
            document.getElementById('registerSummary').textContent = `${n} resolution${n === 1 ? '' : 's'}`;
            updateBulkBar();

            if (n === 0) {
                list.innerHTML = '<p>No resolutions match these filters. Resolutions are recorded within meetings.</p>';
                return;
            }

            list.innerHTML = groupRows(registerRows, groupBy).map((group, gi) => {
                const ids = group.rows.map(r => String(r.id));
                const allChecked = ids.every(id => selectedIds.has(id));
                return `
                <section class="register-group">
                    ${groupBy !== 'none' ? `<h3>
                        ${canCategorise ? `<input type="checkbox" title="Select all in this group" ${allChecked ? 'checked' : ''} onchange="toggleGroup(${gi}, this.checked)">` : ''}
                        ${escapeHtml(group.label)} <span class="count">(${group.rows.length})</span>
                    </h3>` : ''}
                    ${group.rows.map(renderItem).join('')}
                </section>`;
            }).join('');
        }

        function renderItem(res) {
            const id = String(res.id);
            const headingParts = [res.resolution_number, res.title || res.agenda_item_title].filter(Boolean);
            const heading = headingParts.length ? headingParts.join(' — ') : 'Resolution';
            const context = [
                `<a href="meetings.php?id=${encodeURIComponent(res.meeting_id)}">${escapeHtml(res.meeting_type_name + ': ' + res.meeting_title)}</a>`,
                escapeHtml(formatMeetingDate(res.meeting_date)),
                res.agenda_item_number ? 'Item ' + escapeHtml(res.agenda_item_number) : ''
            ].filter(Boolean).join(' · ');
            const category = res.category
                ? `<span class="category-tag">${escapeHtml(res.category)}</span>`
                : `<span class="category-tag none">${escapeHtml(UNCATEGORISED_LABEL)}</span>`;

            return `
            <div class="resolution-item register-item">
                ${canCategorise ? `<input type="checkbox" ${selectedIds.has(id) ? 'checked' : ''} onchange="toggleSelected('${id}', this.checked)" aria-label="Select resolution">` : ''}
                <div class="body">
                    <div class="meeting-header">
                        <h4 style="margin: 0;">${escapeHtml(heading)}</h4>
                        <span class="badge badge-${escapeHtml(String(res.status).toLowerCase())}">${escapeHtml(res.status)}</span>
                    </div>
                    <p class="context">${context}</p>
                    <p>${escapeHtml(formatOutcome(res))}</p>
                    <p>
                        ${category}
                        ${canCategorise ? `<button type="button" class="category-edit" onclick="editCategory('${id}')">${res.category ? 'Change' : 'Set category'}</button>` : ''}
                        ${res.decision_method ? `<span style="margin-left: 10px; color: #666; font-size: 13px;">${escapeHtml(res.decision_method)}</span>` : ''}
                    </p>
                </div>
            </div>`;
        }

        function toggleSelected(id, checked) {
            if (checked) selectedIds.add(id); else selectedIds.delete(id);
            renderRegister();
        }

        function toggleGroup(groupIndex, checked) {
            const group = groupRows(registerRows, document.getElementById('groupBy').value)[groupIndex];
            if (!group) return;
            group.rows.forEach(r => checked ? selectedIds.add(String(r.id)) : selectedIds.delete(String(r.id)));
            renderRegister();
        }

        function clearSelection() {
            selectedIds.clear();
            renderRegister();
        }

        function updateBulkBar() {
            const bar = document.getElementById('registerBulk');
            bar.classList.toggle('visible', canCategorise && selectedIds.size > 0);
            document.getElementById('bulkCount').textContent = `${selectedIds.size} selected`;
        }

        async function setCategory(ids, category) {
            await apiCall('resolutions.php', 'POST', { action: 'set_category', ids: ids.map(Number), category });
            await loadCategories();
            await loadRegister();
        }

        async function applyBulkCategory(clear) {
            const ids = [...selectedIds];
            if (!ids.length) return;
            const category = clear ? null : document.getElementById('bulkCategory').value.trim();
            if (!clear && !category) {
                alert('Enter a category to apply, or use "Clear category".');
                return;
            }
            try {
                await setCategory(ids, category);
                selectedIds.clear();
                document.getElementById('bulkCategory').value = '';
                renderRegister();
            } catch (err) {
                alert('Error setting category: ' + err.message);
            }
        }

        async function editCategory(id) {
            const res = registerRows.find(r => String(r.id) === id);
            if (!res) return;
            const value = prompt('Category for this resolution (leave blank to clear):', res.category || '');
            if (value === null) return;
            try {
                await setCategory([id], value.trim() || null);
            } catch (err) {
                alert('Error setting category: ' + err.message);
            }
        }

        function openExport(format) {
            const url = 'export/resolutions.php?' + filterQuery({
                group_by: document.getElementById('groupBy').value,
                format
            });
            if (format === 'word') {
                window.location.href = url;
            } else {
                window.open(url, '_blank');
            }
        }
    </script>
</body>
</html>
