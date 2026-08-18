<?php
/**
 * Generate an API key for a user, for non-browser clients (e.g. the
 * Standing Committee Word minutes macros - see ../word macro/README.md).
 * The key authenticates as the given user and carries their normal
 * role/permissions; only the SHA-256 hash is stored, so the raw key is
 * shown once and cannot be recovered afterwards.
 *
 * Usage: php database/generate_api_key.php <username> <label>
 * Example: php database/generate_api_key.php jsecretary "Word minutes macro"
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/../config/database.php';

$username = $argv[1] ?? null;
$label = $argv[2] ?? null;

if (!$username || !$label) {
    echo "Usage: php database/generate_api_key.php <username> <label>\n";
    exit(1);
}

try {
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT id, role, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "Error: no user found with username '$username'\n";
        exit(1);
    }
    if (!$user['is_active']) {
        echo "Error: user '$username' is not active\n";
        exit(1);
    }

    $rawKey = 'bapi_' . bin2hex(random_bytes(32));
    $keyHash = hash('sha256', $rawKey);

    $stmt = $db->prepare("INSERT INTO api_keys (user_id, label, key_hash) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $label, $keyHash]);

    echo "API key created for '$username' (role: {$user['role']}), label \"$label\"\n\n";
    echo "$rawKey\n\n";
    echo "This is shown once - store it now (e.g. modConfig.bas's API_KEY constant).\n";
    echo "It won't be recoverable from the database afterwards.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
