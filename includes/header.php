<?php
/**
 * Shared Header Include
 * 
 * Include this at the top of all pages for authentication and consistent navigation.
 * Usage: require_once __DIR__ . '/includes/header.php';
 */

require_once __DIR__ . '/../config/auth.php';

// Require login for all pages that include this
requireLogin();

// Get current user info
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

/**
 * Output the header HTML
 * 
 * @param string $pageTitle The title for the page
 * @param string $activePage The filename of the current page (e.g., 'index.php')
 */
function outputHeader($pageTitle = 'Together in Council', $activePage = '') {
    global $currentUser;
    $activePage = $activePage ?: basename($_SERVER['PHP_SELF']);
    
    // Check permissions for various features
    $canManageMembers = hasPermission('manage_members');
    $canManageUsers = hasPermission('manage_users');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
            padding-left: 20px;
            border-left: 1px solid rgba(255,255,255,0.3);
        }
        .user-info .username {
            font-weight: 600;
        }
        .user-info .role {
            font-size: 12px;
            opacity: 0.8;
            padding: 2px 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
        }
        .user-info .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .user-info .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        header nav {
            display: flex;
            align-items: center;
        }
        header nav ul {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Together in Council</h1>
            <h3>One Church, many councils, discerning together</h3>
            <nav>
                <ul>
                    <li><a href="index.php" <?php echo $activePage === 'index.php' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                    <li><a href="members.php" <?php echo $activePage === 'members.php' ? 'class="active"' : ''; ?>>Board Members</a></li>
                    <li><a href="meetings.php" <?php echo $activePage === 'meetings.php' ? 'class="active"' : ''; ?>>Meetings</a></li>
                    <!-- <li><a href="resolutions.php" <?php echo $activePage === 'resolutions.php' ? 'class="active"' : ''; ?>>Resolutions</a></li> -->
                    <li><a href="documents.php" <?php echo $activePage === 'documents.php' ? 'class="active"' : ''; ?>>Documents</a></li>
                    <?php if ($canManageUsers): ?>
                    <li><a href="users.php" <?php echo $activePage === 'users.php' ? 'class="active"' : ''; ?>>Users</a></li>
                    <?php endif; ?>
                </ul>
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                    <span class="role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </nav>
        </header>
<?php
}

/**
 * Output the closing HTML tags
 */
function outputFooter() {
?>
    </div>
    <script src="assets/js/app.js"></script>
<?php
}

/**
 * Get JavaScript variables for auth state
 * Useful for client-side permission checks
 */
function getAuthJsVars() {
    global $currentUser;
    return json_encode([
        'user' => $currentUser,
        'permissions' => [
            'canManageMembers' => hasPermission('manage_members'),
            'canManageUsers' => hasPermission('manage_users'),
            'canCreateMeeting' => hasPermission('create_meeting'),
            'canEditMeeting' => hasPermission('edit_meeting'),
            'canDeleteMeeting' => hasPermission('delete_meeting'),
            'canManageAgenda' => hasPermission('manage_agenda'),
            'canManageMinutes' => hasPermission('manage_minutes'),
            'canUploadDocuments' => hasPermission('upload_documents'),
            'canCreateResolution' => hasPermission('create_resolution'),
            'canEditResolution' => hasPermission('edit_resolution'),
            'canDeleteResolution' => hasPermission('delete_resolution'),
            'isAdmin' => isAdmin()
        ]
    ]);
}

