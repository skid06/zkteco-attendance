<?php
/**
 * Attendance Sync API Endpoint
 * PHP 5.2+ Compatible
 *
 * This is a simple API endpoint that receives attendance data
 * from the console application and logs it to a file.
 */

// Configuration
define('LOG_DIR', dirname(__FILE__) . '/logs');
define('LOG_FILE', LOG_DIR . '/attendance-' . date('Y-m-d') . '.log');
define('API_KEY', '57f6a5c35acc14c5111cad9dda7c8ce4e10875db542653dcf08be15042ea4414'); // Change this to match your .env ATTENDANCE_API_KEY

// Create logs directory if it doesn't exist
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Helper function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function to log data
function logData($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";

    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }

    $logMessage .= "\n" . str_repeat('-', 80) . "\n";

    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

// Helper function to get request headers
function getRequestHeaders() {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }

    // Fallback for servers that don't have getallheaders()
    $headers = array();
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
}

// Helper function to check API key
function checkApiKey() {
    $headers = getRequestHeaders();

    // Check Authorization header
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (empty($authHeader)) {
        logData('ERROR: Missing Authorization header', array(
            'headers' => $headers,
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI']
        ));
        sendJsonResponse(array(
            'success' => false,
            'message' => 'Missing Authorization header'
        ), 401);
    }

    // Extract token from "Bearer TOKEN" format
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    }

    if ($token !== API_KEY) {
        logData('ERROR: Invalid API key', array(
            'provided_token' => $token,
            'expected_token' => API_KEY
        ));
        sendJsonResponse(array(
            'success' => false,
            'message' => 'Invalid API key'
        ), 401);
    }
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Route the request based on method only
if ($method === 'GET') {
    // Health check endpoint - no auth required
    logData('Health check request');
    sendJsonResponse(array(
        'status' => 'ok',
        'timestamp' => date('c'),
        'message' => 'API is running'
    ));

} elseif ($method === 'POST') {
    // Attendance data endpoint - requires authentication
    checkApiKey();

    // Get request body
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if ($data === null) {
        logData('ERROR: Invalid JSON received', array(
            'raw_input' => $rawInput,
            'json_error' => 'JSON decode failed'
        ));
        sendJsonResponse(array(
            'success' => false,
            'message' => 'Invalid JSON data'
        ), 400);
    }

    // Log the received data
    $records = isset($data['records']) ? $data['records'] : array();
    $deviceInfo = isset($data['device_info']) ? $data['device_info'] : array();

    logData('ATTENDANCE DATA RECEIVED', array(
        'total_records' => count($records),
        'device_info' => $deviceInfo,
        'records' => $records,
        'raw_data' => $data
    ));

    // Send success response
    sendJsonResponse(array(
        'success' => true,
        'message' => 'Records saved successfully',
        'records_received' => count($records),
        'timestamp' => date('c')
    ));

} else {
    // Unsupported method
    logData('ERROR: Unsupported HTTP method', array(
        'method' => $method,
        'uri' => $_SERVER['REQUEST_URI']
    ));
    sendJsonResponse(array(
        'success' => false,
        'message' => 'Method not allowed'
    ), 405);
}
