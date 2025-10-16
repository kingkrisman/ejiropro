<?php
// Start the session to access session variables
session_start();

// Check if the user is logged in and is a student.
// If not, redirect them to the login page.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_role'] !== 'student') {
    header('Location: login.html');
    exit;
}

// --- Configuration for resources ---
$resource_file = 'resource.txt';

// --- Function to fetch all resources ---
function get_all_resources($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return []; // Return empty array if file doesn't exist or isn't readable
    }

    $file_content = file_get_contents($filePath);
    if (empty(trim($file_content))) {
        return [];
    }

    $resources = [];
    $records = explode('---', $file_content);

    foreach ($records as $record) {
        if (empty(trim($record))) {
            continue;
        }
        $resourceData = json_decode(trim($record), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $resources[] = $resourceData;
        }
    }
    return $resources;
}

// Fetch all resources for display
$available_resources = get_all_resources($resource_file);

// Get user's name from session for a personalized welcome message
$user_name = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - LASU FOC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-9-5.747h18" />
                </svg>
                <span class="text-xl font-bold text-gray-800">LASU FOC Portal</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Welcome, <?php echo $user_name; ?>!</span>
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors duration-300 text-sm font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Available Resources</h1>

        <!-- Resources Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($available_resources)): ?>
                <div class="col-span-full bg-white p-8 rounded-lg shadow-md text-center text-gray-500">
                    <i class="fas fa-folder-open fa-3x mb-4"></i>
                    <p class="text-lg">No resources have been uploaded yet.</p>
                    <p>Please check back later.</p>
                </div>
            <?php else: ?>
                <?php foreach ($available_resources as $resource):
                    // Sanitize output to prevent XSS
                    $title = htmlspecialchars($resource['resourceDetails']['title'] ?? 'No Title');
                    $description = htmlspecialchars($resource['resourceDetails']['description'] ?? 'No description available.');
                    $lecturer = htmlspecialchars($resource['uploaderDetails']['name'] ?? 'Unknown Lecturer');
                    $date = isset($resource['metadata']['timestamp']) ? date('F j, Y, g:i a', strtotime($resource['metadata']['timestamp'])) : 'Unknown Date';
                    $filePath = htmlspecialchars($resource['filePath'] ?? '#');
                ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden transform hover:-translate-y-1 transition-transform duration-300">
                        <div class="p-6">
                            <div class="flex justify-between items-start">
                                <h2 class="text-xl font-bold text-gray-900 mb-2"><?php echo $title; ?></h2>
                                <a href="<?php echo $filePath; ?>" download class="bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium flex items-center space-x-2">
                                    <i class="fas fa-download"></i>
                                    <span>Download</span>
                                </a>
                            </div>
                            <p class="text-gray-600 text-sm mb-4"><?php echo $description; ?></p>
                            <div class="border-t border-gray-200 pt-4">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-user-tie mr-2"></i>Uploaded by: <strong><?php echo $lecturer; ?></strong>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-calendar-alt mr-2"></i>Date: <?php echo $date; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
