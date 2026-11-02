<?php
/**
 * Document Helper Functions
 *
 * SharePoint link validation shared by api/documents.php,
 * api/agenda_submissions.php and the download/view redirects - documents
 * are SharePoint links, not uploads.
 *
 * Which hosts are accepted: SHAREPOINT_TENANTS in config/config.php, a list
 * of tenant names ('contoso' allows contoso.sharepoint.com and
 * contoso-my.sharepoint.com) or full host names. When it is undefined or
 * empty, any *.sharepoint.com host is accepted (the old behaviour) - set it
 * so links can only point at the organisation's own tenant(s).
 */

/**
 * Validate a SharePoint URL and return it rebuilt from its parsed parts, or
 * null if it isn't acceptable.
 *
 * Rejects anything browsers and PHP's parse_url() could disagree about -
 * backslashes (browsers treat "\" as "/", so "https://evil.com\@x.sharepoint.com"
 * is evil.com to a browser but x.sharepoint.com to parse_url), userinfo
 * ("@"), whitespace, control characters and quote/angle characters - and
 * any port other than 443.
 */
function normaliseSharePointUrl($url): ?string {
    if (!is_string($url)) {
        return null;
    }
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048) {
        return null;
    }
    if (preg_match('/[\\\\@\s\x00-\x1F\x7F"\'<>`]/', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass'])
        || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
        return null;
    }
    $host = strtolower($parts['host']);
    if (!isAllowedSharePointHost($host)) {
        return null;
    }
    return 'https://' . $host
        . ($parts['path'] ?? '/')
        . (isset($parts['query']) ? '?' . $parts['query'] : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function isAllowedSharePointHost(string $host): bool {
    // Suffix match, so look-alikes such as sharepoint.com.example.net are rejected
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.sharepoint(-df)?\.com$/', $host)) {
        return false;
    }
    $tenants = defined('SHAREPOINT_TENANTS') ? array_filter((array)SHAREPOINT_TENANTS) : [];
    if (!$tenants) {
        return true;
    }
    foreach ($tenants as $tenant) {
        $tenant = strtolower(trim((string)$tenant));
        if (strpos($tenant, '.') !== false) {
            if ($host === $tenant) {
                return true;
            }
        } elseif ($host === "$tenant.sharepoint.com" || $host === "$tenant-my.sharepoint.com") {
            return true;
        }
    }
    return false;
}

function isValidSharePointUrl($url) {
    return normaliseSharePointUrl($url) !== null;
}

/** Error message describing what's accepted */
function sharePointUrlRequirement(): string {
    $tenants = defined('SHAREPOINT_TENANTS') ? array_filter((array)SHAREPOINT_TENANTS) : [];
    return $tenants
        ? 'A valid HTTPS link to the organisation\'s SharePoint is required'
        : 'A valid HTTPS SharePoint URL is required';
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
