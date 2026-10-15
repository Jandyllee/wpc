<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'db.php';

// Admin password now lives on the server, so visitors can't read it in the browser.
// Change it to something only you know.
const ADMIN_PASSWORD = 'Westprime1234';

function out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        out(['error' => 'POST required'], 405);
    }
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // Who is logged in right now?
        case 'me':
            out([
                'user'  => $_SESSION['user'] ?? null,
                'admin' => !empty($_SESSION['admin'])
            ]);

        case 'register':
            requirePost();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (strlen($username) < 3 || strlen($username) > 30) {
                out(['error' => 'Username must be 3 to 30 characters.'], 400);
            }
            if (strlen($password) < 4) {
                out(['error' => 'Password must be at least 4 characters.'], 400);
            }

            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                out(['error' => 'Username is already taken! Please choose another one.'], 409);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            $stmt->execute();

            session_regenerate_id(true);
            $_SESSION['user'] = $username;
            out(['ok' => true, 'user' => $username]);

        case 'login':
            requirePost();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $stmt = $conn->prepare("SELECT username, password_hash FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                out(['error' => 'Username does not exist. Please register first!'], 404);
            }
            if (!password_verify($password, $row['password_hash'])) {
                out(['error' => 'Incorrect password!'], 401);
            }

            session_regenerate_id(true);
            $_SESSION['user'] = $row['username'];
            out(['ok' => true, 'user' => $row['username']]);

        case 'logout':
            requirePost();
            unset($_SESSION['user']);
            out(['ok' => true]);

        // Everyone logged in can read the feed
        case 'feed':
            if (empty($_SESSION['user']) && empty($_SESSION['admin'])) {
                out(['error' => 'Please log in first.'], 401);
            }
            $result = $conn->query(
                "SELECT id, author, tag, content, UNIX_TIMESTAMP(created_at) AS ts
                 FROM confessions ORDER BY id DESC"
            );
            $list = [];
            while ($r = $result->fetch_assoc()) {
                $list[] = [
                    'id'      => (int)$r['id'],
                    'author'  => $r['author'],
                    'tag'     => $r['tag'],
                    'content' => $r['content'],
                    'ts'      => (int)$r['ts']
                ];
            }
            out(['confessions' => $list]);

        case 'post':
            requirePost();
            if (empty($_SESSION['user'])) {
                out(['error' => 'Please log in first.'], 401);
            }
            $tag     = trim($_POST['tag'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if ($tag === '' || $content === '') {
                out(['error' => 'Tag and confession cannot be empty.'], 400);
            }
            if (strlen($tag) > 50) {
                out(['error' => 'Tag is too long (max 50 characters).'], 400);
            }

            $author = $_SESSION['user'];
            $stmt = $conn->prepare("INSERT INTO confessions (author, tag, content) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $author, $tag, $content);
            $stmt->execute();
            out(['ok' => true]);

        case 'admin_login':
            requirePost();
            $pass = $_POST['password'] ?? '';
            if (!hash_equals(ADMIN_PASSWORD, $pass)) {
                out(['error' => 'Incorrect admin password!'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            out(['ok' => true]);

        case 'admin_delete':
            requirePost();
            if (empty($_SESSION['admin'])) {
                out(['error' => 'Admin only.'], 403);
            }
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM confessions WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            out(['ok' => true]);

        default:
            out(['error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    out(['error' => 'Server error: ' . $e->getMessage()], 500);
}