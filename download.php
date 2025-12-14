<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$file_id = $_GET['id'] ?? null;
$share_token = $_GET['token'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

$file_data = null;

// Case 1: Download from authenticated home page (using file ID)
if ($file_id && $user_id) {
    $stmt = $conn->prepare("SELECT filename, original_filename FROM uploaded_files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $file_data = $result->fetch_assoc();
        // Increment download count for authenticated downloads
        $update_stmt = $conn->prepare("UPDATE uploaded_files SET download_count = download_count + 1 WHERE id = ?");
        $update_stmt->bind_param("i", $file_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();
}
// Case 2: Download from a public share link (using token)
elseif ($share_token) {
    $stmt = $conn->prepare("SELECT id, filename, original_filename FROM uploaded_files WHERE share_token = ?");
    $stmt->bind_param("s", $share_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $file_data = $result->fetch_assoc();
        // Increment download count for public downloads
        $update_stmt = $conn->prepare("UPDATE uploaded_files SET download_count = download_count + 1 WHERE id = ?");
        $update_stmt->bind_param("i", $file_data['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();
}

$conn->close();

if (!$file_data) {
    die("File not found or you do not have permission to download it.");
}

$file_path = __DIR__ . "/uploads/" . $file_data['filename'];

if (!file_exists($file_path)) {
    die("File no longer exists on the server.");
}

// Set headers to force download
header('Content-Description: File Transfer');
header('Content-Type: ' . mime_content_type($file_path));
header('Content-Disposition: attachment; filename="' . basename($file_data['original_filename']) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
ob_clean();
flush();
readfile($file_path);
exit;
?>