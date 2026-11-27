#!/usr/bin/env bash
set -Eeuo pipefail
REPO_RAW="https://raw.githubusercontent.com/seanldnet/Auth/main"
INSTALL_DIR="/opt/auth"
if [[ "$(id -u)" -ne 0 ]]; then
  echo "请使用 root 执行：curl -fsSL $REPO_RAW/deploy/install.sh | sudo bash"
  exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then apt-get update -y && apt-get install -y curl ca-certificates
  else echo "系统缺少 curl，请先安装 curl。" >&2; exit 1; fi
fi
if ! command -v docker >/dev/null 2>&1; then
  echo "未检测到 Docker，正在自动安装 Docker Engine 和 Compose 插件..."
  curl -fsSL https://get.docker.com | sh
fi
docker compose version >/dev/null 2>&1 || { echo "Docker Compose 插件不可用。" >&2; exit 1; }
mkdir -p "$INSTALL_DIR"
chmod 700 "$INSTALL_DIR"
cd "$INSTALL_DIR"
if [[ ! -f .env ]]; then
  if ! command -v openssl >/dev/null 2>&1; then apt-get update -y && apt-get install -y openssl; fi
  cat > .env <<EOF
APP_KEY=$(openssl rand -hex 32)
DATABASE_PASSWORD=$(openssl rand -hex 24)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 24)
REDIS_PASSWORD=$(openssl rand -hex 24)
AUTH_HTTP_PORT=80
EOF
  chmod 600 .env
fi
echo "正在下载部署配置..."
curl -fsSL "$REPO_RAW/deploy/docker-compose.yml" -o docker-compose.yml
chmod 600 docker-compose.yml
echo "正在拉取预构建镜像（不会在服务器编译源码）..."
docker compose pull
echo "正在启动 Auth、MySQL 和 Redis..."
docker compose up -d
echo "等待应用启动..."
ready=0
for _ in $(seq 1 120); do
  if curl -fsS http://127.0.0.1/api/health >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
if [[ "$ready" -ne 1 ]]; then
  echo "服务启动失败，容器状态和后端日志如下：" >&2
  docker compose ps >&2
  docker compose logs --tail=120 backend >&2
  exit 1
fi
PUBLIC_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
echo
echo "=================================================="
echo " Auth 部署完成"
echo " 访问地址：http://${PUBLIC_IP:-服务器IP}/"
echo " 管理员账号：admin"
echo " 管理员密码：123456"
echo " 首次登录后系统会要求修改密码。"
echo " 安装目录：$INSTALL_DIR"
echo " 查看日志：cd $INSTALL_DIR && docker compose logs -f"
echo "=================================================="
