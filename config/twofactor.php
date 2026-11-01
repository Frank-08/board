<?php
/**
 * Two-Factor Authentication (2FA) Helper Functions
 * 
 * Implements TOTP (Time-based One-Time Password) using RFC 6238
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 */

require_once __DIR__ . '/database.php';

// TOTP configuration
define('TOTP_ISSUER', 'Together in Council');
define('TOTP_PERIOD', 30); // 30 seconds per code
define('TOTP_DIGITS', 6); // 6-digit codes
define('TOTP_ALGORITHM', 'sha1'); // SHA1 is standard for TOTP

/**
 * Generate a random secret for TOTP
 * 
 * @return string Base32-encoded secret
 */
function generateTwoFactorSecret(): string {
    // Generate 20 random bytes (160 bits) as recommended by RFC 6238
    $randomBytes = random_bytes(20);
    return base32Encode($randomBytes);
}

/**
 * Encode binary data to Base32
 * 
 * @param string $data Binary data
 * @return string Base32-encoded string
 */
function base32Encode(string $data): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $result = '';
    $buffer = 0;
    $bitsLeft = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bitsLeft += 8;
        
        while ($bitsLeft >= 5) {
            $result .= $chars[($buffer >> ($bitsLeft - 5)) & 31];
            $bitsLeft -= 5;
        }
    }
    
    if ($bitsLeft > 0) {
        $result .= $chars[($buffer << (5 - $bitsLeft)) & 31];
    }
    
    return $result;
}

/**
 * Decode Base32 string to binary
 * 
 * @param string $data Base32-encoded string
 * @return string Binary data
 */
function base32Decode(string $data): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper($data);
    $result = '';
    $buffer = 0;
    $bitsLeft = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $pos = strpos($chars, $data[$i]);
        if ($pos === false) {
            continue;
        }
        
        $buffer = ($buffer << 5) | $pos;
        $bitsLeft += 5;
        
        if ($bitsLeft >= 8) {
            $result .= chr(($buffer >> ($bitsLeft - 8)) & 255);
            $bitsLeft -= 8;
        }
    }
    
    return $result;
}

/**
 * Generate TOTP code from secret
 * 
 * @param string $secret Base32-encoded secret
 * @param int|null $time Unix timestamp (null = current time)
 * @return string 6-digit code
 */
