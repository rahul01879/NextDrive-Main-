<?php
// Always output JSON
header("Content-Type: application/json; charset=utf-8");
session_start();

$user_id = $_SESSION['user_id'] ?? 0;

// Block unauthorized access
if (!$user_id) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get search query
$search = $_GET['q'] ?? '';
$search = trim($search);

// Build SQL query
$sql = "SELECT id, original_filename, filename, size, share_token 
        FROM uploaded_files 
        WHERE user_id = ?";

if ($search !== "") {
    $sql .= " AND (original_filename LIKE ? OR filename LIKE ?)";
}

$sql .= " ORDER BY id DESC";

// Prepare statement
$stmt = $conn->prepare($sql);

// Bind parameters
if ($search !== "") {
    $like = "%" . $search . "%";
    $stmt->bind_param("iss", $user_id, $like, $like);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];

// Fetch all files
while ($row = $result->fetch_assoc()) {

    // Sanitize to avoid broken HTML or XSS
    $row['original_filename'] = htmlspecialchars($row['original_filename'], ENT_QUOTES, 'UTF-8');
    $row['filename'] = htmlspecialchars($row['filename'], ENT_QUOTES, 'UTF-8');

    $data[] = $row;
}

// Return JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
