<?php
/**
 * Document Helper Functions
 *
 * SharePoint link validation shared by api/documents.php and
 * api/agenda_submissions.php - documents are SharePoint links, not uploads.
 */

function isValidSharePointUrl($url) {
    if (!is_string($url) || trim($url) === '') {
        return false;
    }

    $parsedUrl = parse_url(trim($url));
    if (!$parsedUrl || ($parsedUrl['scheme'] ?? '') !== 'https') {
        return false;
    }

    // Suffix match, so look-alikes such as sharepoint.com.example.net are rejected
    $host = strtolower($parsedUrl['host'] ?? '');
    return (bool)preg_match('/(^|\.)sharepoint(-df)?\.com$/', $host);
}

function extractFileNameFromUrl($url) {
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) {
        return null;
    }

    $baseName = basename($path);
    if ($baseName === '' || $baseName === '/' || $baseName === '.') {
        return null;
    }

    return urldecode($baseName);
}
