<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Utils\Helper;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class V2boardInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '安装 v2board';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->printBanner();
            $isDocker = filter_var((string) env('docker', false), FILTER_VALIDATE_BOOLEAN);

            $this->ensureEnvFileExists();

            $appKey = $this->resolveValue('APP_KEY', '请输入 APP_KEY（留空自动生成）');
            if ($appKey === '') {
                $appKey = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));
                if ($isDocker) {
                    $this->warn('未检测到 APP_KEY，已写入当前容器内的 .env。Docker 部署建议把 APP_KEY 固定写入 docker-compose.yml。');
                }
            }

            $this->saveToEnv([
                'APP_KEY' => $appKey,
                'DB_HOST' => $this->resolveValue('DB_HOST', '请输入数据库地址', 'localhost'),
                'DB_PORT' => $this->resolveValue('DB_PORT', '请输入数据库端口', '3306'),
                'DB_DATABASE' => $this->resolveRequiredValue('DB_DATABASE', '请输入数据库名称'),
                'DB_USERNAME' => $this->resolveRequiredValue('DB_USERNAME', '请输入数据库用户名'),
                'DB_PASSWORD' => $this->resolveValue('DB_PASSWORD', '请输入数据库密码', null, true),
            ]);

            \Artisan::call('config:clear');
            \Artisan::call('config:cache');

            try {
                DB::connection()->getPdo();
            } catch (\Throwable $exception) {
                abort(500, '数据库连接失败');
            }

            if ($this->isAlreadyInstalled()) {
                $securePath = config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));
                $this->warn('检测到现有安装，已跳过重复安装。');
                $this->info("访问 http(s)://你的站点/{$securePath} 进入管理面板。");
                return self::SUCCESS;
            }

            $this->importInstallSql();

            $email = '';
            while ($email === '') {
                $email = (string) $this->ask('请输入管理员邮箱');
            }

            $password = Helper::guid(false);
            if (!$this->registerAdmin($email, $password)) {
                abort(500, '管理员账号注册失败，请重试');
            }

            $this->info('安装完成');
            $this->info("管理员邮箱：{$email}");
            $this->info("管理员密码：{$password}");

            $securePath = config('v2board.secure_path', config('v2board.frontend_admin_path', hash('crc32b', config('app.key'))));
            $this->info("访问 http(s)://你的站点/{$securePath} 进入管理面板，你可以在用户中心修改密码。");
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function printBanner(): void
    {
        $this->info("__     ______  ____                      _  ");
        $this->info("\ \   / /___ \| __ )  ___   __ _ _ __ __| | ");
        $this->info(" \ \ / /  __) |  _ \ / _ \ / _` | '__/ _` | ");
        $this->info("  \ V /  / __/| |_) | (_) | (_| | | | (_| | ");
        $this->info("   \_/  |_____|____/ \___/ \__,_|_|  \__,_| ");
    }

    private function ensureEnvFileExists(): void
    {
        if (File::exists(base_path('.env'))) {
            return;
        }

        if (!copy(base_path('.env.example'), base_path('.env'))) {
            abort(500, '复制环境文件失败，请检查目录权限');
        }
    }

    private function importInstallSql(): void
    {
        $file = File::get(base_path('database/install.sql'));
        if (!$file) {
            abort(500, '数据库文件不存在');
        }

        $sql = str_replace("\n", '', $file);
        $statements = preg_split('/;/', $sql);
        if (!is_array($statements)) {
            abort(500, '数据库文件格式有误');
        }

        $this->info('正在导入数据库，请稍等...');
        foreach ($statements as $statement) {
            if (!$statement) {
                continue;
            }
            try {
                DB::select(DB::raw($statement));
            } catch (\Throwable $exception) {
            }
        }
        $this->info('数据库导入完成');
    }

    private function isAlreadyInstalled(): bool
    {
        $schema = DB::connection()->getSchemaBuilder();
        if (!$schema->hasTable('users')) {
            return false;
        }

        return User::query()->where('is_admin', 1)->exists();
    }

    private function registerAdmin(string $email, string $password): bool
    {
        $user = new User();
        $user->email = $email;

        if (strlen($password) < 8) {
            abort(500, '管理员密码长度最少为 8 位字符');
        }

        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;

        return $user->save();
    }

    private function resolveRequiredValue(string $key, string $question): string
    {
        $value = '';
        while ($value === '') {
            $value = $this->resolveValue($key, $question);
        }

        return $value;
    }

    private function resolveValue(string $key, string $question, ?string $default = null, bool $secret = false): string
    {
        $existing = env($key);
        if ($existing !== null && $existing !== '') {
            $displayValue = ($secret || $key === 'APP_KEY') ? '******' : (string) $existing;
            $this->line("使用环境变量 {$key}={$displayValue}");
            return (string) $existing;
        }

        if ($secret) {
            return (string) $this->secret($question);
        }

        return (string) $this->ask($question, $default);
    }

    private function saveToEnv(array $data = []): bool
    {
        foreach ($data as $key => $value) {
            $this->setEnvVar((string) $key, (string) $value);
        }

        return true;
    }

    private function setEnvVar(string $key, string $value): bool
    {
        if (!is_bool(strpos($value, ' '))) {
            $value = '"' . $value . '"';
        }
        $key = strtoupper($key);

        $envPath = app()->environmentFilePath();
        $contents = file_get_contents($envPath);
        preg_match("/^{$key}=[^\r\n]*/m", $contents, $matches);
        $oldValue = count($matches) ? $matches[0] : '';

        if ($oldValue) {
            $contents = str_replace($oldValue, "{$key}={$value}", $contents);
        } else {
            $contents .= "\n{$key}={$value}\n";
        }

        $file = fopen($envPath, 'w');
        fwrite($file, $contents);

        return fclose($file);
    }
}
