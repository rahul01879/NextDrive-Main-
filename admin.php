<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "rahul123", "project", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT * FROM Admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && $admin['password'] === $password) {
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <style>
            body {
                background-color: #121212; /* Dark background */
                color: #ffffff; /* Light text color */
            }
            .login-box {
                max-width: 400px;
                margin: 100px auto;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
                background-color: #1e1e1e; /* Darker box background */
            }
            .login-box h2 {
                margin-bottom: 20px;
                color: #ffffff; /* Light text color for heading */
            }
            .form-control {
                background-color: #2a2a2a; /* Dark input background */
                color: #ffffff; /* Light text color for input */
                border: 1px solid #444; /* Dark border */
            }
            .form-control::placeholder {
                color: #bbb; /* Placeholder color */
            }
            .btn-primary {
                background-color: #007bff; /* Primary button color */
                border-color: #007bff; /* Button border color */
            }
            .btn-primary:hover {
                background-color: #0056b3; /* Darker button on hover */
                border-color: #0056b3; /* Darker button border on hover */
            }
            .alert {
                background-color: #dc3545; /* Alert background color */
                color: #ffffff; /* Alert text color */
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2 class="text-center">Admin Login</h2>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
            </form>
        </div>
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}
$conn->close();
?>
