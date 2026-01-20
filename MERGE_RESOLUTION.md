# CostLayer Merge Resolution

## Date: 2026-01-20

## Conflict Description
Merge conflict in `src/Entity/CostLayer.php` when merging `origin/main` (PR #32) into `copilot/implement-purchase-order-workflows`.

## Conflicting Changes

### From PR #32 (Inventory Adjustment Workflows)
- Added `layerType` field with constants: receipt, adjustment, transfer_in, manufacturing
- Added `qualityStatus` field with constants: available, quarantine, rejected
- Added `sourceType` and `sourceReference` fields for tracking
- Added `voided` and `voidReason` fields for void/reversal capability
- Added index: `['item_id', 'layer_type', 'quality_status']`

### From This PR (Procure-to-Pay Workflows)
- Added `originalUnitCost` field for cost before landed costs
- Added `landedCostAdjustments` field for total landed cost adjustments per unit
- Added `vendor` FK relationship to Vendor entity
- Added `lastCostAdjustment` timestamp field
- Added `applyLandedCost()` method
- Added index: `['vendor_id']`

## Resolution
Combined ALL fields from both PRs. The final CostLayer entity includes:
- All 6 fields from PR #32
- All 4 fields from this PR
- All 3 indexes (item_id+receipt_date, item_id+layer_type+quality_status, vendor_id)
- All methods from both PRs

## Migration Compatibility
- Version20260120040000 (from PR #32): Adds PR #32 fields
- Version20260120053000 (from this PR): Adds procure-to-pay fields
- Migrations run in sequence: 040000 first, then 053000
- No conflicts between migrations - they modify different columns

## Result
The CostLayer entity now supports:
- ✅ FIFO costing for multiple layer types
- ✅ Quality status management
- ✅ Landed cost retroactive adjustments
- ✅ Vendor tracking
- ✅ Source tracking and audit trail
- ✅ Void/reversal capability
