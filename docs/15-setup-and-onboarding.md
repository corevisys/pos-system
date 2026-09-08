# 15. Setup & Onboarding Guide

Deploying a massive ERP or POS system requires strict sequencing. If you upload Inventory before establishing Warehouses or Taxes, the automated financial linkages will break. 
Follow this guide to effectively launch CorevisysPOS for a new retail or wholesale business.

---

## Phase 1: Global Configuration
These are the permanent architectural pillars of the software.

1. **Store Identity**: Navigate to **Settings -> Store Settings**. Input your Company Name, Address, Logo, and Timezone. (Crucial for printed invoices).
2. **Tax Brackets**: Navigate to **Places -> Tax List**. Create all applicable tax rules (e.g., `GST 18%`, `VAT 5%`, `Zero Rated 0%`).
3. **Warehouses**: Navigate to **Warehouse -> Add Warehouse**. Establish your physical locations (e.g., "Main StoreFront", "New York Depot").
4. **Accounts Matrix**: Navigate to **Accounts -> Add Account**. Create the literal places money lives (e.g., "Register 1", "Safe", "Chase Checking Account"). Define starting balances if porting from an old system.

---

## Phase 2: CRM & Vendor Base
Before buying or selling, you need entities to interact with.

1. **Suppliers**: Go to **Contacts -> Suppliers List -> Add**. Enter the distributors supplying your assets. 
   - *Pro-Tip*: If a supplier owes you money or vice-versa, enter the Opening Balance to trigger the initial Ledger generation.
2. **Customers** (Optional): Retail branches can skip this. B2B wholesalers should upload their client list via **Import Customers**.

---

## Phase 3: The Inventory Structure
Inventory is the most complex data point.

1. **Brands & Categories**: Navigate to **Items**, and flesh out the categorization trees.
2. **Units**: Define if you sell in Pieces, Meters, Grams, etc.
3. **Item Creation**: Use **Items -> Add Item** or the **Import Items** CSV tool. 
   - Ensure every item is linked strictly to the Taxes, Brands, and Categories established in Phase 1 & 3. 
   - Define the Alert Quantity for the Dashboard widget.
   - Do *not* enter Opening Stock blindly unless commanded by your accountant.

---

## Phase 4: Stocking Up (Financial Impact)
The correct way to pour inventory into the system.

1. **Standard Method (Purchase)**: Navigate to **Purchase -> Add Purchase**. Select a Supplier, log the exact purchase price for the massive initial intake, and select the Warehouse. 
   - *Why?* This establishes your accurate COGS (Cost of Goods Sold) for the Profit Report and legally maps the tax credits (ITC).
2. **Shortcut Method (Opening Stock)**: If porting from old software, you can edit the Item directly and type an Opening Stock. *Warning*: This bypasses the Supplier Ledger and `ac_transactions` ledger mapping. 

---

## Phase 5: Team Deployment
With the system primed, grant access to human operators.

1. **Roles**: Navigate to **Users -> Roles List**. Create templates (e.g., "Cashier Level 1", "Branch Manager"). Lock down dangerous permissions like `profit_report` or `sales_delete`.
2. **Users**: Create the literal login credentials (**Users -> Add User**) and bind them to the Roles.

### You Are Ready to Sell.
Operators can now log in, navigate to the **POS**, and begin transacting. All automated deductions into stock, ledgers, and taxes will function perfectly.
