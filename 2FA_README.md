# Two-Factor Authentication (2FA) Implementation

This document describes the 2FA implementation for the Together in Council application.

## Overview

Two-Factor Authentication (2FA) has been implemented using TOTP (Time-based One-Time Password) as specified in RFC 6238. This is compatible with popular authenticator apps including:
- Google Authenticator
- Authy
- Microsoft Authenticator
- 1Password
- And other TOTP-compatible apps

## Features

- **TOTP-based 2FA**: Uses industry-standard TOTP for secure code generation
- **Backup Codes**: Generates 10 one-time backup codes when 2FA is enabled
- **QR Code Setup**: Easy setup via QR code scanning
- **Manual Entry**: Option to manually enter the secret key
- **Auto-submit**: Verification page auto-submits when 6 digits are entered
- **Session Security**: Pending login sessions expire after 15 minutes

## Installation

### 1. Run Database Migration

Run the database migration to add 2FA fields to the users table:

```sql
mysql -u your_username -p governance_board < database/migration_add_2fa.sql
```

Or manually execute the SQL in `database/migration_add_2fa.sql`.

### 2. Files Added/Modified

**New Files:**
- `config/twofactor.php` - 2FA helper functions (TOTP generation, verification, etc.)
- `verify_2fa.php` - 2FA verification page during login
- `setup_2fa.php` - User interface for enabling/disabling 2FA
- `database/migration_add_2fa.sql` - Database migration script

**Modified Files:**
- `config/auth.php` - Updated login function to check for 2FA
- `login.php` - Updated to redirect to 2FA verification when needed
- `includes/header.php` - Added link to 2FA setup page

## Usage

### For Users

1. **Enable 2FA:**
   - Log in to your account
   - Click "2FA" in the header navigation
   - Click "Enable 2FA"
   - Scan the QR code with your authenticator app
   - Enter the 6-digit code to verify
   - Save your backup codes in a safe place

2. **Logging In with 2FA:**
   - Enter your username and password as usual
   - You'll be redirected to the 2FA verification page
   - Enter the 6-digit code from your authenticator app
   - Or use a backup code if you don't have access to your device

3. **Disable 2FA:**
   - Go to the 2FA setup page
   - Click "Disable 2FA"
   - Confirm the action

### For Administrators

- Users can enable/disable 2FA themselves
- No admin intervention required
- 2FA status is visible in the user management interface (if needed)

## Technical Details

### Database Schema

The following fields are added to the `users` table:
- `two_factor_secret` (VARCHAR(255)) - Base32-encoded TOTP secret
- `two_factor_enabled` (BOOLEAN) - Whether 2FA is enabled for the user
- `two_factor_backup_codes` (TEXT) - JSON array of hashed backup codes
- `two_factor_verified_at` (TIMESTAMP) - When 2FA was last verified during setup

### Security Features

1. **Secret Storage**: TOTP secrets are stored in the database (encrypted at rest if database encryption is enabled)
2. **Backup Code Hashing**: Backup codes are hashed using SHA-256 before storage
3. **Session Expiration**: Pending 2FA verification sessions expire after 15 minutes
4. **Clock Skew Tolerance**: TOTP verification allows ±1 time window (30 seconds) to account for clock differences
5. **CSRF Protection**: All forms include CSRF token verification
6. **One-Time Backup Codes**: Each backup code can only be used once

### TOTP Configuration

- **Algorithm**: SHA1 (standard for TOTP)
- **Digits**: 6
- **Period**: 30 seconds
- **Secret Length**: 160 bits (20 bytes, Base32 encoded)

## API Functions

### Core Functions (in `config/twofactor.php`)

- `generateTwoFactorSecret()` - Generate a new TOTP secret
- `generateTOTPCode($secret, $time)` - Generate a TOTP code for a secret
- `verifyTOTPCode($secret, $code, $window)` - Verify a TOTP code
- `generateTwoFactorQRUrl($secret, $email)` - Generate QR code URL
- `generateBackupCodes($count)` - Generate backup codes
- `enableTwoFactor($userId, $secret, $backupCodes)` - Enable 2FA for a user
- `disableTwoFactor($userId)` - Disable 2FA for a user
- `verifyTwoFactorCode($userId, $code)` - Verify 2FA code (checks both TOTP and backup codes)

### Auth Functions (in `config/auth.php`)

- `login($username, $password)` - Updated to return `requires_2fa` flag
- `completeTwoFactorLogin()` - Complete login after 2FA verification

## Troubleshooting

### User can't log in after enabling 2FA

1. Check that the authenticator app time is synchronized
2. Try using a backup code
3. If backup codes are lost, an admin can disable 2FA for the user

### QR code not displaying

- The setup page uses a CDN for QR code generation (qrcode.js)
- If the CDN is blocked, the manual secret entry option is available
- Alternatively, you can host qrcode.js locally

### Codes not working

- Ensure the device clock is synchronized (TOTP is time-sensitive)
- Check that the secret was entered correctly if using manual entry
- Try the previous or next time window (codes are valid for ±30 seconds)

## Future Enhancements

Potential improvements:
- Email-based 2FA as an alternative
- SMS-based 2FA (less secure, not recommended)
- Recovery email for 2FA reset
- Admin ability to view/regenerate backup codes
- 2FA status in user management interface
- Audit logging of 2FA events

## Security Notes

1. **Backup Codes**: Users should store backup codes securely (password manager, encrypted file, etc.)
2. **Secret Sharing**: Never share your TOTP secret or backup codes
3. **Device Security**: Keep your authenticator device secure and backed up
4. **Database Security**: Ensure database backups are encrypted
5. **HTTPS**: Always use HTTPS in production to protect login sessions

## Support

For issues or questions:
1. Check this documentation
2. Review the code comments in `config/twofactor.php`
3. Check application logs for errors

