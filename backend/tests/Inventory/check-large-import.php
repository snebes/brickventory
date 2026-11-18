<?php

// Opt-in real Messenger integration test, never the application database.
$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$url = getenv('INVENTORY_MYSQL_TEST_URL') ?: throw new RuntimeException('Set INVENTORY_MYSQL_TEST_URL to a dedicated migrated test database URL (Symfony appends _test).');
$directory = sys_get_temp_dir().'/brickventory-large-import-'.bin2hex(random_bytes(5));
foreach (['DATABASE_URL' => $url, 'APP_ENV' => 'test', 'APP_DEBUG' => '0', 'INVENTORY_IMPORT_DIRECTORY' => $directory, 'INVENTORY_IMPORT_TEST_TRANSPORT_DSN' => 'doctrine://default?queue_name=large_import_test&auto_setup=0'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($backend.'/.env');
$kernel = new App\Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$db = $container->get('doctrine')->getConnection();
if (!str_ends_with($db->getDatabase(), '_test')) {
    throw new RuntimeException('Only databases ending in _test are allowed.');
}
$rows = (int) ($argv[1] ?? 100000);
if ($rows < 1 || $rows > 250000) {
    throw new RuntimeException('Rows must be 1–250000.');
}
$prefix = 'LARGE-'.bin2hex(random_bytes(5)).'-';
$file = tempnam(sys_get_temp_dir(), 'large-import-');
$stream = fopen($file, 'wb');
fputcsv($stream, ['sku', 'name', 'price', 'cost', 'is_active'], escape: '');
for ($i = 1; $i <= $rows; ++$i) {
    fputcsv($stream, [$prefix.$i, 'Background product '.$i, '1.25', '0.50', 'true'], escape: '');
}
fclose($stream);
$container->get('messenger.transport.inventory_imports')->setup();
$imports = $container->get(App\Inventory\ProductImports::class);
$started = microtime(true);
$job = $imports->upload(new Symfony\Component\HttpFoundation\File\UploadedFile($file, '100k-products.csv', 'text/csv', null, true));
$queueSeconds = microtime(true) - $started;
if (0 != $db->fetchOne('SELECT COUNT(*) FROM inv_product WHERE sku LIKE ?', [$prefix.'%'])) {
    throw new RuntimeException('Upload processed products on the requesting process.');
}
echo json_encode(['queued' => $job['id'], 'rows' => $rows, 'enqueue_seconds' => round($queueSeconds, 3), 'products_before_worker' => 0])."\n";
$log = $directory.'/worker.log';
$worker = proc_open([PHP_BINARY, $backend.'/bin/console', 'messenger:consume', 'inventory_imports', '--env=test', '--no-debug', '--no-interaction', '--time-limit=900', '--memory-limit=256M', '--sleep=100000'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $backend);
if (!is_resource($worker)) {
    throw new RuntimeException('Worker did not start.');
}
$last = microtime(true);
try {
    do {
        usleep(250000);
        $status = $db->fetchAssociative('SELECT status, processed, `cursor`, error FROM inv_product_import WHERE id = ?', [$job['id']]);
        if (microtime(true) - $last >= 10) {
            echo json_encode($status)."\n";
            $last = microtime(true);
        }
        $process = proc_get_status($worker);
        if (!$process['running'] && 'completed' !== $status['status']) {
            throw new RuntimeException('Worker stopped at '.json_encode($status).': '.substr(file_get_contents($log), -6000));
        }
        if (in_array($status['status'], ['failed', 'cancelled'], true)) {
            throw new RuntimeException(json_encode($status).' '.substr(file_get_contents($log), -6000));
        }
        if (microtime(true) - $started > 900) {
            throw new RuntimeException('Worker test timed out.');
        }
    } while ('completed' !== $status['status']);
    $count = (int) $db->fetchOne('SELECT COUNT(*) FROM inv_product WHERE sku LIKE ?', [$prefix.'%']);
    if ($count !== $rows || (int) $status['processed'] !== $rows) {
        throw new RuntimeException('Incorrect import count.');
    }
    // Redelivery of an already committed checkpoint must leave products untouched.
    $container->get(App\MessageHandler\ProcessProductImportHandler::class)(new App\Message\ProcessProductImport($job['id']));
    if ((int) $db->fetchOne('SELECT COUNT(*) FROM inv_product WHERE sku LIKE ?', [$prefix.'%']) !== $rows) {
        throw new RuntimeException('Duplicate delivery changed count.');
    }
    echo json_encode(['completed' => $count, 'seconds' => round(microtime(true) - $started, 2), 'separate_worker_pid' => $process['pid'], 'duplicate_delivery' => 'ignored'])."\n";
} finally {
    $process = proc_get_status($worker);
    if ($process['running']) {
        proc_terminate($worker);
    }
    proc_close($worker);
    $container->get(App\Inventory\ProductImportStorage::class)->delete($job['id']);
    @unlink($log);
    @rmdir($directory);
}
