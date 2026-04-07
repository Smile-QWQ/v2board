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

- 外置 Nginx
- 外置 MySQL / Redis
- compose 注入环境变量
- `app` / `queue` / `scheduler` 三服务拆分
- 日志直接输出到 stdout/stderr

## 快速开始

### 首次部署

```bash
docker compose pull
# 或 docker compose build

docker compose up -d app queue scheduler
docker compose exec app php artisan v2board:install
```

### 更新

```bash
docker compose pull
# 或 docker compose build

docker compose up -d app
docker compose exec app php artisan v2board:update
docker compose up -d queue scheduler
```

> 更新前请先备份数据库。

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

## Sponsors

Thanks to the open source project license provided by [Jetbrains](https://www.jetbrains.com/)

## Community

Telegram Group: [@unofficialV2board](https://t.me/unofficialV2board)
