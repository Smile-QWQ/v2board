<?php

require_once __DIR__ . '/vendor/autoload.php';

use Adapterman\Adapterman;
use Workerman\Worker;
use Illuminate\Support\Facades\Cache;
use Workerman\Events\EventInterface;

putenv('APP_RUNNING_IN_CONSOLE=false');
define('MAX_REQUEST', 6600);
define('isWEBMAN', true);

Adapterman::init();

$ncpu = substr_count((string)@file_get_contents('/proc/cpuinfo'), "\nprocessor")+1;

$http_worker                = new Worker('http://127.0.0.1:6600');
$http_worker->count         = $ncpu * 2;
$http_worker->name          = 'AdapterMan';

$http_worker->onWorkerStart = static function () {
    //init();
    require __DIR__.'/start.php';
};

$http_worker->onMessage = static function ($connection, $request) {
    static $request_count = 0;
    static $pid;
    if ($request_count == 1) {
        $pid = posix_getppid();
        Cache::forget("WEBMANPID");
        Cache::forever("WEBMANPID", $pid);
    }
    $connection->send(run());
    if (++$request_count > MAX_REQUEST) {
        Worker::stopAll();
    }
};

// 添加 inotify 文件监控功能
if (extension_loaded('inotify')) {
    $file_monitor_worker = new Worker();
    $file_monitor_worker->name = 'FileMonitor';
    $file_monitor_worker->reloadable = false;
    $monitor_dirs = ['app', 'bootstrap', 'config', 'resources', 'routes', 'public', '.env'];
    $monitor_files = [];

    $file_monitor_worker->onWorkerStart = function ($worker) use ($monitor_dirs, &$monitor_files) {
        if (!extension_loaded('inotify')) {
            echo "FileMonitor: Please install inotify extension.\n";
            return;
        }
        $worker->inotifyFd = inotify_init();
        stream_set_blocking($worker->inotifyFd, 0);

        foreach ($monitor_dirs as $monitor_dir) {
            $monitor_realpath = realpath(__DIR__ . "/{$monitor_dir}");
            addInotify($monitor_realpath, $worker->inotifyFd, $monitor_files);
            if (is_file($monitor_realpath)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($monitor_realpath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $realpath = realpath($file);
                    addInotify($realpath, $worker->inotifyFd, $monitor_files);
                }
            }
        }

        Worker::$globalEvent->add($worker->inotifyFd, EventInterface::EV_READ, function () use ($worker, &$monitor_files) {
            checkFilesChange($worker->inotifyFd, $monitor_files);
        });
    };
}

function addInotify(string $realpath, $fd, &$monitor_files)
{
    $wd = inotify_add_watch($fd, $realpath, IN_MODIFY | IN_CREATE | IN_DELETE);
    $monitor_files[$wd] = $realpath;
}

function checkFilesChange($inotify_fd, &$monitor_files)
{
    $events = inotify_read($inotify_fd);
    if ($events) {
        foreach ($events as $ev) {
            $file = $monitor_files[$ev['wd']];
            echo $file . "/{$ev['name']} updated. Reloading...\n";
        }
        posix_kill(posix_getppid(), SIGUSR1);
    }
}

Worker::runAll();
