# 03. Dashboard

The Dashboard is the central intelligence hub of CorevisysPOS. It provides a real-time snapshot of business health, prioritizing immediate actionable data over deep historical accounting.

## Key Metrics & Widgets

### 1. Top-Level Summary Cards
- **Total Purchase Due**: Aggregates all unpaid supplier invoices from `db_purchase`.
- **Total Sales Due**: Aggregates all unpaid customer invoices from `db_sales`.
  - *Note*: This actively subtracts advance payments and tracked installments.
- **Total Sales & Expense**: A gross view of money in vs. money out for the targeted period.
- **Total Customers & Suppliers**: CRM summary.

### 2. Graphical Analysis
- **Purchase & Sales Bar Chart**: A visual comparison of purchasing volume versus sales volume mapped out by month.
- **Trending Items Pie Chart**: Displays the most popular items based on sold quantity, drawing data from `db_salesitems`.

### 3. Operational Alerts
- **Recent Items**: A quick-access grid of the newest inventory added to the system.
- **Stock Alerts**: 
  - **Critical Automation**: This widget actively scans the `db_items` and `db_warehouseitems` tables. If an item's current stock falls below its defined `alert_qty`, it forces a notification here. 

---

# 04. Users & Roles

Proper user management ensures that staff only have access to the data and modules necessary for their specific jobs. 

## 1. Users List
This module manages the individual staff accounts that log into the system.

### Creating a User
1. Navigate to **Users -> Users List -> Add User**.
2. **Mandatory Fields**: 
   - First Name, Last Name
   - Email/Username (Used for login)
   - Password
3. **Role Assignment**: You must assign a Role. The system reads the `db_roles` table to populate this dropdown. The assigned role strictly dictates what the user can see/do.
4. **Data Isolation**: Ensure that `show_all_users_sales_invoices` (and similar permissions) are properly checked or unchecked based on whether this user should see global company data or just their own entries.

## 2. Roles List
Roles act as permission templates. Instead of assigning 50 individual permissions to every cashier, you create a "Cashier" role and assign it to multiple users.

### Managing Roles
1. Navigate to **Users -> Roles List**.
2. **Creating a Role**: Click "Add Role". You will be presented with a massive grid of permissions (e.g., `items_add`, `sales_delete`, `profit_report`).
3. **Check/Uncheck Strategy**: 
   - Grant `_view` permissions generously for training.
   - Restrict `_delete` permissions exclusively to administrators to prevent destructive data-loss or theft masking.
   - Restrict `_report` permissions to management to keep financial performance confidential.

> [!TIP]
> **Best Practice**: Never give a standard 'Salesman' or 'Cashier' role the `accounts_view` or `profit_report` permission. For a full breakdown of recommended roles, see [14. Role-Based Guides](14-role-based-guides.md).
