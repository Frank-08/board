/**
 * PYY Meeting Management System - Main JavaScript
 */

// API Base URL
const API_BASE = 'api';

/**
 * Escape a value for interpolation into HTML built with template literals -
 * element content AND quoted attribute values (it escapes both quote
 * characters, which the old textContent/innerHTML trick did not). Every
 * value that came from the API must go through this (or textContent).
 * Not enough on its own inside inline event handlers (onclick="f('${x}')"):
 * pass ids/numbers there, never free text.
 */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

/**
 * Return url if it is an http(s) link (absolute, or relative to this site),
 * otherwise '#'. Blocks javascript:/data: URLs and backslash tricks in links
 * built from data. Escape the result with escapeHtml() when putting it in
 * an attribute.
 */
function safeUrl(url) {
    if (!url) return '#';
    const value = String(url).trim();
    if (/[\\\u0000-\u001F\u007F]/.test(value)) return '#';
    try {
        const parsed = new URL(value, window.location.href);
        if (parsed.protocol === 'https:' || parsed.protocol === 'http:') {
            return value;
        }
    } catch (e) {
        // fall through
    }
    return '#';
}

// Utility function for API calls
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(`${API_BASE}/${endpoint}`, options);

    // Forced password change (admin-set or default password)
    if (response.status === 403 && response.headers.get('Content-Type')?.includes('json')) {
        const body = await response.clone().json().catch(() => ({}));
        if (body.code === 'PASSWORD_CHANGE_REQUIRED') {
            window.location.href = 'change_password.php';
            throw new Error(body.error);
        }
    }
    
    // Handle authentication errors - redirect to login
    if (response.status === 401) {
        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
        throw new Error('Authentication required');
    }
    
    // Read the body as text first: a PHP fatal error or proxy error page
    // gives an empty or HTML body, and response.json() would only say
    // "Unexpected end of JSON input".
    const text = await response.text();
    let body = null;
    if (text.trim() !== '') {
        try {
            body = JSON.parse(text);
        } catch (e) {
            body = undefined; // not JSON
        }
    }
    const errorMessage = (fallback) => {
        if (body && typeof body.error === 'string') return body.error;
        if (body && typeof body.message === 'string') return body.message;
        return `${fallback} (HTTP ${response.status}${body === null ? ', empty response' : ', unexpected response'})`;
    };

    // Handle permission errors
    if (response.status === 403) {
        throw new Error(errorMessage('You do not have permission to perform this action'));
    }

    if (!response.ok) {
        throw new Error(errorMessage('API request failed'));
    }

    if (body === undefined) {
        throw new Error(`The server sent an unexpected response (HTTP ${response.status}). Check the server error log.`);
    }
    return body;
}

// Check if user has permission (uses authData from page)
function hasPermission(permission) {
    if (typeof authData === 'undefined') return false;
    return authData.permissions && authData.permissions[permission] === true;
}

// Check if user is admin
function isAdmin() {
    return hasPermission('isAdmin');
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format datetime for display
function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Format datetime for input fields
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

// Show notification
function showNotification(message, type = 'info') {
    // Simple alert for now - can be enhanced with a toast notification library
    alert(message);
}

// Confirm action
function confirmAction(message) {
    return confirm(message);
}

// Load committees into select element
async function loadCommitteesIntoSelect(selectId) {
    try {
        const committees = await apiCall('committees.php');
        const select = document.getElementById(selectId);
        
        if (!select) return;
        
        select.innerHTML = '<option value="">Select Committee...</option>';
        committees.forEach(committee => {
            const option = document.createElement('option');
            option.value = committee.id;
            option.textContent = committee.name;
            select.appendChild(option);
        });
        
        return committees;
    } catch (error) {
        console.error('Error loading committees:', error);
        return [];
    }
}

// Alias for backward compatibility
async function loadOrganizationsIntoSelect(selectId) {
    return loadCommitteesIntoSelect(selectId);
}

