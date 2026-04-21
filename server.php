<?php
/**
 * 纯PHP WebSocket 服务器
 * 不需要Composer依赖
 */

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush(true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/User.php';
require_once __DIR__ . '/includes/Message.php';

class ChatServer {
    private $socket;
    private $clients = [];
    private $users = []; // 存储客户端信息: resource => ['username' => '', 'isAdmin' => false]

    public function __construct($host, $port) {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $host, $port);
        socket_listen($this->socket, 5);
        
        echo "===========================================\n";
        echo "  🚀 聊天服务器启动成功！\n";
        echo "  📡 监听地址: ws://{$host}:{$port}\n";
        echo "  👑 管理员账号: admin / admin123\n";
        echo "===========================================\n\n";
    }

    public function run() {
        while (true) {
            $read = array_merge([$this->socket], array_keys($this->clients));
            $write = null;
            $except = null;

            if (socket_select($read, $write, $except, 0) < 1) {
                continue;
            }

            // 新的连接
            if (in_array($this->socket, $read)) {
                $client = socket_accept($this->socket);
                $this->clients[(int)$client] = [
                    'socket' => $client,
                    'handshake' => false
                ];
                
                // 从选择列表中移除服务器socket
                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }

            // 处理现有客户端数据
            foreach ($read as $client) {
                $data = @socket_read($client, 8192, PHP_NORMAL_READ);
                
                if ($data === false || $data === '') {
                    $this->disconnect($client);
                    continue;
                }

                $data = trim($data);
                
                if (!$this->clients[(int)$client]['handshake']) {
                    $this->handshake($client, $data);
                } else {
                    $this->processMessage($client, $data);
                }
            }
        }
    }

    private function handshake($client, $data) {
        if (preg_match('/GET (.*) HTTP/', $data, $matches)) {
            $path = $matches[1];
        } else {
            return;
        }

        // 简单的WebSocket握手
        $headers = [];
        $lines = explode("\r\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        if (!isset($headers['sec-websocket-key'])) {
            return;
        }

        $key = base64_encode(pack('H*', sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$key}\r\n\r\n";

        socket_write($client, $response, strlen($response));
        $this->clients[(int)$client]['handshake'] = true;
        
        // 发送欢迎消息
        $this->send($client, json_encode([
            'type' => 'system',
            'message' => '欢迎连接到聊天服务器！请先登录。',
            'time' => date('H:i:s')
        ]));
    }

    private function processMessage($client, $data) {
        $frame = $this->decodeFrame($data);
        
        if ($frame === false || $frame['opcode'] !== 0x1) {
            return;
        }

        $message = json_decode($frame['payload'], true);
        
        if (!$message || !isset($message['type'])) {
            return;
        }

        switch ($message['type']) {
            case 'login':
                $this->handleLogin($client, $message);
                break;
            case 'message':
                $this->handleChatMessage($client, $message);
                break;
            case 'ping':
                $this->send($client, json_encode(['type' => 'pong']));
                break;
            case 'getUsers':
                $this->sendUserList($client);
                break;
            case 'getHistory':
                $this->sendHistory($client);
                break;
            case 'kick':
                $this->handleKick($client, $message);
                break;
            case 'announcement':
                $this->handleAnnouncement($client, $message);
                break;
            case 'clear':
                $this->handleClear($client);
                break;
        }
    }

    private function handleLogin($client, $message) {
        $username = htmlspecialchars(trim($message['username']));
        $password = $message['password'] ?? '';
        
        if (empty($username)) {
            $this->send($client, json_encode([
                'type' => 'error',
                'message' => '用户名不能为空'
            ]));
            return;
        }

        // 检查是否是管理员登录
        $isAdmin = ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD);
        
        // 存储用户信息
        $this->users[(int)$client] = [
            'username' => $username,
            'isAdmin' => $isAdmin
        ];

        // 通知用户登录成功
        $this->send($client, json_encode([
            'type' => 'loginSuccess',
            'username' => $username,
            'isAdmin' => $isAdmin
        ]));

        // 广播用户加入消息
        $joinMsg = json_encode([
            'type' => 'system',
            'message' => "{$username} 加入了聊天室",
            'time' => date('H:i:s'),
            'userCount' => count($this->users)
        ]);

        // 保存到数据库
        $user = new User();
        $userObj = $user->createUser($username);

        $msg = new Message();
        $msg->saveMessage($username, "{$username} 加入了聊天室", 'system', $userObj['id'] ?? null);

        $this->broadcast($joinMsg, $client);
        
        // 发送当前用户列表给所有人
        $this->broadcastUserList();
        
        echo "[{$username}] 加入了聊天室 " . ($isAdmin ? "(管理员)" : "") . "\n";
    }

    private function handleChatMessage($client, $message) {
        if (!isset($this->users[(int)$client])) {
            return;
        }

        $username = $this->users[(int)$client]['username'];
        $content = htmlspecialchars(trim($message['content']));

        if (empty($content)) {
            return;
        }

        // 保存到数据库
        $user = new User();
        $userObj = $user->getUserByUsername($username);
        
        $msg = new Message();
        $msg->saveMessage($username, $content, 'message', $userObj['id'] ?? null);

        // 广播消息
        $msgData = json_encode([
            'type' => 'message',
            'username' => $username,
            'content' => $content,
            'time' => date('H:i:s'),
            'isAdmin' => $this->users[(int)$client]['isAdmin']
        ]);

        $this->broadcast($msgData);
        echo "[{$username}] {$content}\n";
    }

    private function handleKick($client, $message) {
        if (!isset($this->users[(int)$client]) || !$this->users[(int)$client]['isAdmin']) {
            $this->send($client, json_encode([
                'type' => 'error',
                'message' => '权限不足'
            ]));
            return;
        }

        $targetUsername = $message['username'];
        $targetClient = null;

        foreach ($this->users as $clientId => $user) {
            if ($user['username'] === $targetUsername) {
                $clientIdInt = array_search($clientId, array_keys($this->users));
                $sockets = array_keys($this->clients);
                if (isset($sockets[$clientId])) {
                    $targetClient = $sockets[$clientId];
                }
                break;
            }
        }

        if ($targetClient) {
            $this->send($targetClient, json_encode([
                'type' => 'kicked',
                'message' => '你已被管理员踢出聊天室'
            ]));
            
            $kickMsg = json_encode([
                'type' => 'system',
                'message' => "{$targetUsername} 已被管理员踢出",
                'time' => date('H:i:s')
            ]);
            $this->broadcast($kickMsg);
            
            $this->disconnect($targetClient);
            echo "[管理员] 踢出了 {$targetUsername}\n";
        }
    }

    private function handleAnnouncement($client, $message) {
        if (!isset($this->users[(int)$client]) || !$this->users[(int)$client]['isAdmin']) {
            return;
        }

        $content = htmlspecialchars(trim($message['content']));
        
        if (empty($content)) {
            return;
        }

        // 保存公告到数据库
        $msg = new Message();
        $msg->saveMessage('系统', $content, 'announcement');

        // 广播公告
        $announceMsg = json_encode([
            'type' => 'announcement',
            'content' => $content,
            'time' => date('H:i:s')
        ]);

        $this->broadcast($announceMsg);
        echo "[管理员] 发送公告: {$content}\n";
    }

    private function handleClear($client) {
        if (!isset($this->users[(int)$client]) || !$this->users[(int)$client]['isAdmin']) {
            return;
        }

        // 清空数据库消息
        $msg = new Message();
        $msg->clearMessages();

        // 广播清屏消息
        $this->broadcast(json_encode([
            'type' => 'clear'
        ]));

        echo "[管理员] 清空了聊天记录\n";
    }

    private function sendUserList($client) {
        $userList = [];
        foreach ($this->users as $user) {
            $userList[] = [
                'username' => $user['username'],
                'isAdmin' => $user['isAdmin']
            ];
        }

        $this->send($client, json_encode([
            'type' => 'userList',
            'users' => $userList
        ]));
    }

    private function broadcastUserList() {
        $userList = [];
        foreach ($this->users as $user) {
            $userList[] = [
                'username' => $user['username'],
                'isAdmin' => $user['isAdmin']
            ];
        }

        $this->broadcast(json_encode([
            'type' => 'userList',
            'users' => $userList
        ]));
    }

    private function sendHistory($client) {
        $msg = new Message();
        $messages = $msg->getRecentMessages(MAX_MESSAGES);
        $messages = array_reverse($messages);

        $history = [];
        foreach ($messages as $m) {
            $history[] = [
                'type' => $m['type'],
                'username' => $m['username'],
                'content' => $m['content'],
                'time' => date('H:i:s', strtotime($m['created_at']))
            ];
        }

        $this->send($client, json_encode([
            'type' => 'history',
            'messages' => $history
        ]));
    }

    private function send($client, $message) {
        $frame = $this->encodeFrame($message);
        @socket_write($client, $frame, strlen($frame));
    }

    private function broadcast($message, $exclude = null) {
        foreach ($this->clients as $clientId => $clientData) {
            $client = $clientData['socket'];
            if ($client !== $exclude) {
                $this->send($client, $message);
            }
        }
    }

    private function disconnect($client) {
        $clientId = (int)$client;
        
        if (isset($this->users[$clientId])) {
            $username = $this->users[$clientId]['username'];
            
            $leaveMsg = json_encode([
                'type' => 'system',
                'message' => "{$username} 离开了聊天室",
                'time' => date('H:i:s'),
                'userCount' => count($this->users) - 1
            ]);
            
            // 保存到数据库
            $user = new User();
            $userObj = $user->getUserByUsername($username);
            $msg = new Message();
            $msg->saveMessage($username, "{$username} 离开了聊天室", 'system', $userObj['id'] ?? null);
            
            $this->broadcast($leaveMsg);
            unset($this->users[$clientId]);
            
            $this->broadcastUserList();
            echo "[{$username}] 离开了聊天室\n";
        }

        if (isset($this->clients[$clientId])) {
            socket_close($client);
            unset($this->clients[$clientId]);
        }
    }

    // WebSocket帧解码
    private function decodeFrame($data) {
        $len = strlen($data);
        if ($len < 2) return false;

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);

        $opcode = $firstByte & 0x0F;
        $isMasked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;

        if ($payloadLength === 126) {
            if ($len < 4) return false;
            $payloadLength = (ord($data[2]) << 8) | ord($data[3]);
            $maskStart = 4;
        } elseif ($payloadLength === 127) {
            if ($len < 10) return false;
            $payloadLength = 0;
            for ($i = 0; $i < 8; $i++) {
                $payloadLength = ($payloadLength << 8) | ord($data[2 + $i]);
            }
            $maskStart = 10;
        } else {
            $maskStart = 2;
        }

        if ($len < $maskStart + 4) return false;

        $mask = [];
        if ($isMasked) {
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = ord($data[$maskStart + $i]);
            }
            $maskEnd = $maskStart + 4;
        } else {
            $maskEnd = $maskStart;
        }

        $payload = '';
        $payloadData = substr($data, $maskEnd);
        
        if ($isMasked) {
            for ($i = 0; $i < $payloadLength; $i++) {
                $payload .= chr(ord($payloadData[$i]) ^ $mask[$i % 4]);
            }
        } else {
            $payload = $payloadData;
        }

        return [
            'opcode' => $opcode,
            'payload' => $payload
        ];
    }

    // WebSocket帧编码
    private function encodeFrame($message) {
        $data = $message;
        $length = strlen($data);

        $frame = [];
        $frame[0] = 0x81; // FIN + text frame

        if ($length <= 125) {
            $frame[1] = $length;
            $frame = array_merge($frame, array_map('ord', str_split($data)));
        } elseif ($length <= 65535) {
            $frame[1] = 126;
            $frame[] = ($length >> 8) & 0xFF;
            $frame[] = $length & 0xFF;
            $frame = array_merge($frame, array_map('ord', str_split($data)));
        } else {
            $frame[1] = 127;
            for ($i = 7; $i >= 0; $i--) {
                $frame[] = ($length >> (8 * $i)) & 0xFF;
            }
            $frame = array_merge($frame, array_map('ord', str_split($data)));
        }

        return implode('', array_map('chr', $frame));
    }
}

// 启动服务器
$server = new ChatServer(CHAT_SERVER_HOST, CHAT_SERVER_PORT);
$server->run();
