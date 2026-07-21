# 在 1Panel 中部署 hgame

本文说明如何用 **1Panel + Docker Compose** 部署本项目。项目自带 `Dockerfile` 与 `docker-compose.yml`，默认数据库为 **PostgreSQL 16**，应用容器内为 PHP 8.4 + Nginx（监听 `8080`），并附带 queue worker。

> 官方编排文档：[1Panel 编排](https://1panel.cn/docs/v2/user_manual/containers/compose/)  
> 官方反向代理网站：[创建网站](https://1panel.cn/docs/v2/user_manual/websites/website_create/)

---

## 1. 适用方式

本项目需要**在服务器上构建镜像**（`build: context: .`），不是拉取现成公共镜像。推荐：

1. 把代码放到服务器某个目录（含 `Dockerfile`、`docker-compose.yml`）
2. 在 1Panel **容器 → 编排** 里用该目录启动 Compose
3. 用 1Panel **网站 → 反向代理** 把域名转到应用的 `8080` 端口

不建议先做成「应用商店本地应用」：商店模式更适合已发布公共镜像的应用；本仓库目前以源码构建为主。

---

## 2. 服务器准备

| 项目 | 建议 |
|------|------|
| 系统 | Linux（已安装 1Panel） |
| 资源 | 建议 ≥ 2 核 / 4GB；首次 `docker compose build` 较吃 CPU/内存 |
| 域名 | 已解析到服务器 IP |
| 防火墙 | 放行 `80` / `443`；应用端口可只给本机或 1Panel 反向代理用 |
| Git | 能 `git clone` 仓库（或用 1Panel 文件管理上传代码） |

确认 Docker 可用：1Panel 左侧 **容器** 能正常打开即可。

---

## 3. 放置代码

在终端或 1Panel **主机 → 终端** 中执行（路径可自定）：

```bash
# 示例目录
mkdir -p /opt/apps
cd /opt/apps

# 替换为你的仓库地址
git clone git@github.com:q112664/hgame2.git hgame
cd hgame
```

也可用 1Panel **文件** 上传压缩包后解压到同一目录。目录内必须能看到：

- `Dockerfile`
- `docker-compose.yml`
- `.env.example`
- `composer.json` / `package.json`

---

## 4. 准备生产环境 `.env`

在项目根目录：

```bash
cd /opt/apps/hgame
cp .env.example .env
```

用 1Panel 文件编辑器或 `nano .env`，至少改这些：

```env
APP_NAME=hgame
APP_ENV=production
APP_DEBUG=false
APP_URL=https://你的域名

# 生成方式见下方命令；必须是完整 Laravel key
APP_KEY=

LOG_CHANNEL=stack
LOG_LEVEL=error

# compose 里会强制覆盖为 pgsql，这里仍建议写清楚
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hgame
DB_USERNAME=hgame
DB_PASSWORD=请改成强密码

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MEDIA_DISK=public
```

生成 `APP_KEY`（宿主机有 PHP 时可本地生成后粘贴；没有则等容器起来后在容器内生成再写入 `.env` 并重启）：

```bash
# 方式 A：本机有 PHP
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# 方式 B：临时用官方 PHP 容器生成
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

**注意：**

- `APP_URL` 必须是最终对外访问地址（含 `https://`）。
- `DB_PASSWORD` 请与下方 Compose 环境一致；`docker-compose.yml` 会用 `.env` 里的 `DB_*` 注入 Postgres。
- 不要把真实 `.env` 提交进 Git。

---

## 5. 生产建议：不要把 Postgres 暴露到公网

仓库默认 `docker-compose.yml` 会把 Postgres 映射到宿主机 `5432`，方便调试。**生产建议去掉 `postgres.ports`**，只允许容器网内访问。

编辑 `docker-compose.yml`，把 `postgres` 服务里的这段删掉或注释：

```yaml
    ports:
      - "${FORWARD_DB_PORT:-5432}:5432"
```

应用端口 `APP_PORT`（默认 `8080`）可以保留，供 1Panel 反向代理使用；若只想本机反代，也可改成绑定 `127.0.0.1:8080:8080`（视 1Panel / Docker 网络是否同机而定，多数同机反代用 `http://127.0.0.1:8080` 即可）。

可选环境变量：

| 变量 | 默认 | 含义 |
|------|------|------|
| `APP_PORT` | `8080` | 宿主机访问应用的端口 |
| `FORWARD_DB_PORT` | `5432` | Postgres 宿主机端口（建议生产不映射） |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `hgame` / `hgame` / `secret` | 数据库 |

---

## 6. 在 1Panel 创建编排

1. 打开 **容器 → 编排 → 创建编排**
2. 来源选 **路径选择**（或「编辑」后保证工作目录就是项目根）
3. 选择/指向：`/opt/apps/hgame/docker-compose.yml`  
   （编排工作目录必须是含 `Dockerfile` 的项目根，否则 `build` 会失败）
4. 名称可填：`hgame`
5. 确认创建并启动

首次会执行镜像构建，可能要 **数分钟到十几分钟**。可在编排详情 / 容器日志里看 `app` 构建与启动进度。

等价命令行（排查时好用）：

```bash
cd /opt/apps/hgame
docker compose up -d --build
docker compose ps
docker compose logs -f app
```

启动成功后，本机探测：

```bash
curl -I http://127.0.0.1:8080/up
```

应返回健康检查相关响应（`/up`）。

容器内会自动：

- 跑迁移（`AUTORUN_LARAVEL_MIGRATION`）
- 创建 storage 软链
- `optimize`

queue 由 `worker` 服务处理。

---

## 7. 配置网站反向代理（域名 + HTTPS）

1. 打开 **网站 → 创建网站**
2. 类型选 **反向代理**
3. 填写域名，例如 `hgame.example.com`
4. 代理地址填：`http://127.0.0.1:8080`  
   （若 Compose 把端口映到别的 `APP_PORT`，改成对应端口）
5. 创建后进入网站 → **HTTPS**，申请/配置 Let’s Encrypt 证书
6. 建议开启 **强制 HTTPS**

完成后用浏览器访问 `https://你的域名`。

若出现「资源 URL / 登录跳转变成 http」等问题，优先检查：

- `.env` 的 `APP_URL` 是否为 `https://...`
- 改完 `.env` 后是否 `docker compose up -d` 重启过 `app` / `worker`

---

## 8. 首次使用与管理员

1. 打开站点首页，确认能打开。
2. 用注册页创建账号（若开放注册）。
3. 把该用户设为管理员（在 `app` 容器执行）：

```bash
cd /opt/apps/hgame
docker compose exec app php artisan tinker --execute 'App\Models\User::where("email", "你的邮箱")->update(["is_admin" => true]);'
```

4. 访问后台：`https://你的域名/admin`（Filament）。

如需 API Token，可在后台设置页生成，或：

```bash
docker compose exec app php artisan game:token 你的邮箱 --name=prod
```

---

## 9. 日常更新

```bash
cd /opt/apps/hgame
git pull
docker compose up -d --build
```

说明：

- `--build` 会重新编译前端资源与 PHP 依赖，更新代码后务必带上。
- 数据在 Docker volume：`postgres_data`、`app_storage`，一般不会因重建镜像丢失。
- 迁移会在 `app` 启动时自动执行；也可手动：

```bash
docker compose exec app php artisan migrate --force
```

备份建议在 1Panel 里备份：

- Postgres 数据卷 / 定期 `pg_dump`
- `app_storage` 卷（上传文件、日志等）

---

## 10. 常见问题

### 构建失败 / 内存不足

加大服务器内存，或临时加 swap。构建阶段需要 Node + Composer，比纯运行更吃资源。

### `app` 一直不健康

```bash
docker compose logs app --tail=200
docker compose exec app php artisan migrate --force
```

常见原因：`.env` 缺少 `APP_KEY`、数据库密码不一致、首次 Postgres 未就绪（可再等一会或重启 `app`）。

### 502 Bad Gateway

- 确认 `curl http://127.0.0.1:8080/up` 通
- 确认网站反代端口与 `APP_PORT` 一致
- 确认防火墙未拦错端口

### 上传/封面不显示

确认 `MEDIA_DISK=public`，且启动时已做 storage link：

```bash
docker compose exec app php artisan storage:link
```

### 想用 1Panel 自带的 PostgreSQL 而不是 Compose 内的

可以，但要改网络：

1. 在 1Panel 安装 PostgreSQL 应用，记下账号与库名
2. 让 `app`/`worker` 加入 `1panel-network`，`DB_HOST` 改为 1Panel Postgres 容器名
3. 从 `docker-compose.yml` 移除内置 `postgres` 服务

默认文档路径仍推荐使用仓库自带的 `postgres` 服务，配置最简单。

---

## 11. 结构速览

```text
浏览器
  → 1Panel 网站（Nginx，443）
    → http://127.0.0.1:8080
      → app（PHP-FPM + Nginx，Laravel）
      → worker（queue:work）
      → postgres:16（仅容器网内）
```

相关文件：

- `docker-compose.yml` — 编排
- `Dockerfile` — 构建与运行镜像
- `docker/ensure-storage.sh` — 确保存储目录存在
- `.env` — 生产密钥与站点 URL（勿提交）

---

## 站点地址变成 localhost？

原因通常有两个：

1. 数据库 `settings.site_url` 在首次迁移时写成了错误值  
2. 旧镜像构建时 Wayfinder 把绝对地址打进了前端 JS

在服务器上先改站点地址并清缓存：

```bash
cd /opt/apps/hgame
docker compose exec app php artisan tinker --execute 'App\Models\Setting::set("site_url", "https://你的域名");'
docker compose exec app php artisan optimize:clear
```

然后拉取含相对路由修复的代码并重建：

```bash
git pull
docker compose up -d --build
```

也可在后台 **Admin → Site settings** 里把 Site URL 改成 `https://你的域名`。

- [ ] 代码已放到服务器，含 Dockerfile
- [ ] `.env` 已配置 `APP_KEY` / `APP_URL` / 强数据库密码 / `APP_DEBUG=false`
- [ ] 生产已取消 Postgres 公网端口映射
- [ ] `docker compose up -d --build` 成功，`/up` 可访问
- [ ] 1Panel 反向代理 + HTTPS 正常
- [ ] 已创建管理员并可打开 `/admin`
