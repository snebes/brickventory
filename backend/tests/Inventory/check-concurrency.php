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
if (($argv[1] ?? '') === 'worker') {
    $product = (int) $argv[2];
    $location = (int) $argv[3];
    $inventory->getProduct($product);
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        $inventory->create('orders', ['customer_name' => 'Concurrent buyer', 'location_id' => $location, 'items' => [['product_id' => $product, 'quantity' => 1]]]);
        echo "CREATED\n";
    } catch (Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException) {
        echo "INSUFFICIENT\n";
    }
    exit;
}
$key = bin2hex(random_bytes(4));
$product = $inventory->create('products', ['name' => 'Concurrent stock', 'sku' => 'CONCURRENT-'.$key]);
$location = $inventory->create('locations', ['name' => 'Concurrent bin', 'code' => 'CONCURRENT-'.$key]);
$inventory->create('stock-adjustments', ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => 1]);
$workers = [];
for ($i = 0; $i < 2; ++$i) {
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', $product->id, $location->id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $backend);
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
sort($results);
$connection = $container->get('doctrine')->getConnection();
$quantity = (int) $connection->fetchOne('SELECT quantity FROM inv_balance WHERE product_id = ?', [$product->id]);
if ($results !== ['CREATED', 'INSUFFICIENT'] || 0 !== $quantity) {
    throw new RuntimeException(json_encode([$results, $quantity]));
}
echo json_encode(['concurrent_writers' => $results, 'remaining_stock' => $quantity])."\n";
