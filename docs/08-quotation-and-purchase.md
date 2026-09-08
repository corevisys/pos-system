# 08. Quotation & Purchase

This module manages the acquisition of inventory and the tracking of liabilities owed to suppliers.

## 1. Quotations List
Often, a business needs to quote a price to a B2B client without committing to a financial sale or deducting stock.

### Workflow
1. Navigate to **Quotation -> Add Quotation**.
2. Create an invoice structure precisely identical to a standard sale. The system caches this data separately in `db_quotations`.
3. **No Financial Impact**: Quotations do *not* hit `ac_transactions` and do *not* decrement `db_warehouseitems`.
4. **Conversion**: A manager may open a Quotation and convert it directly into a standard Sale, which triggers all stock and accounting automation instantly upon saving.

## 2. Purchase Module
The fundamental way stock enters the system.

### New Purchase
1. Select a Supplier. If entering an Opening Balance, ensure the Supplier was previously configured in the Contacts module.
2. Search and add Items.
3. Define the precise **Purchase Price** (per unit) and the **Quantity**.
4. Define the **Warehouse** designated to receive this inventory.
5. Apply specific Tax logic. The tax paid here acts as an Input Tax Credit (ITC) for the company.
6. The system calculates the Total Invoice Amount.

### Financial and Stock Automation
- Saving a purchase *instantly* increases `db_warehouseitems.available_qty` for the designated warehouse.
- A liability (Accounts Payable) is established against the Supplier equating to the Total Amount.
- Recording a Purchase Payment (via Cash/Bank) reduces a specific Ledger Account balance and registers a corresponding movement in `ac_transactions`.

## 3. Purchase Returns
Returning defective items to the supplier.

### Workflow
1. Navigate to the **Purchase List** and find the original invoice.
2. Select **Return Item**. Ensure you reference the invoice number to maintain an accurate audit trail.
3. Select the defective items.

### Financial and Stock Automation
- The returned quantity is immediately *subtracted* from both global stock (`db_items`) and warehouse stock (`db_warehouseitems`).
- The value of the return acts as a credit against your liabilities.
- If the supplier refunds cash, you must log a Purchase Return Payment mapped to the target Ledger Account, increasing its balance.
