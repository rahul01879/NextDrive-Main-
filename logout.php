<?php
// logout.php - Admin Logout Page

// Start session and destroy it
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: admin.php");
exit();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging Out...</title>
    <!-- Optional: Add a loading spinner or progress bar -->
    <style>
        body {
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .logout-container {
            text-align: center;
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .spinner {
            margin: 0 auto;
            width: 50px;
            height: 50px;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="spinner"></div>
        <h1 class="text-xl font-semibold mt-4">Logging Out...</h1>
        <p class="text-gray-600">You are being redirected to the login page</p>
    </div>
    <!-- JavaScript fallback redirect in case header fails -->
    <script>
        setTimeout(function() {
            window.location.href = "admin_login.php";
        }, 2000);
    </script>
</body>
</html>
