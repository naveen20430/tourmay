<?php
/**
 * SMTP connection / send test page (Hostinger defaults).
 * Admin login required.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireLogin();

$defaults = [
    'host' => 'smtp.hostinger.com',
    'port' => '465',
    'encryption' => 'ssl',
    'username' => 'noreply@theworldjourney.in',
    'password' => '',
    'from_email' => 'noreply@theworldjourney.in',
    'from_name' => getSetting('site_name') ?: 'The World Journey',
    'to_email' => getSetting('contact_email') ?: (getSetting('site_email') ?: ''),
    'subject' => 'SMTP test from The World Journey',
];

$form = $defaults;
$steps = [];
$overallOk = null;
$error = '';

/**
 * Read one SMTP response (may be multi-line).
 */
function smtpRead($fp, &$log) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        $log[] = '← ' . rtrim($line);
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

/**
 * Write a SMTP command and log it (mask AUTH secrets).
 */
function smtpWrite($fp, $cmd, &$log, $mask = false) {
    $log[] = '→ ' . ($mask ? '[credentials hidden]' : rtrim($cmd));
    fwrite($fp, $cmd . "\r\n");
}

/**
 * Run SMTP connection + AUTH (+ optional mail send).
 *
 * @return array{ok:bool, steps:array<int,array{label:string,ok:bool,detail:string}>, log:array<int,string>, error:?string}
 */
