<?php
// public/preview_content.php - Returns only the preview HTML (for AJAX preview requests)

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in AJAX response
ini_set('log_errors', 1);

// Database Connection
$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    http_response_code(500);
    exit("Database connection failed: " . $conn->connect_error);
}

// Get Share Token
$token = isset($_GET['token']) ? $_GET['token'] : '';
if (empty($token)) {
    http_response_code(400);
    exit("<div class='text-red-400 text-center p-6'>Missing token.</div>");
}

// Fetch File Info from uploaded_files table using share_token
$stmt = $conn->prepare("SELECT filename, original_filename FROM uploaded_files WHERE share_token = ?");
if (!$stmt) {
    http_response_code(500);
    exit("<div class='text-red-400 text-center p-6'>Database error: " . htmlspecialchars($conn->error) . "</div>");
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $filename = $row['filename'];
    $original = $row['original_filename'];

    // Correct path: go up one directory from public/ to reach uploads/
    $uploadDir = realpath(__DIR__ . "/../uploads");
    if ($uploadDir === false) {
        http_response_code(500);
        exit("<div class='text-red-400 text-center p-6'>Upload directory not found.</div>");
    }
    
    $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    // Check if file exists
    if (!file_exists($uploadPath)) {
        http_response_code(404);
        exit("<div class='text-red-400 text-center p-6'>File not found on server.</div>");
    }

    // Get MIME type
    $mime = mime_content_type($uploadPath);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Build file URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Get project path dynamically
    $scriptPath = $_SERVER['SCRIPT_NAME']; // e.g., /project/public/preview_content.php
    $pathParts = explode('/', trim($scriptPath, '/'));
    $projectName = isset($pathParts[0]) ? $pathParts[0] : 'project';
    
    $fileUrl = $protocol . $host . "/" . $projectName . "/uploads/" . rawurlencode($filename);

    // Display filename as header
    echo "<h2 class='text-gray-200 text-lg font-semibold mb-4 text-center'>" . htmlspecialchars($original) . "</h2>";

    // Check MIME type and display accordingly
    if (strpos($mime, 'image/') === 0) {
        // Image file
        echo "<img src='" . htmlspecialchars($fileUrl) . "' alt='" . htmlspecialchars($original) . "' class='max-h-[80vh] mx-auto rounded-lg shadow-lg' onerror=\"this.parentElement.innerHTML='<div class=\\'text-red-400 text-center p-6\\'>Failed to load image.<br><small>Path: " . htmlspecialchars($fileUrl) . "</small></div>';\">";
        
    } elseif (strpos($mime, 'video/') === 0) {
        // Video file
        echo "<video controls class='max-h-[80vh] mx-auto rounded-lg shadow-lg'>
                <source src='" . htmlspecialchars($fileUrl) . "' type='" . htmlspecialchars($mime) . "'>
                Your browser does not support the video tag.
              </video>";
              
    } elseif ($ext === "pdf" || $mime === "application/pdf") {
        // PDF file
        echo "<iframe src='" . htmlspecialchars($fileUrl) . "' class='w-full h-[80vh] rounded-lg border-0'></iframe>";
        
    } elseif ($ext === "txt" || $mime === "text/plain") {
        // Text file
        $content = htmlspecialchars(file_get_contents($uploadPath));
        echo "<pre class='text-gray-300 text-left max-h-[80vh] overflow-auto bg-gray-800 p-4 rounded-lg'>" . $content . "</pre>";
        
    } else {
        // Unsupported file type
        echo "<div class='text-gray-400 text-center text-sm mt-4'>
                <i class='fas fa-file text-4xl mb-4'></i><br>
                Preview not available for this file type.<br>
                <small class='text-gray-500'>MIME: " . htmlspecialchars($mime) . "</small><br>
                <a href='" . htmlspecialchars($fileUrl) . "' class='text-blue-400 underline mt-2 inline-block' download>Download file</a>
              </div>";
    }
} else {
    http_response_code(404);
    echo "<div class='text-red-400 text-center p-6'>Invalid or expired share link.</div>";
}

$stmt->close();
$conn->close();
?>