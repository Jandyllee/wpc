<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
    'path' => '/'
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['error' => 'POST required'], 405);
    }
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond(['error' => 'Your session expired. Refresh the page and try again.'], 403);
    }
}

try {
    $action = $_GET['action'] ?? '';
    if ($action === 'csrf') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            respond(['error' => 'GET required'], 405);
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        respond(['csrf_token' => $_SESSION['csrf_token']]);
    }

    require __DIR__ . '/config/db.php';

    switch ($action) {
        case 'me':
            respond([
                'user' => $_SESSION['user'] ?? null,
                'admin' => !empty($_SESSION['admin'])
            ]);

        case 'contact':
            requirePost();
            $contactFields = ['name', 'email', 'subject', 'message', 'website'];
            foreach ($contactFields as $field) {
                if (isset($_POST[$field]) && !is_string($_POST[$field])) {
                    respond(['error' => 'Please check the form fields and try again.'], 400);
                }
            }
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $honeypot = trim($_POST['website'] ?? '');

            if ($honeypot !== '') {
                respond(['error' => 'Unable to send your message. Please try again.'], 400);
            }
            if (strlen($name) < 2 || strlen($name) > 100) {
                respond(['error' => 'Name must be between 2 and 100 characters.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                respond(['error' => 'Enter a valid email address.'], 400);
            }
            if ($subject === '' || strlen($subject) > 150 || preg_match('/[\r\n]/', $subject)) {
                respond(['error' => 'Subject must be between 1 and 150 characters.'], 400);
            }
            if (strlen($message) < 10 || strlen($message) > 10000) {
                respond(['error' => 'Message must be between 10 and 10,000 characters.'], 400);
            }

            $lastContactAt = (int)($_SESSION['last_contact_at'] ?? 0);
            if ($lastContactAt > time() - 30) {
                respond(['error' => 'Please wait 30 seconds before sending another message.'], 429);
            }

            $smtpHost = appConfigValue('WPC_SMTP_HOST');
            $smtpUsername = appConfigValue('WPC_SMTP_USERNAME');
            $smtpPassword = appConfigValue('WPC_SMTP_PASSWORD');
            $smtpFrom = appConfigValue('WPC_SMTP_FROM_EMAIL');
            $smtpPort = (int)(appConfigValue('WPC_SMTP_PORT', '587'));
            $smtpEncryption = strtolower(appConfigValue('WPC_SMTP_ENCRYPTION', 'tls'));
            $smtpFromName = appConfigValue('WPC_SMTP_FROM_NAME', 'WESTPRIME CONFESSIONS');
            if (!$smtpHost || !$smtpUsername || !$smtpPassword
                || !filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)
                || !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)
                || $smtpPort < 1 || $smtpPort > 65535
                || !in_array($smtpEncryption, ['tls', 'ssl'], true)) {
                respond(['error' => 'The contact form is not configured on the server. Please try again later.'], 503);
            }

            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/Exception.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/SMTP.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/PHPMailer.php';

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = $smtpHost;
                $mailer->SMTPAuth = true;
                $mailer->Username = $smtpUsername;
                $mailer->Password = $smtpPassword;
                $mailer->SMTPSecure = $smtpEncryption === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = $smtpPort;
                $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
                $mailer->setFrom($smtpFrom, $smtpFromName);
                $mailer->addAddress($smtpUsername);
                $mailer->addReplyTo($email, $name);
                $mailer->Subject = '[WESTPRIME CONFESSIONS] ' . $subject;

                $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $mailer->isHTML(true);
                $mailer->Body = "<h2>New website contact message</h2>"
                    . "<p><strong>Name:</strong> {$safeName}</p>"
                    . "<p><strong>Email:</strong> {$safeEmail}</p>"
                    . "<p><strong>Subject:</strong> {$safeSubject}</p>"
                    . "<p><strong>Message:</strong><br>{$safeMessage}</p>";
                $mailer->AltBody = "New website contact message\n\n"
                    . "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\n{$message}";
                $mailer->send();
            } catch (\PHPMailer\PHPMailer\Exception $error) {
                error_log('Contact email delivery failed: ' . $error->getMessage());
                respond(['error' => 'Your message could not be sent right now. Please try again later.'], 502);
            }

            $_SESSION['last_contact_at'] = time();
            respond(['ok' => true]);

        case 'register':
            requirePost();
            $usernameInput = $_POST['username'] ?? '';
            $emailInput = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            if (!is_string($usernameInput) || !is_string($emailInput) || !is_string($password)) {
                respond(['error' => 'Check your username, email, and password and try again.'], 400);
            }
            $username = trim($usernameInput);
            $email = strtolower(trim($emailInput));

            if (strlen($username) < 3 || strlen($username) > 30) {
                respond(['error' => 'Username must be 3 to 30 characters.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                respond(['error' => 'Enter a valid email address.'], 400);
            }
            if (strlen($password) < 12 || strlen($password) > 72) {
                respond(['error' => 'Password must be between 12 and 72 characters.'], 400);
            }

            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                respond([
                    'error' => 'This email is already registered. Log in to your existing account or use Forgot your password.',
                    'code' => 'email_already_registered'
                ], 409);
            }

            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                respond(['error' => 'That username is already in use. Please choose another.'], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $username, $email, $hash);
            try {
                $stmt->execute();
            } catch (mysqli_sql_exception $error) {
                if ($error->getCode() !== 1062) {
                    throw $error;
                }

                $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    respond([
                        'error' => 'This email is already registered. Log in to your existing account or use Forgot your password.',
                        'code' => 'email_already_registered'
                    ], 409);
                }
                respond(['error' => 'That username is already in use. Please choose another.'], 409);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = $username;
            unset($_SESSION['admin']);
            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
            respond(['ok' => true, 'user' => $username]);

        case 'login':
            requirePost();
            $usernameInput = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if (!is_string($usernameInput) || !is_string($password)
                || strlen($usernameInput) > 30 || strlen($password) > 1024) {
                respond(['error' => 'Incorrect username or password.'], 401);
            }
            $username = trim($usernameInput);

            $stmt = $conn->prepare("SELECT username, password_hash FROM users WHERE username = ? AND role = 'user'");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                respond(['error' => 'Incorrect username or password.'], 401);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = $user['username'];
            unset($_SESSION['admin']);
            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
            respond(['ok' => true, 'user' => $user['username']]);

        case 'forgot_password':
            requirePost();
            $emailInput = $_POST['email'] ?? '';
            if (!is_string($emailInput) || strlen($emailInput) > 254) {
                respond(['error' => 'Enter a valid email address.'], 400);
            }
            $email = strtolower(trim($emailInput));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                respond(['error' => 'Enter a valid email address.'], 400);
            }

            $stmt = $conn->prepare(
                "SELECT id, username, email FROM users
                 WHERE role = 'user' AND email = ? LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                respond(['error' => 'That email address is not registered.'], 404);
            }
            if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                respond(['error' => 'This account has no valid recovery email. Contact the administrator.'], 400);
            }

            $smtpHost = appConfigValue('WPC_SMTP_HOST');
            $smtpUsername = appConfigValue('WPC_SMTP_USERNAME');
            $smtpPassword = appConfigValue('WPC_SMTP_PASSWORD');
            $smtpFrom = appConfigValue('WPC_SMTP_FROM_EMAIL');
            $smtpPort = (int)(appConfigValue('WPC_SMTP_PORT', '587'));
            $smtpEncryption = strtolower(appConfigValue('WPC_SMTP_ENCRYPTION', 'tls'));
            $smtpFromName = appConfigValue('WPC_SMTP_FROM_NAME', 'WESTPRIME CONFESSIONS');
            if (!$smtpHost || !$smtpUsername || !$smtpPassword
                || !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)
                || $smtpPort < 1 || $smtpPort > 65535
                || !in_array($smtpEncryption, ['tls', 'ssl'], true)) {
                respond(['error' => 'Password recovery email is not configured on the server.'], 503);
            }

            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/Exception.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/SMTP.php';
            require_once dirname(__DIR__) . '/vendor/PHPMailer/src/PHPMailer.php';
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $userId = (int)$user['id'];

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    'SELECT COUNT(*) AS request_count FROM password_resets
                     WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
                );
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $requestCount = (int)$stmt->get_result()->fetch_assoc()['request_count'];
                if ($requestCount > 0) {
                    $conn->rollback();
                    respond(['error' => 'Please wait one minute before requesting another code.'], 429);
                }

                $stmt = $conn->prepare(
                    'UPDATE password_resets SET used_at = NOW()
                     WHERE user_id = ? AND used_at IS NULL'
                );
                $stmt->bind_param('i', $userId);
                $stmt->execute();

                $stmt = $conn->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
                );
                $stmt->bind_param('is', $userId, $codeHash);
                $stmt->execute();
                $challengeId = $conn->insert_id;
                $conn->commit();
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = $smtpHost;
                $mailer->SMTPAuth = true;
                $mailer->Username = $smtpUsername;
                $mailer->Password = $smtpPassword;
                $mailer->SMTPSecure = $smtpEncryption === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = $smtpPort;
                $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
                $mailer->setFrom($smtpFrom, $smtpFromName);
                $mailer->addAddress($user['email'], $user['username']);
                $mailer->isHTML(true);
                $mailer->Subject = 'Your WESTPRIME CONFESSIONS verification code';
                $safeUsername = htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $mailer->Body = "<p>Hello {$safeUsername},</p>"
                    . "<p>Your password reset verification code is <strong>{$code}</strong>.</p>"
                    . '<p>This code expires in 10 minutes. You have up to five attempts.</p>'
                    . '<p>If you did not request this, you can ignore this email.</p>';
                $mailer->AltBody = "Hello {$user['username']},\n\n"
                    . "Your password reset verification code is {$code}.\n"
                    . "This code expires in 10 minutes. You have up to five attempts.\n\n"
                    . 'If you did not request this, you can ignore this email.';
                $mailer->send();
            } catch (Throwable $mailError) {
                error_log('Password reset email failed: ' . $mailer->ErrorInfo);
                $stmt = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $challengeId);
                $stmt->execute();
                respond(['error' => 'Could not send the verification code. Please try again later.'], 503);
            }

            respond(['ok' => true, 'challenge_id' => $challengeId]);

        case 'verify_reset_code':
            requirePost();
            $challengeId = filter_input(INPUT_POST, 'challenge_id', FILTER_VALIDATE_INT);
            $codeInput = $_POST['code'] ?? '';
            if (!is_string($codeInput)) {
                respond(['error' => 'Enter the six-digit code from your email.'], 400);
            }
            $code = trim($codeInput);
            if (!$challengeId || $challengeId < 1 || !preg_match('/\A\d{6}\z/', $code)) {
                respond(['error' => 'Enter the six-digit code from your email.'], 400);
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    "SELECT pr.user_id, pr.token_hash, pr.attempts
                     FROM password_resets pr
                     JOIN users u ON u.id = pr.user_id
                     WHERE pr.id = ? AND pr.used_at IS NULL
                       AND pr.expires_at > NOW() AND u.role = 'user'
                     LIMIT 1 FOR UPDATE"
                );
                $stmt->bind_param('i', $challengeId);
                $stmt->execute();
                $challenge = $stmt->get_result()->fetch_assoc();

                if (!$challenge || (int)$challenge['attempts'] >= 5) {
                    $conn->rollback();
                    respond(['error' => 'That code is invalid or expired. Request a new code and try again.'], 400);
                }

                if (!password_verify($code, $challenge['token_hash'])) {
                    $stmt = $conn->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?');
                    $stmt->bind_param('i', $challengeId);
                    $stmt->execute();
                    $conn->commit();
                    respond(['error' => 'That code is incorrect. Please check the email and try again.'], 400);
                }

                $stmt = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $stmt->bind_param('i', $challengeId);
                $stmt->execute();
                $conn->commit();

                session_regenerate_id(true);
                $_SESSION['password_reset_user_id'] = (int)$challenge['user_id'];
                $_SESSION['password_reset_expires_at'] = time() + 600;
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
            respond(['ok' => true]);

        case 'reset_password':
            requirePost();
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $userId = (int)($_SESSION['password_reset_user_id'] ?? 0);
            $grantExpiresAt = (int)($_SESSION['password_reset_expires_at'] ?? 0);
            if (!$userId || $grantExpiresAt < time()) {
                unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
                respond(['error' => 'Verify a current email code before resetting your password.'], 401);
            }
            if (!is_string($password) || !is_string($confirmPassword)
                || strlen($password) < 12 || strlen($password) > 72
                || strlen($confirmPassword) > 72) {
                respond(['error' => 'Password must be between 12 and 72 characters.'], 400);
            }
            if ($password !== $confirmPassword) {
                respond(['error' => 'Passwords do not match.'], 400);
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'user'");
            $stmt->bind_param('si', $passwordHash, $userId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
                respond(['error' => 'This account cannot reset its password.'], 400);
            }
            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
            respond(['ok' => true]);

        case 'logout':
            requirePost();
            $_SESSION = [];
            session_regenerate_id(true);
            respond(['ok' => true]);

        case 'feed':
            if (empty($_SESSION['user']) && empty($_SESSION['admin'])) {
                respond(['error' => 'Please log in first.'], 401);
            }

            $where = '';
            if (!empty($_SESSION['admin'])) {
                $period = $_GET['period'] ?? 'all';
                if (!is_string($period)) {
                    respond(['error' => 'Choose a supported date range.'], 400);
                }
                $periodConditions = [
                    'week' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                    'month' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                    '3months' => 'created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)',
                    '6months' => 'created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)'
                ];

                if (isset($periodConditions[$period])) {
                    $where = ' WHERE ' . $periodConditions[$period];
                } elseif ($period === 'custom') {
                    $from = $_GET['from'] ?? '';
                    $to = $_GET['to'] ?? '';
                    if (!is_string($from) || !is_string($to)) {
                        respond(['error' => 'Choose a valid date range.'], 400);
                    }
                    $fromDate = DateTime::createFromFormat('!Y-m-d', $from);
                    $toDate = DateTime::createFromFormat('!Y-m-d', $to);
                    if (!$fromDate || $fromDate->format('Y-m-d') !== $from
                        || !$toDate || $toDate->format('Y-m-d') !== $to
                        || $to < $from) {
                        respond(['error' => 'Choose a valid date range.'], 400);
                    }

                    $stmt = $conn->prepare(
                        'SELECT id, author, is_anonymous, tag, content, UNIX_TIMESTAMP(created_at) AS ts
                         FROM confessions
                         WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
                         ORDER BY id DESC'
                    );
                    $stmt->bind_param('ss', $from, $to);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } elseif ($period !== 'all') {
                    respond(['error' => 'Choose a supported date range.'], 400);
                }
            }

            if (!isset($result)) {
                $result = $conn->query(
                    'SELECT id, author, is_anonymous, tag, content, UNIX_TIMESTAMP(created_at) AS ts
                     FROM confessions' . $where . ' ORDER BY id DESC'
                );
            }
            $isAdmin = !empty($_SESSION['admin']);
            $confessions = [];
            while ($row = $result->fetch_assoc()) {
                $isAnonymous = (bool)$row['is_anonymous'];
                $confessions[] = [
                    'id' => (int)$row['id'],
                    'author' => $isAnonymous && !$isAdmin ? 'Anonymous' : $row['author'],
                    'is_anonymous' => $isAnonymous,
                    'tag' => $row['tag'],
                    'content' => $row['content'],
                    'ts' => (int)$row['ts']
                ];
            }
            respond(['confessions' => $confessions]);

        case 'post':
            requirePost();
            if (empty($_SESSION['user'])) {
                respond(['error' => 'Please log in first.'], 401);
            }

            $tagInput = $_POST['tag'] ?? '';
            $contentInput = $_POST['content'] ?? '';
            if (!is_string($tagInput) || !is_string($contentInput)) {
                respond(['error' => 'Tag and confession must be valid text.'], 400);
            }
            $tag = trim($tagInput);
            $characterCount = mb_strlen($contentInput, 'UTF-8');
            if ($characterCount === false) {
                respond(['error' => 'The confession contains invalid text.'], 400);
            }
            if ($characterCount > 1000) {
                respond(['error' => 'Confessions can contain up to 1,000 characters.'], 400);
            }
            $content = trim($contentInput);
            $anonymous = $_POST['anonymous'] ?? '0';
            if (!is_string($anonymous) || !in_array($anonymous, ['0', '1'], true)) {
                respond(['error' => 'Choose whether to post anonymously.'], 400);
            }
            if ($tag === '' || $content === '') {
                respond(['error' => 'Tag and confession cannot be empty.'], 400);
            }
            if (strlen($tag) > 50) {
                respond(['error' => 'Tag is too long (max 50 characters).'], 400);
            }

            $author = $_SESSION['user'];
            $isAnonymous = (int)$anonymous;
            $stmt = $conn->prepare('INSERT INTO confessions (author, is_anonymous, tag, content) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('siss', $author, $isAnonymous, $tag, $content);
            $stmt->execute();
            respond(['ok' => true]);

        case 'admin_login':
            requirePost();
            $adminPassword = $_POST['password'] ?? '';
            if (!is_string($adminPassword) || strlen($adminPassword) > 1024) {
                respond(['error' => 'Incorrect admin password.'], 401);
            }
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = 'admin' AND role = 'admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();

            if (!$admin || !password_verify($adminPassword, $admin['password_hash'])) {
                respond(['error' => 'Incorrect admin password!'], 401);
            }

            session_regenerate_id(true);
            unset($_SESSION['user']);
            unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_expires_at']);
            $_SESSION['admin'] = true;
            respond(['ok' => true]);

        case 'admin_delete':
            requirePost();
            if (empty($_SESSION['admin'])) {
                respond(['error' => 'Admin only.'], 403);
            }

            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                respond(['error' => 'Invalid confession ID.'], 400);
            }
            $stmt = $conn->prepare('DELETE FROM confessions WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            respond(['ok' => true]);

        default:
            respond(['error' => 'Unknown action.'], 400);
    }
} catch (Throwable $error) {
    error_log('Unhandled API error: ' . get_class($error));
    respond(['error' => 'A server error occurred. Please try again later.'], 500);
}
