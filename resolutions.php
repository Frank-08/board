<?php
require_once __DIR__ . '/includes/header.php';
outputHeader('Resolutions', 'resolutions.php');
?>

        <main>
            <div class="page-header">
                <h2>Resolutions</h2>
            </div>

            <div class="organization-selector">
                <label for="committeeSelect">Committee:</label>
                <select id="committeeSelect" onchange="loadResolutions()">
                    <option value="">Select committee...</option>
                </select>
            </div>

            <div id="resolutions-list" class="meetings-list"></div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Auth permissions from server
        const authData = <?php echo getAuthJsVars(); ?>;
        
        window.addEventListener('DOMContentLoaded', function() {
            loadOrganizationsIntoSelect('committeeSelect').then(committees => {
                if (committees && committees.length > 0) {
                    document.getElementById('committeeSelect').value = committees[0].id;
                    loadResolutions();
                }
            });
        });

        function loadResolutions() {
            const committeeId = document.getElementById('committeeSelect').value;
            if (!committeeId) return;

            // Get all meetings for this committee, then get resolutions for each
            fetch(`api/meetings.php?committee_id=${committeeId}`)
                .then(response => response.json())
                .then(meetings => {
                    const meetingIds = meetings.map(m => m.id);
                    if (meetingIds.length === 0) {
                        document.getElementById('resolutions-list').innerHTML = '<p>No meetings found. Resolutions are created within meetings.</p>';
                        return;
                    }
                    
                    // Fetch resolutions for all meetings
                    Promise.all(meetingIds.map(id => 
                        fetch(`api/resolutions.php?meeting_id=${id}`).then(r => r.json())
                    ))
                    .then(resolutionArrays => {
                        const allResolutions = resolutionArrays.flat();
                        displayResolutions(allResolutions);
                    });
                });
        }

        function displayResolutions(resolutions) {
            const list = document.getElementById('resolutions-list');
            if (resolutions.length === 0) {
                list.innerHTML = '<p>No resolutions found.</p>';
                return;
            }
            
            list.innerHTML = resolutions.map(res => `
                <div class="resolution-item">
                    <div class="meeting-header">
                        <h3>${res.title}</h3>
                        <span class="badge badge-${res.status.toLowerCase()}">${res.status}</span>
                    </div>
                    <p>${res.description}</p>
                    ${res.resolution_number ? `<p><strong>Resolution #:</strong> ${res.resolution_number}</p>` : ''}
                    ${res.moved_first_name ? `<p><strong>Moved by:</strong> ${res.moved_first_name} ${res.moved_last_name}</p>` : ''}
                    ${res.vote_type ? `<p><strong>Vote:</strong> ${res.votes_for} for, ${res.votes_against} against, ${res.votes_abstain} abstain (${res.vote_type})</p>` : ''}
                </div>
            `).join('');
        }
    </script>
</body>
</html>

