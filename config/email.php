<?php
// Lightweight email helper. For production, replace with PHPMailer or an API.

function sendMail($to, $subject, $body, $isHtml = true) {
    $from = EMAIL_FROM_ADDRESS;
    $fromName = EMAIL_FROM_NAME;

    $headers = [];
    if ($isHtml) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
    } else {
        $headers[] = 'Content-type: text/plain; charset=iso-8859-1';
    }
    $headers[] = 'From: ' . $fromName . ' <' . $from . '>';

    return mail($to, $subject, $body, implode("\r\n", $headers));
}
