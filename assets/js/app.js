/**
 * PYY Meeting Management System - Main JavaScript
 */

// API Base URL
const API_BASE = 'api';

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
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'API request failed');
    }
    
    return response.json();
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

