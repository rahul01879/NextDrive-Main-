<?php
session_start();


ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Session and User Authentication ---
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=true");
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$uploadDir = "uploads/";
$thumbnailDir = "uploads/thumbnails/";

// --- Directory Setup and Permissions Check ---
function create_dir_and_check_permissions($dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) { // Use 0755 for better security
            return "Failed to create directory: {$dir}.";
        }
    }
    if (!is_writable($dir)) {
        return "Directory is not writable: {$dir}.";
    }
    return null;
}

$uploadError = create_dir_and_check_permissions($uploadDir);
if ($uploadError) {
    die($uploadError);
}

$thumbnailError = create_dir_and_check_permissions($thumbnailDir);
if ($thumbnailError) {
    die($thumbnailError);
}


// --- Database Connection ---
$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Helper Functions ---
function generateThumbnail($sourcePath, $destinationPath, $width = 100, $height = 100) {
    if (!extension_loaded('gd') && !function_exists('gd_info')) {
        error_log("GD library is not enabled. Thumbnail generation is not possible.");
        return false;
    }
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        error_log("Could not get image size for: " . $sourcePath);
        return false;
    }
    list($sourceWidth, $sourceHeight, $sourceType) = $imageInfo;

    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if ($sourceImage === false) {
        error_log("Could not create image resource from: " . $sourcePath);
        return false;
    }
    
    $thumbnailImage = imagecreatetruecolor($width, $height);
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($thumbnailImage, false);
        imagesavealpha($thumbnailImage, true);
        $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbnailImage, 0, 0, $width, $height, $transparent);
    }
    imagecopyresampled($thumbnailImage, $sourceImage, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

    $success = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($thumbnailImage, $destinationPath, 90);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($thumbnailImage, $destinationPath);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($thumbnailImage, $destinationPath);
            break;
    }

    imagedestroy($sourceImage);
    imagedestroy($thumbnailImage);
    return $success;
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'fas fa-file-pdf text-red-500';
        case 'doc': case 'docx': return 'fas fa-file-word text-blue-500';
        case 'xls': case 'xlsx': return 'fas fa-file-excel text-green-500';
        case 'ppt': case 'pptx': return 'fas fa-file-powerpoint text-orange-500';
        case 'zip': case 'rar': case '7z': return 'fas fa-file-archive text-yellow-500';
        case 'jpg': case 'jpeg': case 'png': case 'gif': case 'bmp': case 'tiff': return 'fas fa-file-image text-purple-500';
        case 'mp3': case 'wav': case 'ogg': return 'fas fa-file-audio text-indigo-500';
        case 'mp4': case 'avi': case 'mov': case 'webm': return 'fas fa-file-video text-pink-500';
        case 'txt': case 'md': return 'fas fa-file-alt text-gray-400';
        case 'php': case 'html': case 'css': case 'js': case 'json': case 'xml': return 'fas fa-file-code text-cyan-500';
        default: return 'fas fa-file text-gray-400';
    }
}

function uploadErrorMessage($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];
    return $errors[$code] ?? 'Unknown upload error.';
}

function formatTimeLeft($deleteDate) {
    if (empty($deleteDate)) return "Never expires";
    $deleteTime = new DateTime($deleteDate);
    $now = new DateTime();
    if ($now >= $deleteTime) return "Expired";
    $interval = $now->diff($deleteTime);
    if ($interval->y > 0) return $interval->y . "y " . $interval->m . "m left";
    if ($interval->m > 0) return $interval->m . "m " . $interval->d . "d left";
    if ($interval->d > 0) return $interval->d . "d " . $interval->h . "h left";
    if ($interval->h > 0) return $interval->h . "h " . $interval->i . "m left";
    return $interval->i . "m " . $interval->s . "s left";
}

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

