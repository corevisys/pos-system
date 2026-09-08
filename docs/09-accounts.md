# 09. Accounts

The Accounts module acts directly on the Chart of Accounts (`ac_accounts`) and acts as the ultimate ledger translating operational POS events into GAAP-compliant balance sheets.

> [!WARNING]
> Access to this module implies access to corporate financial liquidity. Only trusted Administrators should possess the `accounts_view` or `accounts_edit` permissions.

## 1. Accounts List
The master registry of all places where business money resides. 
- You should create accounts mirroring your reality: `Cash Drawer 1`, `Cash Drawer 2`, `HDFC Bank Account`, `Stripe Gateway`.
- **Parent Accounts**: Allows grouping (e.g., `Cash Drawer 1` is a child of `Cash in Hand`).
- **Opening Balances**: Establishing an account with an initial value automatically generates an initial transaction in `ac_transactions` to validate the asset.

## 2. Money Transfer List
Used exclusively for moving liquidity internally.

### Scenario
A cashier's `Cash Drawer` acquires $10,000 during the workday. The manager must deposit $9,000 into the `HDFC Bank Account`.

### Workflow
1. Navigate to **Accounts -> Add Money Transfer**.
2. Select the `From Account` (Cash Drawer) and the `To Account` (HDFC Bank Account).
3. Specify the Amount ($9,000) and Date.

### Financial Automation
The system creates a coupled entry in `ac_transactions`:
- **Credit**: Decreases the `From Account` by $9,000.
- **Debit**: Increases the `To Account` by $9,000.

## 3. Money Deposit List
Used for external injections of liquidity. For instance, an owner invests $50,000 of personal funds into the business checking account, completely unassociated with any POS sale.
- Functionally identical to a Money Transfer, but establishes capital without a decrementing internal source.

## 4. Cash Transactions
A centralized chronological ledger detailing every single movement of cash across every module (Purchases, Sales, EMI, Advances, Deposits, and Transfers). Use this menu for end-of-day discrepancy audits.
