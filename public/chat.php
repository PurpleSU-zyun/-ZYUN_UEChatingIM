<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聊天室</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="chat-container">
        <!-- 左侧：用户列表 -->
        <aside class="user-sidebar">
            <div class="sidebar-header">
                <h3>👥 在线用户</h3>
                <span id="userCount" class="user-count">0</span>
            </div>
            <ul id="userList" class="user-list">
                <!-- 用户列表将通过JS动态生成 -->
            </ul>
        </aside>

        <!-- 中间：聊天区域 -->
        <main class="chat-main">
            <header class="chat-header">
                <h2>💬 聊天室</h2>
                <div class="header-info">
                    <span id="currentUser" class="current-user"></span>
                    <a href="admin.php" id="adminLink" class="admin-link" style="display: none;">管理面板</a>
                    <button id="logoutBtn" class="btn-logout">退出</button>
                </div>
            </header>

            <div id="messages" class="messages-container">
                <!-- 消息将通过WebSocket动态接收 -->
            </div>

            <footer class="chat-footer">
                <input type="text" id="messageInput" 
                       placeholder="输入消息..." autocomplete="off">
                <button id="sendBtn" class="btn-send">发送</button>
            </footer>
        </main>
    </div>

    <script src="js/main.js"></script>
    <script>
        // 检查登录状态
        const username = sessionStorage.getItem('chat_username');
        const isAdmin = sessionStorage.getItem('chat_isAdmin') === '1';

        if (!username) {
            window.location.href = 'index.php';
        }

        // 显示当前用户
        document.getElementById('currentUser').textContent = username + (isAdmin ? ' (管理员)' : '');
        
        // 显示管理面板链接
        if (isAdmin) {
            document.getElementById('adminLink').style.display = 'inline-block';
        }

        // 退出登录
        document.getElementById('logoutBtn').addEventListener('click', function() {
            sessionStorage.clear();
            window.location.href = 'index.php';
        });

        // 初始化WebSocket连接
        initChat('ws://' + window.location.hostname + ':8080', username, isAdmin);
    </script>
</body>
</html>
