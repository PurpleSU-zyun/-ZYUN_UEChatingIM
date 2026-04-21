<?php
/**
 * IM即时通讯 WebSocket服务器
 * 运行方式: php server.php
 * 默认端口: 8080
 */

define('HOST', '0.0.0.0');
define('PORT', 9100);
define('ADMIN_PASSWORD', '13202608528Xian_'); // 管理员密码，请修改

define('DATA_DIR',   __DIR__ . '/data/');
define('MSG_FILE',   DATA_DIR . 'messages.json');
define('BAN_FILE',   DATA_DIR . 'banned.json');
define('IP_BAN_FILE',DATA_DIR . 'banned_ips.json');  // IP封禁列表
define('USERS_FILE', DATA_DIR . 'users.json');   // 用户账号库

define('MAX_ACCOUNTS_PER_IP', 3); // 每个IP最多注册账号数

// 确保数据目录存在
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// 从文件加载持久化数据
function loadJson($file, $default) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return $default;
}
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// 数据存储
$clients = [];      // [socketId => ['socket', 'username', 'isAdmin', 'isMuted', 'ip']]
$groups = [
    'public' => ['name' => '公共大厅', 'members' => [], 'owner' => 'system'],
];
$messageHistory = loadJson(MSG_FILE, []);
$bannedUsers    = loadJson(BAN_FILE, []);
$bannedIps      = loadJson(IP_BAN_FILE, []);   // IP封禁列表: ['1.2.3.4', ...]
// 用户账号库: [ username => ['passwordHash'=>'...', 'ip'=>'...', 'registeredAt'=>'...'] ]
$userAccounts   = loadJson(USERS_FILE, []);

echo "已加载历史消息 " . count($messageHistory) . " 条，封禁用户 " . count($bannedUsers) . " 个，封禁IP " . count($bannedIps) . " 个，注册账号 " . count($userAccounts) . " 个\n";

// 启动服务器
echo "===========================================\n";
echo "  IM即时通讯服务器 启动中...\n";
echo "  监听地址: ws://" . HOST . ":" . PORT . "\n";
echo "  管理员密码: " . ADMIN_PASSWORD . "\n";
echo "===========================================\n";

$server = stream_socket_server("tcp://" . HOST . ":" . PORT, $errno, $errstr);
if (!$server) {
    die("启动失败: $errstr ($errno)\n");
}

stream_set_blocking($server, false);
echo "服务器启动成功！等待连接...\n\n";

$socketList = [$server];

while (true) {
    $read = $socketList;
    $write = null;
    $except = null;

    if (stream_select($read, $write, $except, 0, 200000) === false) {
        break;
    }

    foreach ($read as $sock) {
        if ($sock === $server) {
            $client = stream_socket_accept($server, 0);
            if ($client) {
                stream_set_blocking($client, false);
                $socketList[] = $client;
                $id = (int)$client;
                $clients[$id] = [
                    'socket'    => $client,
                    'username'  => null,
                    'isAdmin'   => false,
                    'isMuted'   => false,
                    'ip'        => stream_socket_get_name($client, true),
                    'handshake' => false,
                ];
                // 只取IP部分，去掉端口
                $rawIp = $clients[$id]['ip'];
                $clients[$id]['ipOnly'] = preg_replace('/:\d+$/', '', $rawIp);
                echo "[连接] 新连接 ID=$id IP=" . $clients[$id]['ipOnly'] . "\n";
            }
        } else {
            $id = (int)$sock;
            $data = fread($sock, 65536);

            if ($data === false || $data === '') {
                disconnectClient($sock, $id, $socketList, $clients, $groups);
                continue;
            }

            if (!$clients[$id]['handshake']) {
                if (doHandshake($sock, $data)) {
                    $clients[$id]['handshake'] = true;
                    echo "[握手] ID=$id 握手成功\n";
                } else {
                    fclose($sock);
                    unset($clients[$id]);
                    $key = array_search($sock, $socketList);
                    if ($key !== false) unset($socketList[$key]);
                }
            } else {
                $decoded = decodeFrame($data);
                if ($decoded === false || $decoded === null) {
                    disconnectClient($sock, $id, $socketList, $clients, $groups);
                    continue;
                }
                if ($decoded === '') continue;

                $msg = json_decode($decoded, true);
                if ($msg) {
                    handleMessage($id, $msg, $clients, $groups, $messageHistory, $bannedUsers, $bannedIps, $userAccounts, $socketList);
                }
            }
        }
    }
}

fclose($server);

