# 12. Reports

CorevisysPOS provides a comprehensive suite of real-time reports aggregating data from sales, purchases, contacts, and accounts. 

> [!WARNING]
> Reports expose the precise financial health of the company. Access to the `profit_report` should be tightly restricted to Management and Administrators.

## 1. Profit & Loss Report
The ultimate metric of business performance. It calculates Net Profit based on Gross Profit minus defined operating expenses. 

### Calculation Logic
- **Sales Revenue**: Reads `grand_total` from `db_sales`, excluding taxes.
- **Cost of Goods Sold (COGS)**: Maps the sold `db_items` against their recorded `purchase_price`.
- **Gross Profit**: Sales Revenue - COGS.
- **Expenses**: Deducts the sum of all recorded data in `db_expense` (e.g., electricity, salaries).
- **Net Profit**: Gross Profit - Expenses. 
- *Note*: You can filter by Date Range to see monthly, quarterly, or annual performance.

## 2. Inventory & Stock Reports
Essential for warehouse management and recognizing asset value.

### Stock Report
- Extracts real-time quantities from `db_warehouseitems` and global `db_items`.
- Multiplies `available_qty` by the item's `purchase_price` to generate the **Total Stock Value**—a crucial figure for balance sheets and insurance audits.

### Item Sales Report
Analyzes which products move the fastest, helping identify dead stock versus best-sellers.

## 3. GST & Tax Reporting (GSTR-1, GSTR-2)
Designed specifically for South Asian / Indian subcontinental tax compliance, but functional for standard VAT outputs.

### GSTR-1 (Sales GST Report)
- Aggregates output tax liability.
- Pulls all `db_sales` records and strips out the raw `tax_amt`. 
- Organizes data by Customer GSTIN, Date, and Invoice Number for direct government portal uploading.

### GSTR-2 (Purchase GST Report)
- Aggregates Input Tax Credit (ITC).
- Pulls all `db_purchase` records and maps the tax paid to suppliers against their specific GSTIN numbers.

## 4. Ledger Reports
Tracks financial relationships and outstanding debt (Accounts Receivable/Payable).

### Customer / Supplier Ledgers
- Select a specific Contact to view a chronological history.
- **Data Flow**: Combines positive records (`db_sales` or `db_purchase`) with negative offset records (`db_salespayments` or `db_purchasepayments`).
- Highlights the definitive Unpaid Balance.

### Sales & Purchase Payment Reports
Provides a raw list of actual cash/bank receipts independent of invoice values. If you need to know exactly how much cash hit the till on Tuesday, pull the Sales Payment Report.