function runSmtpTest(array $cfg, $sendMail = false) {
    $steps = [];
    $log = [];
    $host = trim($cfg['host']);
    $port = (int) $cfg['port'];
    $enc = strtolower(trim($cfg['encryption']));
    $user = trim($cfg['username']);
    $pass = (string) $cfg['password'];
    $from = trim($cfg['from_email']);
    $fromName = trim($cfg['from_name']);
    $to = trim($cfg['to_email']);
    $subject = trim($cfg['subject']);

    if ($host === '' || $port < 1) {
        return ['ok' => false, 'steps' => [], 'log' => [], 'error' => 'Host and port are required.'];
    }
    if ($user === '' || $pass === '') {
        return ['ok' => false, 'steps' => [], 'log' => [], 'error' => 'Username and password are required for AUTH.'];
    }

    $timeout = 20;
    $remote = ($enc === 'ssl') ? ('ssl://' . $host . ':' . $port) : ('tcp://' . $host . ':' . $port);
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);

    $fp = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $ctx
    );

    if (!$fp) {
        $steps[] = [
            'label' => 'TCP / SSL connect',
            'ok' => false,
            'detail' => "Could not connect to {$remote}: [{$errno}] {$errstr}",
        ];
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => $errstr ?: 'Connection failed'];
    }
    stream_set_timeout($fp, $timeout);
    $steps[] = [
        'label' => 'TCP / SSL connect',
        'ok' => true,
        'detail' => "Connected to {$remote}",
    ];

    $banner = smtpRead($fp, $log);
    $code = (int) substr($banner, 0, 3);
    $steps[] = [
        'label' => 'SMTP greeting',
        'ok' => $code === 220,
        'detail' => trim($banner) ?: 'Empty banner',
    ];
    if ($code !== 220) {
        fclose($fp);
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Bad SMTP greeting'];
    }

    $ehloHost = 'theworldjourney.in';
    smtpWrite($fp, 'EHLO ' . $ehloHost, $log);
    $ehlo = smtpRead($fp, $log);
    $code = (int) substr($ehlo, 0, 3);
    $steps[] = [
        'label' => 'EHLO',
        'ok' => $code === 250,
        'detail' => trim(str_replace(["\r", "\n"], ' | ', $ehlo)),
    ];
    if ($code !== 250) {
        fclose($fp);
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'EHLO failed'];
    }

    if ($enc === 'tls') {
        smtpWrite($fp, 'STARTTLS', $log);
        $tlsResp = smtpRead($fp, $log);
        $code = (int) substr($tlsResp, 0, 3);
        $steps[] = [
            'label' => 'STARTTLS',
            'ok' => $code === 220,
            'detail' => trim($tlsResp),
        ];
        if ($code !== 220) {
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'STARTTLS rejected'];
        }
        $crypto = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $steps[] = [
            'label' => 'TLS handshake',
            'ok' => (bool) $crypto,
            'detail' => $crypto ? 'TLS enabled' : 'stream_socket_enable_crypto failed',
        ];
        if (!$crypto) {
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'TLS handshake failed'];
        }
        smtpWrite($fp, 'EHLO ' . $ehloHost, $log);
        $ehlo2 = smtpRead($fp, $log);
        $code = (int) substr($ehlo2, 0, 3);
        $steps[] = [
            'label' => 'EHLO (after TLS)',
            'ok' => $code === 250,
            'detail' => trim(str_replace(["\r", "\n"], ' | ', $ehlo2)),
        ];
        if ($code !== 250) {
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'EHLO after TLS failed'];
        }
    } else {
        $steps[] = [
            'label' => 'Encryption',
            'ok' => true,
            'detail' => 'Using implicit SSL on connect (port ' . $port . ')',
        ];
    }

    smtpWrite($fp, 'AUTH LOGIN', $log);
    $auth1 = smtpRead($fp, $log);
    $code = (int) substr($auth1, 0, 3);
    if ($code !== 334) {
        $steps[] = ['label' => 'AUTH LOGIN', 'ok' => false, 'detail' => trim($auth1)];
        fclose($fp);
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'AUTH LOGIN not accepted'];
    }

    smtpWrite($fp, base64_encode($user), $log, true);
    $authUser = smtpRead($fp, $log);
    $code = (int) substr($authUser, 0, 3);
    if ($code !== 334) {
        $steps[] = ['label' => 'AUTH username', 'ok' => false, 'detail' => trim($authUser)];
        fclose($fp);
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Username rejected'];
    }

    smtpWrite($fp, base64_encode($pass), $log, true);
    $authPass = smtpRead($fp, $log);
    $code = (int) substr($authPass, 0, 3);
    $authOk = ($code === 235);
    $steps[] = [
        'label' => 'AUTH LOGIN',
        'ok' => $authOk,
        'detail' => $authOk ? 'Authenticated as ' . $user : trim($authPass),
    ];
    if (!$authOk) {
        fclose($fp);
        return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Authentication failed — check mailbox password'];
    }

    if ($sendMail) {
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $steps[] = ['label' => 'Send test email', 'ok' => false, 'detail' => 'Valid To email is required'];
            smtpWrite($fp, 'QUIT', $log);
            smtpRead($fp, $log);
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Invalid recipient email'];
        }
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $steps[] = ['label' => 'Send test email', 'ok' => false, 'detail' => 'Valid From email is required'];
            smtpWrite($fp, 'QUIT', $log);
            smtpRead($fp, $log);
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Invalid from email'];
        }

        smtpWrite($fp, 'MAIL FROM:<' . $from . '>', $log);
        $mailFrom = smtpRead($fp, $log);
        $code = (int) substr($mailFrom, 0, 3);
        if ($code !== 250) {
            $steps[] = ['label' => 'MAIL FROM', 'ok' => false, 'detail' => trim($mailFrom)];
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'MAIL FROM rejected'];
        }

        smtpWrite($fp, 'RCPT TO:<' . $to . '>', $log);
        $rcpt = smtpRead($fp, $log);
        $code = (int) substr($rcpt, 0, 3);
        if ($code !== 250 && $code !== 251) {
            $steps[] = ['label' => 'RCPT TO', 'ok' => false, 'detail' => trim($rcpt)];
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'RCPT TO rejected'];
        }

        smtpWrite($fp, 'DATA', $log);
        $dataResp = smtpRead($fp, $log);
        $code = (int) substr($dataResp, 0, 3);
        if ($code !== 354) {
            $steps[] = ['label' => 'DATA', 'ok' => false, 'detail' => trim($dataResp)];
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'DATA rejected'];
        }

        $date = date('r');
        $msgId = sprintf('<%s@%s>', bin2hex(random_bytes(12)), 'theworldjourney.in');
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName !== '' ? $fromName : $from) . '?=';
        $bodyText = "This is a SMTP test message from theworldjourney.in admin panel.\r\n\r\n"
            . "Host: {$host}\r\nPort: {$port}\r\nEncryption: {$enc}\r\n"
            . "Time: {$date}\r\n";

        $headers = [
            'Date: ' . $date,
            'From: ' . $encodedName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . '=?UTF-8?B?' . base64_encode($subject !== '' ? $subject : 'SMTP test') . '?=',
            'Message-ID: ' . $msgId,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: TWJ-SMTP-Test',
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $bodyText . "\r\n.";
        $log[] = '→ [message body ' . strlen($payload) . ' bytes]';
        fwrite($fp, $payload . "\r\n");
        $sendResp = smtpRead($fp, $log);
        $code = (int) substr($sendResp, 0, 3);
        $sendOk = ($code === 250);
        $steps[] = [
            'label' => 'Send test email',
            'ok' => $sendOk,
            'detail' => $sendOk
                ? ('Accepted for delivery to ' . $to . ' — ' . trim($sendResp))
                : trim($sendResp),
        ];
        if (!$sendOk) {
            fclose($fp);
            return ['ok' => false, 'steps' => $steps, 'log' => $log, 'error' => 'Message not accepted'];
        }
    }

    smtpWrite($fp, 'QUIT', $log);
    smtpRead($fp, $log);
    fclose($fp);

    return ['ok' => true, 'steps' => $steps, 'log' => $log, 'error' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($defaults) as $key) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string) $_POST[$key]);
        }
    }
    // Keep typed password only in this request (never echo back into HTML value by default — we do for retry convenience in admin)
    $form['password'] = (string) ($_POST['password'] ?? '');
    $action = (string) ($_POST['action'] ?? 'connect');
    $sendMail = ($action === 'send');

    $result = runSmtpTest($form, $sendMail);
    $steps = $result['steps'];
    $overallOk = $result['ok'];
    $error = $result['error'] ?? '';
    $smtpLog = $result['log'];
} else {
    $smtpLog = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test — Hostinger</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f8; }
        .log-box {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            background: #111827;
            color: #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            max-height: 420px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .step-ok { color: #166534; }
        .step-fail { color: #991b1b; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 920px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">SMTP connection test</h1>
            <p class="text-muted mb-0">Hostinger defaults for <code>noreply@theworldjourney.in</code></p>
        </div>
        <a href="settings.php" class="btn btn-outline-secondary btn-sm">Back to settings</a>
    </div>

    <?php if ($overallOk === true): ?>
        <div class="alert alert-success">SMTP <?php echo isset($_POST['action']) && $_POST['action'] === 'send' ? 'auth + send' : 'connection + auth'; ?> succeeded.</div>
    <?php elseif ($overallOk === false): ?>
        <div class="alert alert-danger"><strong>Failed:</strong> <?php echo htmlspecialchars($error ?: 'See steps below'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP host</label>
                        <input type="text" name="host" class="form-control" value="<?php echo htmlspecialchars($form['host']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" class="form-control" value="<?php echo htmlspecialchars($form['port']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Encryption</label>
                        <select name="encryption" class="form-select">
                            <option value="ssl" <?php echo $form['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (implicit, port 465)</option>
                            <option value="tls" <?php echo $form['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS / STARTTLS (port 587)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($form['username']); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" value="<?php echo htmlspecialchars($form['password']); ?>" placeholder="Mailbox password for noreply@…" required>
                        <div class="form-text">Not stored — used only for this test request.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From email</label>
                        <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($form['from_email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From name</label>
                        <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($form['from_name']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">To email (for send test)</label>
                        <input type="email" name="to_email" class="form-control" value="<?php echo htmlspecialchars($form['to_email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($form['subject']); ?>">
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" name="action" value="connect" class="btn btn-primary">Test connection + AUTH</button>
                    <button type="submit" name="action" value="send" class="btn btn-success">Test connection + send email</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('[name=port]').value='465'; document.querySelector('[name=encryption]').value='ssl';">Use 465 / SSL</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="document.querySelector('[name=port]').value='587'; document.querySelector('[name=encryption]').value='tls';">Use 587 / TLS</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($steps)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Steps</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as $step): ?>
                    <li class="list-group-item">
                        <span class="<?php echo !empty($step['ok']) ? 'step-ok' : 'step-fail'; ?>">
                            <?php echo !empty($step['ok']) ? '✓' : '✗'; ?>
                            <?php echo htmlspecialchars($step['label']); ?>
                        </span>
                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($step['detail']); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($smtpLog)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><strong>SMTP transcript</strong></div>
            <div class="card-body">
                <div class="log-box"><?php echo htmlspecialchars(implode("\n", $smtpLog)); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body small text-muted">
            <p class="mb-1"><strong>Recommended:</strong> <code>smtp.hostinger.com</code> · port <code>465</code> · SSL · AUTH required</p>
            <p class="mb-0">If 465 fails, try port <code>587</code> with TLS/STARTTLS. Username must be the full mailbox address.</p>
        </div>
    </div>
</div>
</body>
</html>
