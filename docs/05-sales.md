# 05. Sales Module

The Sales module is the primary revenue-generating engine of CorevisysPOS. It handles everything from immediate retail transactions to long-term EMI contracts. 

> [!IMPORTANT]
> **Financial Impact**: System automation in this module is heavy. Saving a sale instantly decreases warehouse stock, computes GST/Tax liabilities, and increases Accounts Receivable (Due).

## 1. POS (Point of Sale)
The POS is the high-speed interface designed for retail cashiers. 

### Usage
- **Item Selection**: Scan a barcode or search by item name. For serialized items, the system will force you to select a specific, available Serial Number.
- **Cart Management**: Adjust quantities, apply line-item discounts, or assign a global discount to the entire invoice.
- **Payment Processing**: Clicking "Cash/Multiple Pay" brings up the payment modal. Payments here map directly to the `db_salespayments` table and increase the selected `AcAccount` balance.

### Holding Sales 
- **Action**: If a customer forgets their wallet, click "Hold". This temporarily saves the cart without deducting stock or affecting accounting.
- **Retrieval**: Use the "Hold Sales List" to reopen and complete the transaction.

## 2. Add Sale (Standard Invoice)
This is the back-office interface for B2B wholesale, large orders, or dispatch routing.

### Usage similarities to POS
- Utilizes the same core logic for stock deduction and tax calculation.
- Useful for entering historical data, complex shipping parameters, or applying specific **Customer Advances** previously recorded.

## 3. Sales List
The central repository for all recorded sales.

### Functionality
- **View/Print**: Reprint invoices in various layouts (A4, Thermal, POS).
- **Edit/Delete**: *(Role Restricted)* Editing a sale reverses the stock/financial impact of the old invoice and applies the new one. Deleting a sale puts items back in stock and removes the financial record.
- **Payment Status**: Visual badges indicating Paid, Partial, or Unpaid. 

## 4. Sales Payments
Used when a customer is paying an older "Unpaid" or "Partial" invoice.
1. Locate the invoice in the **Sales List**.
2. Click **Action -> View Payments -> Add Payment**.
3. Select the Date, Payment Type (Cash/Bank), and the exact receiving Account.
4. The system calculates the new Due Amount.

## 5. Sales Returns List
Processing customer refunds or defective item returns.

### System Automation
When a Sales Return is saved:
1. **Stock**: `db_warehouseitems` and `db_items` are increased by the returned quantity.
2. **Financials**: The total sale value of the returned items is calculated as a liability (Money owed back to the customer).
3. **Payments**: If you return cash to the customer, it must be recorded in the Return Payment modal, mapping an outward transaction in `ac_transactions`.

## 6. EMI Sales List
Manages high-ticket items sold on installment.

### The EMI Flow
1. **Creation**: When creating a sale, select "EMI" as the payment type. You must define the Downpayment, Interest Rate (if any), Number of Installments, and Interval (Monthly).
2. **Schedule**: The system generates a distinct repayment schedule (`db_emi_schedules`).
3. **Collecting Installments**: Navigate to the **EMI Sale List**. Click on "Pay Installment".
4. **Accounting**: Each paid installment is registered as a standard `db_salespayment` and updates the accounts ledger.

> [!NOTE]
> **SMS Automation**: If the SMS Module is configured, the system automatically dispatches an `EmiPaymentConfirmation` SMS upon receiving an installment, and an `EmiCompletion` SMS when the final payment clears.
