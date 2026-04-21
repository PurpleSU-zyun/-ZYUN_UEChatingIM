<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box admin-login">
            <div class="admin-icon">👑</div>
            <h1>管理员登录</h1>
            
            <form id="adminLoginForm">
                <div class="form-group">
                    <input type="text" id="username" name="username" 
                           placeholder="管理员用户名" value="admin" required>
                </div>
                
                <div class="form-group">
                    <input type="password" id="password" name="password" 
                           placeholder="管理员密码" required>
                </div>
                
                <button type="submit" class="btn-primary btn-admin">进入管理面板</button>
            </form>
            
            <div class="login-tip">
                <a href="index.php">← 返回聊天</a>
            </div>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('adminLoginForm');

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (username === 'admin' && password === 'admin123') {
                sessionStorage.setItem('chat_username', 'admin');
                sessionStorage.setItem('chat_isAdmin', '1');
                window.location.href = 'adminpanel.php';
            } else {
                alert('管理员账号或密码错误');
            }
        });
    </script>
</body>
</html>
