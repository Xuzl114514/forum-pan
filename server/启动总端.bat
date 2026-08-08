@echo off
chcp 65001 >nul
title Forum Pan 总端数据库服务
echo.
echo ╔══════════════════════════════════════════╗
echo ║  Forum Pan 总端 TCP 数据库服务          ║
echo ║  集中管理 MySQL，客户端通过 TCP 访问    ║
echo ╚══════════════════════════════════════════╝
echo.
echo 启动总端数据库服务 (端口 9527)...
echo.
php db_server.php 0.0.0.0 9527 localhost root password forum_pan
pause