# V2Board Docker 部署说明

本文档对应当前仓库内置的 Docker 部署方案。

默认使用仓库根目录 `Dockerfile` 进行构建。

设计目标：

- 外置 Nginx
- 外置 MySQL / Redis
- compose 注入环境变量
- `app` / `queue` / `scheduler` 三服务拆分
- 使用项目目录 `./data` 持久化运行期文件配置
- 日志直接输出到 stdout/stderr

---

## 架构说明

```text
外置 Nginx -> 宿主机 7002 -> app 容器(webman / adapterman)
                            -> queue 容器(Horizon)
                            -> scheduler 容器(schedule:run)

MySQL / Redis 均为外部已有服务，通过 compose environment 注入连接信息

容器内 `/data` 会映射到项目目录 `./data`，以下运行期文件会持久化在宿主机：

- `/data/config/v2board.php`
- `/data/config/theme/*.php`
- `/data/storage/app/public`
- `/data/custom/rules/custom.*`
- `/data/custom/admin/custom.css`
- `/data/custom/theme/<theme>/assets/custom.css`
- `/data/custom/theme/<theme>/assets/custom.js`
- `/data/custom/public/favicon.ico`
```

---

## 环境变量

所有运行参数都通过 `docker-compose.yml` 中的 `environment:` 注入。

> 如果 MySQL / Redis 也是 Docker 容器，请填写容器服务名和容器内部端口，不要填写宿主机映射端口。

重点变量：

### 应用相关

- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_KEY`
- `LOG_CHANNEL`

### HTTP 服务相关

- `APP_HTTP_HOST`
- `APP_HTTP_PORT`
- `MAX_REQUEST`
- `APP_FILE_WATCH`

### 数据库相关

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Redis / 队列相关

- `CACHE_DRIVER`
- `QUEUE_CONNECTION`
- `SESSION_DRIVER`
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_PASSWORD`

### 调度相关

- `SCHEDULE_INTERVAL`

### V2Board 初始化 / 持久化相关

- `V2BOARD_PERSIST_PATH`
- `V2BOARD_FRONTEND_THEME`
- `V2BOARD_SECURE_PATH`
- `V2BOARD_SUBSCRIBE_PATH`
- `V2BOARD_SERVER_API_URL`
- `V2BOARD_SERVER_TOKEN`

推荐值示例：

```yaml
APP_ENV: "production"
APP_DEBUG: "false"
APP_URL: "https://your-domain.com"
APP_KEY: "base64:请替换成你自己的 APP_KEY"
LOG_CHANNEL: "stderr"

APP_HTTP_HOST: "0.0.0.0"
APP_HTTP_PORT: "7002"
MAX_REQUEST: "6600"
APP_FILE_WATCH: "false"
V2BOARD_PERSIST_PATH: "/data"
V2BOARD_FRONTEND_THEME: "default"
V2BOARD_SECURE_PATH: ""
V2BOARD_SUBSCRIBE_PATH: ""
V2BOARD_SERVER_API_URL: ""
V2BOARD_SERVER_TOKEN: ""

DB_CONNECTION: "mysql"
DB_HOST: "mysql"
DB_PORT: "3306"
DB_DATABASE: "v2board"
DB_USERNAME: "你的用户名"
DB_PASSWORD: "你的密码"

CACHE_DRIVER: "redis"
QUEUE_CONNECTION: "redis"
SESSION_DRIVER: "redis"
REDIS_HOST: "redis"
REDIS_PORT: "6379"
REDIS_PASSWORD: "你的 Redis 密码（没有就留空）"
```

生成 `APP_KEY`：

```bash
php artisan key:generate --show
```

---

## 首次部署

### 1) 拉取或构建镜像

```bash
docker compose pull
# 或
docker compose build
```

### 2) 启动服务

```bash
docker compose up -d app queue scheduler
```

### 3) 执行安装

```bash
docker compose exec app php artisan v2board:install
```

首次启动时容器会自动：

- 生成缺失的 `config/v2board.php`
- 初始化当前主题的 `config/theme/<theme>.php`
- 把这些文件保存在宿主机的 `./data` 目录内

> Docker 方案下请先在 compose 里写好 `APP_KEY`、`DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`，不要指望安装命令把它们持久化进容器内 `.env`。

安装命令会：

- 优先读取 compose 注入的环境变量
- 缺失参数时再交互询问
- 导入 `database/install.sql`
- 交互创建管理员账号

> 首次安装前，`queue` / `scheduler` 可能因为数据库尚未初始化而短暂重启，这是正常现象。

### 4) 验证

- Web 正常访问
- 后台可登录
- MySQL 正常
- Redis 正常
- `queue` 容器正常运行
- `scheduler` 容器正常运行

---

## 旧站迁移

> 旧站迁移不是只迁数据库。至少还要同步旧站的面板配置文件和主题配置文件。

### 必迁文件

- 旧站 `config/v2board.php` -> 新宿主机 `./data/config/v2board.php`
- 旧站 `config/theme/<当前主题>.php` -> 新宿主机 `./data/config/theme/<当前主题>.php`
- 旧站使用中的 `APP_KEY` 与 `APP_NAME` 继续保持一致

### 按需迁移的自定义覆盖文件

- 旧站 `resources/rules/custom.*` -> 新宿主机 `./data/custom/rules/`
- 旧站 `public/assets/admin/custom.css` -> 新宿主机 `./data/custom/admin/custom.css`
- 旧站 `public/theme/<theme>/assets/custom.css` -> 新宿主机 `./data/custom/theme/<theme>/assets/custom.css`
- 旧站 `public/theme/<theme>/assets/custom.js` -> 新宿主机 `./data/custom/theme/<theme>/assets/custom.js`
- 旧站 `public/favicon.ico` -> 新宿主机 `./data/custom/public/favicon.ico`

### 推荐切换顺序

1. 备份旧数据库
2. 停掉旧站的 web / queue / scheduler
3. `docker compose up -d app queue scheduler`
4. 把旧文件复制到新项目目录 `./data/...` 路径
5. 执行 `docker compose exec app php artisan v2board:update`
6. 重启 `queue / scheduler`

### 宿主机目录结构

项目目录下会生成并使用这些路径：

- `./data/config/v2board.php`
- `./data/config/theme/*.php`
- `./data/storage/app/public`
- `./data/custom/rules/custom.*`
- `./data/custom/admin/custom.css`
- `./data/custom/theme/<theme>/assets/custom.css`
- `./data/custom/theme/<theme>/assets/custom.js`
- `./data/custom/public/favicon.ico`

### 复制示例

```bash
cp ./config/v2board.php ./data/config/v2board.php
cp ./config/theme/default.php ./data/config/theme/default.php
```

---

## 后续更新

> 更新前请先备份数据库。

推荐流程：

### 1) 拉新镜像或重新构建

```bash
docker compose pull
# 或
docker compose build
```

### 2) 先更新 app

```bash
docker compose up -d app
```

### 3) 执行升级命令

```bash
docker compose exec app php artisan v2board:update
```

### 4) 重启 queue / scheduler

```bash
docker compose up -d queue scheduler
```

> Horizon 是常驻进程，更新后必须重新加载最新代码。

---

## 回滚

### 1) 切回旧镜像 Tag

修改 `docker-compose.yml` 中的镜像版本。

### 2) 重启服务

```bash
docker compose up -d app queue scheduler
```

### 3) 必要时回滚数据库

如果升级改动已经涉及数据库结构或数据，请结合升级前备份回滚。

---

## 日志查看

优先通过容器日志排障：

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler
```

说明：

- `app` 的 HTTP 运行日志直接输出到 stdout/stderr
- `queue` 的 Horizon 日志直接输出到 stdout/stderr
- `scheduler` 每次执行 `schedule:run` 都会打印执行时间

---

## 外置 Nginx 反代示例

```nginx
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_pass http://127.0.0.1:7002;
    }
}
```

---

## 常见问题

### queue 不消费任务

检查：

- `QUEUE_CONNECTION=redis`
- Redis 连接参数是否正确
- `queue` 容器是否正常启动

```bash
docker compose logs -f queue
```

### scheduler 不执行

检查：

- `scheduler` 容器是否在运行
- `SCHEDULE_INTERVAL` 是否合理

```bash
docker compose logs -f scheduler
```

### 更新后功能异常

按顺序检查：

1. 是否执行了 `php artisan v2board:update`
2. `queue` 是否已经重启
3. 是否需要回滚数据库

### 日志在哪里看

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler
```
