<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NextDrive</title>
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
    <h2 class="text-2xl font-bold text-center text-white mb-6">Register</h2>

    <form action="" method="post" class="space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
            <input type="text" id="name" name="name" required
                   class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
        </div>

        <div>
            <label for="username" class="block text-sm font-medium text-gray-300">Username</label>
            <input type="text" id="username" name="username" required
                   class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-300">Email</label>
            <input type="email" id="email" name="email" required
                   class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 w-full px-4 py-2 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 input-dark">
        </div>

        <button type="submit" name="submit"
                class="w-full py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
            Register
        </button>

        <div class="text-center mt-4">
            <a href="login.php" class="text-blue-400 hover:underline text-sm">Already have an account? Login</a>
        </div>
    </form>

    <?php
    if (isset($_POST['submit'])) {
        // Database connection
        // Ensure this matches your actual database configuration
        $conn = new mysqli("localhost", "root", "", "project");

        if ($conn->connect_error) {
            echo "<p class='text-red-400 text-sm text-center mt-2'>Connection failed: " . $conn->connect_error . "</p>";
        } else {
            $name = $conn->real_escape_string($_POST['name']);
            $username = $conn->real_escape_string($_POST['username']);
            $email = $conn->real_escape_string($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
            $registration_date = date("Y-m-d H:i:s");
            $role = 'user'; // Default role for new registrations

            // Check if username already exists
            $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkUsername->bind_param("s", $username);
            $checkUsername->execute();
            $checkUsername->store_result();

            // Check if email already exists
            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->bind_param("s", $email);
            $checkEmail->execute();
            $checkEmail->store_result();

            if ($checkUsername->num_rows > 0) {
                echo "<p class='text-red-400 text-sm text-center mt-2'>Username already registered.</p>";
            } elseif ($checkEmail->num_rows > 0) {
                echo "<p class='text-red-400 text-sm text-center mt-2'>Email already registered.</p>";
            } else {
                // Insert new user with hashed password and default role
                $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, registration_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $username, $email, $password, $registration_date);

                if ($stmt->execute()) {
                    session_regenerate_id(true); // Prevent session fixation
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['name'] = $name;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;

                    $_SESSION['last_activity'] = time(); // Set last activity for session timeout

                    header("Location: home.php");
                    exit();
                } else {
                    echo "<p class='text-red-400 text-sm text-center mt-2'>Error: " . $stmt->error . "</p>";
                }

                $stmt->close();
            }

            $checkUsername->close();
            $checkEmail->close();
            $conn->close();
        }
    }
    ?>
</div>

</body>
</html>
