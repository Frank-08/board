<?php
/**
 * Email Helper Functions
 * 
 * Provides email sending functionality using Zoho SMTP.
 */

require_once __DIR__ . '/config.php';

// Fallback for quoted_printable_encode if not available (PHP < 5.3.0)
if (!function_exists('quoted_printable_encode')) {
    function quoted_printable_encode($string) {
        $result = '';
        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];
            $ascii = ord($char);
            // Printable ASCII characters (33-60, 62-126) and space/tab
            if (($ascii >= 33 && $ascii <= 60) || ($ascii >= 62 && $ascii <= 126) || $ascii == 32 || $ascii == 9) {
                $result .= $char;
            } else {
                // Encode as =XX
                $result .= '=' . strtoupper(sprintf('%02X', $ascii));
            }
        }
        // Soft line breaks (RFC 2045): lines should not exceed 76 characters
        $result = wordwrap($result, 76, "=\r\n", true);
        return $result;
    }
}

/**
 * Send password reset email via Zoho SMTP
 * 
 * @param string $email Recipient email address
 * @param string $token Password reset token
 * @return array ['success' => bool, 'message' => string]
 */
function sendPasswordResetEmail(string $email, string $token): array {
    // Validate configuration
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD) || empty(SMTP_FROM_EMAIL)) {
        return [
            'success' => false,
            'message' => 'SMTP configuration is incomplete. Please configure SMTP settings in config.php'
        ];
    }
    
    // Generate reset URL
    $resetUrl = BASE_URL . '/reset_password.php?token=' . urlencode($token);
    
    // Email subject
    $subject = 'Password Reset Request - ' . APP_NAME;
    
    // Email body (HTML)
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .button:hover { background: #5568d3; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars(APP_NAME) . '</h1>
            </div>
            <div class="content">
                <h2>Password Reset Request</h2>
                <p>You have requested to reset your password. Click the button below to reset your password:</p>
                <p style="text-align: center;">
                    <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style="word-break: break-all; color: #667eea;">' . htmlspecialchars($resetUrl) . '</p>
                <div class="warning">
                    <strong>Security Notice:</strong> This link will expire in 1 hour. If you did not request a password reset, please ignore this email.
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME) . '. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Plain text version
    $textBody = "Password Reset Request - " . APP_NAME . "\n\n";
    $textBody .= "You have requested to reset your password. Click the link below to reset your password:\n\n";
    $textBody .= $resetUrl . "\n\n";
    $textBody .= "This link will expire in 1 hour.\n\n";
    $textBody .= "If you did not request a password reset, please ignore this email.\n\n";
    $textBody .= "---\n";
    $textBody .= "This is an automated message. Please do not reply to this email.\n";
    $textBody .= "© " . date('Y') . " " . APP_NAME . ". All rights reserved.\n";
    
    // Send email using SMTP
    try {
        $result = sendSMTPEmail($email, $subject, $htmlBody, $textBody);
        return $result;
    } catch (Exception $e) {
        error_log("Failed to send password reset email: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send email. Please try again later.'
        ];
    }
}

/**
 * Send email via SMTP using Zoho
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @return array ['success' => bool, 'message' => string]
 */
function sendSMTPEmail(string $to, string $subject, string $htmlBody, string $textBody): array {
    // Use PHPMailer if available, otherwise use socket-based SMTP
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return sendEmailWithPHPMailer($to, $subject, $htmlBody, $textBody);
    } else {
        return sendEmailWithSocket($to, $subject, $htmlBody, $textBody);
    }
}

/**
 * Send email using PHPMailer library
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmailWithPHPMailer(string $to, string $subject, string $htmlBody, string $textBody): array {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        
        $mail->send();
        
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
    }
}

/**
 * Send email using socket-based SMTP (fallback if PHPMailer not available)
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmailWithSocket(string $to, string $subject, string $htmlBody, string $textBody): array {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $fromEmail = SMTP_FROM_EMAIL;
    $fromName = SMTP_FROM_NAME;
    $encryption = SMTP_ENCRYPTION;
    
    // Create socket connection
    $context = stream_context_create();
    $socket = @stream_socket_client(
        ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        return ['success' => false, 'message' => "Failed to connect to SMTP server: $errstr ($errno)"];
    }
    
    try {
        // Read initial response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP server error: ' . trim($response)];
        }
        
        // Send EHLO
        fputs($socket, "EHLO " . $host . "\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        
        // Start TLS if needed
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                return ['success' => false, 'message' => 'STARTTLS failed: ' . trim($response)];
            }
            
            // Enable crypto
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            fputs($socket, "EHLO " . $host . "\r\n");
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => 'AUTH LOGIN failed: ' . trim($response)];
        }
        
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => 'Username authentication failed: ' . trim($response)];
        }
        
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'message' => 'Password authentication failed: ' . trim($response)];
        }
        
        // Set sender
        fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => 'MAIL FROM failed: ' . trim($response)];
        }
        
        // Set recipient
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => 'RCPT TO failed: ' . trim($response)];
        }
        
        // Send data
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'message' => 'DATA command failed: ' . trim($response)];
        }
        
        // Build email headers and body
        $emailData = "From: $fromName <$fromEmail>\r\n";
        $emailData .= "To: <$to>\r\n";
        $emailData .= "Subject: $subject\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: multipart/alternative; boundary=\"boundary123\"\r\n";
        $emailData .= "\r\n";
        $emailData .= "--boundary123\r\n";
        $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $emailData .= "\r\n";
        $emailData .= quoted_printable_encode($textBody) . "\r\n";
        $emailData .= "--boundary123\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $emailData .= "\r\n";
        $emailData .= quoted_printable_encode($htmlBody) . "\r\n";
        $emailData .= "--boundary123--\r\n";
        $emailData .= ".\r\n";
        
        fputs($socket, $emailData);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => 'Email sending failed: ' . trim($response)];
        }
        
        // Quit
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }
        return ['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()];
    }
}

