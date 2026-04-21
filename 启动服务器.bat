@echo off
chcp 65001 >nul
title IM 即时通讯服务器

echo.
echo  ==========================================
echo    IM 即时通讯服务器
echo  ==========================================
echo.

:: 检查PHP是否可用
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo  [错误] 未找到 PHP！
    echo.
    echo  请先安装 PHP，推荐方案：
    echo  1. 下载 XAMPP: https://www.apachefriends.org/
    echo  2. 下载 PHP: https://windows.php.net/download/
    echo     下载后将 php.exe 所在目录添加到系统 PATH
    echo.
    echo  安装完成后，重新双击此文件启动。
    echo.
    pause
    exit /b 1
)

echo  PHP 已找到，正在启动服务器...
echo.
echo  服务器地址: ws://127.0.0.1:8080
echo  聊天页面:   用浏览器打开 index.html
echo.
echo  按 Ctrl+C 停止服务器
echo  ==========================================
echo.



:: 启动PHP WebSocket服务器
php "%~dp0server.php"

echo.
echo  服务器已停止。
pause
