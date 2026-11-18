<?php

declare(strict_types=1);

namespace App\Tests\Inventory;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InventoryApiTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        putenv('DATABASE_URL=sqlite:///:memory:');
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        putenv('INVENTORY_API_TOKEN=inventory-functional-test-token');
        $_ENV['INVENTORY_API_TOKEN'] = $_SERVER['INVENTORY_API_TOKEN'] = 'inventory-functional-test-token';
        putenv('INVENTORY_MAIL_FROM=inventory@example.com');
        $_ENV['INVENTORY_MAIL_FROM'] = $_SERVER['INVENTORY_MAIL_FROM'] = 'inventory@example.com';
        putenv('INVENTORY_CURRENCY=USD');
        $_ENV['INVENTORY_CURRENCY'] = $_SERVER['INVENTORY_CURRENCY'] = 'USD';
        $this->client = self::createClient([], ['HTTP_AUTHORIZATION' => 'Bearer inventory-functional-test-token']);
        $this->client->disableReboot();
        $em = self::getContainer()->get('doctrine')->getManager();
        $metadata = array_values(array_filter($em->getMetadataFactory()->getAllMetadata(), fn ($m) => str_starts_with($m->name, 'App\\Entity\\Inventory\\')));
        $em->getConfiguration()->setUseSchemaEditorApi(true);
        (new SchemaTool($em))->createSchema($metadata);
    }

    private function call(string $method, string $path, array $body = [], int $expected = 200): array
    {
        $this->client->jsonRequest($method, '/api/v1/'.$path, $body);
        self::assertResponseStatusCodeSame($expected, $this->client->getResponse()->getContent());

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    private function fixtures(): array
    {
        $p = $this->call('POST', 'products', ['sku' => 'PART-1', 'name' => 'Brick', 'price' => '1.25'], 201)['data']['id'];
        $a = $this->call('POST', 'locations', ['name' => 'A', 'code' => 'A'], 201)['data']['id'];
        $b = $this->call('POST', 'locations', ['name' => 'B', 'code' => 'B'], 201)['data']['id'];

        return [$p, $a, $b];
    }

    public function testProductOperationsAreAtomicAndDoNotCopyStock(): void
    {
        [$p, $a] = $this->fixtures();
        $copy = $this->call('POST', "products/$p/duplicate", ['sku' => 'COPY-1'], 201)['data'];
        self::assertSame(0, $copy['stock']);
        $id = $copy['id'];
        $this->call('POST', 'products/bulk/price', ['ids' => [$p, $id], 'type' => 'percentage', 'value' => '10']);
        self::assertSame('1.38', $this->call('GET', "products/$p")['data']['price']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1], 201);
        $this->call('POST', 'products/bulk/delete', ['ids' => [$id, $p]], 422);
        self::assertSame('COPY-1', $this->call('GET', "products/$id")['data']['sku']);
        $this->call('POST', 'products/bulk/price', ['ids' => [$id], 'type' => 'fixed', 'value' => '-20']);
        self::assertSame('0.00', $this->call('GET', "products/$id")['data']['price']);
    }

    public function testWarehouseLevelsAndDefault(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $w = $this->call('POST', 'warehouses', ['name' => 'North'], 201)['data']['id'];
        $x = $this->call('POST', 'warehouses', ['name' => 'South'], 201)['data']['id'];
        $this->call('PATCH', "locations/$a", ['warehouse_id' => $w]);
        $this->call('PATCH', "locations/$b", ['warehouse_id' => $x]);
        $this->call('POST', "warehouses/$w/set-default");
        $this->call('POST', "warehouses/$x/set-default");
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $rows = $this->call('PUT', "products/$p/warehouse-levels", ['levels' => [['warehouse_id' => $w, 'min_stock' => 6, 'max_stock' => 10]]])['data'];
        self::assertTrue($rows[0]['low_stock']);
        self::assertFalse($rows[0]['is_default']);
        self::assertTrue($rows[1]['is_default']);
        self::assertCount(1, $this->call('GET', "stock-balances?warehouse_id=$w")['data']);
        self::assertCount(0, $this->call('GET', "stock-balances?warehouse_id=$x")['data']);
        $this->call('PUT', "products/$p/warehouse-levels", ['levels' => [['warehouse_id' => $w, 'min_stock' => 20, 'max_stock' => 10]]], 422);
    }

    public function testCycleCountsNeedRecordedAndReviewedCounts(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $schedule = $this->call('POST', 'cycle-counts', ['name' => 'Daily count', 'frequency' => 'daily', 'scope_type' => 'location', 'scope_id' => $a, 'products_per_run' => 10], 201)['data']['id'];
        $run = $this->call('POST', "cycle-counts/$schedule/run")['data'];
        self::assertCount(1, $run['created']);
        $id = $run['created'][0]['id'];
        self::assertSame([], $this->call('POST', "cycle-counts/$schedule/run")['data']['created']);
        $this->call('POST', "stock-audits/$id/complete", [], 422);
        $this->call('POST', "stock-audits/$id/items/1/count", ['quantity' => 4]);
        $this->call('POST', "stock-audits/$id/items/1/review");
        $this->call('POST', "stock-audits/$id/items/1/count", ['quantity' => 3]);
        $this->call('POST', "stock-audits/$id/complete", [], 422);
        self::assertCount(2, $this->call('GET', "stock-audits/$id/items/1/counts")['data']);
        $this->call('POST', "stock-audits/$id/items/1/review");
        $this->call('POST', "stock-audits/$id/complete");
        self::assertSame(3, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
    }

    public function testTrackedProductCreationAndDuplication(): void
    {
        $p = $this->call('POST', 'products', ['sku' => 'SERIAL-CREATE', 'name' => 'Tracked', 'tracking' => 'serial'], 201)['data'];
        self::assertSame('serial', $p['details']['tracking']);
        $copy = $this->call('POST', "products/{$p['id']}/duplicate", ['sku' => 'SERIAL-COPY'], 201)['data'];
        self::assertSame('serial', $copy['details']['tracking']);
        self::assertSame(0, $copy['stock']);
    }

    public function testSerialAvailabilityAndHistory(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'serial']);
        $this->call('POST', "products/$p/serials", ['serial_number' => 'SER-1', 'location_id' => $a, 'notes' => 'Warranty'], 201);
        $this->call('POST', "products/$p/serials", ['serial_number' => 'SER-1'], 409);
        $this->call('PATCH', "products/$p/serials/SER-1", ['status' => 'damaged']);
        self::assertSame(0, $this->call('GET', 'stock-balances')['data'][0]['available_quantity']);
        $body = ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1, 'serials' => ['SER-1']]]];
        $this->call('POST', 'orders', $body, 422);
        $this->call('PATCH', "products/$p/serials/SER-1", ['status' => 'available']);
        $this->call('POST', 'orders', $body, 201);
        self::assertSame('sold', $this->call('GET', "products/$p/serials")['data'][0]['status']);
        $this->call('PATCH', "products/$p/serials/SER-1", ['status' => 'available'], 422);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1, 'type' => 'return', 'serials' => ['SER-1']], 422);
        self::assertCount(2, $this->call('GET', "products/$p/serials/SER-1/history")['data']);
    }

    public function testFulfillmentDocumentsAndTracking(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $id = $this->call('POST', 'orders', ['location_id' => $a, 'customer_name' => '<script>buyer</script>', 'items' => [['product_id' => $p, 'quantity' => 3]]], 201)['data']['id'];
        $this->call('POST', "orders/$id/process");
        $this->call('POST', "orders/$id/ship", ['items' => [['id' => 1, 'quantity' => 1]], 'carrier' => 'Manual']);
        self::assertCount(1, $this->call('GET', 'shipments')['data']);
        $this->call('PATCH', "orders/$id/shipments/1", ['tracking_number' => 'TRACK-1']);
        self::assertSame('TRACK-1', $this->call('GET', 'shipments?search=TRACK-1')['data'][0]['tracking_number']);
        $html = self::getContainer()->get(\App\Inventory\Fulfillment::class)->html($id, 1);
        self::assertStringNotContainsString('Unit price', $html);
        self::assertStringNotContainsString('1.25', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        foreach (["orders/$id/pick-list", "orders/$id/shipments/1/packing-slip"] as $path) {
            $this->client->request('GET', '/api/v1/'.$path);
            self::assertResponseIsSuccessful();
            self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
        }
    }

    public function testOrderCancellationRestocksExactlyOnce(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 10], 201);
        $customer = $this->call('POST', 'customers', ['name' => 'Buyer', 'email' => 'buyer@example.com'], 201)['data']['id'];
        $order = $this->call('POST', 'orders', ['customer_id' => $customer, 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 3], ['product_id' => $p, 'quantity' => 2]]], 201)['data']['id'];
        self::assertSame(5, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        $this->call('POST', "orders/$order/cancel");
        $this->call('POST', "orders/$order/cancel");
        self::assertSame(10, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        self::assertCount(5, $this->call('GET', 'stock-movements')['data']);
    }

    public function testPartialReceiptAndTransfer(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'items' => [['product_id' => $p, 'quantity' => 10]]], 201)['data']['id'];
        $this->call('POST', "purchase-orders/$po/send");
        $receipt = ['items' => [['id' => 1, 'quantity_to_receive' => 4, 'receiving_location_id' => $a]]];
        self::assertSame('partial', $this->call('POST', "purchase-orders/$po/receive", $receipt)['data']['status']);
        $receipt['items'][0]['quantity_to_receive'] = 6;
        self::assertSame('received', $this->call('POST', "purchase-orders/$po/receive", $receipt)['data']['status']);
        $transfer = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 3]]], 201)['data']['id'];
        foreach (['prepare', 'ship', 'complete'] as $action) {
            $this->call('POST', "stock-transfers/$transfer/$action");
        }
        $balances = $this->call('GET', 'stock-balances')['data'];
        self::assertSame(3, $balances[0]['quantity']);
        self::assertSame(7, $balances[1]['quantity']);
    }

    public function testInsufficientStockRollsBackEveryLine(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 3], ['product_id' => $p, 'quantity' => 3]]], 422);
        // A failed Doctrine transaction closes the manager; reconnect to the same SQLite connection.
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(5, (int) $connection->fetchOne('SELECT quantity FROM inv_balance'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM inv_movement'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM inv_document'));
    }

    public function testInvalidPayloadAndMissingRecords(): void
    {
        $this->call('GET', 'products/999', [], 404);
        $this->call('POST', 'products', ['name' => 'Missing SKU'], 422);
    }

    public function testOverReceiptIsAtomic(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'items' => [['product_id' => $p, 'quantity' => 5]]], 201)['data']['id'];
        $this->call('POST', "purchase-orders/$po/send");
        $this->call('POST', "purchase-orders/$po/receive", ['items' => [['id' => 1, 'quantity_to_receive' => 6, 'receiving_location_id' => $a]]], 422);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM inv_movement'));
        self::assertSame('sent', $connection->fetchOne('SELECT status FROM inv_document'));
    }

    public function testUnknownFieldsAreRejected(): void
    {
        $this->call('POST', 'products', ['name' => 'Brick', 'sku' => 'PART', 'stock' => 100], 422);
    }

    public function testAdjustmentListExcludesSalesAndCancellationReturns(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 2]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/cancel");
        self::assertSame(1, $this->call('GET', 'stock-adjustments')['meta']['total']);
        self::assertSame(3, $this->call('GET', 'stock-movements')['meta']['total']);
    }

    public function testUnsupportedDocumentFieldsAreRejected(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'supplier_id' => 1, 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1]]], 422);
    }

    public function testUnsupportedLineFieldsAreRejected(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1, 'discount' => '10.00']]], 422);
    }

    public function testStockOverflowRollsBack(): void
    {
        [$p, $a] = $this->fixtures();
        for ($i = 0; $i < 2; ++$i) {
            $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1000000000], 201);
        }
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1000000000], 422);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(2000000000, (int) $connection->fetchOne('SELECT quantity FROM inv_balance'));
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM inv_movement'));
    }

    public function testMissingAndInvalidTokensAreRejected(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->call('GET', 'products', [], 401);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer wrong-token');
        $this->call('GET', 'products', [], 401);
        $this->call('POST', 'products', ['sku' => 'NO', 'name' => 'Unauthorized'], 401);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer inventory-functional-test-token');
        self::assertSame(0, $this->call('GET', 'products')['meta']['total']);
    }

    public function testUnconfiguredTokenRejectsAccess(): void
    {
        putenv('INVENTORY_API_TOKEN=');
        $_ENV['INVENTORY_API_TOKEN'] = $_SERVER['INVENTORY_API_TOKEN'] = '';
        $this->call('GET', 'products', [], 401);
    }

    public function testPricingEditsPaymentsAndReturns(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 10], 201);
        $body = ['customer_name' => 'Buyer', 'location_id' => $a, 'shipping' => '2.00', 'tax' => '1.00', 'discount_type' => 'percent', 'discount_value' => '10', 'items' => [['product_id' => $p, 'quantity' => 4, 'unit_price' => '5.00', 'discount_type' => 'fixed', 'discount_value' => '2.00', 'tax' => '0.50']]];
        $order = $this->call('POST', 'orders', $body, 201)['data'];
        self::assertSame('19.70', $order['total']);
        $id = $order['id'];
        $updated = $this->call('PATCH', "orders/$id", ['items' => [['product_id' => $p, 'quantity' => 2, 'unit_price' => '5.00']]])['data'];
        self::assertSame('12.00', $updated['total']);
        self::assertSame(8, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        self::assertSame('paid', $this->call('POST', "orders/$id/pay", ['amount' => '12.00', 'reference' => 'PAY-1'])['data']['payment_status']);
        $this->call('POST', "orders/$id/process");
        $this->call('POST', "orders/$id/ship", ['tracking_number' => 'TRACK']);
        $this->call('POST', "orders/$id/return", ['items' => [['id' => 1, 'quantity' => 1, 'receiving_location_id' => $a]]]);
        self::assertSame(9, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        self::assertSame('partial', $this->call('POST', "orders/$id/refund", ['amount' => '5.00', 'reference' => 'REF-1'])['data']['payment_status']);
    }

    public function testVariantsCategoriesAndWarehouses(): void
    {
        $category = $this->call('POST', 'categories', ['name' => 'Bricks'], 201)['data']['id'];
        $warehouse = $this->call('POST', 'warehouses', ['name' => 'Main', 'address' => 'One Street'], 201)['data']['id'];
        $location = $this->call('POST', 'locations', ['name' => 'Bin', 'code' => 'B', 'warehouse_id' => $warehouse, 'aisle' => '1'], 201)['data']['id'];
        $product = $this->call('POST', 'products', ['name' => 'Brick', 'sku' => 'BRICK', 'category_id' => $category, 'options' => ['color' => ['red', 'blue']]], 201)['data']['id'];
        $variant = $this->call('POST', 'variants', ['parent_id' => $product, 'name' => 'Red brick', 'sku' => 'RED', 'barcode' => '12345', 'attributes' => ['color' => 'red']], 201)['data']['id'];
        $this->call('POST', 'stock-adjustments', ['product_id' => $product, 'product_variant_id' => $variant, 'location_id' => $location, 'quantity' => 5], 201);
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $location, 'items' => [['product_id' => $product, 'product_variant_id' => $variant, 'quantity' => 2]]], 201);
        self::assertSame(3, $this->call('GET', "variants/$variant")['data']['stock']);
        self::assertSame(3, $this->call('GET', "products/$product")['data']['stock']);
        self::assertSame($warehouse, $this->call('GET', "locations/$location")['data']['warehouse_id']);
        self::assertSame(1, $this->call('GET', 'products')['meta']['total']);
    }

    public function testTransferPreparationReservesAndCancellationReleasesStock(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 10], 201);
        $id = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 7]]], 201)['data']['id'];
        $this->call('POST', "stock-transfers/$id/prepare");
        self::assertSame(3, $this->call('GET', 'stock-balances')['data'][0]['available_quantity']);
        $this->call('POST', "stock-transfers/$id/cancel");
        self::assertSame(10, $this->call('GET', 'stock-balances')['data'][0]['available_quantity']);
    }

    public function testStockCountCreatesOnlyTheDifference(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 10], 201);
        $id = $this->call('POST', 'stock-audits', ['location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 8]]], 201)['data']['id'];
        $this->call('POST', "stock-audits/$id/complete");
        self::assertSame(8, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        self::assertSame(-2, $this->call('GET', 'stock-movements')['data'][0]['quantity']);
    }

    public function testContactsSearchSortingAndCustomerHistory(): void
    {
        $a = $this->call('POST', 'customers', ['name' => 'Zulu', 'state' => 'IL', 'zip_code' => '60601', 'metadata' => ['source' => 'test']], 201)['data'];
        $this->call('POST', 'customers', ['name' => 'Alpha', 'is_active' => false], 201);
        $list = $this->call('GET', 'customers?sort_by=name&sort_dir=asc&per_page=1');
        self::assertSame('Alpha', $list['data'][0]['name']);
        self::assertSame(2, $list['meta']['total']);
        self::assertSame(1, $this->call('GET', 'customers?search=zulu&is_active=true')['meta']['total']);
        self::assertSame(0, $this->call('GET', 'customers/'.$a['id'].'/orders')['meta']['total']);
    }

    public function testSerialReceiptSaleTransferAndReturn(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'serial']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 3, 'serials' => ['S1', 'S2', 'S3']], 201);
        $transfer = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 1, 'serials' => ['S3']]]], 201)['data']['id'];
        foreach (['prepare', 'ship', 'complete'] as $action) {
            $this->call('POST', "stock-transfers/$transfer/$action");
        }
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 2, 'serials' => ['S1', 'S2']]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/process");
        $this->call('POST', "orders/$order/ship");
        $this->call('POST', "orders/$order/return", ['items' => [['id' => 1, 'quantity' => 1, 'receiving_location_id' => $a, 'serials' => ['S1']]]]);
        $traces = $this->call('GET', 'trace-balances')['data'];
        $stock = [];
        foreach ($traces as $trace) {
            $stock[$trace['serial']] = ($stock[$trace['serial']] ?? 0) + $trace['quantity'];
        }
        self::assertSame(1, $stock['S1']);
        self::assertSame(0, $stock['S2']);
        self::assertSame(1, $stock['S3']);
    }

    public function testDuplicateSerialRollsBack(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'serial']);
        $body = ['product_id' => $p, 'location_id' => $a, 'quantity' => 1, 'serials' => ['S1']];
        $this->call('POST', 'stock-adjustments', $body, 201);
        $this->call('POST', 'stock-adjustments', $body, 422);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT quantity FROM inv_balance'));
    }

    public function testBatchExpiryBlocksIssuing(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'batch']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5, 'batch' => 'OLD', 'expires_at' => '2000-01-01'], 201);
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1, 'batch' => 'OLD']]], 422);
    }

    public function testApprovalsGateShippingAndStatusUpdates(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $order = $this->call('POST', 'orders', ['approval_required' => true, 'customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/approve");
        $this->call('POST', "orders/$order/process");
        self::assertSame('shipped', $this->call('PATCH', "orders/$order", ['status' => 'shipped'])['data']['status']);
    }

    public function testPendingApprovalCannotBeBypassedByPatch(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $order = $this->call('POST', 'orders', ['approval_required' => true, 'customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1]]], 201)['data']['id'];
        $this->call('PATCH', "orders/$order", ['status' => 'processing']);
        $this->call('PATCH', "orders/$order", ['status' => 'shipped'], 422);
    }

    public function testPartialShippingAndPartialPurchaseCancellation(): void
    {
        [$p, $a] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'items' => [['product_id' => $p, 'quantity' => 10]]], 201)['data']['id'];
        $this->call('POST', "purchase-orders/$po/send");
        $this->call('POST', "purchase-orders/$po/receive", ['items' => [['id' => 1, 'quantity_to_receive' => 5, 'receiving_location_id' => $a]]]);
        $this->call('POST', "purchase-orders/$po/cancel");
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 4]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/process");
        self::assertSame('processing', $this->call('POST', "orders/$order/ship", ['items' => [['id' => 1, 'quantity' => 2]]])['data']['status']);
        self::assertSame('shipped', $this->call('POST', "orders/$order/ship")['data']['status']);
    }

    public function testCountDetectsInterveningStockMovement(): void
    {
        [$p, $a] = $this->fixtures();
        $id = $this->call('POST', 'stock-audits', ['location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 8]]], 201)['data']['id'];
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1], 201);
        $this->call('POST', "stock-audits/$id/complete", [], 422);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(1, (int) $connection->fetchOne('SELECT quantity FROM inv_balance'));
    }

    public function testBarcodeImportExportAndPdf(): void
    {
        $import = $this->call('POST', 'products/import', ['rows' => [['name' => 'Imported', 'sku' => 'IMP', 'barcode' => '9876', 'price' => '1.00']]])['data'][0];
        self::assertSame($import['id'], $this->call('GET', 'barcode-lookup/9876')['data']['id']);
        $this->client->request('GET', '/api/v1/products/export');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('IMP,Imported', $csv);
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'items' => [['product_id' => $import['id'], 'quantity' => 1]]], 201)['data']['id'];
        $this->client->request('GET', "/api/v1/purchase-orders/$po/document");
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
    }

    public function testAttachmentUploadDownloadAndDelete(): void
    {
        [$p] = $this->fixtures();
        $path = tempnam(sys_get_temp_dir(), 'inventory-image-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        try {
            $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'brick.png', 'image/png', null, true);
            $this->client->request('POST', "/api/v1/products/$p/attachments", [], ['file' => $file]);
            self::assertResponseStatusCodeSame(201, $this->client->getResponse()->getContent());
            $id = json_decode($this->client->getResponse()->getContent(), true)['data']['id'];
            $this->client->request('GET', "/api/v1/products/$p/attachments/$id");
            self::assertResponseIsSuccessful();
            self::assertSame(file_get_contents($path), $this->client->getResponse()->getContent());
            $this->call('DELETE', "products/$p/attachments/$id", [], 204);
        } finally {
            unlink($path);
        }
    }

    public function testReturnRequestApprovalAndReceipt(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 3]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/process");
        $this->call('POST', "orders/$order/ship");
        $return = $this->call('POST', 'order-returns', ['order_id' => $order, 'reason' => 'Wrong item', 'items' => [['id' => 1, 'quantity' => 2, 'receiving_location_id' => $a]]], 201)['data']['id'];
        self::assertSame(2, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        $this->call('POST', "order-returns/$return/approve");
        $this->call('POST', "order-returns/$return/receive");
        self::assertSame(4, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
    }

    public function testTrackedCountAndDirectTransferCompletion(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'serial']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 2, 'serials' => ['A', 'B']], 201);
        $count = $this->call('POST', 'stock-audits', ['location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 2, 'serials' => ['B', 'C']]]], 201)['data']['id'];
        $this->call('POST', "stock-audits/$count/complete");
        $transfer = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 1, 'serials' => ['C']]]], 201)['data']['id'];
        $this->call('POST', "stock-transfers/$transfer/complete");
        self::assertSame(2, $this->call('GET', "products/$p")['data']['stock']);
    }

    public function testCsvImportAndCatalogFilters(): void
    {
        [$p, $a] = $this->fixtures();
        $path = tempnam(sys_get_temp_dir(), 'inventory-csv-');
        file_put_contents($path, "sku,name,price,min_stock,is_active\nCSV-1,Imported,2.25,4,true\n");
        try {
            $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'products.csv', 'text/csv', null, true);
            $this->client->request('POST', '/api/v1/products/import-csv', [], ['file' => $file]);
            self::assertResponseIsSuccessful();
            self::assertSame(2, $this->call('GET', 'products?low_stock=true')['meta']['total']);
            $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1], 201);
            self::assertSame(1, $this->call('GET', "products?location_id=$a")['meta']['total']);
            self::assertSame(1, $this->call('GET', 'products?low_stock=true')['meta']['total']);
        } finally {
            unlink($path);
        }
    }

    public function testSupplierPricesAndDocumentEmailQueue(): void
    {
        putenv('INVENTORY_MAIL_FROM=inventory@example.com');
        $_ENV['INVENTORY_MAIL_FROM'] = $_SERVER['INVENTORY_MAIL_FROM'] = 'inventory@example.com';
        [$p] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com', 'code' => 'SUP-1'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'currency' => 'USD', 'items' => [['product_id' => $p, 'quantity' => 5, 'unit_cost' => '0.75', 'supplier_sku' => 'SUP-BRICK']]], 201)['data']['id'];
        self::assertSame('0.75', $this->call('GET', "suppliers/$supplier/price-history")['data'][0]['items'][0]['unit_cost']);
        $this->call('POST', "purchase-orders/$po/email", [], 202);
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAttachmentCount($email, 1);
        self::assertEmailAddressContains($email, 'To', 'supplier@example.com');
    }

    public function testOrderEditFailurePreservesOriginalAllocation(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 2]]], 201)['data']['id'];
        $this->call('PATCH', "orders/$order", ['items' => [['product_id' => $p, 'quantity' => 6]]], 422);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(3, (int) $connection->fetchOne('SELECT quantity FROM inv_balance'));
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM inv_movement'));
        self::assertSame(2, json_decode($connection->fetchOne('SELECT items FROM inv_document WHERE id = ?', [$order]), true)[0]['quantity']);
    }

    public function testReservedSerialCannotBeSold(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['tracking' => 'serial']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 2, 'serials' => ['ONE', 'TWO']], 201);
        $transfer = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 1, 'serials' => ['ONE']]]], 201)['data']['id'];
        $this->call('POST', "stock-transfers/$transfer/prepare");
        $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1, 'serials' => ['ONE']]]], 422);
        self::assertSame(2, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT quantity FROM inv_balance'));
    }

    public function testFailedPurchaseOrderEmailDoesNotMarkSent(): void
    {
        [$p] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'No email'], 201)['data']['id'];
        $po = $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'items' => [['product_id' => $p, 'quantity' => 1]]], 201)['data']['id'];
        $this->call('POST', "purchase-orders/$po/send", [], 422);
        self::assertSame('draft', self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT status FROM inv_document WHERE id = ?', [$po]));
        self::assertQueuedEmailCount(0);
    }

    public function testRefundCannotExceedReceivedPayments(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 1], 201);
        $order = $this->call('POST', 'orders', ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1]]], 201)['data']['id'];
        $this->call('POST', "orders/$order/pay", ['amount' => '1.00', 'reference' => 'ONE']);
        $this->call('POST', "orders/$order/refund", ['amount' => '1.01'], 422);
        $details = json_decode(self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT details FROM inv_document WHERE id = ?', [$order]), true);
        self::assertCount(1, $details['payments']);
    }

    public function testDashboardEmptyAndAuthenticated(): void
    {
        $dashboard = $this->call('GET', 'dashboard')['data'];
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame(0, $dashboard['stats']['totalProducts']);
        self::assertSame('0.00', $dashboard['stats']['totalValue']);
        self::assertCount(7, $dashboard['stockMovements']);
        self::assertSame([], $dashboard['recentOrders']);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->call('GET', 'dashboard', [], 401);
    }

    public function testDashboardRollsVariantsIntoRootWithoutDoubleCounting(): void
    {
        [$p, $a] = $this->fixtures();
        $category = $this->call('POST', 'categories', ['name' => 'Bricks'], 201)['data']['id'];
        $this->call('PATCH', "products/$p", ['category_id' => $category, 'min_stock' => 10, 'options' => ['color' => ['red']]]);
        $variant = $this->call('POST', 'variants', ['parent_id' => $p, 'name' => 'Red', 'sku' => 'RED', 'attributes' => ['color' => 'red'], 'price' => '2.00', 'cost' => '0.50'], 201)['data']['id'];
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 2], 201);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'product_variant_id' => $variant, 'location_id' => $a, 'quantity' => 3], 201);
        $dashboard = $this->call('GET', 'dashboard')['data'];
        self::assertSame(1, $dashboard['stats']['totalProducts']);
        self::assertSame(1, $dashboard['stats']['lowStockProducts']);
        self::assertSame('8.50', $dashboard['stats']['totalValue']);
        self::assertSame(5, $dashboard['lowStockProducts'][0]['stock']);
        self::assertSame('8.50', $dashboard['stockByCategory'][0]['value']);
        self::assertSame(5, $dashboard['reorderSuggestions'][0]['suggested_quantity']);
        self::assertTrue($dashboard['reorderSuggestions'][0]['has_variants']);
        self::assertSame(5, array_sum(array_column($dashboard['stockMovements'], 'inbound')));
    }

    public function testDashboardSeparatesCurrenciesAndClampsIndividualReceivables(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 20], 201);
        $base = ['customer_name' => 'Buyer', 'location_id' => $a, 'items' => [['product_id' => $p, 'quantity' => 1, 'unit_price' => '10.00']]];
        $one = $this->call('POST', 'orders', $base + ['currency' => 'USD'], 201)['data']['id'];
        $this->call('POST', "orders/$one/pay", ['amount' => '15.00']);
        $this->call('POST', 'orders', $base + ['currency' => 'USD'], 201);
        $this->call('POST', 'orders', $base + ['currency' => 'EUR'], 201);
        $cancelled = $this->call('POST', 'orders', $base + ['currency' => 'USD'], 201)['data']['id'];
        $this->call('POST', "orders/$cancelled/cancel");
        $old = $this->call('POST', 'orders', $base + ['currency' => 'USD', 'order_date' => '2000-01-01'], 201)['data']['id'];
        $dashboard = $this->call('GET', 'dashboard')['data'];
        self::assertSame('20.00', $dashboard['stats']['revenueThisMonth']);
        self::assertSame([['currency' => 'USD', 'amount' => '20.00'], ['currency' => 'EUR', 'amount' => '10.00']], $dashboard['stats']['byCurrency']['revenueThisMonth']);
        self::assertSame('20.00', $dashboard['stats']['outstandingReceivables']);
        self::assertSame(5, $dashboard['stats']['totalOrders']);
        self::assertSame($old, $dashboard['recentOrders'][0]['id']);
    }

    public function testDashboardDeadStockAndSupplierReorderContext(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['min_stock' => 10, 'cost' => '0.50']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 2], 201);
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier, 'currency' => 'EUR', 'cost_price' => '0.75'], 201);
        $this->call('POST', 'purchase-orders', ['supplier_id' => $supplier, 'currency' => 'EUR', 'items' => [['product_id' => $p, 'quantity' => 5, 'unit_cost' => '0.75']]], 201);
        self::getContainer()->get('doctrine')->getConnection()->executeStatement('UPDATE inv_product SET created_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $p]);
        $dashboard = $this->call('GET', 'dashboard')['data'];
        self::assertSame(1, $dashboard['stats']['deadStockCount']);
        self::assertSame('1.00', $dashboard['stats']['deadStockValue']);
        self::assertSame($supplier, $dashboard['reorderSuggestions'][0]['supplier_id']);
        self::assertSame('EUR', $dashboard['reorderSuggestions'][0]['currency']);
        self::assertSame('0.75', $dashboard['reorderSuggestions'][0]['unit_cost']);
        self::assertSame(1, $dashboard['stats']['pendingPurchaseOrders']);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => -1], 201);
        self::assertSame(0, $this->call('GET', 'dashboard')['data']['stats']['deadStockCount']);
    }

    public function testSupplierCatalogPrimarySwitchAndVariantLinks(): void
    {
        [$p] = $this->fixtures();
        $one = $this->call('POST', 'suppliers', ['name' => 'One'], 201)['data']['id'];
        $two = $this->call('POST', 'suppliers', ['name' => 'Two'], 201)['data']['id'];
        $a = $this->call('POST', "products/$p/suppliers", ['supplier_id' => $one, 'cost_price' => '0.25', 'currency' => 'USD', 'supplier_sku' => 'ONE', 'minimum_order_quantity' => 5], 201)['data'];
        self::assertTrue($a['is_primary']);
        $b = $this->call('POST', "products/$p/suppliers", ['supplier_id' => $two, 'cost_price' => '0.50'], 201)['data']['id'];
        $updated = $this->call('PATCH', "products/$p/suppliers/$b", ['is_primary' => true, 'cost_price' => '0.75', 'currency' => 'EUR', 'lead_time_days' => 7])['data'];
        self::assertSame('0.75', $updated['cost_price']);
        self::assertSame('EUR', $updated['currency']);
        self::assertSame(7, $updated['lead_time_days']);
        $links = $this->call('GET', "products/$p/suppliers")['data'];
        self::assertSame($b, $links[0]['id']);
        self::assertFalse($links[1]['is_primary']);
        self::assertSame($p, $this->call('GET', "suppliers/$one/products")['data'][0]['product_id']);
        $this->call('DELETE', "products/$p/suppliers/$b", [], 204);
        self::assertSame(1, $this->call('GET', "products/$p/suppliers")['meta']['total']);
    }

    public function testReplenishmentDraftsAreIdempotentAndAccountForIncomingStock(): void
    {
        [$p, $a] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['min_stock' => 4, 'reorder_point' => 4, 'max_stock' => 10]);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 2], 201);
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier', 'email' => 'supplier@example.com'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier, 'cost_price' => '0.50', 'currency' => 'EUR', 'minimum_order_quantity' => 5], 201);
        $suggestion = $this->call('GET', 'replenishment')['data'][0];
        self::assertSame(8, $suggestion['suggested_quantity']);
        self::assertSame(2, $suggestion['available_quantity']);
        $body = ['request_id' => '00000000-0000-4000-8000-000000000001', 'product_ids' => [$p]];
        $result = $this->call('POST', 'replenishment/drafts', $body, 201)['data'];
        self::assertSame($result, $this->call('POST', 'replenishment/drafts', $body, 201)['data']);
        $po = $result['purchase_orders'][0];
        self::assertSame('draft', $po['status']);
        self::assertSame('EUR', $po['details']['currency']);
        self::assertSame('4.00', $po['total']);
        self::assertSame(0, $this->call('GET', 'replenishment')['meta']['total']);
        self::assertSame(2, $this->call('GET', 'stock-balances')['data'][0]['quantity']);
        self::assertQueuedEmailCount(0);
        $id = $po['id'];
        $this->call('POST', "purchase-orders/$id/send");
        $this->call('POST', "purchase-orders/$id/receive", ['items' => [['id' => 1, 'quantity_to_receive' => 2, 'receiving_location_id' => $a]]]);
        self::assertSame(0, $this->call('GET', 'replenishment')['meta']['total']);
        $this->call('POST', "purchase-orders/$id/cancel");
        self::assertSame(6, $this->call('GET', 'replenishment')['data'][0]['suggested_quantity']);
    }

    public function testReplenishmentGroupsBySupplierAndCurrency(): void
    {
        [$p] = $this->fixtures();
        $other = $this->call('POST', 'products', ['name' => 'Other', 'sku' => 'OTHER', 'min_stock' => 2], 201)['data']['id'];
        $this->call('PATCH', "products/$p", ['min_stock' => 3]);
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        foreach ([$p => 'USD', $other => 'EUR'] as $id => $currency) {
            $this->call('POST', "products/$id/suppliers", ['supplier_id' => $supplier, 'cost_price' => '0.25', 'currency' => $currency], 201);
        }
        $orders = $this->call('POST', 'replenishment/drafts', ['request_id' => '00000000-0000-4000-8000-000000000002', 'product_ids' => [$p, $other]], 201)['data']['purchase_orders'];
        self::assertCount(2, $orders);
        self::assertNotSame($orders[0]['details']['currency'], $orders[1]['details']['currency']);
    }

    public function testReplenishmentBlockedSelectionRollsBackWholeBatch(): void
    {
        [$p] = $this->fixtures();
        $other = $this->call('POST', 'products', ['name' => 'No supplier', 'sku' => 'NONE', 'min_stock' => 2], 201)['data']['id'];
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier], 201);
        $this->call('POST', 'replenishment/drafts', ['request_id' => '00000000-0000-4000-8000-000000000003', 'product_ids' => [$p, $other]], 422);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM inv_document'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM inv_reorder_batch'));
    }

    public function testReplenishmentUsesReservedQuantityAndPerVariantCatalog(): void
    {
        [$p, $a, $b] = $this->fixtures();
        $this->call('PATCH', "products/$p", ['min_stock' => 4]);
        $this->call('POST', 'stock-adjustments', ['product_id' => $p, 'location_id' => $a, 'quantity' => 5], 201);
        $transfer = $this->call('POST', 'stock-transfers', ['from_location_id' => $a, 'to_location_id' => $b, 'items' => [['product_id' => $p, 'quantity' => 2]]], 201)['data']['id'];
        $this->call('POST', "stock-transfers/$transfer/prepare");
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier, 'minimum_order_quantity' => 3], 201);
        $row = $this->call('GET', 'replenishment')['data'][0];
        self::assertSame(2, $row['reserved_quantity']);
        self::assertSame(3, $row['available_quantity']);
        self::assertSame(3, $row['suggested_quantity']);
        $variant = $this->call('POST', 'variants', ['parent_id' => $p, 'sku' => 'V', 'name' => 'Variant', 'min_stock' => 2, 'attributes' => []], 201)['data']['id'];
        $this->call('POST', "products/$variant/suppliers", ['supplier_id' => $supplier], 201);
        $order = $this->call('POST', 'replenishment/drafts', ['request_id' => '00000000-0000-4000-8000-000000000004', 'product_ids' => [$variant]], 201)['data']['purchase_orders'][0];
        self::assertSame($p, $order['items'][0]['product_id']);
        self::assertSame($variant, $order['items'][0]['product_variant_id']);
    }

    public function testReplenishmentRejectsReusedRequestIdWithDifferentSelection(): void
    {
        [$p] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier], 201);
        $body = ['request_id' => '00000000-0000-4000-8000-000000000005', 'product_ids' => [$p]];
        $this->call('POST', 'replenishment/drafts', $body, 201);
        $body['product_ids'] = [$p + 100];
        $this->call('POST', 'replenishment/drafts', $body, 409);
        self::assertSame(1, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM inv_document'));
    }

    public function testReplenishmentRejectsStaleReviewedPurchasingTerms(): void
    {
        [$p] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $link = $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier, 'cost_price' => '0.50'], 201)['data']['id'];
        $fingerprint = $this->call('GET', 'replenishment')['data'][0]['fingerprint'];
        $this->call('PATCH', "products/$p/suppliers/$link", ['cost_price' => '0.75']);
        $this->call('POST', 'replenishment/drafts', ['request_id' => '00000000-0000-4000-8000-000000000006', 'product_ids' => [$p], 'expected' => [$p => $fingerprint]], 409);
        $db = self::getContainer()->get('doctrine')->getConnection();
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM inv_document'));
    }

    public function testSupplierCatalogLinkCannotBeChangedThroughAnotherProduct(): void
    {
        [$p] = $this->fixtures();
        $other = $this->call('POST', 'products', ['name' => 'Other', 'sku' => 'OTHER'], 201)['data']['id'];
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $link = $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier], 201)['data']['id'];
        $this->call('PATCH', "products/$other/suppliers/$link", ['cost_price' => '1.00'], 404);
        self::assertSame('0.00', self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT cost_price FROM inv_product_supplier WHERE id = ?', [$link]));
    }

    public function testReplenishmentFiltersAndPaginationKeepAccurateTotals(): void
    {
        [$p] = $this->fixtures();
        $this->call('POST', 'products', ['name' => 'Blocked', 'sku' => 'BLOCK'], 201);
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supply company'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier], 201);
        $ready = $this->call('GET', 'replenishment?eligible=true&per_page=1');
        self::assertSame(1, $ready['meta']['total']);
        self::assertSame($p, $ready['data'][0]['id']);
        self::assertSame(1, $this->call('GET', 'replenishment?eligible=false')['meta']['total']);
        self::assertSame(1, $this->call('GET', 'replenishment?search=Supply')['meta']['total']);
        self::assertSame([], $this->call('GET', 'replenishment?page=2&per_page=1&eligible=true')['data']);
    }

    public function testSupplierCatalogRejectsEmptyCurrencyWithoutCreatingLink(): void
    {
        [$p] = $this->fixtures();
        $supplier = $this->call('POST', 'suppliers', ['name' => 'Supplier'], 201)['data']['id'];
        $this->call('POST', "products/$p/suppliers", ['supplier_id' => $supplier, 'currency' => ''], 422);
        self::assertSame(0, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM inv_product_supplier'));
    }
}
