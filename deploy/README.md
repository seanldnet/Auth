# Auth Docker 一键部署

## 支持环境
- Ubuntu / Debian Linux，x86_64。
- 需要公网访问 GitHub Container Registry（GHCR）和 Docker 安装源。
- 默认使用 TCP 80 端口；请确保云厂商安全组和系统防火墙放行 80。
- 部署过程只拉取预构建镜像，服务器不下载源码、不执行 Composer/npm 构建。

## 一键安装

以 root 用户执行：

```bash
curl -fsSL https://raw.githubusercontent.com/seanldnet/Auth/main/deploy/install.sh | bash
```

普通 sudo 用户：

```bash
curl -fsSL https://raw.githubusercontent.com/seanldnet/Auth/main/deploy/install.sh | sudo bash
```

安装脚本会自动安装 Docker（若不存在）、生成数据库/Redis/应用加密随机密钥、拉取 GHCR 镜像、启动 MySQL/Redis/API/Nginx，自动执行数据库迁移并创建管理员。

## 首次登录
- 地址：`http://服务器公网IP/`
- 用户名：`admin`
- 初始密码：`123456`
- 首次登录必须修改初始密码。

默认管理员密码仅用于首次测试。不要在公网长期保留默认密码；首次登录后立即设置强密码，并在安全组中限制管理入口。

## 管理命令
安装目录为 `/opt/auth`：

```bash
cd /opt/auth
docker compose ps
docker compose logs -f
docker compose pull && docker compose up -d
docker compose restart
```

数据持久化在 Docker volumes：`mysql_data` 和 `redis_data`。升级镜像不会删除数据。不要执行 `docker compose down -v`，否则会删除数据库卷。

## 数据库迁移
后端启动时按文件名顺序执行 `backend/database/schema/*.sql`。已执行迁移记录在 `auth_schema_migrations`，重启不会重复执行。迁移失败时后端容器不会启动，需先检查日志和数据库状态。

## 当前边界
- 默认只配置 HTTP 80，不会自动申请域名证书或配置 HTTPS。生产使用请在前置反向代理配置 TLS。
- 支付渠道商户参数、回调域名、邮件 SMTP、Telegram Bot 等外部服务尚未配置；无凭据时不能声称这些外部渠道已接通。
- 初始管理员密码固定为用户要求的 `admin / 123456`，首次登录后强制改密。
