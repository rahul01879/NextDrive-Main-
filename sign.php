<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

<div class="w-full max-w-sm p-8 bg-white rounded-2xl shadow-xl">
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Register</h2>

    <form action="" method="post" class="space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" id="name" name="name" required
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" id="username" name="username" required
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" name="email" required
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" id="password" name="password" required
                   class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" name="submit"
                class="w-full py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
            Register
        </button>

        <div class="text-center mt-4">
            <a href="login.php" class="text-blue-600 hover:underline text-sm">Already have an account? Login</a>
        </div>
    </form>

    <?php
    if (isset($_POST['submit'])) {
        // 🔧 Adjust the port to 3307 if needed
        $conn = new mysqli("localhost", "root", "rahul123", "project", 3307);

        if ($conn->connect_error) {
            echo "<p class='text-red-600 text-sm text-center mt-2'>Connection failed: " . $conn->connect_error . "</p>";
        } else {
            $name = $conn->real_escape_string($_POST['name']);
            $username = $conn->real_escape_string($_POST['username']);
            $email = $conn->real_escape_string($_POST['email']); // Capture email
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $registration_date = date("Y-m-d H:i:s"); // Get current date and time

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
                echo "<p class='text-red-600 text-sm text-center mt-2'>Username already registered.</p>";
            } elseif ($checkEmail->num_rows > 0) {
                echo "<p class='text-red-600 text-sm text-center mt-2'>Email already registered.</p>";
            } else {
                // Insert user
                $stmt = $conn->prepare("INSERT INTO users (name, username, email, password, registration_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $name, $username, $email, $password, $registration_date);

                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['name'] = $name;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;

                    header("Location: home.php");
                    exit();
                } else {
                    echo "<p class='text-red-600 text-sm text-center mt-2'>Error: " . $stmt->error . "</p>";
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
