# 14. Users & Roles Structure (Permission Matrix)

Enterprise security requires strict compartmentalization. CorevisysPOS utilizes a comprehensive Role-Permission system defining exactly what data can be viewed, created, edited, or deleted.

## Role Hierarchies

By default, the system recognizes the following fundamental archetypes. Each archetype is assigned an array of functional permissions saved in the `db_permissions` table.

### 1. Super Admin
- **Description**: Technical owner or ultimate business owner.
- **Access**: Total, unrestricted access to the entire application.
- **Hidden Menus**: None.
- **Exclusive Access**: Only the Super Admin / Admin roles have access to Danger Zones (e.g., hard-deleting User accounts), SMTP Configuration, and DB Backup tools.

### 2. Admin
- **Description**: Senior branch manager or operations director.
- **Access**: Almost identical to Super Admin.
- **Scope**: Manages branches, roles, permissions, financial settings, and taxes.

### 3. Manager
- **Description**: Store manager focusing on daily retail logic and performance.
- **Access Allowed**:
  - Full Item & Brand management.
  - Full Customer & Supplier CRM control.
  - Complete control over Sales, Purchases, Expenses, and Quotations.
  - Ability to execute Stock Transfers and Adjustments.
  - Access to advanced profitability and stock reports.
- **Access Denied**:
  - `roles_add`, `roles_edit`: Cannot alter security protocols.
  - `store_edit`, `smtp_settings`, `sms_settings`: Cannot change fundamental system logic or integrations.

### 4. Salesman
- **Description**: Staff interacting directly with customers but not necessarily managing the till.
- **Access Allowed**:
  - Can view Items, Brands, and Categories (to answer customer queries).
  - Can Create (`sales_add`) and View (`sales_view`) Sales.
  - Can Create (`customers_add`) Customers.
  - Can process Sales Returns (`sales_return_add`).
- **Access Denied**:
  - Cannot access Purchases, Expenses, or deeply financial Reports (`profit_report`).
  - Cannot Edit/Delete Sales post-creation.

### 5. Cashier
- **Description**: Staff stationed strictly at the POS register processing money.
- **Access Allowed**:
  - `sales_add`, `sales_view`, `sales_payment_add`, `sales_payment_view`.
  - Cash transaction overviews.
- **Access Denied**:
  - Cannot execute Stock adjustments.
  - Cannot view general Dashboard performance metrics (`dashboard_pur_sal_chart`, etc.).
  - Cannot delete invoices.

---

## Important System Logic Triggered by Roles

### Data View Isolation
A permission like `show_all_users_sales_invoices` dictates whether a user can see the global list of sales across the store, or strictly the sales *they* generated (`created_by` = their user ID). 
- **Admins & Managers** usually have this enabled.
- **Salesmen** usually have this disabled to prevent internal lead stealing or data scraping.

### Cashier vs. Ledger Access
A Cashier is empowered to use `sales_payment_add` which interfaces with the `AcAccount` (receiving bank/drawer). However, they lack `money_transfer_add` or `accounts_view`, preventing them from altering the actual banking balances or moving funds to unauthorized deposit ledgers.

### Generating New Roles
Administrators can create bespoke roles under **Users -> Roles List -> Add Role**.
When creating a custom role, a grid of checkboxes appears. 
> [!WARNING]
> **Security Tip**: Be uniquely careful with `items_delete`, `sales_delete`, and `expenses_delete`. Typically, only Administrators should have the right to retroactively destroy financial logs.
