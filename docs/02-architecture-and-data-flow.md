# 02. Data Flow & Financial Architecture

Understanding how data moves under the hood is critical for accountants, store managers, and system auditors. CorevisysPOS uses an automated double-entry-like backend system where operational actions immediately hit the ledger, stock, and tax tables.

---

## 1. Financial Flow Map

All financial movement is standardized through the central Accounts ledger (`ac_accounts`) and transaction logs (`ac_transactions`).

### A. Sales & Revenue Flow
When a Sale (either via POS or Standard Invoice) is processed:
1. **Invoice Generation**: The gross total, subtotal, discount, and tax are saved into `db_sales`.
2. **Account Receipt (Payments)**: If the customer pays immediately (Cash/Card/Bank), a record is created in `db_salespayments` linked to a specific Account ID.
3. **Ledger Impact**: The target bank/cash account's balance in `ac_accounts` is immediately increased.
4. **Unpaid Balance (Due)**: The system calculates `grand_total` minus `paid_amount`. This outstanding amount represents Accounts Receivable.

### B. Purchase & Liability Flow
When an Inventory Purchase is recorded:
1. **Invoice Storage**: The raw purchase data hits `db_purchase`.
2. **Account Deduction**: Payments made to the supplier are saved in `db_purchasepayments`.
3. **Ledger Impact**: The payment is mapped to the exact cash/bank Account ID, and the balance in `ac_accounts` is decreased.
4. **Outstanding Balance**: Calculated as an Account Payable to the specific Supplier.

### C. EMI & Installment Flow
1. **Initial Setup**: A record is created in `db_emi_sales` alongside the standard `db_sales`.
2. **Schedule Generation**: The system divides the total loan amount across `db_emi_schedules` rows.
3. **Monthly Receipt**: When an installment is paid, a standard `db_salespayments` entry is generated.
4. **Ledger Impact**: The receiving account balance increases. The specific EMI schedule status flips to "Paid".

### D. Customer Advance Adjustment
1. **Receipt of Advance**: Recorded in `db_custadvance` and increases the respective Account Bank balance.
2. **Sales Adjustment**: When the customer makes a purchase, the `advance_adjusted` column in `db_salespayments` is utilized to map the pre-paid funds against the new invoice, preventing double-counting of revenue.

---

## 2. Stock Flow Map

Inventory movements are restricted per Warehouse. A Global aggregate is kept in `db_items.stock` while location-specific numbers live in `db_warehouseitems`.

### A. Purchase (Stock Inward)
- **Warehouse Target**: The selected `warehouse_id` on the purchase triggers an increase in `db_warehouseitems.available_qty`.
- **Global Stock**: The system concurrently increments `db_items.stock`.
- **Price Averaging**: The system overwrites the `db_items.purchase_price` to the most recent purchase value.

### B. Sale (Stock Outward)
- **Warehouse Source**: Decrements the `db_warehouseitems.available_qty` from the warehouse selected at the POS or Add Sale screen.
- **Global Stock**: Concurrently decreases `db_items.stock`.
- **Serial Validation**: If the item `is_serialized`, the specific `db_item_serials` status is flipped from Available (0) to Sold (1) and mapped to the `sale_id`.

### C. Transfer (Inter-Location)
- **Source Reduction**: Decreases stock in `db_warehouseitems` for the 'From' warehouse.
- **Destination Addition**: Increases stock in `db_warehouseitems` for the 'To' warehouse.
- **Global Stock**: `db_items.stock` remains completely unchanged.
- **Serial Porting**: Serial numbers are reassigned to the new `warehouse_id`.

### D. Adjustment (Manual Correction)
- Used for theft, damage, or audit corrections recorded in `db_stockadjustment`.
- Stock can be manually adjusted up or down, directly changing both the global and warehouse-specific tables.

---

## 3. GST & Tax Engine Flow

CorevisysPOS handles complex tax mappings suitable for the Subcontinent (GST) and standard VAT regimes.

### Rate Application
- Taxes are defined globally in `db_taxes` (e.g., "GST 18%", "VAT 5%").
- When assigned to an item, the controller processes whether the tax is **Inclusive** (price contains tax) or **Exclusive** (tax is stacked on top of price).

### Tax Collection (Sales) vs Tax Paid (Purchase)
- **Sales Tax**: Recorded line-by-line in `db_salesitems.tax_amt`. Accumulated as total Tax Liability.
- **Purchase Tax**: Recorded line-by-line in `db_purchaseitems.tax_amt`. Accumulated as Input Tax Credit (ITC).
- **GSTR Reporting**: The reporting modules (`GSTR-1`, `GSTR-2`, `Sales GST`) map these line-item values against the specific Date and Customer/Supplier GSTINs for government compliance.

---

## 4. SMS & Automation Rule Engine

The Messaging system (`SmsAutoRuleController`) operates independently on an Event-Driven architecture:
1. **Trigger Event Occurs**: For example, a Sale is saved (`InvoiceCreated` event).
2. **Rule Resolution**: The `RuleResolverService` checks `sms_auto_rules` to see if the event has an active listener.
3. **Template Compilation**: If active, it grabs the `DbSmsTemplate`, parses variables (e.g., replaces `{invoice_no}` with the actual identifier).
4. **Dispatch & Log**: Dispatches to the 3rd-party API and saves a record in `db_sms_logs`.
