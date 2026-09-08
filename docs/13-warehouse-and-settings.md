# 13. Warehouse & Settings

This module controls the global parameters that affect the entire CorevisysPOS application. Only Super Admins should interact with these utilities.

---

## 1. Warehouse Management

CorevisysPOS handles multi-location inventory. Even if you only have one physical store, the system treats it as "Warehouse 1". 

### Adding a Warehouse
- Establish a distinct entity in the database (`db_warehouse`).
- You must link the new Warehouse to a specific `Store ID`. 

### Financial Impact
- Creating a warehouse generates a parallel tracking table for inventory (`db_warehouseitems`).
- Sales, Purchases, and Adjustments now require the user to explicitly define *which* warehouse the stock event should happen in, ensuring accurate location-based valuation.

---

## 2. Store Settings
This configures the global brand identity and fundamental logic for the business.

### A. General Profile
- **Company Name, Address, Phone, Email**: These variables are injected dynamically into all printed PDF invoices and Quotations.
- **Logo**: Used on the login screen and printed receipts. 
- **Timezone**: Critical. Ensure this matches your physical location, otherwise Daily Shift Reports, Sales Dates, and automated SMS triggers will execute at incorrect hours.

### B. Number Configuration
CorevisysPOS uses automated alphanumeric string generation for invoices. For example, generating `SL-0001` or `PUR-0042`. Administrators can customize the Prefix (`SL-`, `INV-`, `POS-`) in the settings.

---

## 3. System Configuration & Integrations

### A. SMTP / Email Settings
Allows the system to push automated emails for password resets or sending PDF invoices directly to clients.
1. Enter your Provider details (Host, Port, Username, Password).
2. Recommended to use authenticated TLS connections (e.g., SendGrid, Mailgun, Amazon SES).

### B. Payment Types
Administrators define the labels available in the Payment Modal (e.g., "Cash", "Cheque", "Credit Card", "UPI", "Bank Transfer").
These labels must map to specific `ac_accounts` when a cashier finalizes a transaction.

### C. Tax Settings
Defines the global tax rates available for attachment to Items.
- Calculate whether your business uses Inclusive or Exclusive tax logic prior to building the catalog.

### D. Subscription & Licensing
Manages the SaaS billing profile (if applicable) and limits the number of active Users or total Sales per month based on your tiered license.
