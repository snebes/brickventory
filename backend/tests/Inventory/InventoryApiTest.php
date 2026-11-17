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
}
