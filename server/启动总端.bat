@echo off
chcp 65001 >nul
title Forum Pan 总端数据库服务
echo.
echo ╔══════════════════════════════════════════╗
echo ║  Forum Pan 总端 TCP 数据库服务          ║
echo ║  集中管理 MySQL，客户端通过 TCP 访问    ║
echo ╚══════════════════════════════════════════╝
echo.

:: ============================================
:: PHP 路径配置（自动检测 phpstudy 路径）
:: ============================================
set PHP_PATH=

:: 方式1: phpstudy 默认路径
if exist "E:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe" (
    set PHP_PATH=E:\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe
    echo [检测] 发现 phpstudy PHP: %PHP_PATH%
)

:: 方式2: 系统 PATH 中的 php
if "%PHP_PATH%"=="" (
    where php >nul 2>&1
    if %errorlevel% equ 0 (
        set PHP_PATH=php
        echo [检测] 使用系统 PATH 中的 PHP
    )
)

:: 未找到 PHP
if "%PHP_PATH%"=="" (
    echo [错误] 未找到 PHP！请编辑此脚本设置 PHP_PATH 变量
    pause
    exit /b 1
)

:: ============================================
:: 数据库配置（修改为你的 MySQL 账号密码）
:: ============================================
set DB_HOST=localhost
set DB_USER=root
set DB_PWD=root
set DB_NAME=forum_pan
set BIND_PORT=9527

echo.
echo 启动总端数据库服务 (端口 %BIND_PORT%)...
echo MySQL: %DB_HOST%/%DB_NAME%
echo.
echo 提示: 确保防火墙已放行端口 %BIND_PORT%
echo.

"%PHP_PATH%" db_server.php 0.0.0.0 %BIND_PORT% %DB_HOST% %DB_USER% %DB_PWD% %DB_NAME%
pause