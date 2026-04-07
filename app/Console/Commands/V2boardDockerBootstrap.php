<?php

namespace App\Console\Commands;

use App\Services\ThemeService;
use App\Utils\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class V2boardDockerBootstrap extends Command
{
    protected $signature = 'v2board:docker-bootstrap';

    protected $description = 'Bootstrap persistent runtime config for Docker deployment';

    public function handle()
    {
        try {
            $this->ensureThemeConfigDirectory();

            $existingConfig = $this->loadV2boardConfig();
            $defaultConfig = $this->buildDefaultV2boardConfig();
            $v2boardConfig = $this->mergeV2boardConfig($existingConfig, $defaultConfig);

            if ($existingConfig !== $v2boardConfig) {
                $this->writePhpConfig(base_path('config/v2board.php'), $v2boardConfig);
                $this->info(File::exists(base_path('config/v2board.php')) ? '已更新 config/v2board.php 默认项' : '已生成默认 config/v2board.php');
            }

            config(['v2board' => $v2boardConfig]);

            $theme = $this->resolveThemeName($v2boardConfig);
            $themeConfigPath = base_path("config/theme/{$theme}.php");
            if (!File::exists($themeConfigPath)) {
                $themeService = new ThemeService($theme);
                $themeService->init();
                $this->info("已初始化主题配置 config/theme/{$theme}.php");
            }

            if (File::exists($themeConfigPath)) {
                config(["theme.{$theme}" => include $themeConfigPath]);
            }

            Artisan::call('config:clear');
            Artisan::call('config:cache');
            $this->info('Docker 配置 bootstrap 完成');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function ensureThemeConfigDirectory(): void
    {
        File::ensureDirectoryExists(base_path('config/theme'));
    }

    private function loadV2boardConfig(): array
    {
        $path = base_path('config/v2board.php');
        if (!File::exists($path)) {
            return [];
        }

        $config = include $path;
        return is_array($config) ? $config : [];
    }

    private function buildDefaultV2boardConfig(): array
    {
        $securePath = trim((string) env('V2BOARD_SECURE_PATH', ''));
        if ($securePath === '') {
            $securePath = hash('crc32b', (string) config('app.key'));
        }

        $subscribePath = $this->normalizeOptionalPath(env('V2BOARD_SUBSCRIBE_PATH'));
        $serverApiUrl = trim((string) env('V2BOARD_SERVER_API_URL', (string) env('APP_URL', '')));
        if ($serverApiUrl === '') {
            $serverApiUrl = null;
        }

        $serverToken = trim((string) env('V2BOARD_SERVER_TOKEN', ''));
        if ($serverToken === '') {
            $serverToken = Helper::randomChar(32);
        }

        $subscribeUrl = trim((string) env('V2BOARD_SUBSCRIBE_URL', ''));
        if ($subscribeUrl === '') {
            $subscribeUrl = null;
        }

        return [
            'app_name' => env('APP_NAME', 'V2Board'),
            'app_description' => env('V2BOARD_APP_DESCRIPTION', 'V2Board is best!'),
            'app_url' => env('APP_URL', 'http://localhost'),
            'subscribe_url' => $subscribeUrl,
            'subscribe_path' => $subscribePath,
            'frontend_theme' => env('V2BOARD_FRONTEND_THEME', 'default'),
            'frontend_theme_sidebar' => env('V2BOARD_FRONTEND_THEME_SIDEBAR', 'light'),
            'frontend_theme_header' => env('V2BOARD_FRONTEND_THEME_HEADER', 'dark'),
            'frontend_theme_color' => env('V2BOARD_FRONTEND_THEME_COLOR', 'default'),
            'secure_path' => $securePath,
            'server_api_url' => $serverApiUrl,
            'server_token' => $serverToken,
            'email_template' => 'default',
            'currency' => env('V2BOARD_CURRENCY', 'CNY'),
            'currency_symbol' => env('V2BOARD_CURRENCY_SYMBOL', '¥'),
        ];
    }

    private function mergeV2boardConfig(array $existingConfig, array $defaultConfig): array
    {
        $merged = $existingConfig;
        $mustFallbackWhenEmpty = [
            'app_name',
            'app_url',
            'frontend_theme',
            'frontend_theme_sidebar',
            'frontend_theme_header',
            'frontend_theme_color',
            'secure_path',
            'server_api_url',
            'server_token',
            'email_template',
            'currency',
            'currency_symbol',
        ];

        foreach ($defaultConfig as $key => $value) {
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
                continue;
            }

            if (in_array($key, $mustFallbackWhenEmpty, true) && ($merged[$key] === null || $merged[$key] === '')) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function resolveThemeName(array $config): string
    {
        $theme = (string) ($config['frontend_theme'] ?? env('V2BOARD_FRONTEND_THEME', 'default'));
        return $theme !== '' ? $theme : 'default';
    }

    private function normalizeOptionalPath($value): ?string
    {
        $path = trim((string) $value);
        if ($path === '') {
            return null;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function writePhpConfig(string $path, array $config): void
    {
        $data = var_export($config, true);
        $content = sprintf("<?php\n\nreturn %s;\n", $data);
        if (!File::put($path, $content)) {
            throw new \RuntimeException("写入 {$path} 失败");
        }
    }
}
