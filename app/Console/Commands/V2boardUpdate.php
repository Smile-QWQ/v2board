<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class V2boardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2board:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新 v2board';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Artisan::call('config:cache');
        DB::connection()->getPdo();

        $file = File::get(base_path('database/update.sql'));
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

        \Artisan::call('horizon:terminate');
        $this->info('更新完成，请确认 Horizon 进程已经重新加载最新代码。');
    }
}
