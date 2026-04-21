# 实时群聊网站 - 技术规范

## 1. 项目概述

- **项目名称**: PHP Realtime Chat
- **项目类型**: 实时通讯网站
- **核心功能**: 支持群聊、实时消息推送、管理员面板
- **目标用户**: 需要内部沟通的团队/社区

## 2. 技术架构

### 后端
- PHP 7.4+ (WebSocket服务器)
- Ratchet WebSocket库
- MySQL数据库

### 前端
- HTML5 + CSS3 + Vanilla JavaScript
- WebSocket客户端

### 目录结构
```
chat/
├── server.php          # WebSocket服务器入口
├── config.php          # 配置文件
├── db.sql              # 数据库结构
├── includes/
│   ├── Database.php    # 数据库类
│   ├── User.php        # 用户类
│   └── Message.php     # 消息类
├── public/
│   ├── index.php       # 登录页
│   ├── chat.php        # 聊天页面
│   ├── admin.php       # 管理员登录
│   ├── adminpanel.php  # 管理面板
│   ├── css/
│   │   └── style.css   # 样式
│   └── js/
│       └── main.js     # 前端脚本
└── vendor/             # Composer依赖
```

## 3. 功能规范

### 3.1 用户系统
- 用户名登录（无需密码，简化体验）
- 区分普通用户和管理员
- 在线状态显示
- 用户列表展示

### 3.2 群聊功能
- 实时消息发送/接收
- 消息时间戳
- 消息类型：文本
- 新消息自动滚动
- 消息历史记录（最近100条）

### 3.3 管理员功能
- 登录验证（管理员账号密码）
- 查看所有在线用户
- 踢出用户
- 发送系统公告
- 查看聊天历史
- 清屏功能

## 4. 数据库设计

### users表
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### messages表
```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('message', 'system', 'announcement') DEFAULT 'message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 5. 界面设计

### 用户端
- 简洁的深色主题
- 左侧：在线用户列表
- 中间：消息区域
- 底部：输入框 + 发送按钮
- 右下角：在线人数统计

### 管理端
- 数据表格展示
- 操作按钮：踢出、禁言等
- 公告发布表单

## 6. 默认账号

- 管理员: admin / admin123
- 普通用户: 任意用户名登录

## 7. 验收标准

1. ✅ 用户可以输入用户名进入聊天室
2. ✅ 消息实时发送和接收（无刷新）
3. ✅ 在线用户列表实时更新
4. ✅ 管理员可以登录管理面板
5. ✅ 管理员可以踢出用户
6. ✅ 管理员可以发送公告
7. ✅ 页面美观，交互流畅
