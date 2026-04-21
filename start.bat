@echo off
chcp 65001 >nul
echo ========================================
echo   启动聊天服务器...
echo ========================================

cd /d "%~dp0"

php server.php

pause
