#!/usr/bin/env bash
set -Eeuo pipefail
REPO="https://raw.githubusercontent.com/seanldnet/Auth/main"
INSTALL_DIR="/opt/auth"
if [[ "${EUID}" -ne 0 ]]; then
  echo "请使用 root 执行：curl -fsSL ${REPO}/deploy/install.sh | sudo bash"
  exit 1
fi
if ! command -v apt-get >/dev/null 2>&1; then
  echo "当前一键安装脚本支持 Ubuntu / Debian。"
  exit 1
fi
if ! command -v docker >/dev/null 2>&1; then
  echo "[1/5] 正在安装 Docker Engine..."
  apt-get update
  apt-get install -y ca-certificates curl
  curl -fsSL https://get.docker.com | sh
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose 插件未安装，请检查 Docker 安装源后重试。"
  exit 1
fi
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"
if [[ ! -f .env ]]; then
  echo "[2/5] 正在生成数据库和应用密钥..."
  command -v openssl >/dev/null 2>&1 || apt-get install -y openssl
  APP_KEY="$(openssl rand -hex 32)"
  AUTH_DB_PASSWORD="$(openssl rand -hex 24)"
  MYSQL_ROOT_PASSWORD="$(openssl rand -hex 24)"
  cat > .env <<EOF
APP_KEY=$APP_KEY
AUTH_DB_PASSWORD=$AUTH_DB_PASSWORD
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD
AUTH_BOOTSTRAP_ADMIN_PASSWORD=123456
EOF
  chmod 600 .env
else
  echo "[2/5] 检测到已有配置，保留原数据库密码和应用密钥。"
fi
echo "[3/5] 正在下载 Docker Compose 部署文件..."
curl -fsSL "$REPO/deploy/docker-compose.yml" -o docker-compose.yml
chmod 600 docker-compose.yml
if ss -ltn 2>/dev/null | grep -qE 'LISTEN[[:space:]]+[^[:space:]]*:80[[:space:]]'; then
  echo "错误：服务器 80 端口已被占用，请先释放 80 端口。"
  exit 1
fi
echo "[4/5] 正在拉取预构建镜像（服务器不会编译源码）..."
docker compose pull
echo "[5/5] 正在启动数据库、缓存、后端和前端..."
docker compose up -d
echo "等待服务启动..."
for attempt in $(seq 1 60); do
  if curl -fsS http://127.0.0.1/api/health >/dev/null 2>&1; then
    break
  fi
  sleep 5
done
if ! curl -fsS http://127.0.0.1/api/health >/dev/null 2>&1; then
  echo "服务尚未就绪，最近日志如下："
  docker compose ps
  docker compose logs --tail=100 backend db frontend
  exit 1
fi
PUBLIC_IP="$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
echo
echo "=================================================="
echo " Auth 已启动"
echo "=================================================="
if [[ -n "$PUBLIC_IP" ]]; then
  echo "访问地址：http://$PUBLIC_IP"
else
  echo "访问地址：http://服务器公网IP"
fi
echo "管理员账号：admin"
echo "初始密码：123456"
echo "首次登录必须修改密码。"
echo "部署目录：$INSTALL_DIR"
echo "查看日志：cd $INSTALL_DIR && docker compose logs -f"
echo "停止服务：cd $INSTALL_DIR && docker compose down"
echo "数据库数据保存在 Docker 持久化卷中。"
echo "=================================================="
