# IM 即时通讯系统

一个基于 PHP WebSocket 的即时通讯系统，无需 Node.js，部署极简。
![示例图片1](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/1.png)
![示例图片2](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/2.png)
![示例图片3](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/3.png)
![示例图片4](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/4.png)
![示例图片5](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/5.png)
![示例图片6](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/6.png)
![示例图片7](https://github.com/PurpleSU-zyun/-ZYUN_UEChatingIM/blob/main/%E7%A4%BA%E4%BE%8B%E5%9B%BE/7.png)

## 快速启动

**双击 `启动服务器.bat` 即可！**

脚本会自动：
1. 检测 PHP 是否安装
2. 启动 WebSocket 服务器（端口 8080）
3. 自动在浏览器中打开 `index.html`

---

## 系统要求

只需安装 **PHP 7.4+**，推荐方案：

| 方式 | 说明 |
|------|------|
| **XAMPP**（推荐） | 下载 https://www.apachefriends.org/ 安装后自带PHP |
| **独立PHP** | 下载 https://windows.php.net/download/ 解压后配置PATH |

---

## 功能列表

### 聊天功能
- ✅ 用户注册登录（无需数据库，昵称即账户）
- ✅ 公共大厅（所有人可见）
- ✅ 私信（点击用户名即可私聊）
- ✅ 群组聊天（支持多群组）
- ✅ 消息历史（保留最近100条）
- ✅ 未读消息角标提示
- ✅ 断线自动重连

### 管理员面板
- ✅ **踢出用户**（立即断开连接）
- ✅ **禁言 / 解除禁言**
- ✅ **封禁账号**（封禁后该昵称无法登录）
- ✅ **解封账号**
- ✅ **创建群组**
- ✅ **解散群组**
- ✅ **邀请用户加入群组**
- ✅ **全体公告**（醒目展示给所有人）

---

## 管理员登录

在登录页「管理员密码」输入框中填入密码即可获得管理员权限。

**默认密码**：`admin123`

> ⚠️ 正式使用前，请修改 `server.php` 第5行的 `ADMIN_PASSWORD` 常量！

---

## 文件结构

```
im-chat/
├── server.php      # PHP WebSocket 服务端
├── index.html      # 前端聊天页面（含管理员面板）
├── 启动服务器.bat  # 一键启动脚本
└── README.md       # 本文档
```

---

## 修改配置

编辑 `server.php` 顶部：

```php
define('HOST', '0.0.0.0');       // 监听地址（0.0.0.0 表示所有网卡）
define('PORT', 8080);             // 端口号
define('ADMIN_PASSWORD', 'admin123'); // 管理员密码（请修改！）
```

如果修改了端口，还需在 `index.html` 中搜索 `ws://127.0.0.1:8080` 并同步修改。

---

## 局域网多人使用

1. 启动服务器后，查看本机 IP（运行 `ipconfig`）
2. 将 `index.html` 中的 `ws://127.0.0.1:8080` 改为 `ws://你的IP:8080`
3. 其他人用浏览器打开你机器上的 `index.html` 即可加入
