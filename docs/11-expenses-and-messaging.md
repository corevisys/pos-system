# 11. Expenses & Messaging Intelligence

## 1. Expenses 

Every business sustains operational costs (e.g., electricity, payroll, internet) that decrease net profit entirely independent of inventory purchasing. CorevisysPOS tracks this separately to maintain clean gross margin calculations.

### Category Management
Administrators must first define overhead buckets in the **Expense Category List** (e.g., "Utilities", "Salaries", "Maintenance").

### Creating an Expense
1. Navigate to **Expense -> Add Expense**.
2. Input Date, Payment Type (Cash/Cheque), and Amount.
3. **Critical**: You must select an `AcAccount`. 
4. **Automation**: Saving the Expense deducts the designated Cash/Bank account balance exactly like a Purchase Payment. The system logs a `ref_expense_id` within the master `ac_transactions` ledger to justify the missing cash.

> [!IMPORTANT]
> This precise Account mapping allows the Profit & Loss statement to accurately deduct operational overhead from gross revenue.

---

## 2. Messaging (SMS Automation)

CorevisysPOS features a sophisticated, event-driven SMS integration engine capable of automatically firing personalized notifications to clients via 3rd-party APIs (e.g., Twilio, local SMS gateways).

### A. SMS API List
Requires developers/administrators to embed the API endpoint, Authorization keys, and formatting JSON specific to their vendor. The system dynamically reads this config block when transmitting.

### B. SMS Templates
Administrators craft boilerplate language. 
- The system supports variables encased in brackets: `Welcome {customer_name}! Your invoice no {invoice_no} is generated.`
- The `SmsTriggerService` class parses the exact invoice data, replacing these placeholders dynamically prior to dispatch.

### C. SMS Auto Rules List
This is the central intelligent router. Administrators define 'Triggers' binding a Template to an Event.

#### Common Triggers
- `InvoiceCreated`: Fires a "Thank you" template when a sale is successfully saved.
- `EmiPaymentConfirmation`: Dispatches a receipt-template to a customer when their payment logs into the system.
- `EmiCompletion`: Reaches out upon finalizing a complex installment plan.

> [!NOTE]
> Ensure the **Contacts** module has valid formatting for customer Mobile numbers or the automated 3rd-party API callbacks will fail.

### D. Send SMS
A manual interface allowing management to bulk-select customers (e.g., marketing blasts to all active clients) and push custom text. Logs generate within `db_sms_logs`.
