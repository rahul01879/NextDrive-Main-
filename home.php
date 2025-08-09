<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$uploadDir = "uploads/";

// Function to extract original filename (remove timestamp_)
function getOriginalFilename($filename) {
    $parts = explode('_', $filename, 2);
    return isset($parts[1]) ? $parts[1] : $filename;
}

// 1. Connect to DB
// 1. Connect to DB on port 3307
$conn = new mysqli("localhost", "root", "rahul123", "project", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Delete file if requested and belongs to this user
if (isset($_GET['delete'])) {
    $filenameToDelete = $_GET['delete'];

    $stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE name = ? AND user_id = ?");
    $stmt->bind_param("si", $filenameToDelete, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $filePath = $uploadDir . $filenameToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $delStmt = $conn->prepare("DELETE FROM uploaded_files WHERE name = ? AND user_id = ?");
        $delStmt->bind_param("si", $filenameToDelete, $user_id);
        $delStmt->execute();
        $delStmt->close();
    }

    $stmt->close();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

// 3. Auto-delete expired files for the current user
$currentTime = date("Y-m-d H:i:s");
$result = $conn->query("SELECT * FROM uploaded_files WHERE user_id = $user_id AND delete_date != '' AND delete_date <= '$currentTime'");

while ($row = $result->fetch_assoc()) {
    $file = $uploadDir . $row['name'];
    if (file_exists($file)) {
        unlink($file);
    }
    $conn->query("DELETE FROM uploaded_files WHERE id = " . $row['id']);
}

// 4. Handle file uploads
if (!is_dir($uploadDir)) {
    mkdir($uploadDir);
}

if (isset($_POST['upload'])) {
    foreach ($_FILES['files']['name'] as $key => $name) {
        if ($_FILES['files']['error'][$key] === 0) {
            $tmpName = $_FILES['files']['tmp_name'][$key];
            $uniqueName = time() . "_" . basename($name);
            move_uploaded_file($tmpName, $uploadDir . $uniqueName);

            $uploadTime = date("Y-m-d H:i:s");

            $deleteDate = $_POST['delete_date'] ?? date("Y-m-d");
            $deleteHour = str_pad($_POST['delete_hour'] ?? '23', 2, "0", STR_PAD_LEFT);
            $deleteMinute = str_pad($_POST['delete_minute'] ?? '59', 2, "0", STR_PAD_LEFT);

            $deleteDateTime = "$deleteDate $deleteHour:$deleteMinute:00";
            
            //if user didn't select anything
            if (empty($deleteDate)) {
                $deleteDate = date("Y-m-d");
            }
            if ($deleteHour === '') {
                $deleteHour = "23";
            }
            if ($deleteMinute === '') {
                $deleteMinute = "59";
            }

            $deleteDateTime = "$deleteDate $deleteHour:$deleteMinute:00";


            $stmt = $conn->prepare("INSERT INTO uploaded_files (name, size, upload_time, delete_date, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sissi", $uniqueName, $_FILES['files']['size'][$key], $uploadTime, $deleteDateTime, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 5. Get files uploaded by the current user
$uploadedFiles = [];
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sql = "SELECT * FROM uploaded_files WHERE user_id = $user_id";
if (!empty($search)) {
    $sql .= " AND name LIKE '%$search%'";
}
$sql .= " ORDER BY id DESC";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $uploadedFiles[] = $row;
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page - FENI</title>
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

        .glass-effect {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .upload-zone {
            background: rgba(255, 255, 255, 0.02);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            transition: all 0.4s ease;
        }

        .upload-zone:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #60a5fa;
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .input-dark {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            color: #e2e8f0;
            transition: all 0.3s ease;
        }

        .input-dark:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        .file-card {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            box-shadow: 8px 8px 24px rgba(0, 0, 0, 0.1), -8px -8px 24px rgba(255, 255, 255, 0.02);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .file-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 12px 12px 32px rgba(0, 0, 0, 0.15), -12px -12px 32px rgba(255, 255, 255, 0.03);
            background: rgba(255, 255, 255, 0.05);
        }

        .progress-ring__circle {
            transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        button:hover {
            transform: translateY(-2px);
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

        /* Neumorphic Effects */
        .neumorphic {
            box-shadow: 8px 8px 16px rgba(0, 0, 0, 0.2), -8px -8px 16px rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .neumorphic:hover {
            box-shadow: 12px 12px 24px rgba(0, 0, 0, 0.25), -12px -12px 24px rgba(255, 255, 255, 0.03);
        }
    </style>
</head>
<body class="bg-gray-900 flex flex-col min-h-screen custom-scrollbar text-gray-100">
<header class="glass-effect fixed w-full z-10 shadow-lg">
    <div class="container mx-auto px-4 py-3 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <button id="toggleSidebar" class="md:hidden text-gray-300 hover:text-blue-400 focus:outline-none transition-colors">
                <i class="fas fa-bars text-xl"></i>
            </button>
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
                        aria-label="Search">
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

        <div class="flex items-center ">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <span class="text-sm font-semibold text-gray-400 ">   Welcome back,</span>
                    <h3 class="text-base font-bold text-gray-100">   <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></h3>
                </div>
            </div>
        <div class="flex items-center ">
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

<div class="main-content flex flex-1 mt-16 flex-col md:flex-row">
    

    <main class="flex-1 p-6 md:p-8 space-y-8">
        <!-- Upload Section -->
        <div class="neumorphic p-8">
            <h2 class="text-2xl font-bold text-gray-100 mb-8 flex items-center">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center mr-4">
                    <i class="fas fa-cloud-upload-alt text-2xl text-blue-400"></i>
                </div>
                Upload Files
            </h2>
                
                <form action="" method="post" enctype="multipart/form-data" class="space-y-8">
                    <div class="flex flex-col md:flex-row md:items-end gap-8">
                        <div class="flex-1">
                            <div class="mt-1">
                                <label class="upload-zone flex flex-col items-center justify-center w-full h-56 px-4">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6 space-y-4">
                                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                                            <i class="fas fa-cloud-upload-alt text-3xl text-blue-400"></i>
                                        </div>
                                        <div class="text-center space-y-2">
                                            <p class="text-lg text-gray-300">
                                                <span class="font-semibold text-blue-400">Click to upload</span> or drag and drop
                                            </p>
                                            <p class="text-sm text-gray-500">Supports multiple files</p>
                                        </div>
                                    </div>
                                    <input type="file" name="files[]" multiple class="hidden" required>
                                </label>
                            </div>
                        </div>
                        
    
                    </div>
                    
                <!-- Auto-delete Settings -->
                <div class="neumorphic p-6 space-y-6">
                    <h3 class="text-lg font-semibold text-blue-400 flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                            <i class="fas fa-clock text-blue-400"></i>
                        </div>
                        <span>Auto-delete Settings</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-300">Date</label>
                                <input type="date" name="delete_date" class="w-full p-3 input-dark">
                            </div>
                           <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-300">Hour</label>
                                <select name="delete_hour" class="w-full p-3 input-dark">
                                    <option value="" class="bg-gray-800 text-gray-400">Select Hour</option>
                                    <?php for ($i = 0; $i < 24; $i++): ?>
                                        <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>"  class="bg-gray-800 text-gray-100">
                                            <?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-300">Minute</label>
                                <select name="delete_minute" class="w-full p-3 input-dark">
                                    <option value="" class="bg-gray-800 text-gray-400">Select Minute</option>
                                    <?php for ($i = 0; $i < 60; $i += 1): ?>
                                        <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>"   class="bg-gray-800 text-gray-100">
                                            <?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="w-full md:w-auto">
                            <button type="submit" name="upload" 
                                class="w-full md:w-auto bg-gradient-to-r from-blue-500 to-indigo-500 
                                hover:from-blue-600 hover:to-indigo-600 text-white font-medium 
                                py-4 px-8 rounded-2xl shadow-lg transition-all duration-300 
                                transform hover:scale-105 hover:shadow-xl flex items-center justify-center 
                                space-x-3">
                                <i class="fas fa-upload text-lg"></i>
                                <span>Upload Now</span>
                            </button>
                        </div>
                </form>
            </div>
            
            <!-- Files Section -->
            <div class="neumorphic p-8">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-100 flex items-center">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center mr-4">
                            <i class="fas fa-folder-open text-2xl text-blue-400"></i>
                        </div>
                        My Files
                    </h2>
                    <div class="text-sm font-medium text-gray-400 bg-white/5 px-4 py-2 rounded-xl">
                        <?= count($uploadedFiles) ?> files
                    </div>
                </div>
                
                <?php if (!empty($uploadedFiles)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach ($uploadedFiles as $file): ?>
                            <div class="file-card">
                                <div class="p-6 space-y-6">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-file text-xl text-blue-400"></i>
                                        </div>
                                        <div class="flex-1 min-w-0 space-y-2">
                                            <p class="text-base font-semibold text-gray-100 truncate">
                                                <?= htmlspecialchars(explode('_', $file['name'], 2)[1]) ?>
                                            </p>
                                            <p class="text-sm text-gray-400">
                                                <?= round($file['size'] / 1024, 2) ?> KB
                                            </p>
                                            <div class="flex items-center text-xs text-gray-500 space-x-2">
                                                <i class="fas fa-clock text-gray-600"></i>
                                                <span><?= $file['upload_time'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="relative w-10 h-10">
                                                <!-- <svg class="progress-ring w-10 h-10" viewBox="0 0 36 36">
                                                    <circle class="progress-ring__circle stroke-white/10" stroke-width="3" fill="none" r="16" cx="18" cy="18"></circle>
                                                    <circle class="progress-ring__circle stroke-blue-400" stroke-width="3" stroke-dasharray="100 100" stroke-dashoffset="<?= 100 - calculateTimePercentage($file['delete_date']) ?>" fill="none" r="16" cx="18" cy="18"></circle>
                                                </svg> -->
                                                <!-- <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-blue-400">
                                                    <?= calculateTimePercentage($file['delete_date']) ?>%
                                                </span> -->
                                            </div>
                                            <span class="text-xs text-gray-400" data-delete="<?= $file['delete_date'] ?>">
                                                <?= formatTimeLeft($file['delete_date']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="flex space-x-4">
                                            <a href="uploads/<?= urlencode($file['name']) ?>" download 
                                                class="text-gray-400 hover:text-blue-400 transition-colors duration-300" 
                                                title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?delete=<?= $file['name'] ?>" 
                                                class="text-gray-400 hover:text-red-400 transition-colors duration-300" 
                                                title="Delete" 
                                                onclick="return confirm('Are you sure you want to delete this file?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
    
                                            <div class="relative group">
                                                <button class="text-gray-400 hover:text-green-400 transition-colors duration-300" title="Share">
                                                    <i class="fas fa-share-alt"></i>
                                                </button>
                                                    

                                                <div class="absolute right-0 bottom-full mb-2 opacity-0 group-hover:opacity-100 
                                                        scale-95 group-hover:scale-100 pointer-events-none group-hover:pointer-events-auto 
                                                        transition-all duration-300 delay-100 neumorphic min-w-[160px] p-2 z-10">
                                                    <a href="#" 
                                                    class="flex items-center px-4 py-3 text-sm text-gray-300 hover:bg-white/5 rounded-xl transition-colors duration-200 space-x-3">
                                                        <i class="fas fa-envelope text-blue-400"></i>
                                                        <span>Email</span>
                                                    </a>
                                                    <a href="https://wa.me/?text=<?= urlencode('Check out this file: ' . 'uploads/' . $file['name']) ?>" 
                                                    target="_blank" 
                                                    class="flex items-center px-4 py-3 text-sm text-gray-300 hover:bg-white/5 rounded-xl transition-colors duration-200 space-x-3">
                                                        <i class="fab fa-whatsapp text-green-400"></i>
                                                        <span>WhatsApp</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 mx-auto mb-8 rounded-2xl bg-white/5 flex items-center justify-center">
                            <i class="fas fa-folder-open text-4xl text-gray-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-300 mb-3">No files uploaded yet</h3>
                        <p class="text-gray-500">Upload your first file using the form above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Toggle Sidebar
    document.getElementById("toggleSidebar").addEventListener("click", function () {
        const sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("sidebar-hidden");
    });

    // Real-time search
    const searchInput = document.getElementById("fileSearchInput");
    const fileCards = document.querySelectorAll(".file-card");

    searchInput.addEventListener("input", function () {
        const searchTerm = this.value.trim().toLowerCase();
        
        fileCards.forEach(card => {
            const fileName = card.querySelector(".text-gray-900").textContent.toLowerCase();
            const fileSize = card.querySelector(".text-gray-500").textContent.toLowerCase();
            
            if (fileName.includes(searchTerm) || fileSize.includes(searchTerm)) {
                card.classList.remove("hidden");
                card.classList.add("animate-pulse");
                setTimeout(() => card.classList.remove("animate-pulse"), 500);
            } else {
                card.classList.add("hidden");
            }
        });
    });

    // Update time left indicators
    function updateTimeIndicators() {
        document.querySelectorAll("[data-delete]").forEach(element => {
            const deleteTime = new Date(element.getAttribute("data-delete")).getTime();
            const now = new Date().getTime();
            const diff = deleteTime - now;
            
            if (diff <= 0) {
                element.textContent = "Expired";
                element.classList.add("text-red-500");
            } else {
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                element.textContent = `${hours}h ${minutes}m left`;
                
                if (hours < 1) {
                    element.classList.add("text-red-500");
                } else if (hours < 24) {
                    element.classList.add("text-yellow-500");
                } else {
                    element.classList.remove("text-red-500", "text-yellow-500");
                }
            }
        });
    }

    // Initialize and update every minute
    updateTimeIndicators();
    setInterval(updateTimeIndicators, 60000);
</script>
</body>
</html>


<?php
// Helper functions
function calculateTimePercentage($deleteDate) {
    if (empty($deleteDate)) return 0;
    
    $deleteTime = strtotime($deleteDate);
    $currentTime = time();
    $uploadTime = strtotime('-24 hours', $deleteTime); // Assuming 24h lifetime
    
    if ($currentTime >= $deleteTime) return 100;
    if ($currentTime <= $uploadTime) return 0;
    
    $total = $deleteTime - $uploadTime;
    $elapsed = $currentTime - $uploadTime;
    
    return min(100, round(($elapsed / $total) * 100));
}

function formatTimeLeft($deleteDate) {
    if (empty($deleteDate)) return "Never expires";
    
    $deleteTime = new DateTime($deleteDate);
    $now = new DateTime();
    
    if ($now >= $deleteTime) return "Expired";
    
    $interval = $now->diff($deleteTime);
    
    if ($interval->d > 0) {
        return $interval->d . "d " . $interval->h . "h left";
    } elseif ($interval->h > 0) {
        return $interval->h . "h " . $interval->i . "m left";
    } else {
        return $interval->i . "m left";
    }
}
?>
