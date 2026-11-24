<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for wise-form-schema package tests
 *
 * 兼容两种布局：
 *   - 独立安装：包自带 vendor/
 *   - monorepo：vendor 在 src/ 下（此前硬编码 `../vendor/autoload.php`，仓库内必然失败）
 */

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
    dirname(__DIR__, 5) . '/vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    throw new \RuntimeException(
        '找不到 composer autoload.php，请先在项目根执行 composer install。候选路径：'
        . implode(', ', $autoloadCandidates)
    );
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'wise\\form\\schema\\tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

$appRoot = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'app';
spl_autoload_register(static function (string $class) use ($appRoot): void {
    if (str_starts_with($class, 'app\\')) {
        $file = $appRoot . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
