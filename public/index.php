<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 聊天室</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>💬 聊天室</h1>
            <p class="subtitle">实时群聊系统</p>
            
            <form id="loginForm">
                <div class="form-group">
                    <input type="text" id="username" name="username" 
                           placeholder="请输入用户名" required autocomplete="off">
                </div>
                
                <div class="form-group admin-toggle">
                    <label class="switch">
                        <input type="checkbox" id="isAdmin">
                        <span class="slider"></span>
                    </label>
                    <span>管理员登录</span>
                </div>
                
                <div class="form-group admin-password" style="display: none;">
                    <input type="password" id="password" name="password" 
                           placeholder="管理员密码">
                </div>
                
                <button type="submit" class="btn-primary">进入聊天室</button>
            </form>
            
            <div class="login-tip">
                <p>普通用户：输入用户名即可直接进入</p>
                <p>管理员：admin / admin123</p>
            </div>
        </div>
    </div>

    <script>
        const isAdminCheckbox = document.getElementById('isAdmin');
        const adminPassword = document.querySelector('.admin-password');
        const loginForm = document.getElementById('loginForm');
        const usernameInput = document.getElementById('username');

        // 自动聚焦用户名输入
        usernameInput.focus();

        // 切换管理员输入框
        isAdminCheckbox.addEventListener('change', function() {
            if (this.checked) {
                adminPassword.style.display = 'block';
                document.getElementById('password').required = true;
            } else {
                adminPassword.style.display = 'none';
                document.getElementById('password').required = false;
                document.getElementById('password').value = '';
            }
        });

        // 表单提交
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = usernameInput.value.trim();
            const isAdmin = isAdminCheckbox.checked;
            const password = document.getElementById('password').value;

            if (!username) {
                alert('请输入用户名');
                return;
            }

            if (isAdmin && password !== 'admin123') {
                alert('管理员密码错误');
                return;
            }

            // 存储登录信息
            sessionStorage.setItem('chat_username', username);
            sessionStorage.setItem('chat_isAdmin', isAdmin ? '1' : '0');

            // 跳转到聊天页面
            window.location.href = 'chat.php';
        });
    </script>
</body>
</html>