// --- Delete file (manual) ---
if (isset($_GET['delete'])) {
    $fileIdToDelete = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT filename, thumbnail_filename FROM uploaded_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $fileIdToDelete, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $filePath = $uploadDir . $row['filename'];
        $thumbnailPath = $thumbnailDir . $row['thumbnail_filename'];
        if (file_exists($filePath)) unlink($filePath);
        if ($row['thumbnail_filename'] && file_exists($thumbnailPath)) unlink($thumbnailPath);
        $delStmt = $conn->prepare("DELETE FROM uploaded_files WHERE id = ? AND user_id = ?");
        $delStmt->bind_param("ii", $fileIdToDelete, $user_id);
        $delStmt->execute();
        $delStmt->close();
    }
    $stmt->close();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// --- Auto-delete expired files ---
$currentTime = date("Y-m-d H:i:s");
$stmt_expired = $conn->prepare("SELECT id, filename, thumbnail_filename FROM uploaded_files WHERE user_id = ? AND delete_date IS NOT NULL AND delete_date <= ?");
$stmt_expired->bind_param("is", $user_id, $currentTime);
$stmt_expired->execute();
$result_expired = $stmt_expired->get_result();
while ($row = $result_expired->fetch_assoc()) {
    $file = $uploadDir . $row['filename'];
    $thumbnail = $thumbnailDir . $row['thumbnail_filename'];
    if (file_exists($file)) unlink($file);
    if ($row['thumbnail_filename'] && file_exists($thumbnail)) unlink($thumbnail);
    $stmt_del_expired = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
    $stmt_del_expired->bind_param("i", $row['id']);
    $stmt_del_expired->execute();
    $stmt_del_expired->close();
}
$stmt_expired->close();


// --- Handle file uploads via POST request (from JS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json'); // Respond with JSON

    $response = ['status' => 'success', 'message' => 'Files uploaded successfully.'];
    $errors = []; // Will accumulate error messages if any upload fails

    $allowedTypes = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'text/plain', 'audio/mpeg', 'audio/wav', 'audio/ogg', 'video/mp4', 'video/webm', 'video/avi', 'video/quicktime',
        'text/css', 'application/javascript', 'application/json', 'text/xml', 'application/octet-stream',
    ];
    $maxFileSize = 10 * 1024 * 1024; // 10 MB

    foreach ($_FILES['files']['name'] as $key => $name) {
        $errorCode = $_FILES['files']['error'][$key];
        $originalFileName = basename($name);

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading {$originalFileName}: " . uploadErrorMessage($errorCode);
            continue; // continue to next file instead of break
        }

        $tmpName = $_FILES['files']['tmp_name'][$key];
        $fileSize = $_FILES['files']['size'][$key];
        $fileType = mime_content_type($tmpName);
        $fileExt = pathinfo($originalFileName, PATHINFO_EXTENSION);

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Error: File type not allowed for " . htmlspecialchars($originalFileName) . ".";
            continue;
        }
        if ($fileSize > $maxFileSize) {
            $errors[] = "Error: File " . htmlspecialchars($originalFileName) . " is too large (max 10MB).";
            continue;
        }

        $safeFileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalFileName);
        $uniqueName = uniqid() . '_' . time() . '_' . $safeFileName;
        $destinationPath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($tmpName, $destinationPath)) {
            $errors[] = "Error moving uploaded file " . htmlspecialchars($originalFileName) . ".";
            continue;
        }

        $uniqueThumbnailName = null;
        if (str_starts_with($fileType, 'image/')) {
            $uniqueThumbnailName = 'thumb_' . $uniqueName;
            $thumbnailPath = $thumbnailDir . $uniqueThumbnailName;
            if (!generateThumbnail($destinationPath, $thumbnailPath, 100, 100)) {
                error_log("Failed to generate thumbnail for: " . $uniqueName);
                $uniqueThumbnailName = null;
            }
        }

        $uploadTime = date("Y-m-d H:i:s");
        $deleteDate = $_POST['delete_date'] ?? '';
        $deleteHour = str_pad($_POST['delete_hour'] ?? '23', 2, "0", STR_PAD_LEFT);
        $deleteMinute = str_pad($_POST['delete_minute'] ?? '59', 2, "0", STR_PAD_LEFT);

        $deleteDateTime = null;
        if (!empty($deleteDate)) {
            $deleteDateTime = "$deleteDate $deleteHour:$deleteMinute:00";
        }

        try {
            $shareToken = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            error_log("Error generating random bytes for share token: " . $e->getMessage());
            $errors[] = "Internal server error generating share token for file " . htmlspecialchars($originalFileName) . ".";
            if (file_exists($destinationPath)) unlink($destinationPath);
            if ($uniqueThumbnailName && file_exists($thumbnailPath)) unlink($thumbnailPath);
            continue;
        }

        $stmt = $conn->prepare("INSERT INTO uploaded_files (user_id, filename, thumbnail_filename, original_filename, share_token, size, uploaded_at, delete_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isssssss",
            $user_id,
            $uniqueName,
            $uniqueThumbnailName,
            $originalFileName,
            $shareToken,
            $fileSize,
            $uploadTime,
            $deleteDateTime
        );

        if (!$stmt->execute()) {
            $errors[] = "Error inserting file " . htmlspecialchars($originalFileName) . " into database: " . htmlspecialchars($stmt->error);
            if (file_exists($destinationPath)) unlink($destinationPath);
            if ($uniqueThumbnailName && file_exists($thumbnailPath)) unlink($thumbnailPath);
            $stmt->close();
            continue;
        }
        $stmt->close();
    }

    if (!empty($errors)) {
        $response['status'] = 'error';
        $response['message'] = implode("\n", $errors);
    }

    echo json_encode($response);
    $conn->close();
    exit();
}