function generateTOTPCode(string $secret, ?int $time = null): string {
    if ($time === null) {
        $time = time();
    }
    
    // Calculate time counter
    $timeCounter = floor($time / TOTP_PERIOD);
    
    // Decode secret
    $key = base32Decode($secret);
    
    // Pack time counter as 64-bit big-endian integer
    $timeCounterBytes = pack('N*', 0, $timeCounter);
    
    // Generate HMAC
    $hmac = hash_hmac(TOTP_ALGORITHM, $timeCounterBytes, $key, true);
    
    // Dynamic truncation (RFC 4226)
    $offset = ord($hmac[19]) & 0x0F;
    $code = (
        ((ord($hmac[$offset]) & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
        (ord($hmac[$offset + 3]) & 0xFF)
    ) % pow(10, TOTP_DIGITS);
    
    // Pad with leading zeros
    return str_pad((string)$code, TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Verify TOTP code
 * Allows for clock skew by checking current, previous, and next time windows
 * 
 * @param string $secret Base32-encoded secret
 * @param string $code Code to verify
 * @param int $window Number of time windows to check on each side (default: 1)
 * @return bool True if code is valid
 */
function verifyTOTPCode(string $secret, string $code, int $window = 1): bool {
    return matchTOTPStep($secret, $code, $window) !== null;
}

/**
 * Like verifyTOTPCode(), but returns the time step (counter) the code
 * matched, or null - so callers can refuse to accept the same step twice.
 */
function matchTOTPStep(string $secret, string $code, int $window = 1): ?int {
    $code = trim($code);
    
    if (strlen($code) !== TOTP_DIGITS || !ctype_digit($code)) {
        return null;
    }
    
    $time = time();
    
    // Check current and adjacent time windows to account for clock skew
    for ($i = -$window; $i <= $window; $i++) {
        $testTime = $time + ($i * TOTP_PERIOD);
        $testCode = generateTOTPCode($secret, $testTime);
        if (hash_equals($testCode, $code)) {
            return (int)floor($testTime / TOTP_PERIOD);
        }
    }
    
    return null;
}

/**
 * Optional at-rest encryption of TOTP secrets. Set TWOFACTOR_ENCRYPTION_KEY
 * in config/config.php to 64 hex chars (php -r "echo bin2hex(random_bytes(32));")
 * and keep it out of the database backups. Secrets saved before the key was
 * set stay readable and are re-saved encrypted on the user's next sign-in.
 * Losing the key means everyone with 2FA has to re-enrol.
 */
function twoFactorEncryptionKey(): ?string {
    if (!defined('TWOFACTOR_ENCRYPTION_KEY') || TWOFACTOR_ENCRYPTION_KEY === '' || !function_exists('sodium_crypto_secretbox')) {
        return null;
    }
    $key = @hex2bin(TWOFACTOR_ENCRYPTION_KEY);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        error_log('TWOFACTOR_ENCRYPTION_KEY must be 64 hex characters - 2FA secrets are being stored unencrypted');
        return null;
    }
    return $key;
}

function encryptTwoFactorSecret(string $secret): string {
    $key = twoFactorEncryptionKey();
    if ($key === null) {
        return $secret;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'enc1:' . base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $key));
}

function decryptTwoFactorSecret(string $stored): ?string {
    if (strpos($stored, 'enc1:') !== 0) {
        return $stored; // stored before encryption was configured
    }
    $key = twoFactorEncryptionKey();
    $raw = base64_decode(substr($stored, 5), true);
    if ($key === null || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        error_log('Cannot decrypt a 2FA secret - is TWOFACTOR_ENCRYPTION_KEY set correctly?');
        return null;
    }
    $plain = sodium_crypto_secretbox_open(
        substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        $key
    );
    return $plain === false ? null : $plain;
}

/**
 * Generate QR code URL for authenticator apps
 * 
 * @param string $secret Base32-encoded secret
 * @param string $email User email
 * @return string otpauth:// URL
 */
function generateTwoFactorQRUrl(string $secret, string $email): string {
    $issuer = urlencode(TOTP_ISSUER);
    $label = urlencode($email);
    
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
        $issuer,
        $label,
        $secret,
        $issuer,
        strtoupper(TOTP_ALGORITHM),
        TOTP_DIGITS,
        TOTP_PERIOD
    );
}

/**
 * Render a QR code for $text as an SVG string (no GD, no temp files, no
 * request carrying the data). Returns null if the phpqrcode library is
 * missing.
 */
function renderQrCodeSvg(string $text, int $margin = 2): ?string {
    $lib = __DIR__ . '/../libs/phpqrcode.php';
    if (!file_exists($lib)) {
        return null;
    }
    require_once $lib;
    $rows = QRcode::text($text, false, QR_ECLEVEL_M, 1, 0);
    if (!is_array($rows) || !$rows) {
        return null;
    }
    $size = count($rows) + 2 * $margin;
    $path = '';
    foreach ($rows as $y => $row) {
        for ($x = 0, $n = strlen($row); $x < $n; $x++) {
            if ($row[$x] === '1') {
                $path .= 'M' . ($x + $margin) . ',' . ($y + $margin) . 'h1v1h-1z';
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges">'
        . '<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
}

/**
 * Generate backup codes for 2FA
 * 
 * @param int $count Number of codes to generate (default: 10)
 * @return array Array of backup codes
 */
function generateBackupCodes(int $count = 10): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-character alphanumeric codes
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 35)];
        }
        $codes[] = $code;
    }
    return $codes;
}

/**
 * Hash backup code for storage
 * 
 * @param string $code Plain backup code
 * @return string Hashed code
 */
function hashBackupCode(string $code): string {
    // Salted, slow hash: the codes have ~41 bits of entropy, which an
    // unsalted SHA-256 wouldn't protect against offline cracking
    return password_hash(normaliseBackupCode($code), PASSWORD_DEFAULT);
}

