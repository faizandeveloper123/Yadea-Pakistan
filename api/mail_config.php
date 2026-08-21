<?php
/**
 * EVEE CRM - SMTP / mail configuration
 *
 * Fill in the values below (or set the matching environment variables).
 * When SMTP_HOST is empty the API falls back to PHP's mail() so nothing
 * breaks before credentials are added.
 *
 * Credentials you need from your email provider:
 *   1. SMTP HOST      e.g. smtp.gmail.com, smtp.hostinger.com, mail.yourdomain.com
 *   2. SMTP PORT      465 (SSL) or 587 (TLS/STARTTLS)
 *   3. ENCRYPTION     'ssl' for port 465, 'tls' for port 587
 *   4. USERNAME       the full email address used to send (e.g. no-reply@yourdomain.com)
 *   5. PASSWORD       the mailbox password (for Gmail: an App Password,
 *                     generated at https://myaccount.google.com/apppasswords)
 *   6. FROM NAME      display name shown in the inbox (e.g. "Yadea Pakistan")
 *
 * Anti-spam notes:
 *   - Always use a real mailbox on YOUR domain as the sender.
 *   - Make sure your domain's DNS has an SPF record that includes your SMTP
 *     provider, and enable DKIM in the provider panel when available.
 *
 * APP_URL is used for login links inside emails (no trailing slash).
 */

define('SMTP_HOST', getenv('SMTP_HOST') ?: '');            // e.g. 'smtp.gmail.com'
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));    // 465 or 587
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');     // 'ssl' | 'tls' | ''
define('SMTP_USER', getenv('SMTP_USER') ?: '');            // sender mailbox
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');            // mailbox password
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Yadea Pakistan');

/** Public site URL used in email links (no trailing slash). */
define('APP_URL', getenv('APP_URL') ?: 'http://169.58.191.84/Yadea');
