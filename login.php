<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NextDrive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(120deg, #2b3a67 0%, #1a1f35 100%);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
        }
        .input-dark {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            color: #e2e8f0;
            transition: all 0.3s ease;
        }
        .input-dark:focus {
            background: rgba(255, 255, 255, 0.05);
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }
        .shadow-xl {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="flex items-center justify-center h-screen">

    <div class="w-full max-w-sm p-8 bg-gray-800 rounded-2xl shadow-xl">
        <h2 class="text-2xl font-bold text-center text-white mb-6">Login</h2>

        <form action="" method="post" class="space-y-5">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-300">Username</label>
                <input type="text" id="username" name="username" required
                    class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
                <input type="password" id="password" name="password" required
                    class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
            </div>

            <button type="submit" name="login"
                class="w-full py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
                Login
            </button>

            <div class="text-center mt-4">
                <a href="sign.php" class="text-blue-400 hover:underline text-sm">Don't have an account? Register</a>
            </div>
        </form>

        <?php
        if (isset($_POST['login'])) {
            // Database connection
            // Ensure this matches your actual database configuration
            $conn = new mysqli("localhost", "root", "", "project");

            if ($conn->connect_error) {
                echo "<p class='text-red-400 text-sm text-center mt-2'>Connection failed: " . $conn->connect_error . "</p>";
                exit();
            }

            $username = trim($_POST['username']);
            $password = $_POST['password'];

            // Using prepared statement for security
            $stmt = $conn->prepare("SELECT id, name, username, password, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password hash
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true); // Prevent session fixation
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role']; // Store user role
                    $_SESSION['last_activity'] = time(); // Set last activity for session timeout

                    if (isset($user['role']) && strtolower(trim($user['role'])) === 'admin') {
                        $_SESSION['admin_id'] = $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        header("Location: admin_dashboard.php");
                        exit;
                    }
                    else {
                        header("Location: home.php");
                    }
                    exit();
                } else {
                    echo "<p class='text-red-400 text-sm text-center mt-2'>Invalid password.</p>";
                }
            } else {
                echo "<p class='text-red-400 text-sm text-center mt-2'>No user found with this username.</p>";
            }

            $conn->close();
        }
        ?>
    </div>

</body>
</html>
