<?php
// Start the session at the very beginning.
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// --- Configuration ---
$user_file = 'user.txt';

// --- Function to find user and verify password ---
function authenticate_user($email, $password, $filePath) {
    if (!file_exists($filePath)) {
        return ['authenticated' => false, 'message' => 'User database not found.'];
    }

    // Read the entire file content
    $file_content = file_get_contents($filePath);
    if (empty(trim($file_content))) {
        return ['authenticated' => false, 'message' => 'No users registered yet.'];
    }

    // Split the content by the '---' separator to get individual JSON records
    $records = explode('---', $file_content);

    foreach ($records as $record) {
        if (empty(trim($record))) {
            continue; // Skip empty lines between records
        }

        // Decode the JSON string into a PHP associative array
        $userData = json_decode(trim($record), true);

        // Check for JSON decoding errors and if the required keys exist
        if (json_last_error() !== JSON_ERROR_NONE || !isset($userData['personalDetails']['email'])) {
            // Malformed record, skip it
            continue;
        }

        // Check if the email matches
        if (strtolower(trim($userData['personalDetails']['email'])) === strtolower(trim($email))) {
            // Email found, now verify the password
            if (password_verify($password, $userData['personalDetails']['passwordHash'])) {
                // Password is correct, authentication successful
                return [
                    'authenticated' => true,
                    'user' => [
                        // NOTE: Assumes 'role' and 'firstName' are in personalDetails.
                        // You will need to add these to your registration script.
                        'firstName' => $userData['personalDetails']['firstName'] ?? 'User',
                        'email' => $userData['personalDetails']['email'],
                        'role' => $userData['personalDetails']['role'] ?? 'student' // Default to student if not set
                    ]
                ];
            } else {
                // Email found, but password was incorrect
                return ['authenticated' => false, 'message' => 'Invalid email or password.'];
            }
        }
    }

    // If the loop finishes, the email was not found
    return ['authenticated' => false, 'message' => 'Invalid email or password.'];
}

// --- Main Script Logic ---

// Default response
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    // --- Validation ---
    if (empty($email) || empty($password)) {
        $response['message'] = 'Email and password are required.';
    } elseif (!$email) {
        $response['message'] = 'Invalid email format.';
    } else {
        // --- Process Authentication ---
        $auth_result = authenticate_user($email, $password, $user_file);

        if ($auth_result['authenticated']) {
            // Store user data in the session
            $_SESSION['loggedin'] = true;
            $_SESSION['user_email'] = $auth_result['user']['email'];
            $_SESSION['user_name'] = $auth_result['user']['firstName'];
            $_SESSION['user_role'] = $auth_result['user']['role'];

            // Determine redirect URL based on role
            $redirect_url = 'student_dashboard.php'; // Default redirect
            if ($auth_result['user']['role'] === 'lecturer') {
                $redirect_url = 'lecturer_dashboard.php';
            }

            $response = [
                'status' => 'success',
                'message' => 'Login successful! Redirecting...',
                'redirect' => $redirect_url
            ];
        } else {
            $response['message'] = $auth_result['message'];
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

// Echo the JSON response
echo json_encode($response);
?>
