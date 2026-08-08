#!/bin/bash
# ============================================
#  Forum Pan 总端 TCP 数据库服务 — Linux 版
#  用法: ./start_server.sh
# ============================================

set -e

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║  Forum Pan 总端 TCP 数据库服务 (Linux)  ║"
echo "║  集中管理 MySQL，客户端通过 TCP 访问    ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# ============================================
# 数据库配置（修改为你的 MySQL 账号密码）
# ============================================
DB_HOST="localhost"
DB_USER="root"
DB_PWD="root"
DB_NAME="forum_pan"
BIND_PORT=9527

# ============================================
# PHP 路径检测
# ============================================
if command -v php &> /dev/null; then
    PHP_PATH="php"
    echo "[检测] 使用系统 PHP: $(which php)"
elif [ -f "/usr/bin/php" ]; then
    PHP_PATH="/usr/bin/php"
    echo "[检测] 使用 /usr/bin/php"
elif [ -f "/usr/local/php/bin/php" ]; then
    PHP_PATH="/usr/local/php/bin/php"
    echo "[检测] 使用 /usr/local/php/bin/php"
else
    echo "[错误] 未找到 PHP！请安装 PHP 或手动设置 PHP_PATH"
    echo "        Ubuntu/Debian: sudo apt install php-cli php-mysql"
    echo "        CentOS/RHEL:   sudo yum install php-cli php-mysqlnd"
    exit 1
fi

# ============================================
# 切换到脚本所在目录
# ============================================
cd "$(dirname "$0")"

echo ""
echo "启动总端数据库服务 (端口 $BIND_PORT)..."
echo "MySQL: $DB_HOST/$DB_NAME"
echo ""
echo "提示: 确保防火墙已放行端口 $BIND_PORT"
echo "      iptables: iptables -A INPUT -p tcp --dport $BIND_PORT -j ACCEPT"
echo "      ufw:      ufw allow $BIND_PORT/tcp"
echo ""

# ============================================
# 启动服务
# ============================================
exec "$PHP_PATH" db_server.php 0.0.0.0 "$BIND_PORT" "$DB_HOST" "$DB_USER" "$DB_PWD" "$DB_NAME"