<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    sleep(1); // Simulation of check
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FindIT Lab | Login</title>
    <style>
        :root { --it-blue: #1e3a8a; --border: #e2e8f0; --text: #1e293b; }
        
        body { 
            margin: 0; 
            font-family: 'Inter', sans-serif; 
            background-color: #ffffff; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            color: var(--text);
        }

        .login-container { 
            width: 100%; 
            max-width: 360px; 
            padding: 20px;
            text-align: center;
        }

        .logo-header { margin-bottom: 40px; }
        .logo-header h1 { margin: 0; font-size: 28px; color: var(--it-blue); font-weight: 800; }
        .logo-header p { margin: 5px 0; color: #64748b; font-size: 14px; letter-spacing: 1px; }

        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase; }

        input { 
            width: 100%; 
            padding: 12px 15px; 
            background: #fff; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 15px; 
            transition: all 0.2s;
        }

        input:focus { outline: none; border-color: var(--it-blue); box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1); }

        button { 
            width: 100%; 
            padding: 14px; 
            background: var(--it-blue); 
            color: white; 
            border: none; 
            border-radius: 6px; 
            font-weight: 600; 
            font-size: 16px;
            cursor: pointer; 
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }

        button:hover { background: #111827; }
        button:disabled { background: #94a3b8; cursor: not-allowed; }

        .error-msg { color: #dc2626; font-size: 13px; margin-bottom: 20px; padding: 10px; border-radius: 4px; background: #fef2f2; border: 1px solid #fee2e2; }

        /* Small Spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            display: none;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .footer-text { margin-top: 30px; font-size: 12px; color: #cbd5e1; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo-header">
            <h1>FindIT Lab</h1>
            <p>Palompon Institute of Technology</p>
        </div>

        <?php if(isset($error)) echo "<div class='error-msg'>$error</div>"; ?>

        <form id="loginForm" method="POST" onsubmit="return showLoad()">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="admin" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" id="submitBtn">
                <div class="spinner" id="loader"></div>
                <span id="btnText">Sign In</span>
            </button>
        </form>

        <div class="footer-text">© 2026 FindIT Internal System</div>
    </div>

    <script>
        function showLoad() {
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('loader').style.display = 'block';
            document.getElementById('btnText').innerText = 'Verifying...';
            return true;
        }
    </script>
</body>
</html>