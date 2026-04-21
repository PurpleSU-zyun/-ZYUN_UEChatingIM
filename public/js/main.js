/**
 * 聊天室前端脚本
 */

let ws;
let username;
let isAdmin;
let reconnectTimer;
const reconnectInterval = 3000;

// 初始化聊天
function initChat(wsUrl, user, admin) {
    username = user;
    isAdmin = admin;

    // 检查WebSocket支持
    if (!window.WebSocket) {
        alert('您的浏览器不支持WebSocket，请使用现代浏览器');
        return;
    }

    connect(wsUrl);
}

// 连接WebSocket
function connect(wsUrl) {
    ws = new WebSocket(wsUrl);

    ws.onopen = function() {
        console.log('WebSocket连接成功');
        addSystemMessage('连接成功，正在登录...');
        
        // 发送登录消息
        ws.send(JSON.stringify({
            type: 'login',
            username: username,
            password: isAdmin ? 'admin123' : ''
        }));
    };

    ws.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            handleMessage(data);
        } catch (e) {
            console.error('消息解析错误:', e);
        }
    };

    ws.onclose = function() {
        console.log('WebSocket连接断开');
        addSystemMessage('连接断开，正在重连...');
        
        // 自动重连
        reconnectTimer = setTimeout(function() {
            connect(wsUrl);
        }, reconnectInterval);
    };

    ws.onerror = function(error) {
        console.error('WebSocket错误:', error);
    };
}

// 处理消息
function handleMessage(data) {
    switch (data.type) {
        case 'loginSuccess':
            handleLoginSuccess(data);
            break;
            
        case 'error':
            handleError(data);
            break;
            
        case 'system':
            handleSystemMessage(data);
            break;
            
        case 'message':
            handleChatMessage(data);
            break;
            
        case 'announcement':
            handleAnnouncement(data);
            break;
            
        case 'userList':
            handleUserList(data);
            break;
            
        case 'history':
            handleHistory(data);
            break;
            
        case 'kicked':
            handleKicked(data);
            break;
            
        case 'clear':
            handleClear();
            break;
            
        case 'pong':
            // 心跳响应
            break;
    }
}

// 登录成功
function handleLoginSuccess(data) {
    addSystemMessage('登录成功！欢迎 ' + data.username);
    
    // 获取历史消息和用户列表
    setTimeout(function() {
        ws.send(JSON.stringify({ type: 'getHistory' }));
        ws.send(JSON.stringify({ type: 'getUsers' }));
    }, 300);
}

// 错误消息
function handleError(data) {
    alert(data.message);
}

// 系统消息
function handleSystemMessage(data) {
    addSystemMessage(data.message);
    
    if (data.userCount !== undefined) {
        document.getElementById('userCount').textContent = data.userCount;
    }
}

// 聊天消息
function handleChatMessage(data) {
    const messages = document.getElementById('messages');
    const div = document.createElement('div');
    div.className = 'message';
    
    const firstChar = data.username.charAt(0).toUpperCase();
    const isAdminClass = data.isAdmin ? 'admin' : '';
    
    div.innerHTML = `
        <div class="message-avatar">${firstChar}</div>
        <div class="message-content">
            <div class="message-header">
                <span class="message-username ${isAdminClass}">${escapeHtml(data.username)}${data.isAdmin ? ' (管理员)' : ''}</span>
                <span class="message-time">${data.time}</span>
            </div>
            <div class="message-text">${escapeHtml(data.content)}</div>
        </div>
    `;
    
    messages.appendChild(div);
    scrollToBottom();
}

// 公告
function handleAnnouncement(data) {
    const messages = document.getElementById('messages');
    const div = document.createElement('div');
    div.className = 'message announcement';
    div.innerHTML = `
        <div class="message-content">
            <div class="message-header">
                <span class="message-username">📢 系统公告</span>
                <span class="message-time">${data.time}</span>
            </div>
            <div class="message-text">${escapeHtml(data.content)}</div>
        </div>
    `;
    
    messages.appendChild(div);
    scrollToBottom();
}

// 用户列表
function handleUserList(data) {
    const list = document.getElementById('userList');
    const count = document.getElementById('userCount');
    
    list.innerHTML = '';
    count.textContent = data.users.length;
    
    data.users.forEach(function(user) {
        const li = document.createElement('li');
        li.className = user.isAdmin ? 'admin' : '';
        li.innerHTML = `
            <span class="user-status"></span>
            <span class="user-name">${escapeHtml(user.username)}${user.isAdmin ? ' (管理员)' : ''}</span>
        `;
        list.appendChild(li);
    });
}

// 历史消息
function handleHistory(data) {
    const messages = document.getElementById('messages');
    messages.innerHTML = '';
    
    data.messages.forEach(function(msg) {
        if (msg.type === 'system') {
            addSystemMessage(msg.message || msg.content);
        } else if (msg.type === 'announcement') {
            const div = document.createElement('div');
            div.className = 'message announcement';
            div.innerHTML = `
                <div class="message-content">
                    <div class="message-header">
                        <span class="message-username">📢 系统公告</span>
                        <span class="message-time">${msg.time}</span>
                    </div>
                    <div class="message-text">${escapeHtml(msg.content)}</div>
                </div>
            `;
            messages.appendChild(div);
        } else {
            const div = document.createElement('div');
            div.className = 'message';
            div.innerHTML = `
                <div class="message-avatar">${msg.username.charAt(0).toUpperCase()}</div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="message-username">${escapeHtml(msg.username)}</span>
                        <span class="message-time">${msg.time}</span>
                    </div>
                    <div class="message-text">${escapeHtml(msg.content)}</div>
                </div>
            `;
            messages.appendChild(div);
        }
    });
    
    scrollToBottom();
}

// 被踢出
function handleKicked(data) {
    alert(data.message);
    sessionStorage.clear();
    window.location.href = 'index.php';
}

// 清屏
function handleClear() {
    document.getElementById('messages').innerHTML = '';
}

// 添加系统消息
function addSystemMessage(text) {
    const messages = document.getElementById('messages');
    const div = document.createElement('div');
    div.className = 'message system';
    div.innerHTML = `<span class="message-text">${escapeHtml(text)}</span>`;
    messages.appendChild(div);
    scrollToBottom();
}

// 滚动到底部
function scrollToBottom() {
    const messages = document.getElementById('messages');
    messages.scrollTop = messages.scrollHeight;
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 发送消息
function sendMessage() {
    const input = document.getElementById('messageInput');
    const content = input.value.trim();
    
    if (!content) {
        return;
    }
    
    if (!ws || ws.readyState !== WebSocket.OPEN) {
        alert('连接已断开，请刷新页面');
        return;
    }
    
    ws.send(JSON.stringify({
        type: 'message',
        content: content
    }));
    
    input.value = '';
    input.focus();
}

// 绑定发送按钮和输入框事件
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    
    if (messageInput && sendBtn) {
        // 点击发送按钮
        sendBtn.addEventListener('click', sendMessage);
        
        // 按下回车发送
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // 心跳保活
    setInterval(function() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ type: 'ping' }));
        }
    }, 30000);
});

// 页面卸载时关闭连接
window.addEventListener('beforeunload', function() {
    if (ws) {
        ws.close();
    }
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
    }
});
