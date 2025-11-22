<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resolutions - Governance Board Management</title>
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
                    <li><a href="meetings.php">Meetings</a></li>
                    <li><a href="resolutions.php" class="active">Resolutions</a></li>
                    <li><a href="documents.php">Documents</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="page-header">
                <h2>Resolutions</h2>
            </div>

            <div class="organization-selector">
                <label for="orgSelect">Organization:</label>
                <select id="orgSelect" onchange="loadResolutions()">
                    <option value="">Select organization...</option>
                </select>
            </div>

            <div id="resolutions-list" class="meetings-list"></div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            loadOrganizationsIntoSelect('orgSelect').then(orgs => {
                if (orgs && orgs.length > 0) {
                    document.getElementById('orgSelect').value = orgs[0].id;
                    loadResolutions();
                }
            });
        });

        function loadResolutions() {
            const orgId = document.getElementById('orgSelect').value;
            if (!orgId) return;

            // Get all meetings for this organization, then get resolutions for each
            fetch(`api/meetings.php?organization_id=${orgId}`)
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

