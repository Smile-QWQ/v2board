<?php

require_once __DIR__ . '/vendor/autoload.php';

use Adapterman\Adapterman;
use Illuminate\Support\Facades\Cache;
use Workerman\Events\EventInterface;
use Workerman\Worker;

putenv('APP_RUNNING_IN_CONSOLE=false');
define('isWEBMAN', true);

Adapterman::init();

$runningInDocker = filter_var((string) env('docker', false), FILTER_VALIDATE_BOOLEAN);
$defaultHost = $runningInDocker ? '0.0.0.0' : '127.0.0.1';
$defaultPort = $runningInDocker ? 7002 : 6600;
$defaultFileWatch = $runningInDocker ? 'false' : 'true';

$maxRequest = (int) env('MAX_REQUEST', 6600);
if ($maxRequest <= 0) {
    $maxRequest = 6600;
}
define('MAX_REQUEST', $maxRequest);

$listenHost = (string) env('APP_HTTP_HOST', $defaultHost);
$listenPort = (int) env('APP_HTTP_PORT', $defaultPort);
if ($listenPort <= 0) {
    $listenPort = $defaultPort;
}

$workerCount = (int) env('APP_WORKER_COUNT', 0);
$cpuCount = substr_count((string) @file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1;
if ($workerCount <= 0) {
    $workerCount = max($cpuCount * 2, 1);
}

$enableFileWatch = filter_var((string) env('APP_FILE_WATCH', $defaultFileWatch), FILTER_VALIDATE_BOOLEAN);

$httpWorker = new Worker(sprintf('http://%s:%d', $listenHost, $listenPort));
$httpWorker->count = $workerCount;
$httpWorker->name = 'AdapterMan';
$httpWorker->onWorkerStart = static function () {
    require __DIR__ . '/start.php';
};
$httpWorker->onMessage = static function ($connection, $request) {
    static $requestCount = 0;
    static $pid;

    if ($requestCount === 1) {
        $pid = posix_getppid();
        Cache::forget('WEBMANPID');
        Cache::forever('WEBMANPID', $pid);
    }

    $connection->send(run());
    if (++$requestCount > MAX_REQUEST) {
        Worker::stopAll();
    }
};

if ($enableFileWatch && extension_loaded('inotify')) {
    $fileMonitorWorker = new Worker();
    $fileMonitorWorker->name = 'FileMonitor';
    $fileMonitorWorker->reloadable = false;
    $monitorDirs = ['app', 'bootstrap', 'config', 'resources', 'routes', 'public', '.env'];
    $monitorFiles = [];

    $fileMonitorWorker->onWorkerStart = function ($worker) use ($monitorDirs, &$monitorFiles) {
        if (!extension_loaded('inotify')) {
            echo "FileMonitor: inotify extension is not available.\n";
            return;
        }

        $worker->inotifyFd = inotify_init();
        stream_set_blocking($worker->inotifyFd, 0);

        foreach ($monitorDirs as $monitorDir) {
            $monitorRealPath = realpath(__DIR__ . "/{$monitorDir}");
            if ($monitorRealPath === false) {
                continue;
            }

            addInotify($monitorRealPath, $worker->inotifyFd, $monitorFiles);
            if (is_file($monitorRealPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($monitorRealPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $realPath = realpath((string) $file);
                    if ($realPath !== false) {
                        addInotify($realPath, $worker->inotifyFd, $monitorFiles);
                    }
                }
            }
        }

        Worker::$globalEvent->add($worker->inotifyFd, EventInterface::EV_READ, function () use ($worker, &$monitorFiles) {
            checkFilesChange($worker->inotifyFd, $monitorFiles);
        });
    };
} elseif ($enableFileWatch) {
    echo "FileMonitor: inotify extension is not available.\n";
}

function addInotify(string $realPath, $fd, array &$monitorFiles): void
{
    $watchDescriptor = inotify_add_watch($fd, $realPath, IN_MODIFY | IN_CREATE | IN_DELETE);
    $monitorFiles[$watchDescriptor] = $realPath;
}

function checkFilesChange($inotifyFd, array &$monitorFiles): void
{
    $events = inotify_read($inotifyFd);
    if (!$events) {
        return;
    }

    foreach ($events as $event) {
        $file = $monitorFiles[$event['wd']] ?? 'unknown';
        echo $file . "/{$event['name']} updated. Reloading...\n";
    }

    posix_kill(posix_getppid(), SIGUSR1);
}

Worker::runAll();
