<?php
require_once 'includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['register'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $team_name = isset($_POST['team_name']) ? $_POST['team_name'] : '';

        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, team_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $team_name);
            if ($stmt->execute()) {
                $success = "Registration successful! Please login.";
            } else {
                $error = "Error during registration.";
            }
        }
    } elseif (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: team/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "User not found!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPL Auction - Authenticate</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-container animate-fade-in">
        <div class="auth-header">
            <h1>IPL AUCTION</h1>
            <p>Enter the arena of legends</p>
        </div>

        <div class="glass" style="padding: 30px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="name@ipl.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" name="login" class="btn-premium">Login to Dashboard</button>
                <div class="auth-toggle">
                    Don't have an account? <a href="#" onclick="toggleAuth(true)">Register Team</a>
                </div>
            </form>

            <!-- Register Form (Hidden by default) -->
            <form id="registerForm" method="POST" action="" style="display: none;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Owner Name">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" required placeholder="team@ipl.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control" onchange="toggleTeamField(this.value)">
                        <option value="team">Team Owner</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-group" id="teamField">
                    <label>Team Name</label>
                    <input type="text" name="team_name" class="form-control" placeholder="e.g. Mumbai Indians">
                </div>
                <button type="submit" name="register" class="btn-premium">Create Account</button>
                <div class="auth-toggle">
                    Already registered? <a href="#" onclick="toggleAuth(false)">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleAuth(showRegister) {
            document.getElementById('loginForm').style.display = showRegister ? 'none' : 'block';
            document.getElementById('registerForm').style.display = showRegister ? 'block' : 'none';
        }

        function toggleTeamField(role) {
            document.getElementById('teamField').style.display = (role === 'team') ? 'block' : 'none';
        }
    </script>
</body>
</html>
