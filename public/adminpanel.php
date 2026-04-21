<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理面板</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-page">
    <div class="admin-container">
        <header class="admin-header">
            <h1>👑 管理员面板</h1>
            <div class="header-actions">
                <a href="chat.php" class="btn-link">← 返回聊天</a>
                <button id="logoutBtn" class="btn-logout">退出登录</button>
            </div>
        </header>

        <div class="admin-content">
            <!-- 在线用户管理 -->
            <section class="admin-section">
                <h2>📋 在线用户管理</h2>
                <div class="section-content">
                    <p class="section-info">当前在线: <span id="onlineCount">0</span> 人</p>
                    <ul id="onlineUsers" class="admin-user-list">
                        <!-- 在线用户列表 -->
                    </ul>
                </div>
            </section>

            <!-- 发送公告 -->
            <section class="admin-section">
                <h2>📢 发送公告</h2>
                <div class="section-content">
                    <textarea id="announcementText" placeholder="输入公告内容..." rows="3"></textarea>
                    <button id="sendAnnouncement" class="btn-primary">发送公告</button>
                </div>
            </section>

            <!-- 聊天管理 -->
            <section class="admin-section">
                <h2>🧹 聊天管理</h2>
                <div class="section-content">
                    <div class="action-buttons">
                        <button id="clearHistory" class="btn-danger">清空聊天记录</button>
                        <button id="refreshUsers" class="btn-secondary">刷新用户列表</button>
                    </div>
                </div>
            </section>

            <!-- 聊天记录 -->
            <section class="admin-section">
                <h2>📜 聊天记录</h2>
                <div class="section-content">
                    <div id="chatHistory" class="chat-history">
                        <!-- 聊天记录 -->
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        // 检查管理员权限
        const username = sessionStorage.getItem('chat_username');
        const isAdmin = sessionStorage.getItem('chat_isAdmin') === '1';

        if (!username || !isAdmin) {
            alert('请先登录管理员账号');
            window.location.href = 'admin.php';
        }

        // 初始化WebSocket连接
        let ws;
        const reconnectInterval = 3000;
        let reconnectTimer;

        function connect() {
            ws = new WebSocket('ws://' + window.location.hostname + ':8080');

            ws.onopen = function() {
                console.log('WebSocket连接成功');
                // 登录为管理员
                ws.send(JSON.stringify({
                    type: 'login',
                    username: 'admin',
                    password: 'admin123'
                }));
                
                // 获取用户列表和历史记录
                setTimeout(() => {
                    ws.send(JSON.stringify({ type: 'getUsers' }));
                    ws.send(JSON.stringify({ type: 'getHistory' }));
                }, 500);
            };

            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleMessage(data);
            };

            ws.onclose = function() {
                console.log('WebSocket连接断开');
                reconnectTimer = setTimeout(connect, reconnectInterval);
            };

            ws.onerror = function(error) {
                console.error('WebSocket错误:', error);
            };
        }

        function handleMessage(data) {
            switch (data.type) {
                case 'loginSuccess':
                    if (data.isAdmin) {
                        console.log('管理员登录成功');
                    }
                    break;
                    
                case 'userList':
                    updateOnlineUsers(data.users);
                    break;
                    
                case 'history':
                    updateChatHistory(data.messages);
                    break;
                    
                case 'system':
                case 'message':
                    // 更新聊天记录
                    ws.send(JSON.stringify({ type: 'getHistory' }));
                    break;
            }
        }

        function updateOnlineUsers(users) {
            const list = document.getElementById('onlineUsers');
            const count = document.getElementById('onlineCount');
            
            count.textContent = users.length;
            list.innerHTML = '';

            users.forEach(user => {
                const li = document.createElement('li');
                li.className = 'admin-user-item';
                li.innerHTML = `
                    <span class="user-name">${user.username}${user.isAdmin ? ' (管理员)' : ''}</span>
                    ${!user.isAdmin ? `<button class="btn-kick" data-user="${user.username}">踢出</button>` : ''}
                `;
                list.appendChild(li);
            });

            // 绑定踢出按钮事件
            document.querySelectorAll('.btn-kick').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetUser = this.getAttribute('data-user');
                    if (confirm(`确定要踢出用户 ${targetUser} 吗？`)) {
                        ws.send(JSON.stringify({
                            type: 'kick',
                            username: targetUser
                        }));
                    }
                });
            });
        }

        function updateChatHistory(messages) {
            const container = document.getElementById('chatHistory');
            container.innerHTML = '';

            messages.slice(-50).forEach(msg => {
                const div = document.createElement('div');
                div.className = 'history-item';
                
                if (msg.type === 'system' || msg.type === 'announcement') {
                    div.classList.add('system-msg');
                    div.innerHTML = `<span class="msg-content">${msg.content || msg.message}</span>`;
                } else {
                    div.innerHTML = `<span class="msg-user">${msg.username}:</span> <span class="msg-content">${msg.content}</span>`;
                }
                
                div.innerHTML += ` <span class="msg-time">${msg.time}</span>`;
                container.appendChild(div);
            });

            container.scrollTop = container.scrollHeight;
        }

        // 发送公告
        document.getElementById('sendAnnouncement').addEventListener('click', function() {
            const text = document.getElementById('announcementText').value.trim();
            if (!text) {
                alert('请输入公告内容');
                return;
            }
            
            ws.send(JSON.stringify({
                type: 'announcement',
                content: text
            }));
            
            document.getElementById('announcementText').value = '';
            alert('公告已发送');
        });

        // 清空聊天记录
        document.getElementById('clearHistory').addEventListener('click', function() {
            if (confirm('确定要清空所有聊天记录吗？')) {
                ws.send(JSON.stringify({ type: 'clear' }));
                setTimeout(() => {
                    document.getElementById('chatHistory').innerHTML = '';
                    alert('聊天记录已清空');
                }, 500);
            }
        });

        // 刷新用户列表
        document.getElementById('refreshUsers').addEventListener('click', function() {
            ws.send(JSON.stringify({ type: 'getUsers' }));
        });

        // 退出登录
        document.getElementById('logoutBtn').addEventListener('click', function() {
            sessionStorage.clear();
            window.location.href = 'index.php';
        });

        // 页面加载时连接
        connect();
    </script>
</body>
</html>
