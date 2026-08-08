@echo off
chcp 65001 >nul
title Forum Pan 客户端节点
echo.
echo ╔══════════════════════════════════════════╗
echo ║  Forum Pan 客户端节点                   ║
echo ║  通过 TCP 连接总端获取数据              ║
echo ╚══════════════════════════════════════════╝
echo.
echo 启动 PHP 内置 Web 服务器 (端口 8080)...
echo 浏览器访问 http://localhost:8080
echo.
php -S 0.0.0.0:8080
pause