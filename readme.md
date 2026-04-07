<img src="https://avatars.githubusercontent.com/u/56885001?s=200&v=4" alt="logo" width="130" height="130" align="right"/>

# V2Board

这是一个带有 `v2node` / `V2bX` 节点后端支持的 V2Board 分支。

## 支持的后端

- [修改版 V2bX](https://github.com/wyx2685/V2bX)
- [v2node](https://github.com/wyx2685/v2node)

## 文档

- 官方文档: https://v2board.com
- 官方更新说明: https://v2board.com/use/update.html
- 官方 aaPanel 部署说明: https://v2board.com/deploy/aapanel.html
- Docker 部署说明: [DEPLOYMENT.md](./DEPLOYMENT.md)

## Docker 特性

当前仓库内置的是一套面向生产环境的 Docker 部署方案：

- 外置 Nginx 只做整站反代
- 外置 MySQL / Redis
- `app` / `queue` / `scheduler` 三服务拆分
- `app` 容器内部自带 `nginx + php-fpm`，直接对外提供完整站点
- 使用项目目录 `./data` 持久化面板配置、主题配置和自定义覆盖
- 更新镜像后不需要再让宿主机同步 `public/` 静态资源
- 日志直接输出到 stdout/stderr

## 快速开始

### 首次部署

```bash
docker compose pull
# 或 docker compose build

docker compose up -d app queue scheduler
docker compose exec app php artisan v2board:install
```

> 外置 Nginx 只需要把整站反代到 `127.0.0.1:7002`。
> 首次部署会自动初始化 `config/v2board.php` 和当前主题配置，无需手工补文件。

### 更新

```bash
docker compose pull
# 或 docker compose build

docker compose up -d app
docker compose exec app php artisan v2board:update
docker compose up -d queue scheduler
```

> 更新前请先备份数据库。
> 当前仓库保持上游固定版本号机制不变；如果浏览器仍命中旧 bundle，强制刷新一次即可。

### 查看日志

```bash
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler
```

## GitHub Actions

仓库内置 GitHub Actions Docker 发布流程：

- PR / Push 会执行 Docker 构建校验
- `master` 分支 push 会发布镜像到 GHCR
- 默认使用仓库根目录 `Dockerfile` 构建镜像
- 详细发布逻辑见 `.github/workflows/docker-publish.yml`

## 旧站迁移提醒

如果你是从旧环境迁移到当前 Docker 方案，除了数据库外，至少还要把旧站的以下文件迁入项目目录 `./data`：

- `config/v2board.php`
- `config/theme/<当前主题>.php`
- 并保持原来的 `APP_KEY` / `APP_NAME`

如果你还做过自定义订阅模板或前端资源覆盖，再额外迁这些文件：

- `resources/rules/custom.*`
- `public/assets/admin/custom.css`
- `public/theme/<theme>/assets/custom.css`
- `public/theme/<theme>/assets/custom.js`
- `public/favicon.ico`

详细迁移路径、外置 Nginx 示例和 1Panel 说明见 [DEPLOYMENT.md](./DEPLOYMENT.md)。

## Sponsors

Thanks to the open source project license provided by [Jetbrains](https://www.jetbrains.com/)

## Community

Telegram Group: [@unofficialV2board](https://t.me/unofficialV2board)
