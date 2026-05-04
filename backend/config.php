<?php

define('CLOUDFLARE_ACCOUNT_ID', 'your_account_id_here');
define('CLOUDFLARE_STREAM_TOKEN', 'your_api_token_here');
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'education_platform';
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 86400");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($conn->connect_error) {
    die("Connection failed");
}

function validateRequestMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        respond('error', 'Invalid request method');
    }
}

function respond($status, $data = [])
{
    header('Content-Type: application/json');

    if ($status == 'error') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $data
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    }
    exit;
}

function requireParams($params)
{
    $input = [];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    } else {
        $json = json_decode(file_get_contents('php://input'), true);

        if (!empty($json)) {
            $input = $json;
        } else {
            $input = $_POST;
        }
    }

    foreach ($params as $param) {
        if (!isset($input[$param])) {
            respond('error', "Missing parameter: $param");
        }

        if (is_string($input[$param]) && trim($input[$param]) === '') {
            respond('error', "Parameter cannot be empty: $param");
        }
    }

    foreach ($input as $key => $value) {
        if (is_string($value)) {
            $input[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    return $input;
}

function getToken()
{
    $headers = getallheaders();
    $headers = array_change_key_case($headers, CASE_LOWER);

    if (isset($headers['x-authorization'])) {
        return str_replace('Bearer ', '', $headers['x-authorization']);
    }

    if (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
        return str_replace('Bearer ', '', $_SERVER['HTTP_X_AUTHORIZATION']);
    }

    return null;
}

function verifyToken($token)
{
    global $conn;

    $query = "
        SELECT 
            admins.id AS id,
            admins.role AS auth_type,
            'admin' AS user_type
        FROM admins_sessions
        JOIN admins ON admins.id = admins_sessions.admin_id
        WHERE admins_sessions.token = ?
        AND admins_sessions.status = 'active'
        AND admins_sessions.expires_at > NOW()
        AND admins.status = 'active'
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    $query = "
        SELECT 
            students.id AS id,
            'student' AS auth_type,
            'student' AS user_type
        FROM student_sessions
        JOIN students ON students.id = student_sessions.student_id
        WHERE student_sessions.token = ?
        AND student_sessions.status = 'active'
        AND student_sessions.expires_at > NOW()
        AND students.status = 'active'
        LIMIT 1
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    respond('error', 'Invalid session');
}

function requireAuth($allowedRoles = [])
{
    $token = getToken();

    if (!$token) {
        respond('error', 'Authorization token required');
    }

    $auth = verifyToken($token);

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (in_array('admins', $allowedRoles)) {
        $allowedRoles = array_merge($allowedRoles, [
            'super_admin',
            'admin',
            'assistant'
        ]);
    }

    if (!in_array($auth['auth_type'], $allowedRoles)) {
        respond('error', 'You are not authorized to perform this action');
    }

    $auth['token'] = $token;

    return $auth;
}

function getBody()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}
function logAction($adminId, $action, $target_type, $target_id, $details = null)
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $adminId, $action, $target_type, $target_id, $details);
    $stmt->execute();
    $logId = $stmt->insert_id;
    $stmt->close();

    return ['log_id' => $logId];}

function optionalAuth()
{
    $token = getToken();

    if (!$token) {
        return null;
    }

    return verifyToken($token);
}