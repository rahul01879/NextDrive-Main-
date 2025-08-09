<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "rahul123", "project", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle user deletion with foreign key constraint
if (isset($_GET['delete_user'])) {
    $userId = $_GET['delete_user'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First delete user's files
        $conn->query("DELETE FROM uploaded_files WHERE user_id = $userId");
        
        // Then delete the user
        $conn->query("DELETE FROM users WHERE id = $userId");
        
        // Also delete related messages
        $conn->query("DELETE FROM messages WHERE sender_id = $userId OR receiver_id = $userId");
        
        // Commit changes
        $conn->commit();
        
        header("Location: admin_dashboard.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting user: " . addslashes($e->getMessage()) . "')</script>";
    }
}

// Handle file deletion
if (isset($_GET['delete_file'])) {
    $filename = $_GET['delete_file'];
    $filePath = "uploads/" . $filename;
    
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    $conn->query("DELETE FROM uploaded_files WHERE name = '" . $conn->real_escape_string($filename) . "'");
    
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM Users")->fetch_assoc()['count'];
$totalFiles = $conn->query("SELECT COUNT(*) as count FROM uploaded_files")->fetch_assoc()['count'];

// Fetch total storage used
$totalStorageUsed = 0;
$result = $conn->query("SELECT size FROM uploaded_files");
while ($row = $result->fetch_assoc()) {
    $totalStorageUsed += $row['size']; // Assuming 'size' is a column in 'uploaded_files' that stores the file size in bytes
}

// Function to format bytes into human-readable format
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

$recentUploads = $conn->query("
    SELECT uploaded_files.name, uploaded_files.upload_time, Users.username
    FROM uploaded_files
    JOIN Users ON uploaded_files.user_id = Users.id
    ORDER BY uploaded_files.upload_time DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT * FROM users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            color-scheme: dark;
        }
        body {
            background-color: #0f172a;
            font-family: 'Inter', sans-serif;
        }
        .glass-effect {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="min-h-screen text-gray-200">
    <!-- Header -->
    <header class="glass-effect fixed w-full z-10 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-2xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                Admin Dashboard
            </h1>
            <div class="flex items-center space-x-4">
                <a href="logout.php" class="flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <div class="pt-16 px-4 pb-8">
        <div class="container mx-auto">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-slate-800 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-500/20 rounded-lg mr-4">
                            <i class="fas fa-users text-blue-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Total Users</p>
                            <p class="text-2xl font-bold"><?= $totalUsers ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-slate-800 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-500/20 rounded-lg mr-4">
                            <i class="fas fa-file-upload text-green-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Total Files</p>
                            <p class="text-2xl font-bold"><?= $totalFiles ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-slate-800 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-500/20 rounded-lg mr-4">
                            <i class="fas fa-database text-purple-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Storage Used</p>
                            <p class="text-2xl font-bold"><?= formatSize($totalStorageUsed) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Uploads Section -->
            <div class="bg-slate-800 rounded-xl shadow-lg overflow-hidden mb-8">
                <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-clock text-blue-400 mr-3"></i>
                        Recent Uploads
                    </h2>
                    <div class="text-sm text-gray-400">
                        Last 5 uploads
                    </div>
                </div>
                
                <div class="p-2">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-700">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">File</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Uploader</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-800 divide-y divide-slate-700">
                                <?php foreach ($recentUploads as $file): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-file text-blue-400 mr-3"></i>
                                            <div class="truncate max-w-xs"><?= htmlspecialchars($file['name']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($file['username']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                        <?= date('M j, Y g:i A', strtotime($file['upload_time'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <a href="uploads/<?= urlencode($file['name']) ?>" 
                                               class="text-blue-400 hover:text-blue-300 p-2 rounded-full hover:bg-blue-900/30 transition-colors"
                                               title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?delete_file=<?= urlencode($file['name']) ?>" 
                                               class="text-red-400 hover:text-red-300 p-2 rounded-full hover:bg-red-900/30 transition-colors"
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this file?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Management Section -->
            <div class="bg-slate-800 rounded-xl shadow-lg overflow-hidden">
                <div class="border-b border-slate-700 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-users-cog text-purple-400 mr-3"></i>
                        User Management
                    </h2>
                    <div class="text-sm text-gray-400">
                        <?= $totalUsers ?> registered users
                    </div>
                </div>
                
                <div class="p-2">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-700">
                            <thead class="bg-slate-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Registered</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Files</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-800 divide-y divide-slate-700">
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                        <?= date('M j, Y g:i A', strtotime($user['registration_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            $fileCount = $conn->query("SELECT COUNT(*) as count FROM uploaded_files WHERE user_id = " . $user['id'])->fetch_assoc()['count'];
                                            echo $fileCount;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="?delete_user=<?= $user['id'] ?>" 
                                           class="text-red-400 hover:text-red-300 px-3 py-1 rounded-lg hover:bg-red-900/30 transition-colors"
                                           onclick="return confirm('Are you sure you want to delete this user and all their files?')">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
