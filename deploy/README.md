# Docker 一键部署（Ubuntu / Debian）

## 服务器要求
- Ubuntu 22.04/24.04 或 Debian 12，x86_64/ARM64 均使用 Docker 官方多架构基础镜像。
- 至少 2 核 CPU、4 GB RAM、20 GB 可用磁盘（生产建议更高）。
- 公网服务器安全组/防火墙放行 TCP 80。
- 能访问 GitHub Container Registry（ghcr.io）及 Docker Hub 拉取基础设施镜像。
- 安装脚本会自动安装 Docker Engine（若尚未安装），不会在服务器下载源码或编译应用。

## 一键安装
发布完成且 GHCR 镜像已设为 Public 后，服务器只需执行：

```bash
curl -fsSL https://raw.githubusercontent.com/seanldnet/Auth/main/deploy/install.sh | sudo bash
```

部署后访问 `http://服务器公网IP/`。

## 默认首次登录
- 用户名：`admin`
- 初始密码：`123456`
- 首次登录强制修改密码。
- 默认密码仅为首次初始化便利，公网环境不要长期保留。

## 自动化行为
- 自动生成随机 APP_KEY、MySQL 应用用户密码和 MySQL root 密码，保存在服务器 `/opt/auth/.env`（权限 0600）。
- 启动 MySQL 8、Redis、PHP 8.2 Apache API、Nginx 静态前端及定时任务容器。
- 按文件名顺序执行 `backend/database/schema/*.sql`，并用 `auth_schema_migrations` 记录已应用版本。
- 初始化单一钱包管理员账号；不创建分币种钱包。
- 自动清理 API Key Nonce；每小时执行授权到期站内提醒任务。
- MySQL 和 Redis 使用 Docker named volumes 持久化。
- 对外只发布 TCP 80；MySQL、Redis 和后端不映射公网端口。

## 常用管理命令
```bash
cd /opt/auth
docker compose ps
docker compose logs -f
docker compose pull && docker compose up -d
docker compose down
```

## 重要限制
1. 一键脚本支持 Ubuntu/Debian apt 系统；不支持 CentOS、Alpine 或 Windows Server。
2. 服务器不编译前后端，但第一次安装仍需从镜像仓库拉取镜像，网络必须能访问 GHCR。
3. 首次发布 GHCR 包后，仓库维护者必须将 `auth-backend` 和 `auth-frontend` 两个容器包设置为 Public，否则匿名服务器无法拉取。此为镜像仓库的一次性权限设置，不是服务器配置。
4. 80 端口必须空闲，云厂商安全组也必须允许 TCP 80。脚本无法自动修改云厂商控制台的安全组。
5. 当前默认 HTTP 仅用于快速验收，登录 Cookie 为适配 HTTP 测试而关闭 Secure 标志。正式公网必须启用 HTTPS，并将后端环境变量 `AUTH_COOKIE_SECURE=true`；不要长期通过明文 HTTP 传输登录凭据。
6. 默认账号密码是公开测试凭据；首次登录后强制改密。部署目录中的 `.env` 含应用与数据库秘密，不得外传或提交 Git。
7. 支付通道商户参数、回调域名、邮件/Telegram 凭据仍需在后台或正式部署时配置；无凭据时不能完成真实支付/外部通知联调。
8. 首次部署自动执行全量迁移。已有业务数据库不得直接套用本安装脚本；升级前先备份并按迁移台账执行。
9. `docker compose down` 不删除数据卷；`docker compose down -v` 会删除数据库和 Redis 数据，禁止在有数据的环境误用。

## 验收状态
CI 通过和 Docker 镜像成功发布只是构建交付。只有完成全新 Ubuntu/Debian 主机拉取镜像、首次迁移、管理员登录、强制改密、前后端 API 访问及重启持久化验证后，才能标记为“Docker 一键部署验收通过”。
