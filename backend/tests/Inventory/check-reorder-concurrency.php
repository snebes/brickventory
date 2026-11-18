<?php

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$url = getenv('INVENTORY_MYSQL_TEST_URL') ?: throw new RuntimeException('Set INVENTORY_MYSQL_TEST_URL to a dedicated migrated test database URL (Symfony appends _test).');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
putenv('DATABASE_URL='.$url);
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';
putenv('APP_ENV=test');
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($backend.'/.env');
$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$connection = $container->get('doctrine')->getConnection();
if (!str_ends_with($connection->getDatabase(), '_test')) {
    throw new RuntimeException('Concurrency checks require a database ending in _test.');
}
$inventory = $container->get(App\Inventory\InventoryService::class);

$replenishment = $container->get(App\Inventory\ReplenishmentService::class);
if (($argv[1] ?? '') === 'worker') {
    $product = (int) $argv[2];
    $requestId = $argv[3];
    $inventory->getProduct($product);
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        $result = $replenishment->createDrafts($requestId, [$product]);
        echo 'CREATED:'.$result['purchase_orders'][0]['id']."\n";
    } catch (Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException) {
        echo "NO_LONGER_DUE\n";
    }
    exit;
}
$summary = [];
foreach (['same-request-id', 'different-request-ids'] as $mode) {
    $key = bin2hex(random_bytes(4));
    $product = $inventory->create('products', ['name' => 'Concurrent replenishment', 'sku' => 'REORDER-'.$key, 'min_stock' => 5]);
    $supplier = $inventory->create('suppliers', ['name' => 'Reorder supplier '.$key]);
    $container->get(App\Inventory\SupplierCatalog::class)->save($product->id, null, ['supplier_id' => $supplier->id, 'cost_price' => '0.50', 'currency' => 'USD']);
    $requestId = Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    $workers = [];
    for ($i = 0; $i < 2; ++$i) {
        $workerId = 'different-request-ids' === $mode ? Symfony\Component\Uid\Uuid::v4()->toRfc4122() : $requestId;
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $product->id, $workerId], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $backend);
        if ('READY' !== trim(fgets($pipes[1]))) {
            throw new RuntimeException('Worker not ready: '.stream_get_contents($pipes[2]));
        }
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (0 !== proc_close($process)) {
            throw new RuntimeException($error);
        }
    }
    $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM inv_document WHERE kind = 'purchase-orders' AND supplier_id = ?", [$supplier->id]);
    sort($results);
    $valid = 'same-request-id' === $mode ? $results[0] === $results[1] && str_starts_with($results[0], 'CREATED:') : str_starts_with($results[0], 'CREATED:') && 'NO_LONGER_DUE' === $results[1];
    if (1 !== $count || !$valid) {
        throw new RuntimeException(json_encode([$mode, $results, $count]));
    }
    $summary[$mode] = ['request_count' => 2, 'created_purchase_orders' => $count, 'results' => $results];
}
echo json_encode($summary)."\n";
