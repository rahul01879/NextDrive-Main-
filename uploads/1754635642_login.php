<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-full max-w-sm p-8 bg-white rounded-2xl shadow-xl">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Login</h2>

        <form action="" method="post" class="space-y-5">
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

            <button type="submit" name="login"
                class="w-full py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
                Login
            </button>

            <div class="text-center mt-4">
                <a href="sign.php" class="text-blue-600 hover:underline text-sm">Don't have an account? Register</a>
            </div>
        </form>

        <?php
        if (isset($_POST['login'])) {
            // ✅ Using port 3307 here
            $conn = new mysqli("localhost", "root", "rahul123", "project", 3307);

            if ($conn->connect_error) {
                echo "<p class='text-red-600 text-sm text-center mt-2'>Connection failed: " . $conn->connect_error . "</p>";
                exit();
            }

            $email = trim($_POST['email']);
            $password = $_POST['password'];

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // 🔐 Verify password hash
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['name'];

                    header("Location: home.php");
                    exit();
                } else {
                    echo "<p class='text-red-600 text-sm text-center mt-2'>Invalid password.</p>";
                }
            } else {
                echo "<p class='text-red-600 text-sm text-center mt-2'>No user found with this email.</p>";
            }

            $stmt->close();
            $conn->close();
        }
        ?>
    </div>

</body>
</html>
