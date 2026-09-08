# 01. System Overview

## Introduction
Welcome to the CorevisysPOS Help Center. CorevisysPOS is an enterprise-grade Point of Sale (POS) and Inventory Management system tailored for complex business environments. The system acts as a centralized brain for retail, wholesale, and distribution chains, allowing real-time tracking of sales, inventory movement, financials, and customer engagement.

## Business Philosophy
The core architecture of CorevisysPOS revolves around strict, automated accounting principles integrated seamlessly with operational tasks. Whether a cashier rings up a sale or an administrator imports bulk stock, the system automatically translates those operational actions into precise ledger entries, tax distributions, and stock valuations without requiring dual entry.

## Target Audience
This documentation caters to all system users:
- **Cashiers & Sales Staff**: Navigate to the [Sales & POS Module](#05-sales) for daily transactions.
- **Accountants & Finance Staff**: Reference the [Data & Financial Architecture](#02-architecture-and-data-flow) and [Accounts Module](#09-accounts).
- **Inventory/Warehouse Managers**: Focus on the [Stock & Items Module](#10-items-and-stock) and the [Warehouse Settings](#13-warehouse).
- **System Administrators**: Refer the [Users & Roles Module](#04-users-and-roles) and [Setup Guide](#15-setup-and-onboarding).

## Core System Modules at a Glance

| Module | Primary Function | Automatic Integrations |
| :--- | :--- | :--- |
| **Sales (POS & Standard)** | Processing retail/wholesale transactions. | Decreases stock; Increases cash/bank/due ledgers; Calculates Tax/GST; Triggers SMS alerts. |
| **Purchases** | Restocking inventory from external vendors. | Increases precise warehouse stock; Updates Average Purchase Price; Increases Liabilities (Due) or Decreases Asset Ledgers. |
| **Accounts** | Managing internal business liquidity (Banks, Cash). | Tracks money transfers, deposits, and automated payment mappings from Sales/Purchases. |
| **Stock Management** | Inter-warehouse movements and inventory correction. | Transfers stock between store physical locations without affecting P&L; Adjusts values on loss. |
| **Expenses** | Tracking operational overheads. | Reduces available cash/bank balances; Factors into the final Net Profit calculations. |
| **Messaging Intelligence** | Automated and manual SMS marketing/alerts. | Evaluates Sale events (e.g., EmiPaymentConfirmation, InvoiceCreated) and triggers templated responses. |
| **Reports** | Real-time GST compilation, P&L, and business intelligence. | Aggregates live data from `db_sales`, `db_purchases`, and `ac_transactions` tables. |

## Important Architectural Notes
- **Multi-Warehouse Capable**: Stock is tracked both globally (`db_items.stock`) and on a per-warehouse basis (`db_warehouseitems.available_qty`).
- **Strict Financial Mapping**: Every monetary transaction (Sales Payment, Purchase Payment, Deposit, Expense) generates a corresponding entry in the `ac_transactions` ledger mapping debit/credit between the system's Chart of Accounts.
- **Serialized Tracking**: Enables the sale and return of electronics or appliances referencing exact unique Serial Numbers (`db_item_serials`).