// --- Fetch uploaded files for current user ---
$uploadedFiles = [];
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql = "SELECT id, filename, thumbnail_filename, original_filename, size, uploaded_at, delete_date, download_count, share_token FROM uploaded_files WHERE user_id = ?";
if (!empty($search)) {
    $sql .= " AND (filename LIKE ? OR original_filename LIKE ?)";
}
$sql .= " ORDER BY id DESC";

$stmt_files = $conn->prepare($sql);
if (!empty($search)) {
    $searchTerm = "%" . $search . "%";
    $stmt_files->bind_param("iss", $user_id, $searchTerm, $searchTerm);
} else {
    $stmt_files->bind_param("i", $user_id);
}
$stmt_files->execute();
$result_files = $stmt_files->get_result();
while ($row = $result_files->fetch_assoc()) {
    $uploadedFiles[] = $row;
}
$stmt_files->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page - NextDrive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
        }
        body {
            background: linear-gradient(120deg, #2b3a67 0%, #1a1f35 100%);
            color: #e2e8f0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        /* Light mode styles */
        body[data-theme="light"] {
            background: linear-gradient(120deg, #e0e7ff 0%, #c3daff 100%);
            color: #333;
        }
        body[data-theme="light"] .glass-effect { background: rgba(255, 255, 255, 0.8); border: 1px solid rgba(0, 0, 0, 0.1); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
        body[data-theme="light"] .neumorphic { background: #f0f2f5; box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.1), -5px -5px 10px rgba(255, 255, 255, 0.8); }
        body[data-theme="light"] .neumorphic:hover { box-shadow: 8px 8px 16px rgba(0, 0, 0, 0.15), -8px -8px 16px rgba(255, 255, 255, 0.9); }
        body[data-theme="light"] .input-dark { background: #ffffff; border: 1px solid #ccc; color: #333; }
        body[data-theme="light"] .input-dark:focus { background: #f9f9f9; border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2); }
        body[data-theme="light"] .file-card { background: #ffffff; box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.08), -5px -5px 15px rgba(255, 255, 255, 0.8); border: 1px solid rgba(0, 0, 0, 0.05); }
        body[data-theme="light"] .file-card:hover { background: #f9f9f9; box-shadow: 8px 8px 20px rgba(0, 0, 0, 0.1), -8px -8px 20px rgba(255, 255, 255, 0.9); }
        body[data-theme="light"] .text-gray-100 { color: #333; }
        body[data-theme="light"] .text-gray-300 { color: #555; }
        body[data-theme="light"] .text-gray-400 { color: #777; }
        body[data-theme="light"] .text-gray-500 { color: #999; }
        body[data-theme="light"] .bg-gray-800 { background-color: #f0f2f5; }
        body[data-theme="light"] .bg-gray-700 { background-color: #e0e2e5; }
        body[data-theme="light"] .bg-slate-800 { background-color: #f0f2f5; }
        body[data-theme="light"] .bg-slate-900\/50 { background-color: rgba(220, 222, 225, 0.5); }
        body[data-theme="light"] .border-slate-700 { border-color: #ccc; }
        body[data-theme="light"] .bg-white\/5 { background-color: rgba(0, 0, 0, 0.05); }
        body[data-theme="light"] .bg-white\/10 { background-color: rgba(0, 0, 0, 0.1); }
        body[data-theme="light"] .stroke-white\/10 { stroke: rgba(0, 0, 0, 0.1); }
        body[data-theme="light"] .hover\:bg-white\/5:hover { background-color: rgba(0, 0, 0, 0.05); }
        body[data-theme="light"] .hover\:text-blue-400:hover { color: #3b82f6; }
        body[data-theme="light"] .hover\:text-red-400:hover { color: #ef4444; }
        body[data-theme="light"] .hover\:text-green-400:hover { color: #22c55e; }
        body[data-theme="light"] .hover\:bg-blue-900\/30:hover { background-color: rgba(59, 130, 246, 0.1); }
        body[data-theme="light"] .hover\:bg-red-900\/30:hover { background-color: rgba(239, 68, 68, 0.1); }
        .glass-effect { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); }
        .upload-zone { background: rgba(255, 255, 255, 0.02); border: 2px dashed rgba(255, 255, 255, 0.1); border-radius: 20px; transition: all 0.4s ease; }
        .upload-zone:hover { background: rgba(255, 255, 255, 0.05); border-color: #60a5fa; transform: translateY(-2px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); }
        .input-dark { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; color: #e2e8f0; transition: all 0.3s ease; }
        .input-dark:focus { background: rgba(255, 255, 255, 0.05); border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2); }
        .file-card { background: rgba(255, 255, 255, 0.02); border-radius: 20px; box-shadow: 8px 8px 24px rgba(0, 0, 0, 0.1), -8px -8px 24px rgba(255, 255, 255, 0.02); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.05); }
        .file-card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 12px 12px 32px rgba(0, 0, 0, 0.15), -12px -12px 32px rgba(255, 255, 255, 0.03); background: rgba(255, 255, 255, 0.05); }
        .progress-ring__circle { transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1); transform: rotate(-90deg); transform-origin: 50% 50%; }
        button { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        button:hover { transform: translateY(-2px); }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; transition: all 0.3s ease; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
        .neumorphic { box-shadow: 8px 8px 16px rgba(0, 0, 0, 0.2), -8px -8px 16px rgba(255, 255, 255, 0.02); border-radius: 20px; transition: all 0.3s ease; }
        .neumorphic:hover { box-shadow: 12px 12px 24px rgba(0, 0, 0, 0.25), -12px -12px 24px rgba(255, 255, 255, 0.03); }
    </style>
</head>
<body class="bg-gray-900 flex flex-col min-h-screen custom-scrollbar text-gray-100" data-theme="dark">
<header class="glass-effect fixed w-full z-10 shadow-lg">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <div class="text-2xl font-bold flex items-center">
                <a href="home.php">
                    <span class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg px-4 py-1.5 mr-2 shadow-lg">NextDrive</span>
                </a>
            </div>
        </div>
<div class="flex items-center w-full max-w-lg md:ml-">
            <form method="GET" action="home.php" class="flex items-center w-full">
                <div class="relative w-full">
                    <input type="text" name="search" id="fileSearchInput"
                        class="w-full pl-10 pr-4 py-2.5 input-dark rounded-xl
                        focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                        transition-all duration-300"
                        placeholder="Search your files..."
                        aria-label="Search"
                        value="<?= htmlspecialchars($search) ?>">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-500"></i>
                    </div>
                </div>
                <button type="submit"
                    class="ml-3 bg-gradient-to-r from-blue-500 to-indigo-500
                    hover:from-blue-600 hover:to-indigo-600 text-white font-medium
                    py-2.5 px-6 rounded-xl shadow-lg transition-all duration-300
                    transform hover:scale-105 hover:shadow-xl">
                    Search
                </button>
            </form>
        </div>
        <div class="flex items-center space-x-4">
            <button id="darkModeToggle" class="p-2 rounded-full bg-gray-700 text-gray-300 hover:bg-gray-600 transition-colors">
                <i class="fas fa-moon"></i>
            </button>
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <span class="text-sm font-semibold text-gray-400 ">Welcome back,</span>
                    <h3 class="text-base font-bold text-gray-100"><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></h3>
                </div>
            </div>
            <a href="login.php"
                class="flex items-center justify-center p-3 bg-gradient-to-r
                from-red-500 to-red-600 text-white rounded-xl
                hover:from-red-600 hover:to-red-700
                transition-all duration-300 transform hover:scale-105
                shadow-lg hover:shadow-xl group">
                <i class="fas fa-sign-out-alt mr-2 group-hover:rotate-180 transition-transform duration-500"></i>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </div>
</header>
<div id="fileResults" 
     class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mt-10 px-6">
</div>

<div class="main-content flex flex-1 mt-16 flex-col md:flex-row">
    <main class="flex-1 p-6 md:p-8 space-y-8">
        <section class="neumorphic p-8" aria-labelledby="upload-files-title">
            <h2 id="upload-files-title" class="text-2xl font-bold text-gray-100 mb-8 flex items-center">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center mr-4">
                    <i class="fas fa-cloud-upload-alt text-2xl text-blue-400" aria-hidden="true"></i>
                </div>
                Upload Files
            </h2>

            <form action="home.php" method="post" enctype="multipart/form-data" class="space-y-8" id="uploadForm" aria-describedby="upload-instructions">
                <div class="flex flex-col md:flex-row md:items-end gap-8">
                    <div class="flex-1">
                        <label for="fileInput" class="upload-zone flex flex-col items-center justify-center w-full h-56 px-4 cursor-pointer">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6 space-y-4">
                                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-blue-400" aria-hidden="true"></i>
                                </div>
                                <div class="text-center space-y-2" id="upload-instructions">
                                    <p class="text-lg text-gray-300">
                                        <span class="font-semibold text-blue-400">Click to upload</span> or drag and drop
                                    </p>
                                    <p class="text-sm text-gray-500">Supports multiple files (Max 10MB each)</p>
                                </div>
                            </div>
                            <input type="file" id="fileInput" name="files[]" multiple class="hidden" required aria-describedby="upload-instructions" />
                        </label>
                    </div>
                </div>

                <div id="uploadProgressBar" class="w-full bg-gray-700 rounded-full h-2.5 hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Upload progress">
                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: 0%" id="progressBarFill"></div>
                </div>

                <fieldset class="neumorphic p-6 space-y-6" aria-labelledby="auto-delete-settings-title">
                    <legend id="auto-delete-settings-title" class="text-lg font-semibold text-blue-400 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                            <i class="fas fa-clock text-blue-400" aria-hidden="true"></i>
                        </div>
                        <span>Auto-delete Settings (Optional)</span>
                    </legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label for="delete_date" class="block text-sm font-medium text-gray-300">Date</label>
                            <input type="date" id="delete_date" name="delete_date" class="w-full p-3 input-dark" />
                        </div>
                        <div class="space-y-2">
                            <label for="delete_hour" class="block text-sm font-medium text-gray-300">Hour</label>
                            <select id="delete_hour" name="delete_hour" class="w-full p-3 input-dark">
                                <option value="" class="bg-gray-800 text-gray-400">Select Hour</option>
                                <?php for ($i = 0; $i < 24; $i++): ?>
                                    <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>" class="bg-gray-800 text-gray-100">
                                        <?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="delete_minute" class="block text-sm font-medium text-gray-300">Minute</label>
                            <select id="delete_minute" name="delete_minute" class="w-full p-3 input-dark">
                                <option value="" class="bg-gray-800 text-gray-400">Select Minute</option>
                                <?php for ($i = 0; $i < 60; $i++): ?>
                                    <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>" class="bg-gray-800 text-gray-100">
                                        <?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <div class="w-full md:w-auto">
                    <button type="submit" name="upload"
                        class="w-full md:w-auto bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white font-medium py-4 px-8 rounded-2xl shadow-lg transition-all duration-300 transform hover:scale-105 hover:shadow-xl flex items-center justify-center space-x-3">
                        <i class="fas fa-upload text-lg" aria-hidden="true"></i>
                        <span>Upload Now</span>
                    </button>
                </div>
            </form>
        </section>

<section class="neumorphic p-8" aria-labelledby="my-files-title">
    <div class="flex justify-between items-center mb-8">
        <h2 id="my-files-title" class="text-2xl font-bold text-gray-100 flex items-center">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center mr-4">
                <i class="fas fa-folder-open text-2xl text-blue-400" aria-hidden="true"></i>
            </div>
            My Files
        </h2>
        <div class="text-sm font-medium text-gray-400 bg-white/5 px-4 py-2 rounded-xl" aria-live="polite" aria-atomic="true">
            <?= count($uploadedFiles) ?> files
        </div>
    </div>

    <!-- ⭐ AJAX Search Will Replace Everything Inside This Div ⭐ -->
    <div id="fileListContainer">

    <?php if (!empty($uploadedFiles)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($uploadedFiles as $file): ?>
                <article class="file-card" aria-label="<?= htmlspecialchars($file['original_filename'] ?: preg_replace('/^\d+_/', '', $file['filename'])) ?>">
                    <div class="p-6 space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                <?php
                                    $fileExtension = pathinfo($file['filename'], PATHINFO_EXTENSION);
                                    $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                ?>
                                <?php if (!empty($file['thumbnail_filename'])): ?>
                                    <img src="<?= htmlspecialchars($thumbnailDir . $file['thumbnail_filename']) ?>"
                                         alt="Thumbnail of <?= htmlspecialchars($file['original_filename']) ?>"
                                         class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <i class="<?= getFileIcon($file['filename']) ?> text-xl" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 min-w-0 space-y-2">
                                <p class="text-base font-semibold text-gray-100 truncate" 
                                   title="<?= htmlspecialchars($file['original_filename'] ?: preg_replace('/^\d+_/', '', $file['filename'])) ?>">

                                    <a href="#"
                                       class="preview-link text-blue-400 hover:underline"
                                       data-token="<?= htmlspecialchars($file['share_token'] ?? '') ?>">
                                        <?= htmlspecialchars($file['original_filename'] ?: preg_replace('/^\d+_/', '', $file['filename'])) ?>
                                    </a>
                                </p>
                                <p class="text-sm text-gray-400"><?= formatSize($file['size']) ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-400">Uploaded</p>
                                <p class="text-base font-semibold text-gray-100">
                                    <?= (new DateTime($file['uploaded_at']))->format('M j, Y') ?>
                                </p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-400">Downloads</p>
                                <p class="text-base font-semibold text-gray-100">
                                    <?= number_format($file['download_count']) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between bg-gray-800/50 px-6 py-4 border-t border-gray-700/50">
                        <div class="text-xs text-gray-400">
                            <?= formatTimeLeft($file['delete_date']) ?>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="copy-link-btn text-gray-400 hover:text-blue-400 transition-colors"
                                    data-token="<?= htmlspecialchars($file['share_token'] ?? '') ?>"
                                    aria-label="Copy link">
                                <i class="fas fa-link"></i>
                            </button>

                            <a href="download.php?id=<?= $file['id'] ?>" 
                               class="text-gray-400 hover:text-green-400 transition-colors"
                               aria-label="Download">
                                <i class="fas fa-download"></i>
                            </a>

                            <a href="?delete=<?= $file['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this file?')"
                               class="text-gray-400 hover:text-red-400 transition-colors"
                               aria-label="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="text-center py-16">
            <i class="fas fa-folder-open text-5xl text-blue-500/30 mb-4"></i>
            <p class="text-lg font-medium text-gray-400">No files uploaded yet.</p>
            <p class="text-sm text-gray-500">Start by uploading your first file!</p>
        </div>
    <?php endif; ?>

    </div> <!-- END #fileListContainer -->
</section>

    </main>

            
        <!-- Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="bg-gray-900 p-4 rounded-xl w-[90%] max-w-4xl relative">
    <button onclick="document.getElementById('previewModal').classList.add('hidden')" class="absolute top-3 right-3 text-gray-400 hover:text-white text-xl">&times;</button>
    <div id="previewContent" class="mt-6 text-center"></div>
  </div>
</div>


</div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    /* -------------------- THEME HANDLING -------------------- */
    const body = document.body;
    const darkModeToggle = document.getElementById('darkModeToggle');
    const storedTheme = localStorage.getItem('theme');

    if (storedTheme) {
        body.setAttribute('data-theme', storedTheme);
        if (storedTheme === 'light')
            darkModeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
    } else if (window.matchMedia('(prefers-color-scheme: light)').matches) {
        body.setAttribute('data-theme', 'light');
        darkModeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
    }

    darkModeToggle.addEventListener('click', function () {
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        body.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        const icon = darkModeToggle.querySelector('i');
        icon.classList.replace(
            newTheme === 'light' ? 'fa-moon' : 'fa-sun',
            newTheme === 'light' ? 'fa-sun' : 'fa-moon'
        );
    });



    /* -------------------- AJAX LIVE SEARCH -------------------- */
    const searchInput = document.getElementById("fileSearchInput");
    const fileListContainer = document.getElementById("fileListContainer");

    let searchTimeout = null;

    searchInput.addEventListener("keyup", function () {
        const q = this.value.trim();
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            fetch("ajax_search.php?q=" + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    renderSearchResults(data);
                    reattachAllHandlers();   // ⭐ FIXED ⭐
                })
                .catch(err => console.error("Search error:", err));
        }, 300);
    });



    /* -------------------- COPY SHARE LINK -------------------- */
    function attachCopyHandlers() {
        document.querySelectorAll('.copy-link-btn').forEach(button => {
            button.onclick = function () {
                const token = this.getAttribute('data-token');
                const shareUrl =
                    `${window.location.origin}/FileUpload/public/share.php?token=${encodeURIComponent(token)}`;

                navigator.clipboard.writeText(shareUrl).then(() => {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => (this.innerHTML = originalHTML), 2000);
                });
            };
        });
    }



    /* -------------------- PREVIEW MODAL -------------------- */
    function attachPreviewHandlers() {
        document.querySelectorAll(".preview-link").forEach(link => {
            link.onclick = function (e) {
                e.preventDefault();

                const token = this.dataset.token;
                const previewModal = document.getElementById("previewModal");
                const previewContent = document.getElementById("previewContent");

                previewModal.classList.remove("hidden");
                previewContent.innerHTML =
                    "<div class='text-gray-400 p-6 text-center'><i class='fas fa-spinner fa-spin text-3xl'></i><br>Loading...</div>";

                fetch("public/preview_content.php?token=" + encodeURIComponent(token))
                    .then(res => res.text())
                    .then(html => (previewContent.innerHTML = html))
                    .catch(() => {
                        previewContent.innerHTML =
                            "<div class='text-red-400 p-6 text-center'>Error loading preview</div>";
                    });
            };
        });
    }



    /* ----------- CALL THIS ANY TIME NEW HTML ARRIVES ----------- */
    function reattachAllHandlers() {
        attachPreviewHandlers();
        attachCopyHandlers();
    }

    // ⭐ Important! Run once on first page load so default files work
    reattachAllHandlers();



    /* -------------------- FILE UPLOAD -------------------- */
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const uploadProgressBar = document.getElementById('uploadProgressBar');
    const progressBarFill = document.getElementById('progressBarFill');

    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (fileInput.files.length === 0) return alert('Select files');

        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'home.php', true);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                uploadProgressBar.classList.remove('hidden');
                progressBarFill.style.width = percent + "%";
            }
        });

        xhr.onload = () => {
            uploadProgressBar.classList.add('hidden');
            if (xhr.status === 200) location.reload();
        };

        xhr.send(formData);
    });



    /* -------------------- DRAG & DROP -------------------- */
    const uploadZone = document.querySelector('.upload-zone');
    uploadZone.addEventListener('dragover', e => {
        e.preventDefault();
        uploadZone.classList.add('bg-gray-700/50', 'border-blue-500');
    });
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('bg-gray-700/50', 'border-blue-500');
    });
    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.classList.remove('bg-gray-700/50', 'border-blue-500');
        fileInput.files = e.dataTransfer.files;
        uploadForm.dispatchEvent(new Event('submit'));
    });

});



/* -------------------- RENDER SEARCH RESULTS -------------------- */
function renderSearchResults(files) {
    const container = document.getElementById("fileListContainer");
    container.innerHTML = "";

    if (!files.length) {
        container.innerHTML = `
            <div class="text-center py-16">
                <i class="fas fa-folder-open text-5xl text-blue-500/30 mb-4"></i>
                <p class="text-lg font-medium text-gray-400">No files found.</p>
            </div>`;
        return;
    }

    let html = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">`;

    files.forEach(file => {
        const ext = file.original_filename.split('.').pop().toLowerCase();
        const isImage = ["jpg", "jpeg", "png", "gif", "webp"].includes(ext);

        const thumb = file.thumbnail_filename
            ? `thumbnails/${file.thumbnail_filename}`
            : (isImage ? `uploads/${file.filename}` : "https://cdn-icons-png.flaticon.com/512/564/564619.png");

        html += `
        <article class="file-card">
            <div class="p-6 space-y-6">

                <div class="flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 
                                flex items-center justify-center overflow-hidden">
                        <img src="${thumb}" class="w-full h-full object-cover" />
                    </div>

                    <div class="flex-1 min-w-0 space-y-2">
                        <p class="text-base font-semibold text-gray-100 truncate">
                            <a href="#"
                               class="preview-link text-blue-400 hover:underline"
                               data-token="${file.share_token}">
                                ${file.original_filename}
                            </a>
                        </p>
                        <p class="text-sm text-gray-400">${(file.size/1024).toFixed(1)} KB</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-400">Uploaded</p>
                        <p class="text-base text-gray-100">${file.uploaded_at}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Downloads</p>
                        <p class="text-base text-gray-100">${file.download_count}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between bg-gray-800/50 px-6 py-4 border-t border-gray-700/50">
                <button class="copy-link-btn text-gray-400 hover:text-blue-400"
                        data-token="${file.share_token}">
                    <i class="fas fa-link"></i>
                </button>

                <a href="download.php?id=${file.id}" class="text-gray-400 hover:text-green-400">
                    <i class="fas fa-download"></i>
                </a>

                <a href="?delete=${file.id}" class="text-gray-400 hover:text-red-400"
                   onclick="return confirm('Delete this file?')">
                    <i class="fas fa-trash-alt"></i>
                </a>
            </div>
        </article>
        `;
    });

    html += `</div>`;
    container.innerHTML = html;
}
</script>



</body>
</html>