<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Governance Board Management</title>
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
                    <li><a href="resolutions.php">Resolutions</a></li>
                    <li><a href="documents.php" class="active">Documents</a></li>
                    <li style="float: right;">
                        <span style="margin-right: 15px; color: #666;">
                            <?php 
                            $user = getCurrentUser();
                            echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] ?: $user['username']);
                            ?>
                        </span>
                        <a href="#" onclick="handleLogout(); return false;">Logout</a>
                    </li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="page-header">
                <h2>Documents</h2>
                <button onclick="showUploadModal()" class="btn btn-primary">+ Upload Document</button>
            </div>

            <div id="documents-list">
                <p>Loading documents...</p>
            </div>
        </main>
    </div>

    <!-- Upload Document Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeUploadModal()">&times;</span>
            <h2>Upload Document</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="uploadTitle">Title *</label>
                    <input type="text" id="uploadTitle" required>
                </div>
                <div class="form-group">
                    <label for="uploadDescription">Description</label>
                    <textarea id="uploadDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="uploadDocumentType">Document Type</label>
                    <select id="uploadDocumentType">
                        <option value="Other">Other</option>
                        <option value="Agenda">Agenda</option>
                        <option value="Minutes">Minutes</option>
                        <option value="Resolution">Resolution</option>
                        <option value="Report">Report</option>
                        <option value="Policy">Policy</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="uploadMeetingType">Meeting Type (Optional)</label>
                    <select id="uploadMeetingType" onchange="loadMeetingsForUpload()">
                        <option value="">Select meeting type...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="uploadMeeting">Meeting (Optional)</label>
                    <select id="uploadMeeting" onchange="loadAgendaItemsForUpload()">
                        <option value="">Select meeting...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="uploadAgendaItem">Agenda Item (Optional)</label>
                    <select id="uploadAgendaItem">
                        <option value="">Select agenda item...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="uploadFile">File *</label>
                    <input type="file" id="uploadFile" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload Document</button>
            </form>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Document</h2>
            <form id="editForm">
                <input type="hidden" id="editDocumentId">
                <div class="form-group">
                    <label for="editTitle">Title *</label>
                    <input type="text" id="editTitle" required>
                </div>
                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea id="editDescription" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="editDocumentType">Document Type</label>
                    <select id="editDocumentType">
                        <option value="Other">Other</option>
                        <option value="Agenda">Agenda</option>
                        <option value="Minutes">Minutes</option>
                        <option value="Resolution">Resolution</option>
                        <option value="Report">Report</option>
                        <option value="Policy">Policy</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        let allMeetingTypes = [];
        let allMeetings = [];

        window.addEventListener('DOMContentLoaded', function() {
            loadMeetingTypes();
            loadDocuments();
        });

        function loadMeetingTypes() {
            fetch('api/meeting_types.php')
                .then(response => response.json())
                .then(data => {
                    allMeetingTypes = data;
                    const select = document.getElementById('uploadMeetingType');
                    select.innerHTML = '<option value="">Select meeting type...</option>';
                    data.forEach(mt => {
                        const option = document.createElement('option');
                        option.value = mt.id;
                        option.textContent = mt.name;
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading meeting types:', error);
                });
        }

        function loadMeetingsForUpload() {
            const meetingTypeId = document.getElementById('uploadMeetingType').value;
            const meetingSelect = document.getElementById('uploadMeeting');
            meetingSelect.innerHTML = '<option value="">Select meeting...</option>';
            document.getElementById('uploadAgendaItem').innerHTML = '<option value="">Select agenda item...</option>';
            
            if (!meetingTypeId) return;
            
            fetch(`api/meetings.php?meeting_type_id=${meetingTypeId}`)
                .then(response => response.json())
                .then(data => {
                    allMeetings = data;
                    data.forEach(meeting => {
                        const option = document.createElement('option');
                        option.value = meeting.id;
                        option.textContent = meeting.title + ' - ' + formatDate(meeting.scheduled_date);
                        meetingSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading meetings:', error);
                });
        }

        function loadAgendaItemsForUpload() {
            const meetingId = document.getElementById('uploadMeeting').value;
            const agendaSelect = document.getElementById('uploadAgendaItem');
            agendaSelect.innerHTML = '<option value="">Select agenda item...</option>';
            
            if (!meetingId) return;
            
            fetch(`api/agenda.php?meeting_id=${meetingId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = (item.item_number || '') + (item.item_number ? '. ' : '') + item.title;
                        agendaSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading agenda items:', error);
                });
        }

        function loadDocuments() {
            fetch('api/documents.php')
                .then(response => response.json())
                .then(data => {
                    const list = document.getElementById('documents-list');
                    if (data.length === 0) {
                        list.innerHTML = '<p>No documents found. Upload your first document.</p>';
                        return;
                    }
                    
                    list.innerHTML = `
                        <table class="documents-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <thead>
                                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                    <th style="padding: 12px; text-align: left;">Title</th>
                                    <th style="padding: 12px; text-align: left;">Type</th>
                                    <th style="padding: 12px; text-align: left;">File Name</th>
                                    <th style="padding: 12px; text-align: left;">Size</th>
                                    <th style="padding: 12px; text-align: left;">Linked To</th>
                                    <th style="padding: 12px; text-align: left;">Uploaded By</th>
                                    <th style="padding: 12px; text-align: left;">Date</th>
                                    <th style="padding: 12px; text-align: left;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.map(doc => `
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 12px;">${escapeHtml(doc.title)}</td>
                                        <td style="padding: 12px;">
                                            <span class="badge badge-${doc.document_type.toLowerCase()}" style="padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                                                ${doc.document_type}
                                            </span>
                                        </td>
                                        <td style="padding: 12px; color: #666;">${escapeHtml(doc.file_name || 'N/A')}</td>
                                        <td style="padding: 12px; color: #666;">${formatFileSize(doc.file_size || 0)}</td>
                                        <td style="padding: 12px; color: #666;">
                                            ${doc.meeting_title ? `<div><strong>Meeting:</strong> ${escapeHtml(doc.meeting_title)}</div>` : ''}
                                            ${doc.agenda_item_title ? `<div><strong>Agenda Item:</strong> ${escapeHtml(doc.agenda_item_title)}</div>` : ''}
                                            ${!doc.meeting_title && !doc.agenda_item_title ? '—' : ''}
                                        </td>
                                        <td style="padding: 12px; color: #666;">
                                            ${doc.uploaded_first_name ? `${escapeHtml(doc.uploaded_first_name)} ${escapeHtml(doc.uploaded_last_name)}` : '—'}
                                        </td>
                                        <td style="padding: 12px; color: #666;">${formatDate(doc.created_at)}</td>
                                        <td style="padding: 12px;">
                                            <a href="api/download.php?id=${doc.id}" target="_blank" class="btn btn-sm" style="margin-right: 5px;">Download</a>
                                            <button onclick="showEditModal(${doc.id})" class="btn btn-sm">Edit</button>
                                            <button onclick="deleteDocument(${doc.id})" class="btn btn-sm btn-danger">Delete</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                })
                .catch(error => {
                    console.error('Error loading documents:', error);
                    document.getElementById('documents-list').innerHTML = '<p>Error loading documents.</p>';
                });
        }

        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('uploadForm').reset();
            document.getElementById('uploadMeeting').innerHTML = '<option value="">Select meeting...</option>';
            document.getElementById('uploadAgendaItem').innerHTML = '<option value="">Select agenda item...</option>';
        }

        function showEditModal(documentId) {
            fetch(`api/documents.php?id=${documentId}`)
                .then(response => response.json())
                .then(doc => {
                    document.getElementById('editDocumentId').value = doc.id;
                    document.getElementById('editTitle').value = doc.title;
                    document.getElementById('editDescription').value = doc.description || '';
                    document.getElementById('editDocumentType').value = doc.document_type;
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error loading document:', error);
                    alert('Error loading document details');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editForm').reset();
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            const fileInput = document.getElementById('uploadFile');
            
            if (!fileInput.files[0]) {
                alert('Please select a file');
                return;
            }
            
            formData.append('file', fileInput.files[0]);
            formData.append('title', document.getElementById('uploadTitle').value);
            formData.append('description', document.getElementById('uploadDescription').value);
            formData.append('document_type', document.getElementById('uploadDocumentType').value);
            
            const meetingTypeId = document.getElementById('uploadMeetingType').value;
            const meetingId = document.getElementById('uploadMeeting').value;
            const agendaItemId = document.getElementById('uploadAgendaItem').value;
            
            if (meetingTypeId) formData.append('meeting_type_id', meetingTypeId);
            if (meetingId) formData.append('meeting_id', meetingId);
            if (agendaItemId) formData.append('agenda_item_id', agendaItemId);
            
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
                    closeUploadModal();
                    loadDocuments();
                }
            })
            .catch(error => {
                console.error('Error uploading document:', error);
                alert('Error uploading document: ' + error.message);
            });
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const documentId = document.getElementById('editDocumentId').value;
            const data = {
                id: documentId,
                title: document.getElementById('editTitle').value,
                description: document.getElementById('editDescription').value,
                document_type: document.getElementById('editDocumentType').value
            };
            
            fetch('api/documents.php', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Update failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    closeEditModal();
                    loadDocuments();
                }
            })
            .catch(error => {
                console.error('Error updating document:', error);
                alert('Error updating document: ' + error.message);
            });
        });

        function deleteDocument(documentId) {
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
                    loadDocuments();
                }
            })
            .catch(error => {
                console.error('Error deleting document:', error);
                alert('Error deleting document');
            });
        }

        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const uploadModal = document.getElementById('uploadModal');
            const editModal = document.getElementById('editModal');
            if (event.target == uploadModal) {
                closeUploadModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        function handleLogout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('api/auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'logout'})
                })
                .then(response => response.json())
                .then(data => {
                    window.location.href = 'login.php';
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = 'login.php';
                });
            }
        }
    </script>
</body>
</html>