// ===== WebSocket握手 =====
function doHandshake($socket, $data) {
    if (preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $data, $matches)) {
        $key = trim($matches[1]);
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $response = "HTTP/1.1 101 Switching Protocols\r\n"
                  . "Upgrade: websocket\r\n"
                  . "Connection: Upgrade\r\n"
                  . "Sec-WebSocket-Accept: $accept\r\n\r\n";
        fwrite($socket, $response);
        return true;
    }
    return false;
}

// ===== 解码WebSocket帧 =====
function decodeFrame($data) {
    if (strlen($data) < 2) return false;
    $b1 = ord($data[0]);
    $b2 = ord($data[1]);
    $opcode = $b1 & 0x0F;

    if ($opcode === 0x8) return false;
    if ($opcode === 0x9) return '';
    if ($opcode === 0xA) return '';

    $masked = ($b2 & 0x80) !== 0;
    $len = $b2 & 0x7F;
    $offset = 2;

    if ($len === 126) {
        if (strlen($data) < 4) return false;
        $len = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($len === 127) {
        if (strlen($data) < 10) return false;
        $len = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }

    if ($masked) {
        if (strlen($data) < $offset + 4 + $len) return false;
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $len);
        $decoded = '';
        for ($i = 0; $i < $len; $i++) {
            $decoded .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        return $decoded;
    } else {
        return substr($data, $offset, $len);
    }
}

// ===== 编码WebSocket帧 =====
function encodeFrame($data) {
    $len = strlen($data);
    if ($len <= 125) {
        return chr(0x81) . chr($len) . $data;
    } elseif ($len <= 65535) {
        return chr(0x81) . chr(126) . pack('n', $len) . $data;
    } else {
        return chr(0x81) . chr(127) . pack('J', $len) . $data;
    }
}

// ===== 发送消息给指定客户端 =====
function sendTo($socket, $data) {
    $frame = encodeFrame(json_encode($data, JSON_UNESCAPED_UNICODE));
    @fwrite($socket, $frame);
}

// ===== 广播给所有已登录用户 =====
function broadcast($data, &$clients, $excludeId = null) {
    foreach ($clients as $id => $client) {
        if ($client['username'] && $id !== $excludeId) {
            sendTo($client['socket'], $data);
        }
    }
}

// ===== 广播给群组成员 =====
function broadcastGroup($groupId, $data, &$clients, &$groups) {
    if (!isset($groups[$groupId])) return;
    foreach ($groups[$groupId]['members'] as $username) {
        foreach ($clients as $id => $client) {
            if ($client['username'] === $username) {
                sendTo($client['socket'], $data);
            }
        }
    }
}

// ===== 断开客户端连接 =====
function disconnectClient($sock, $id, &$socketList, &$clients, &$groups) {
    $username = $clients[$id]['username'] ?? null;
    fclose($sock);
    $key = array_search($sock, $socketList);
    if ($key !== false) unset($socketList[$key]);

    if ($username) {
        foreach ($groups as $gid => &$group) {
            $group['members'] = array_values(array_filter($group['members'], fn($m) => $m !== $username));
        }
        echo "[下线] $username (ID=$id)\n";
        broadcast(['type' => 'user_offline', 'username' => $username], $clients, $id);
        broadcast(['type' => 'system', 'message' => "「$username」已离线"], $clients, $id);
    }
    unset($clients[$id]);
}

// ===== 获取在线用户列表 =====
function getOnlineUsers(&$clients) {
    $users = [];
    foreach ($clients as $id => $c) {
        if ($c['username']) {
            $users[] = ['username' => $c['username'], 'isAdmin' => $c['isAdmin'], 'isMuted' => $c['isMuted']];
        }
    }
    return $users;
}

// ===== 统计某IP已注册账号数 =====
function countAccountsByIp($ip, &$userAccounts) {
    $count = 0;
    foreach ($userAccounts as $acc) {
        if (($acc['ip'] ?? '') === $ip) $count++;
    }
    return $count;
}

// ===== 主消息处理 =====
function handleMessage($id, $msg, &$clients, &$groups, &$messageHistory, &$bannedUsers, &$bannedIps, &$userAccounts, &$socketList) {
    $type = $msg['type'] ?? '';

    switch ($type) {

        // ---------- 登录/注册 ----------
        case 'login':
            $username = trim($msg['username'] ?? '');
            $password = $msg['password'] ?? '';       // 用户密码
            $adminPass = $msg['adminPassword'] ?? '';  // 管理员密码（可选）
            $isRegister = (bool)($msg['register'] ?? false);

            // 基本校验
            if (empty($username) || strlen($username) > 20) {
                sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '用户名无效（1-20字符）']);
                return;
            }
            if (!preg_match('/^[\w\x{4e00}-\x{9fa5}]+$/u', $username)) {
                sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '用户名只能包含字母数字汉字下划线']);
                return;
            }
            if (empty($password)) {
                sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '密码不能为空']);
                return;
            }
            if (in_array($username, $bannedUsers)) {
                sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '该用户名已被封禁']);
                return;
            }

            $clientIp = $clients[$id]['ipOnly'];

            // IP封禁检查
            if (in_array($clientIp, $bannedIps)) {
                sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '您的IP已被封禁，无法登录']);
                return;
            }

            $userExists = isset($userAccounts[$username]);

            if ($isRegister) {
                // ---- 注册流程 ----
                if ($userExists) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '该用户名已被注册，请直接登录']);
                    return;
                }
                if (strlen($password) < 4) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '密码至少4位']);
                    return;
                }
                // IP注册数量限制
                $ipCount = countAccountsByIp($clientIp, $userAccounts);
                if ($ipCount >= MAX_ACCOUNTS_PER_IP) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => "该IP最多注册 " . MAX_ACCOUNTS_PER_IP . " 个账号，已达上限"]);
                    return;
                }
                // 创建账号
                $userAccounts[$username] = [
                    'passwordHash'   => password_hash($password, PASSWORD_DEFAULT),
                    'ip'             => $clientIp,
                    'registeredAt'   => date('Y-m-d H:i:s'),
                ];
                saveJson(USERS_FILE, $userAccounts);
                echo "[注册] 新用户: $username (IP=$clientIp)\n";
            } else {
                // ---- 登录流程 ----
                if (!$userExists) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '账号不存在，请先注册']);
                    return;
                }
                if (!password_verify($password, $userAccounts[$username]['passwordHash'])) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '密码错误']);
                    return;
                }
            }

            // 检查重名在线
            foreach ($clients as $cid => $c) {
                if ($c['username'] === $username && $cid !== $id) {
                    sendTo($clients[$id]['socket'], ['type' => 'login_fail', 'message' => '该账号已在其他地方登录']);
                    return;
                }
            }

            $isAdmin = ($adminPass === ADMIN_PASSWORD);
            $clients[$id]['username'] = $username;
            $clients[$id]['isAdmin']  = $isAdmin;

            // 加入公共大厅
            if (!in_array($username, $groups['public']['members'])) {
                $groups['public']['members'][] = $username;
            }

            echo "[登录] $username" . ($isAdmin ? " [管理员]" : "") . "\n";

            sendTo($clients[$id]['socket'], [
                'type'     => 'login_ok',
                'username' => $username,
                'isAdmin'  => $isAdmin,
                'groups'   => getGroupList($groups, $username),
                'history'  => array_slice($messageHistory, -50),
            ]);

            broadcast(['type' => 'user_online', 'username' => $username, 'isAdmin' => $isAdmin], $clients, $id);
            broadcast(['type' => 'system', 'message' => "「$username」加入了聊天室"], $clients, $id);

            sendTo($clients[$id]['socket'], ['type' => 'user_list', 'users' => getOnlineUsers($clients)]);
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "欢迎来到聊天室，当前在线 " . count(array_filter($clients, fn($c) => $c['username'])) . " 人"]);
            break;

        // ---------- 公共消息 ----------
        case 'public_message':
            if (!$clients[$id]['username']) return;
            if ($clients[$id]['isMuted']) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '您已被禁言，无法发送消息']);
                return;
            }
            $text = trim($msg['message'] ?? '');
            if (empty($text) || strlen($text) > 1000) return;

            $packet = [
                'type'    => 'public_message',
                'from'    => $clients[$id]['username'],
                'message' => $text,
                'isAdmin' => $clients[$id]['isAdmin'],
                'time'    => date('H:i:s'),
            ];
            $messageHistory[] = $packet;
            if (count($messageHistory) > 200) array_shift($messageHistory);
            saveJson(MSG_FILE, $messageHistory);
            broadcast($packet, $clients, $id);        // 广播给其他人（排除自己）
            sendTo($clients[$id]['socket'], $packet);  // 给自己回显一次
            break;

        // ---------- 私聊 ----------
        case 'private_message':
            if (!$clients[$id]['username']) return;
            if ($clients[$id]['isMuted']) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '您已被禁言']);
                return;
            }
            $to = $msg['to'] ?? '';
            $text = trim($msg['message'] ?? '');
            if (empty($text) || empty($to)) return;

            $packet = [
                'type'    => 'private_message',
                'from'    => $clients[$id]['username'],
                'to'      => $to,
                'message' => $text,
                'time'    => date('H:i:s'),
            ];
            foreach ($clients as $cid => $c) {
                if ($c['username'] === $to) {
                    sendTo($c['socket'], $packet);
                }
            }
            sendTo($clients[$id]['socket'], $packet);
            break;

        // ---------- 群组消息 ----------
        case 'group_message':
            if (!$clients[$id]['username']) return;
            if ($clients[$id]['isMuted']) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '您已被禁言']);
                return;
            }
            $groupId = $msg['groupId'] ?? '';
            $text = trim($msg['message'] ?? '');
            if (empty($text) || !isset($groups[$groupId])) return;
            if (!in_array($clients[$id]['username'], $groups[$groupId]['members'])) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '您不在该群组中']);
                return;
            }

            $packet = [
                'type'      => 'group_message',
                'groupId'   => $groupId,
                'groupName' => $groups[$groupId]['name'],
                'from'      => $clients[$id]['username'],
                'message'   => $text,
                'time'      => date('H:i:s'),
            ];
            broadcastGroup($groupId, $packet, $clients, $groups);
            break;

        // ---------- 加入群组 ----------
        case 'join_group':
            if (!$clients[$id]['username']) return;
            $groupId = $msg['groupId'] ?? '';
            if (!isset($groups[$groupId])) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '群组不存在']);
                return;
            }
            $username = $clients[$id]['username'];
            if (!in_array($username, $groups[$groupId]['members'])) {
                $groups[$groupId]['members'][] = $username;
            }
            sendTo($clients[$id]['socket'], [
                'type'      => 'join_group_ok',
                'groupId'   => $groupId,
                'groupName' => $groups[$groupId]['name'],
            ]);
            broadcastGroup($groupId, ['type' => 'system', 'message' => "「$username」加入了群组「{$groups[$groupId]['name']}」"], $clients, $groups);
            foreach ($clients as $cid => $c) {
                if ($c['username']) {
                    sendTo($c['socket'], ['type' => 'group_list', 'groups' => getGroupList($groups, $c['username'])]);
                }
            }
            break;

        // ---------- 管理员：踢人 ----------
        case 'admin_kick':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            foreach ($clients as $cid => $c) {
                if ($c['username'] === $target) {
                    sendTo($c['socket'], ['type' => 'kicked', 'message' => '您已被管理员踢出']);
                    disconnectClient($c['socket'], $cid, $socketList, $clients, $groups);
                    echo "[管理] 踢出用户: $target\n";
                    sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已踢出用户「$target」"]);
                    broadcast(['type' => 'system', 'message' => "「$target」已被管理员踢出"], $clients);
                    return;
                }
            }
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "用户「$target」不在线"]);
            break;

        // ---------- 管理员：禁言/解禁 ----------
        case 'admin_mute':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            $mute = (bool)($msg['mute'] ?? true);
            foreach ($clients as $cid => &$c) {
                if ($c['username'] === $target) {
                    $c['isMuted'] = $mute;
                    $action = $mute ? '禁言' : '解除禁言';
                    sendTo($c['socket'], ['type' => 'system', 'message' => $mute ? '您已被管理员禁言' : '您的禁言已被解除']);
                    sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已{$action}用户「$target」"]);
                    broadcast(['type' => 'user_list', 'users' => getOnlineUsers($clients)], $clients);
                    echo "[管理] {$action}用户: $target\n";
                    return;
                }
            }
            break;

        // ---------- 管理员：封禁账号 ----------
        case 'admin_ban':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            if (!in_array($target, $bannedUsers)) $bannedUsers[] = $target;
            saveJson(BAN_FILE, $bannedUsers);
            foreach ($clients as $cid => $c) {
                if ($c['username'] === $target) {
                    sendTo($c['socket'], ['type' => 'kicked', 'message' => '您的账号已被封禁']);
                    disconnectClient($c['socket'], $cid, $socketList, $clients, $groups);
                    break;
                }
            }
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已封禁用户「$target」"]);
            echo "[管理] 封禁用户: $target\n";
            break;

        // ---------- 管理员：解封 ----------
        case 'admin_unban':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            $bannedUsers = array_values(array_filter($bannedUsers, fn($u) => $u !== $target));
            saveJson(BAN_FILE, $bannedUsers);
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已解封用户「$target」"]);
            echo "[管理] 解封用户: $target\n";
            break;

        // ---------- 管理员：封禁IP ----------
        case 'admin_ban_ip':
            if (!$clients[$id]['isAdmin']) return;
            $targetIp = trim($msg['ip'] ?? '');
            if (empty($targetIp)) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => 'IP地址不能为空']);
                return;
            }
            if (!in_array($targetIp, $bannedIps)) $bannedIps[] = $targetIp;
            saveJson(IP_BAN_FILE, $bannedIps);
            // 踢出该IP下所有在线用户
            $kickedCount = 0;
            foreach ($clients as $cid => $c) {
                if (($c['ipOnly'] ?? '') === $targetIp && $cid !== $id) {
                    sendTo($c['socket'], ['type' => 'kicked', 'message' => '您的IP已被管理员封禁']);
                    disconnectClient($c['socket'], $cid, $socketList, $clients, $groups);
                    $kickedCount++;
                }
            }
            $kickMsg = $kickedCount > 0 ? "，已踢出该IP下 $kickedCount 个在线用户" : '';
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已封禁IP「$targetIp」$kickMsg"]);
            echo "[管理] 封禁IP: $targetIp\n";
            break;

        // ---------- 管理员：解封IP ----------
        case 'admin_unban_ip':
            if (!$clients[$id]['isAdmin']) return;
            $targetIp = trim($msg['ip'] ?? '');
            $bannedIps = array_values(array_filter($bannedIps, fn($ip) => $ip !== $targetIp));
            saveJson(IP_BAN_FILE, $bannedIps);
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已解封IP「$targetIp」"]);
            echo "[管理] 解封IP: $targetIp\n";
            break;

        // ---------- 管理员：获取IP封禁列表 ----------
        case 'get_ip_ban_list':
            if (!$clients[$id]['isAdmin']) return;
            sendTo($clients[$id]['socket'], ['type' => 'ip_ban_list', 'ips' => $bannedIps]);
            break;

        // ---------- 管理员：清除历史消息 ----------
        case 'admin_clear_history':
            if (!$clients[$id]['isAdmin']) return;
            $messageHistory = [];
            saveJson(MSG_FILE, $messageHistory);
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '✅ 历史消息已清除']);
            broadcast(['type' => 'system', 'message' => '管理员已清除聊天记录'], $clients, $id);
            echo "[管理] " . $clients[$id]['username'] . " 清除了历史消息\n";
            break;

        // ---------- 管理员：删除账号 ----------
        case 'admin_delete_account':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            if (isset($userAccounts[$target])) {
                unset($userAccounts[$target]);
                saveJson(USERS_FILE, $userAccounts);
                // 同时踢出在线用户
                foreach ($clients as $cid => $c) {
                    if ($c['username'] === $target) {
                        sendTo($c['socket'], ['type' => 'kicked', 'message' => '您的账号已被管理员删除']);
                        disconnectClient($c['socket'], $cid, $socketList, $clients, $groups);
                        break;
                    }
                }
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已删除账号「$target」"]);
                echo "[管理] 删除账号: $target\n";
            } else {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "账号「$target」不存在"]);
            }
            break;

        // ---------- 管理员：获取账号列表 ----------
        case 'get_account_list':
            if (!$clients[$id]['isAdmin']) return;
            $list = [];
            foreach ($userAccounts as $uname => $acc) {
                $list[] = [
                    'username'     => $uname,
                    'ip'           => $acc['ip'] ?? '',
                    'registeredAt' => $acc['registeredAt'] ?? '',
                ];
            }
            sendTo($clients[$id]['socket'], ['type' => 'account_list', 'accounts' => $list]);
            break;

        // ---------- 管理员：创建群组 ----------
        case 'admin_create_group':
            if (!$clients[$id]['isAdmin']) return;
            $groupName = trim($msg['groupName'] ?? '');
            if (empty($groupName) || strlen($groupName) > 30) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '群组名无效']);
                return;
            }
            $groupId = 'g_' . time() . '_' . rand(100, 999);
            $groups[$groupId] = ['name' => $groupName, 'members' => [$clients[$id]['username']], 'owner' => $clients[$id]['username']];
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "群组「$groupName」创建成功"]);
            foreach ($clients as $cid => $c) {
                if ($c['username']) {
                    sendTo($c['socket'], ['type' => 'group_list', 'groups' => getGroupList($groups, $c['username'])]);
                }
            }
            echo "[管理] 创建群组: $groupName (ID=$groupId)\n";
            break;

        // ---------- 管理员：删除群组 ----------
        case 'admin_delete_group':
            if (!$clients[$id]['isAdmin']) return;
            $groupId = $msg['groupId'] ?? '';
            if ($groupId === 'public') {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '无法删除公共大厅']);
                return;
            }
            if (!isset($groups[$groupId])) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '群组不存在']);
                return;
            }
            $groupName = $groups[$groupId]['name'];
            broadcastGroup($groupId, ['type' => 'system', 'message' => "群组「$groupName」已被管理员解散"], $clients, $groups);
            unset($groups[$groupId]);
            foreach ($clients as $cid => $c) {
                if ($c['username']) {
                    sendTo($c['socket'], ['type' => 'group_list', 'groups' => getGroupList($groups, $c['username'])]);
                }
            }
            sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "已删除群组「$groupName」"]);
            echo "[管理] 删除群组: $groupName\n";
            break;

        // ---------- 管理员：全体公告 ----------
        case 'admin_announcement':
            if (!$clients[$id]['isAdmin']) return;
            $text = trim($msg['message'] ?? '');
            if (empty($text)) return;
            $packet = ['type' => 'announcement', 'message' => $text, 'from' => $clients[$id]['username'], 'time' => date('H:i:s')];
            broadcast($packet, $clients, $id);        // 广播给其他人（排除自己）
            sendTo($clients[$id]['socket'], $packet);  // 给自己回显一次
            echo "[公告] " . $clients[$id]['username'] . ": $text\n";
            break;

        // ---------- 管理员：邀请加入群组（支持将自己加入） ----------
        case 'admin_invite_group':
            if (!$clients[$id]['isAdmin']) return;
            $target = $msg['username'] ?? '';
            $groupId = $msg['groupId'] ?? '';
            if (!isset($groups[$groupId])) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => '群组不存在']);
                return;
            }
            $found = false;
            foreach ($clients as $cid => $c) {
                if ($c['username'] === $target) {
                    $found = true;
                    if (!in_array($target, $groups[$groupId]['members'])) {
                        $groups[$groupId]['members'][] = $target;
                    }
                    sendTo($c['socket'], [
                        'type'      => 'join_group_ok',
                        'groupId'   => $groupId,
                        'groupName' => $groups[$groupId]['name'],
                    ]);
                    // 若目标不是管理员自己，才发提示（避免重复提示）
                    if ($cid !== $id) {
                        sendTo($c['socket'], ['type' => 'system', 'message' => "您已被管理员加入群组「{$groups[$groupId]['name']}」"]);
                    }
                    foreach ($clients as $ccid => $cc) {
                        if ($cc['username']) {
                            sendTo($cc['socket'], ['type' => 'group_list', 'groups' => getGroupList($groups, $cc['username'])]);
                        }
                    }
                    $selfMsg = ($cid === $id) ? "您已加入群组「{$groups[$groupId]['name']}」" : "已将「$target」加入群组「{$groups[$groupId]['name']}」";
                    sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => $selfMsg]);
                    break;
                }
            }
            if (!$found) {
                sendTo($clients[$id]['socket'], ['type' => 'system', 'message' => "用户「$target」不在线"]);
            }
            break;

        // ---------- 请求在线用户列表 ----------
        case 'get_user_list':
            if (!$clients[$id]['username']) return;
            sendTo($clients[$id]['socket'], ['type' => 'user_list', 'users' => getOnlineUsers($clients)]);
            break;

        // ---------- 请求群列表 ----------
        case 'get_group_list':
            if (!$clients[$id]['username']) return;
            sendTo($clients[$id]['socket'], ['type' => 'group_list', 'groups' => getGroupList($groups, $clients[$id]['username'])]);
            break;

        // ---------- 获取封禁列表 ----------
        case 'get_banned_list':
            if (!$clients[$id]['isAdmin']) return;
            sendTo($clients[$id]['socket'], ['type' => 'banned_list', 'users' => $bannedUsers]);
            break;
    }
}

// ===== 获取群组列表（含是否已加入标记）=====
function getGroupList(&$groups, $username) {
    $list = [];
    foreach ($groups as $id => $g) {
        $list[] = [
            'id'     => $id,
            'name'   => $g['name'],
            'owner'  => $g['owner'],
            'count'  => count($g['members']),
            'joined' => in_array($username, $g['members']),
        ];
    }
    return $list;
}