function normaliseBackupCode(string $code): string {
    return strtoupper(preg_replace('/[\s-]+/', '', $code));
}

/**
 * Index of the stored hash the code matches, or null. Accepts both
 * password_hash() hashes and the legacy unsalted SHA-256 ones.
 */
function findBackupCode(string $code, array $hashedCodes): ?int {
    $code = normaliseBackupCode($code);
    if ($code === '') {
        return null;
    }
    foreach (array_values($hashedCodes) as $i => $hash) {
        if (!is_string($hash)) {
            continue;
        }
        $match = strpos($hash, '$') === 0
            ? password_verify($code, $hash)
            : hash_equals($hash, hash('sha256', $code));
        if ($match) {
            return $i;
        }
    }
    return null;
}

/**
 * Verify backup code
 * 
 * @param string $code Plain backup code
 * @param array $hashedCodes Array of hashed backup codes
 * @return bool True if code is valid
 */
function verifyBackupCode(string $code, array $hashedCodes): bool {
    return findBackupCode($code, $hashedCodes) !== null;
}

/**
 * Enable 2FA for a user
 * 
 * @param int $userId User ID
 * @param string $secret Base32-encoded secret
 * @param array $backupCodes Array of backup codes
 * @return bool Success
 */
function enableTwoFactor(int $userId, string $secret, array $backupCodes): bool {
    try {
        $db = getDBConnection();
        
        // Check if columns exist first
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($stmt->rowCount() === 0) {
            throw new Exception("2FA database columns not found. Please run the migration: database/migration_add_2fa.sql");
        }
        
        // Hash backup codes for storage
        $hashedCodes = array_map('hashBackupCode', $backupCodes);
        $backupCodesJson = json_encode($hashedCodes);
        
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_secret = ?, 
                two_factor_enabled = TRUE,
                two_factor_backup_codes = ?,
                two_factor_verified_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([encryptTwoFactorSecret($secret), $backupCodesJson, $userId]);
    } catch (PDOException $e) {
        error_log("Error enabling 2FA: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error enabling 2FA: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Disable 2FA for a user
 * 
 * @param int $userId User ID
 * @return bool Success
 */
function disableTwoFactor(int $userId): bool {
    try {
        $db = getDBConnection();
        
        // Check if columns exist first
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        $stmt = $db->prepare("
            UPDATE users 
            SET two_factor_secret = NULL, 
                two_factor_enabled = FALSE,
                two_factor_backup_codes = NULL,
                two_factor_verified_at = NULL
            WHERE id = ?
        ");
        
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error disabling 2FA: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has 2FA enabled
 * 
 * @param int $userId User ID
 * @return bool
 */
function isTwoFactorEnabled(int $userId): bool {
    try {
        $db = getDBConnection();
        
        // Check if column exists first
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($stmt->rowCount() === 0) {
            // Column doesn't exist - migration not run
            return false;
        }
        
        $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result && isset($result['two_factor_enabled']) && $result['two_factor_enabled'] == 1;
    } catch (PDOException $e) {
        // If there's an error (like column doesn't exist), return false
        error_log("Error checking 2FA status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's 2FA secret
 * 
 * @param int $userId User ID
 * @return string|null Secret or null if not set
 */
function getUserTwoFactorSecret(int $userId): ?string {
    try {
        $db = getDBConnection();
        
        // Check if column exists first
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_secret'");
        if ($stmt->rowCount() === 0) {
            return null;
        }
        
        $stmt = $db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result || empty($result['two_factor_secret'])) {
            return null;
        }
        return decryptTwoFactorSecret($result['two_factor_secret']);
    } catch (PDOException $e) {
        error_log("Error getting 2FA secret: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user's backup codes (hashed)
 * 
 * @param int $userId User ID
 * @return array Array of hashed backup codes
 */
function getUserBackupCodes(int $userId): array {
    try {
        $db = getDBConnection();
        
        // Check if column exists first
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_backup_codes'");
        if ($stmt->rowCount() === 0) {
            return [];
        }
        
        $stmt = $db->prepare("SELECT two_factor_backup_codes FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result || !isset($result['two_factor_backup_codes']) || !$result['two_factor_backup_codes']) {
            return [];
        }
        
        $codes = json_decode($result['two_factor_backup_codes'], true);
        return is_array($codes) ? $codes : [];
    } catch (PDOException $e) {
        error_log("Error getting backup codes: " . $e->getMessage());
        return [];
    }
}

/**
 * Remove a used backup code
 * 
 * @param int $userId User ID
 * @param int $index Index of the used code (from findBackupCode())
 * @return bool Success
 */
function removeBackupCode(int $userId, int $index): bool {
    $db = getDBConnection();
    
    $hashedCodes = array_values(getUserBackupCodes($userId));
    unset($hashedCodes[$index]);
    
    $stmt = $db->prepare("UPDATE users SET two_factor_backup_codes = ? WHERE id = ?");
    return $stmt->execute([json_encode(array_values($hashedCodes)), $userId]);
}

/**
 * Verify 2FA code for a user (checks both TOTP and backup codes)
 * 
 * @param int $userId User ID
 * @param string $code Code to verify
 * @return array ['success' => bool, 'message' => string, 'used_backup' => bool]
 */
function verifyTwoFactorCode(int $userId, string $code): array {
    $secret = getUserTwoFactorSecret($userId);
    
    if (!$secret) {
        return ['success' => false, 'message' => '2FA is not enabled for this user', 'used_backup' => false];
    }

    // Brute-force guard: a 6-digit code (3 valid at any moment) falls to a
    // few hundred thousand guesses, so cap failures per user. This is kept
    // in the database, so starting a new login doesn't reset it.
    $throttleKey = 'user:' . $userId;
    if (function_exists('countAuthAttempts')
        && countAuthAttempts('2fa', $throttleKey, TWOFACTOR_FAILURE_WINDOW) >= TWOFACTOR_MAX_FAILURES_PER_USER) {
        return ['success' => false, 'message' => 'Too many incorrect codes. Please wait 15 minutes and try again.', 'used_backup' => false, 'locked' => true];
    }
    $fail = function () use ($throttleKey) {
        if (function_exists('recordAuthAttempt')) {
            recordAuthAttempt('2fa', $throttleKey);
        }
        return ['success' => false, 'message' => 'Invalid code', 'used_backup' => false];
    };
    $succeed = function (bool $usedBackup) use ($throttleKey, $userId, $secret) {
        if (function_exists('clearAuthAttempts')) {
            clearAuthAttempts('2fa', $throttleKey);
        }
        // Re-save a secret stored before encryption was configured
        if (twoFactorEncryptionKey() !== null) {
            try {
                $stmt = getDBConnection()->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ? AND two_factor_secret NOT LIKE 'enc1:%'");
                $stmt->execute([encryptTwoFactorSecret($secret), $userId]);
            } catch (PDOException $e) {
                error_log('Re-encrypting 2FA secret failed: ' . $e->getMessage());
            }
        }
        return ['success' => true, 'message' => $usedBackup ? 'Backup code verified' : 'Code verified', 'used_backup' => $usedBackup];
    };

    $code = trim($code);

    // 6 digits: an authenticator code
    if (strlen($code) === TOTP_DIGITS && ctype_digit($code)) {
        $step = matchTOTPStep($secret, $code);
        if ($step === null) {
            return $fail();
        }
        // Replay guard: each time step is accepted once. The conditional
        // UPDATE is atomic, so two simultaneous uses can't both win.
        try {
            $stmt = getDBConnection()->prepare("
                UPDATE users SET two_factor_last_step = ?
                WHERE id = ? AND (two_factor_last_step IS NULL OR two_factor_last_step < ?)
            ");
            $stmt->execute([$step, $userId, $step]);
            if ($stmt->rowCount() === 0) {
                return $fail();
            }
        } catch (PDOException $e) {
            error_log('2FA replay check unavailable (run database/migration_security_hardening.sql?): ' . $e->getMessage());
        }
        return $succeed(false);
    }

    // Anything else: try the backup codes
    $index = findBackupCode($code, getUserBackupCodes($userId));
    if ($index !== null) {
        removeBackupCode($userId, $index);
        return $succeed(true);
    }
    
    return $fail();
}
