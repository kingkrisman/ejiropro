<?php
// Start the session to access session variables
session_start();

// --- Security Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_role'] !== 'lecturer') {
    header('Location: login.html');
    exit;
}

// --- Configuration ---
$resource_file = 'resource.txt';
$upload_dir = 'uploads/';

// --- AJAX Action Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
    
    try {
        if (!file_exists($resource_file) || !is_readable($resource_file) || !is_writable($resource_file)) {
            throw new Exception("Database file is not accessible. Check permissions.");
        }

        $action = $_POST['action'];
        $resourceId = $_POST['resourceId'] ?? null;

        if (!$resourceId) {
            throw new Exception("Resource ID not provided.");
        }

        $file_content = file_get_contents($resource_file);
        $records = explode('---', $file_content);
        $new_records = [];
        $found = false;

        foreach ($records as $record) {
            if (empty(trim($record))) continue;
            
            $resourceData = json_decode(trim($record), true);
            
            if (isset($resourceData['resourceId']) && $resourceData['resourceId'] === $resourceId) {
                 if ($resourceData['uploaderDetails']['email'] !== $_SESSION['user_email']) {
                    throw new Exception("You are not authorized to modify this resource.");
                }
                $found = true;

                if ($action === 'delete') {
                    // Don't add this record to new_records, effectively deleting it
                    $file_to_delete = $resourceData['filePath'] ?? null;
                    if ($file_to_delete && file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                    continue; // Skip to the next record
                } elseif ($action === 'edit') {
                    // FIX: Replaced deprecated FILTER_SANITIZE_STRING with htmlspecialchars
                    $newTitle = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    $newDescription = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');

                    if(empty($newTitle) || empty($newDescription)){
                        throw new Exception("Title and description cannot be empty.");
                    }
                    
                    $resourceData['resourceDetails']['title'] = $newTitle;
                    $resourceData['resourceDetails']['description'] = $newDescription;
                }
            }
            $new_records[] = json_encode($resourceData, JSON_PRETTY_PRINT);
        }

        if (!$found) {
            throw new Exception("Resource not found.");
        }

        $new_file_content = implode("\n---\n", $new_records);
        if (!empty($new_file_content)) {
            $new_file_content .= "\n---\n";
        }

        if (file_put_contents($resource_file, $new_file_content, LOCK_EX) === false) {
            throw new Exception("Failed to update the database.");
        }
        
        $message = $action === 'delete' ? 'Resource deleted successfully.' : 'Resource updated successfully.';
        $response = ['status' => 'success', 'message' => $message];

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}


// --- File Upload Logic ---
$upload_message = '';
$upload_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resourceFile'])) {
    try {
        // FIX: Replaced deprecated FILTER_SANITIZE_STRING with htmlspecialchars
        $title = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $file = $_FILES['resourceFile'];

        if (empty($title) || empty($description)) throw new Exception("Title and description are required.");
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error code: " . $file['error']);
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) throw new Exception("Failed to create upload directory.");
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $new_filename = $safe_filename . '_' . time() . '.' . $file_extension;
        $destination = $upload_dir . $new_filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) throw new Exception("Failed to move uploaded file.");

        $resourceId = 'res_' . time() . '_' . bin2hex(random_bytes(4));
        $resourceData = [
            'resourceId' => $resourceId,
            'resourceDetails' => ['title' => $title, 'description' => $description],
            'uploaderDetails' => ['name' => $_SESSION['user_name'], 'email' => $_SESSION['user_email']],
            'filePath' => $destination,
            'metadata' => [
                'timestamp' => (new DateTime('now', new DateTimeZone('Africa/Lagos')))->format(DateTime::ATOM),
                'fileType' => $file['type'],
                'fileSize' => $file['size']
            ]
        ];
        
        $json_data = json_encode($resourceData, JSON_PRETTY_PRINT);
        $file_entry = $json_data . "\n---\n";

        if (file_put_contents($resource_file, $file_entry, FILE_APPEND | LOCK_EX) === false) {
            unlink($destination);
            throw new Exception("Failed to save resource metadata.");
        }
        $upload_status = 'success';
        $upload_message = 'Resource uploaded successfully!';
    } catch (Exception $e) {
        $upload_status = 'error';
        $upload_message = $e->getMessage();
    }
}

// --- Function to fetch resources ---
function get_lecturer_resources($filePath, $lecturerEmail) {
    if (!file_exists($filePath) || !is_readable($filePath)) return [];
    $file_content = file_get_contents($filePath);
    if (empty(trim($file_content))) return [];
    
    $resources = [];
    $records = explode('---', $file_content);
    foreach ($records as $record) {
        if (empty(trim($record))) continue;
        $resourceData = json_decode(trim($record), true);
        if (json_last_error() === JSON_ERROR_NONE && isset($resourceData['uploaderDetails']['email']) && $resourceData['uploaderDetails']['email'] === $lecturerEmail) {
            $resources[] = $resourceData;
        }
    }
    return array_reverse($resources);
}

