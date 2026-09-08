# 10. Items & Stock

This module governs the defining characteristics of inventory prior to any transactional movement.

## 1. Item Definitions
Before creating an item, you must establish its metadata:
- **Brand List**: Determines the manufacturer.
- **Category List**: Essential for categorizing POS buttons and grouping analytical reports.
- **Units List**: Defines if the item is sold in Pieces (Pcs), Kilograms (Kg), or Meters (m).

## 2. Items List
The master registry of all products.

### Creating an Item
1. Provide a physical **Barcode/SKU**.
2. Define pricing:
   - **Cost Price**: Baseline value of inventory assets.
   - **Sales Price**: Tax-exclusive selling value.
   - **Tax**: The applicable GST/VAT structure from the global tax table.
3. Configure tracking flags:
   - **Is Serialized?**: Forces cashiers to specify unique alpha-numeric serial strings upon Sale or Purchase.
   - **Alert Quantity**: Dictates the low-water mark that triggers notifications on the [Dashboard](03-dashboard.md).
   - **Opening Stock**: Only use this during initial setup. Automatically generates stock entries across targeted warehouses without a formal Purchase invoice.

> [!TIP]
> **Performance Edge**: A massive catalog can slow down POS software. CorevisysPOS utilizes server constraints and pagination to allow tens of thousands of items to load gracefully. Managers may also utilize **Import Items** for CSV bulk uploading.

## 3. Stock Management Tools
Direct manipulation of physical asset counts. 

### A. Print Labels
Generates printable, scannable Barcode sticker grids mapped directly to specific Items and Quantities, crucial for retail floors.

### B. Stock Adjustment List
Used exclusively to reconcile digital inventory with physical reality.

#### Workflow (Damage/Theft)
1. Navigate to **Items -> Add Stock Adjustment**.
2. Select the Warehouse.
3. Add the lost or damaged Items and specify the missing Quantity.
4. **Automation**: Saves an entry to `db_stockadjustment`. Both the global `db_items.stock` and local `db_warehouseitems` are permanently decreased, recognizing the asset loss.

### C. Stock Transfer List
Used to shuffle inventory between physical locations without triggering a financial P&L event.

#### Workflow
1. Navigate to **Items -> Add Stock Transfer**.
2. Select `Warehouse From` (Source) and `Warehouse To` (Destination).
3. The system ensures the Item exists in the source warehouse before allowing the quantity to be shifted. Serial numbered items are formally re-assigned to the new database branch.
