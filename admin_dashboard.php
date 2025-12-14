<?php
session_start();

// Session timeout configuration (30 minutes)
$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();     
    session_destroy();   
    header("Location: admin.php?timeout=true"); 
    exit();
}
$_SESSION['last_activity'] = time(); 

// Check if admin is logged in and has the 'admin' role
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin.php"); // Redirect to admin login if not authenticated as admin
    exit();
}

// Database connection
// Ensure this matches your actual database configuration
$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle user deletion with foreign key constraint
if (isset($_GET['delete_user'])) {
    $userId = intval($_GET['delete_user']); // Sanitize input
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First delete user's files from filesystem
        $stmt_files = $conn->prepare("SELECT filename FROM uploaded_files WHERE user_id = ?");
        $stmt_files->bind_param("i", $userId);
        $stmt_files->execute();
        $result_files = $stmt_files->get_result();
        while ($row = $result_files->fetch_assoc()) {
            $filePath = "uploads/" . $row['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $stmt_files->close();

        // Then delete user's files from database
        $stmt_del_files = $conn->prepare("DELETE FROM uploaded_files WHERE user_id = ?");
        $stmt_del_files->bind_param("i", $userId);
        $stmt_del_files->execute();
        $stmt_del_files->close();
        
        // Also delete related messages (if you implement messaging)
        // $stmt_del_messages = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
        // $stmt_del_messages->bind_param("ii", $userId, $userId);
        // $stmt_del_messages->execute();
        // $stmt_del_messages->close();
        
        // Finally, delete the user
        $stmt_del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del_user->bind_param("i", $userId);
        $stmt_del_user->execute();
        $stmt_del_user->close();
        
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
    $file_id = intval($_GET['delete_file']); // Sanitize input

    // Fetch file info first using prepared statement
    $stmt = $conn->prepare("SELECT filename FROM uploaded_files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $filename = $row['filename'];
        $filePath = "uploads/" . $filename;

        // Delete from filesystem
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database using prepared statement
        $del = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
        $del->bind_param("i", $file_id);
        $del->execute();
        $del->close();
    }
    $stmt->close();

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
    $totalStorageUsed += $row['size']; // Assuming 'size' is in bytes
}

// Fetch active vs expired files
$activeFiles = $conn->query("SELECT COUNT(*) as count FROM uploaded_files WHERE delete_date > NOW() OR delete_date IS NULL")->fetch_assoc()['count'];
$expiredFiles = $conn->query("SELECT COUNT(*) as count FROM uploaded_files WHERE delete_date <= NOW()")->fetch_assoc()['count'];

// Function to format bytes into human-readable format
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

// Fetch recent uploads
$recentUploads = $conn->query("
    SELECT uf.id, uf.filename, uf.original_filename, uf.uploaded_at, u.username
    FROM uploaded_files uf
    JOIN Users u ON uf.user_id = u.id
    ORDER BY uf.uploaded_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Fetch all users
$users = $conn->query("SELECT id, name, username, email, registration_date FROM users ORDER BY registration_date DESC");


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
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
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
                <span class="text-gray-300">Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></span>
                <a href="logout.php" class="flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <div class="pt-20 px-4 pb-8">
        <div class="container mx-auto">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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

                <div class="bg-slate-800 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 bg-emerald-500/20 rounded-lg mr-4">
                            <i class="fas fa-check-circle text-emerald-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Active Files</p>
                            <p class="text-2xl font-bold"><?= $activeFiles ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-xl p-6 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-3 bg-rose-500/20 rounded-lg mr-4">
                            <i class="fas fa-times-circle text-rose-400 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-400">Expired Files</p>
                            <p class="text-2xl font-bold"><?= $expiredFiles ?></p>
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
                                <?php if (empty($recentUploads)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-400">No recent uploads.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentUploads as $file): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="fas fa-file text-blue-400 mr-3"></i>
                                                <div class="truncate max-w-xs"><?= htmlspecialchars($file['original_filename'] ?? $file['filename']) ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($file['username']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                            <?= date('M j, Y g:i A', strtotime($file['uploaded_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex space-x-2">
                                                <a href="download.php?file=<?= urlencode($file['filename']) ?>" 
                                                   class="text-blue-400 hover:text-blue-300 p-2 rounded-full hover:bg-blue-900/30 transition-colors"
                                                   title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete_file=<?= $file['id'] ?>" 
                                                   class="text-red-400 hover:text-red-300 p-2 rounded-full hover:bg-red-900/30 transition-colors"
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this file?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Registered</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Files</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-slate-800 divide-y divide-slate-700">
                                <?php if ($users->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-400">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($user['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                            <?= date('M j, Y g:i A', strtotime($user['registration_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                                $stmt_file_count = $conn->prepare("SELECT COUNT(*) as count FROM uploaded_files WHERE user_id = ?");
                                                $stmt_file_count->bind_param("i", $user['id']);
                                                $stmt_file_count->execute();
                                                $fileCount = $stmt_file_count->get_result()->fetch_assoc()['count'];
                                                $stmt_file_count->close();
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
                                <?php endif; ?>
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
$conn->close();