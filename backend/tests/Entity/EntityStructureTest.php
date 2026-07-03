<?php

declare(strict_types=1);

/**
 * Test script to validate NetSuite ERP entity structure
 * Verifies that all entities properly extend abstract base classes
 * and have correct default values set.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Entity\AbstractTransactionalEntity;
use App\Entity\AbstractMasterDataEntity;
use App\Entity\Location;
use App\Entity\PurchaseOrder;
use App\Entity\SalesOrder;
use App\Entity\Item;
use App\Entity\Vendor;

echo "NetSuite ERP Entity Structure Validation\n";
echo "==========================================\n\n";

// Test AbstractMasterDataEntity
echo "Testing AbstractMasterDataEntity...\n";
$location = new Location();
echo "✓ Location created\n";
echo "  - UUID: " . $location->uuid . "\n";
echo "  - Created At: " . $location->createdAt->format('Y-m-d H:i:s') . "\n";
echo "  - Updated At: " . $location->updatedAt->format('Y-m-d H:i:s') . "\n";
echo "  - Active: " . ($location->active ? 'true' : 'false') . "\n";
echo "  - Created By: " . ($location->createdBy ?? 'null') . "\n";
echo "  - Modified By: " . ($location->modifiedBy ?? 'null') . "\n";
assert($location instanceof AbstractMasterDataEntity, 'Location should extend AbstractMasterDataEntity');
assert(!empty($location->uuid), 'UUID should be auto-generated');
assert($location->active === true, 'Active should default to true');
echo "✓ All assertions passed\n\n";

// Test AbstractTransactionalEntity
echo "Testing AbstractTransactionalEntity...\n";
$po = new PurchaseOrder();
echo "✓ PurchaseOrder created\n";
echo "  - UUID: " . $po->uuid . "\n";
echo "  - Created At: " . $po->createdAt->format('Y-m-d H:i:s') . "\n";
echo "  - Updated At: " . $po->updatedAt->format('Y-m-d H:i:s') . "\n";
echo "  - Created By: " . ($po->createdBy ?? 'null') . "\n";
echo "  - Modified By: " . ($po->modifiedBy ?? 'null') . "\n";
assert($po instanceof AbstractTransactionalEntity, 'PurchaseOrder should extend AbstractTransactionalEntity');
assert(!empty($po->uuid), 'UUID should be auto-generated');
echo "✓ All assertions passed\n\n";

// Test timestamp updates
echo "Testing timestamp auto-update...\n";
$so = new SalesOrder();
$originalUpdatedAt = clone $so->updatedAt;
sleep(1);
$so->touch();
echo "✓ SalesOrder touched\n";
echo "  - Original Updated At: " . $originalUpdatedAt->format('Y-m-d H:i:s') . "\n";
echo "  - New Updated At: " . $so->updatedAt->format('Y-m-d H:i:s') . "\n";
assert($so->updatedAt > $originalUpdatedAt, 'Updated timestamp should be newer after touch()');
echo "✓ Touch method works correctly\n\n";

// Test Item entity
echo "Testing Item entity (master data)...\n";
$item = new Item();
echo "✓ Item created\n";
echo "  - UUID: " . $item->uuid . "\n";
echo "  - Created At: " . $item->createdAt->format('Y-m-d H:i:s') . "\n";
echo "  - Updated At: " . $item->updatedAt->format('Y-m-d H:i:s') . "\n";
echo "  - Active: " . ($item->active ? 'true' : 'false') . "\n";
assert($item instanceof AbstractMasterDataEntity, 'Item should extend AbstractMasterDataEntity');
assert($item->active === true, 'Item active should default to true');
echo "✓ All assertions passed\n\n";

// Test Vendor entity
echo "Testing Vendor entity (master data)...\n";
$vendor = new Vendor();
echo "✓ Vendor created\n";
echo "  - UUID: " . $vendor->uuid . "\n";
echo "  - Active: " . ($vendor->active ? 'true' : 'false') . "\n";
assert($vendor instanceof AbstractMasterDataEntity, 'Vendor should extend AbstractMasterDataEntity');
echo "✓ All assertions passed\n\n";

// Test user tracking methods
echo "Testing user tracking methods...\n";
$location->setCreatedBy('test-user');
$location->setModifiedBy('test-modifier');
echo "✓ User tracking set\n";
echo "  - Created By: " . $location->createdBy . "\n";
echo "  - Modified By: " . $location->modifiedBy . "\n";
assert($location->createdBy === 'test-user', 'Created by should be set correctly');
assert($location->modifiedBy === 'test-modifier', 'Modified by should be set correctly');
echo "✓ All assertions passed\n\n";

// Test active/inactive methods
echo "Testing active/inactive methods...\n";
$item->deactivate();
echo "✓ Item deactivated\n";
echo "  - Active: " . ($item->active ? 'true' : 'false') . "\n";
assert($item->active === false, 'Item should be inactive after deactivate()');
$item->activate();
echo "✓ Item activated\n";
echo "  - Active: " . ($item->active ? 'true' : 'false') . "\n";
assert($item->active === true, 'Item should be active after activate()');
echo "✓ All assertions passed\n\n";

echo "==========================================\n";
echo "All validations passed! ✓\n";
echo "Entity structure correctly follows NetSuite ERP patterns.\n";
