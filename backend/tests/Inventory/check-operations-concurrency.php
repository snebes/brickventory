<?php

// Opt-in integration check; uses only a dedicated migrated database ending in _test.
$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$url = getenv('INVENTORY_MYSQL_TEST_URL') ?: throw new RuntimeException('Set INVENTORY_MYSQL_TEST_URL to a dedicated migrated database (Symfony appends _test).');
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
    throw new RuntimeException('Only dedicated test databases are permitted.');
}
$inventory = $container->get(App\Inventory\InventoryService::class);
$serials = $container->get(App\Inventory\SerialManagement::class);
$counts = $container->get(App\Inventory\StockCounts::class);
if (($argv[1] ?? '') === 'worker') {
    $mode = $argv[2];
    $id = (int) $argv[3];
    $location = (int) $argv[4];
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        if ('cycle' === $mode) {
            $result = $counts->run($id, true);
            echo count($result['created']) ? "CREATED\n" : "SKIPPED\n";
        } elseif ('hold' === $mode) {
            $serials->save($id, ['status' => 'reserved'], 'SERIAL-ONE');
            echo "HELD\n";
        } else {
            $inventory->create('orders', ['location_id' => $location, 'customer_name' => 'Race test', 'items' => [['product_id' => $id, 'quantity' => 1, 'serials' => ['SERIAL-ONE']]]]);
            echo "SOLD\n";
        }
    } catch (Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException|Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
        echo "REJECTED\n";
    }
    exit;
}
function race(array $jobs, string $backend): array
{
    $workers = [];
    foreach ($jobs as $job) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', ...$job], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $backend);
        if ('READY' !== trim(fgets($pipes[1]))) {
            throw new RuntimeException(stream_get_contents($pipes[2]));
        }
        $workers[] = [$process, $pipes];
    }
    foreach ($workers as [$process, $pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $result = [];
    foreach ($workers as [$process, $pipes]) {
        $result[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (0 !== proc_close($process)) {
            throw new RuntimeException($error.' '.json_encode($result));
        }
    }
    sort($result);

    return $result;
}
$key = bin2hex(random_bytes(5));
$product = $inventory->create('products', ['name' => 'Serial race', 'sku' => 'SERIAL-RACE-'.$key, 'tracking' => 'serial']);
$location = $inventory->create('locations', ['name' => 'Race bin', 'code' => 'RACE-'.$key]);
$serials->save($product->id, ['serial_number' => 'SERIAL-ONE', 'location_id' => $location->id]);
$serialRace = race([['hold', $product->id, $location->id], ['sell', $product->id, $location->id]], $backend);
if (!in_array($serialRace, [['HELD', 'REJECTED'], ['REJECTED', 'SOLD']], true)) {
    throw new RuntimeException(json_encode($serialRace));
}
$plain = $inventory->create('products', ['name' => 'Count race', 'sku' => 'COUNT-RACE-'.$key]);
$inventory->create('stock-adjustments', ['product_id' => $plain->id, 'location_id' => $location->id, 'quantity' => 2]);
$schedule = $counts->save(['name' => 'Count race '.$key, 'frequency' => 'daily', 'scope_type' => 'location', 'scope_id' => $location->id, 'products_per_run' => 10], null);
$countRace = race([['cycle', $schedule['id'], 0], ['cycle', $schedule['id'], 0]], $backend);
if (['CREATED', 'SKIPPED'] !== $countRace) {
    throw new RuntimeException(json_encode($countRace));
}
echo json_encode(['serial_hold_vs_sale' => $serialRace, 'cycle_count_runs' => $countRace])."\n";
