<?php
/**
 * Admin Login Page
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && $_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

require_once '../db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check admin credentials
        $stmt = $conn->prepare("SELECT id, username, password_hash, first_name, last_name FROM admin_users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($admin = $result->fetch_assoc()) {
            if (password_verify($password, $admin['password_hash'])) {
                // Login successful
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['is_admin'] = true;
                
                // Update last login
                $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FreeReminders.net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #0066CC;
            --primary-dark: #004494;
            --white: #FFFFFF;
            --black: #000000;
            --dark-grey: #333333;
            --light-grey: #F5F5F5;
            --mid-grey: #999999;
            --border-color: #E0E0E0;
            --error-color: #CC3300;
            --admin-primary: #2c3e50;
            --admin-secondary: #34495e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .admin-logo {
            font-size: 3rem;
            color: var(--admin-primary);
            margin-bottom: 10px;
        }

        .login-title {
            font-size: 1.8rem;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .login-subtitle {
            color: var(--mid-grey);
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--dark-grey);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--admin-primary);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background-color: var(--admin-primary);
            color: var(--white);
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .login-btn:hover {
            background-color: var(--admin-secondary);
        }

        .error-message {
            background-color: #ffebee;
            color: var(--error-color);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid var(--error-color);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: var(--mid-grey);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link a:hover {
            color: var(--admin-primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="admin-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Admin Panel</h1>
            <p class="login-subtitle">FreeReminders.net Administration</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Login to Admin Panel
            </button>
        </form>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
        </div>
    </div>
</body>
</html>