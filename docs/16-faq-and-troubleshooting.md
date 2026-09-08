# 16. Frequently Asked Questions (FAQ)

**Q: Can a cashier see how much profit the store made today?**
A: No. By default, the `profit_report` and `dashboard_pur_sal_chart` permissions are isolated. A cashier only sees their shift's cash intake unless an Administrator grants deeper access explicitly.

**Q: Why is my item not dropping from Stock when I sell it?**
A: Check if the invoice is saved as a "Quotation" or if it is on "Hold". Only finalized Sales or EMI Sales instantly deduct inventory from `db_warehouseitems`.

**Q: My salesperson sold an item assigned to Warehouse A, but the physical item was in Warehouse B. Does this break the system?**
A: Yes, it creates negative digital stock in Warehouse A and locks false value in Warehouse B. A manager must perform a **Stock Transfer** retroactively to reconcile the location mapping.

**Q: Can I delete old sales to hide revenue?**
A: Only if the role possesses the `sales_delete` permission. Doing so permanently destroys the financial mapping in `ac_transactions` and forcefully pushes the sold item back into the `db_warehouseitems` count as if it never left the building. 

---

# 17. Troubleshooting Common Errors

### 1. Missing SMS Automation
**Symptom**: A customer completes an EMI contract, but no SMS is received.
**Resolution**:
1. Check the **Sms Auto Rules List**. Ensure the `EmiCompletion` trigger is toggled to **Active**.
2. Check the **Sms API List**. Verify the Gateway Balance hasn't depleted.
3. Check the **Customer List**. If the phone number lacks formatting or country codes, the 3rd-party API will reject the dispatch. (View `db_sms_logs` to isolate the error).

### 2. Profit Report Shows Extremely High/Invalid Costs
**Symptom**: Sales look normal, but the P&L statement shows massive losses.
**Resolution**:
1. You likely skipped entering the **Purchase Price** when importing new items.
2. The system calculates COGS (Cost of Goods Sold) based on that precise figure. If it is wildly inaccurate, your gross margins collapse. 
3. Verify via the **Item Sales Report**.

### 3. "Insufficient Stock" Warning at POS
**Symptom**: Cashier attempts a sale, but the system blocks the transaction.
**Resolution**: 
1. CorevisysPOS tracks per-warehouse. Ensure the POS interface is pointed at the correct `Warehouse ID`.
2. To bypass this temporarily, a manager with `stock_adjustment_add` or `purchase_add` permissions must manually elevate the count in `db_warehouseitems` before the cashier processes the sale.

### 4. Serial Number Missing
**Symptom**: Cannot select a laptop's serial number during a sale.
**Resolution**: 
1. Serial numbers are permanently linked to the specific Warehouse they were purchased into. 
2. Use **Stock Transfer** to move the serial string to the storefront before attempting the sale.
