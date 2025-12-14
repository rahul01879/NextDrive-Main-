<?php
// public/share.php - Place this in your PUBLIC folder

// Database connection
$conn = new mysqli("localhost", "root", "", "project");
if ($conn->connect_error) {
    die("Database connection failed.");
}

// Get token from URL
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("<h3>Invalid file link - No token provided.</h3>");
}

// Fetch file info using the share_token
$stmt = $conn->prepare("SELECT * FROM uploaded_files WHERE share_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();

if (!$file) {
    die("<h3>File not found or link expired.</h3>");
}

// Define upload directory (go up one level from public/)
$uploadDir = dirname(__DIR__) . "/uploads/";
$fullPath = $uploadDir . $file['filename'];

if (!file_exists($fullPath)) {
    die("<h3>File does not exist on server.</h3>");
}

// Get base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'];
$shareLink = $baseUrl . "/FileUpload/public/share.php?token=" . $token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Share - <?= htmlspecialchars($file['original_filename']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #2b3a67 0%, #1a1f35 100%);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">

<div class="glass-effect rounded-2xl p-8 max-w-4xl w-full">
    <h2 class="text-3xl font-bold text-white mb-6 flex items-center">
        <i class="fas fa-share-alt mr-3 text-blue-400"></i>
        Shared File
    </h2>

    <div class="bg-gray-800/50 rounded-xl p-6 mb-6">
        <p class="mb-2"><strong class="text-gray-400">File Name:</strong> 
            <span class="text-white"><?= htmlspecialchars($file['original_filename']) ?></span>
        </p>
        <p class="mb-2"><strong class="text-gray-400">Size:</strong> 
            <span class="text-white"><?= number_format($file['size'] / 1024, 2) ?> KB</span>
        </p>
        <p class="mb-2"><strong class="text-gray-400">Downloads:</strong> 
            <span class="text-white"><?= $file['download_count'] ?></span>
        </p>
    </div>

    <div class="mb-6">
        <label class="block text-gray-400 mb-2"><strong>Share Link:</strong></label>
        <div class="flex gap-2">
            <input type="text" value="<?= htmlspecialchars($shareLink) ?>" id="linkBox" 
                   class="flex-1 px-4 py-2 bg-gray-700 text-white rounded-lg border border-gray-600" readonly>
            <button onclick="copyLink()" 
                    class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition">
                Copy Link
            </button>
        </div>
    </div>

    <div class="flex gap-3 mb-6">
        <button onclick="downloadFile()" 
                class="flex-1 px-6 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg transition font-medium">
            <i class="fas fa-download mr-2"></i>Download
        </button>
    </div>

    <div class="bg-gray-800/50 rounded-xl p-6">
        <h3 class="text-xl font-semibold text-white mb-4">Preview:</h3>
        <div class="bg-gray-900 rounded-lg p-4">
            <?php
                $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                // File URL points to uploads folder (one level up from public/)
                $fileUrl = $baseUrl . "/FileUpload/uploads/" . $file['filename'];

                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    echo "<img src='$fileUrl' class='max-w-full h-auto rounded-lg mx-auto' alt='Preview'>";
                }
                elseif (in_array($ext, ['mp4','webm','ogg'])) {
                    echo "<video controls class='max-w-full h-auto rounded-lg mx-auto'>
                            <source src='$fileUrl' type='video/$ext'>
                          </video>";
                }
                elseif ($ext === 'pdf') {
                    echo "<iframe src='$fileUrl' class='w-full h-[600px] rounded-lg'></iframe>";
                }
                elseif ($ext === 'txt') {
                    $content = htmlspecialchars(file_get_contents($fullPath));
                    echo "<pre class='text-gray-300 whitespace-pre-wrap'>$content</pre>";
                }
                else {
                    echo "<p class='text-gray-400 text-center'>Preview not available for this file type.</p>
                          <a href='$fileUrl' class='text-blue-400 underline block text-center mt-2'>Download to view</a>";
                }
            ?>
        </div>
    </div>
</div>

<script>
function copyLink() {
    let box = document.getElementById("linkBox");
    box.select();
    document.execCommand("copy");
    alert("Link copied to clipboard!");
}

function downloadFile() {
    window.location.href = "<?= $baseUrl ?>/FileUpload/download.php?token=<?= $token ?>";
}
</script>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>