$my_resources = get_lecturer_resources($resource_file, $_SESSION['user_email']);
$user_name = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - LASU FOC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 1050; display: none; }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1040; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-9-5.747h18" /></svg>
                <span class="text-xl font-bold text-gray-800">LASU FOC Portal</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Welcome, <?php echo $user_name; ?>!</span>
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors duration-300 text-sm font-medium"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Upload Form -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Upload New Resource</h2>
                <form action="lecturer_dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Resource Title</label>
                        <input type="text" id="title" name="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="description" name="description" rows="3" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="resourceFile" class="block text-sm font-medium text-gray-700">Select File</label>
                        <input type="file" id="resourceFile" name="resourceFile" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors duration-300 font-semibold flex items-center justify-center">
                            <i class="fas fa-upload mr-2"></i>Upload Resource
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: My Uploads -->
        <div class="lg:col-span-2">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">My Uploaded Resources</h2>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div id="resource-list" class="space-y-4">
                    <?php if (empty($my_resources)): ?>
                        <div id="no-resources-message" class="text-center text-gray-500 py-8">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-4"></i>
                            <p>You have not uploaded any resources yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_resources as $resource):
                            $resourceId = htmlspecialchars($resource['resourceId'] ?? '');
                            $title = htmlspecialchars($resource['resourceDetails']['title'] ?? 'N/A');
                            $description = htmlspecialchars($resource['resourceDetails']['description'] ?? '');
                            $date = isset($resource['metadata']['timestamp']) ? date('M d, Y', strtotime($resource['metadata']['timestamp'])) : 'N/A';
                            $fileSize = isset($resource['metadata']['fileSize']) ? round($resource['metadata']['fileSize'] / 1024, 2) . ' KB' : 'N/A';
                            $filePath = htmlspecialchars($resource['filePath'] ?? '#');
                        ?>
                        <div id="resource-<?php echo $resourceId; ?>" class="resource-item flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors" data-description="<?php echo $description; ?>">
                            <div class="flex items-center space-x-4 overflow-hidden">
                                <i class="fas fa-file-alt text-blue-500 fa-2x"></i>
                                <div class="overflow-hidden">
                                    <p class="font-semibold text-gray-800 truncate resource-title"><?php echo $title; ?></p>
                                    <p class="text-xs text-gray-500">Uploaded on <?php echo $date; ?> &bull; Size: <?php echo $fileSize; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 flex-shrink-0">
                                <a href="<?php echo $filePath; ?>" download class="text-gray-500 hover:text-blue-600" title="Download"><i class="fas fa-download"></i></a>
                                <button data-id="<?php echo $resourceId; ?>" class="edit-btn text-gray-500 hover:text-green-600" title="Edit"><i class="fas fa-edit"></i></button>
                                <button data-id="<?php echo $resourceId; ?>" class="delete-btn text-gray-500 hover:text-red-600" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Edit Modal -->
    <div id="edit-modal" class="modal-overlay">
        <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Edit Resource</h2>
            <form id="edit-form">
                <input type="hidden" id="edit-resource-id" name="resourceId">
                <input type="hidden" name="action" value="edit">
                <div class="space-y-4">
                    <div>
                        <label for="edit-title" class="block text-sm font-medium text-gray-700">Resource Title</label>
                        <input type="text" id="edit-title" name="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="edit-description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="edit-description" name="description" rows="3" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancel-edit" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast p-4 rounded-lg shadow-lg text-white">
        <p id="toast-message"></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            const resourceList = document.getElementById('resource-list');
            const editModal = document.getElementById('edit-modal');
            const editForm = document.getElementById('edit-form');
            const cancelEditBtn = document.getElementById('cancel-edit');

            function showToast(message, status) {
                toastMessage.textContent = message;
                toast.className = 'toast p-4 rounded-lg shadow-lg text-white';
                toast.classList.add(status === 'success' ? 'bg-green-500' : 'bg-red-500');
                toast.style.display = 'block';
                setTimeout(() => { toast.style.display = 'none'; }, 4000);
            }

            const uploadStatus = '<?php echo $upload_status; ?>';
            const uploadMessage = '<?php echo $upload_message; ?>';
            if (uploadStatus) {
                showToast(uploadMessage, uploadStatus);
            }

            resourceList.addEventListener('click', function(e) {
                const targetButton = e.target.closest('button');
                if (!targetButton) return;

                const resourceId = targetButton.dataset.id;
                const resourceRow = document.getElementById('resource-' + resourceId);

                if (targetButton.classList.contains('delete-btn')) {
                    if (confirm('Are you sure you want to delete this resource? This action cannot be undone.')) {
                        fetch('lecturer_dashboard.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=delete&resourceId=${resourceId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                resourceRow.style.transition = 'opacity 0.5s ease';
                                resourceRow.style.opacity = '0';
                                setTimeout(() => { resourceRow.remove(); }, 500);
                            }
                            showToast(data.message, data.status);
                        })
                        .catch(error => showToast('An error occurred.', 'error'));
                    }
                } else if (targetButton.classList.contains('edit-btn')) {
                    const title = resourceRow.querySelector('.resource-title').textContent;
                    const description = resourceRow.dataset.description;
                    
                    document.getElementById('edit-resource-id').value = resourceId;
                    document.getElementById('edit-title').value = title;
                    document.getElementById('edit-description').value = description;
                    
                    editModal.style.display = 'flex';
                }
            });

            cancelEditBtn.addEventListener('click', () => {
                editModal.style.display = 'none';
            });

            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const resourceId = formData.get('resourceId');

                fetch('lecturer_dashboard.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const resourceRow = document.getElementById('resource-' + resourceId);
                        resourceRow.querySelector('.resource-title').textContent = formData.get('title');
                        resourceRow.dataset.description = formData.get('description');
                        editModal.style.display = 'none';
                    }
                    showToast(data.message, data.status);
                })
                .catch(error => showToast('An error occurred.', 'error'));
            });
        });
    </script>
</body>
</html>
