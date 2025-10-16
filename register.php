<?php
// Set header to return JSON and suppress default PHP errors for custom handling
header('Content-Type: application/json');
error_reporting(0);

// --- Configuration ---
$user_file = 'user.txt';

// --- Function to check if email exists in the JSON-structured file ---
function emailExists($email, $filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false; // Cannot check if file doesn't exist or isn't readable
    }

    $file_content = file_get_contents($filePath);
    if (empty(trim($file_content))) {
        return false;
    }

    $records = explode('---', $file_content);

    foreach ($records as $record) {
        if (empty(trim($record))) {
            continue;
        }
        $userData = json_decode(trim($record), true);

        if (json_last_error() === JSON_ERROR_NONE && isset($userData['personalDetails']['email'])) {
            if (strtolower(trim($userData['personalDetails']['email'])) === strtolower(trim($email))) {
                return true; // Email found
            }
        }
    }
    return false; // Email not found
}

// --- Helper function to get OS and Browser from User Agent ---
function parseUserAgent($userAgent) {
    $os = 'Unknown';
    $browser = 'Unknown';
    $deviceType = 'desktop';

    // OS detection
    if (preg_match('/linux/i', $userAgent)) $os = 'Linux';
    elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'Mac OS';
    elseif (preg_match('/windows|win32/i', $userAgent)) $os = 'Win32';
    elseif (preg_match('/android/i', $userAgent)) { $os = 'Android'; $deviceType = 'mobile'; }
    elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) { $os = 'iOS'; $deviceType = 'mobile'; }

    // Browser detection
    if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/msie/i', $userAgent) || preg_match('/trident/i', $userAgent)) $browser = 'Internet Explorer';
    elseif ($browser === 'Unknown' && preg_match('/mozilla/i', $userAgent) && !preg_match('/compatible/i', $userAgent)) $browser = 'Netscape';

    return ['os' => $os, 'browser' => $browser, 'deviceType' => $deviceType];
}

// --- Main Script Logic ---
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Get form data
    $fullname = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    // --- Validation ---
    if (empty($fullname) || empty($email) || empty($password) || empty($role)) {
        throw new Exception('Please fill in all required fields.');
    }
    if (!$email) {
        throw new Exception('Invalid email format provided.');
    }
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters long.');
    }
    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match.');
    }
    if (!in_array($role, ['student', 'lecturer'])) {
        throw new Exception('Invalid role selected.');
    }
    if (emailExists($email, $user_file)) {
        throw new Exception('An account with this email already exists.');
    }

    // --- Process Registration ---
    // Check for write permissions before attempting to write file
    if ((file_exists($user_file) && !is_writable($user_file)) || (!file_exists($user_file) && !is_writable(dirname($user_file)))) {
        throw new Exception('Server configuration error: Cannot write to database file. Please check permissions.');
    }
    
    // Split fullname into first and last name
    $nameParts = explode(' ', trim($fullname), 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    // Generate a placeholder matric number and hash password
    $matricNumber = strtoupper(substr($firstName, 0, 2)) . rand(100, 999);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Collect Metadata
    $userAgentInfo = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $screenResolution = $_POST['screenResolution'] ?? 'Unknown';

    // Create the user data array
    $userData = [
        'personalDetails' => [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'matricNumber' => $matricNumber,
            'email' => $email,
            'role' => $role,
            'phoneNumber' => '', // Placeholder
            'passwordHash' => $hashed_password
        ],
        'filePaths' => [
            'passport' => '',
            'signature' => ''
        ],
        'metadata' => [
            'deviceType' => $userAgentInfo['deviceType'],
            'os' => $userAgentInfo['os'],
            'browser' => $userAgentInfo['browser'],
            'screenResolution' => $screenResolution,
            'ip' => $ip,
            'location' => 'Lagos, Nigeria', // Static as per example
            'timestamp' => (new DateTime('now', new DateTimeZone('Africa/Lagos')))->format(DateTime::ATOM)
        ]
    ];

    // Convert array to JSON string
    $json_data = json_encode($userData, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        throw new Exception('Server error: Failed to process user data.');
    }

    // Create the entry with a separator
    $file_entry = $json_data . "\n---\n";

    // Append data to the file
    if (file_put_contents($user_file, $file_entry, FILE_APPEND | LOCK_EX) === false) {
        throw new Exception('Server error: Failed to save user data to the database.');
    }

    // If everything is successful
    $response = [
        'status' => 'success',
        'message' => 'Registration successful! Redirecting to login...'
    ];

} catch (Exception $e) {
    // Catch any exception thrown and set it as the error message
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Echo the final JSON response
echo json_encode($response);
?>
