# PHP 实时聊天室

一个基于 WebSocket 的实时群聊系统，带管理员面板。

## 功能特性

实时消息发送与接收
在线用户列表
群聊支持
管理员面板
踢出用户
发送系统公告
清空聊天记录
聊天历史记录

## 原理

- **后端**: 纯 PHP (无需 Composer)
- **协议**: WebSocket
- **数据库**: MySQL

## 目录结构

```
chat/
├── server.php          # WebSocket 服务器
├── config.php          # 配置文件
├── db.sql              # 数据库结构
├── start.bat           # Windows 启动脚本
├── includes/
│   ├── Database.php    # 数据库类
│   ├── User.php        # 用户类
│   └── Message.php     # 消息类
└── public/
    ├── index.php       # 登录页面
    ├── chat.php        # 聊天室
    ├── admin.php       # 管理员登录
    ├── adminpanel.php  # 管理面板
    ├── css/
    │   └── style.css   # 样式
    └── js/
        └── main.js     # 前端脚本
```

## 安装步骤

### 1. 环境要求

- PHP 7.4+
- MySQL 5.6+
- 扩展: pdo, pdo_mysql

### 2. 配置数据库

```sql
-- 导入数据库
mysql -u root -p < db.sql
```

或在 phpMyAdmin 中导入 `db.sql` 文件。

### 3. 修改配置

编辑 `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // 修改为你的数据库密码
```

### 4. 启动服务器

Windows:
```bash
start.bat
```

或直接运行:
```bash
php server.php
```

### 5. 访问聊天室

打开浏览器访问: `http://localhost:8080/public/`

> 注意: 需要使用 PHP 内置服务器或配置 Nginx/Apache

```bash
# 启动 PHP 内置服务器
php -S localhost:8080 -t public
```

## 使用说明

### 普通用户

1. 打开登录页面
2. 输入用户名
3. 点击"进入聊天室"
4. 开始聊天

### 管理员

1. 打开登录页面
2. 勾选"管理员登录"
3. 输入账号: `admin`
4. 输入密码: `admin123`
5. 进入聊天后可点击"管理面板"

## 管理员功能

- 👥 查看所有在线用户
- 🚫 踢出指定用户
- 📢 发送系统公告
- 🧹 清空聊天记录
- 📜 查看聊天历史

## 常见问题

### 连接失败

1. 确认 PHP 服务器正在运行
2. 检查端口 8080 是否被占用
3. 确认防火墙允许该端口

### 数据库连接失败

1. 检查 MySQL 是否运行
2. 确认数据库凭据正确
3. 确保数据库已创建

## 截图

![聊天室界面](screenshot.png)

## License

MIT
