# 18. Data Safety & Backup Best Practices

CorevisysPOS acts as the central financial ledger and tax reporting aggregate for your entire enterprise. Securing its backend data is paramount to avoiding catastrophic compliance failures or asset loss.

## Best Practice 1: Role Compartmentalization
The single biggest threat to data integrity is a compromised or undertrained employee account.
- **Never Share Logins**: The `ac_transactions` and `db_sales` tables log the `created_by` parameter on every single action executing an audit trail. If the Cashier and Manager share a login, you cannot legally prove who stole from the till.
- **Restrict Deletion**: An unhappy employee with the `items_delete` or `sales_delete` permission can irreversibly cripple months of accounting. Only a Super Admin should ever possess these permissions. 

## Best Practice 2: Regular Database Backups
- If the server crashes, your entire Accounts Receivable, Accounts Payable, and GST/Tax liabilities vanish.
- Establish an automated cron job on your VPS/Server hosting the application to dump the MySQL/MariaDB database nightly.
- Store these `.sql` files on an isolated, off-site cloud provider (e.g., AWS S3, Google Drive).

## Best Practice 3: Strict Ledger Hygiene
- The system prevents you from simply "typing in" money. Every cent generated requires a causal action (A Sale, A Purchase, an Advance, an Expense, a Deposit).
- **Daily Reconciliation**: At the end of a shift, require the cashier to generate a **Cash Transactions** report. The literal cash in the physical drawer *must* precisely equal the `AcAccount` (e.g., "Till 1") balance within the system. Reconcile daily; finding a $40 discrepancy today is easier than locating a $4,000 discrepancy next month.

## Best Practice 4: Handle Opening Stock Carefully
Use the formal **Purchase** module to intake standard inventory whenever possible, even if you are just launching the software.
- The **Opening Stock** override bypasses the `ac_transactions` ledger mapping entirely. It will put digital assets in your warehouse without proving where the money came from, confusing advanced audits or specific P&L assessments down the road.
