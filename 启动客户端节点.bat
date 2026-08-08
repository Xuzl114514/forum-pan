@echo off
chcp 65001 >nul
title Forum Pan 客户端节点
echo.
echo ╔══════════════════════════════════════════╗
echo ║  Forum Pan 客户端节点                   ║
echo ║  通过 TCP 连接总端获取数据              ║
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
    echo         例如: set PHP_PATH=C:\php\php.exe
    pause
    exit /b 1
)

:: ============================================
:: 端口配置
:: ============================================
set WEB_PORT=8080
echo.
echo 启动 PHP 内置 Web 服务器 (端口 %WEB_PORT%)...
echo 浏览器访问 http://localhost:%WEB_PORT%
echo.
echo 提示: 也可用 phpstudy 的 Apache 部署，将网站根目录指向当前文件夹即可
echo.

"%PHP_PATH%" -S 0.0.0.0:%WEB_PORT%
pause