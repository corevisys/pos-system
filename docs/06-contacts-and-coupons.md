# 06. Contacts

The Contacts module serves as the primary CRM (Customer Relationship Management) and Vendor Management system. 

## 1. Customers List
Stores all individuals or businesses you sell to.

### Functionality
- **Add Customer**: Requires basic details (Name, Mobile). Advanced fields include GST Number, Tax Number, Credit Limit, and Opening Balance.
- **Opening Balance**: 
  - If a customer owes you money *before* using this POS, enter it here. 
  - The system automatically creates a retroactive invoice mapping this debt so it appears in Ledger reports.
- **Import Customers**: Allows bulk uploading via CSV template for rapid onboarding.

## 2. Suppliers List
Stores all businesses you purchase inventory from.

### Functionality
- **Add Supplier**: Collects vendor details.
- **Opening Balance**: If you owe a vendor money prior to using the POS, entering an opening balance creates a historical liability in your Accounts Payable.
- **Purchase Tracking**: Every supplier acts as an anchor point for `db_purchase` records, allowing you to generate comprehensive Supplier Ledger reports analyzing total bought vs. total paid.

---

# 07. Advance & Coupons

## 1. Customer Advances (Wallet)
Often, a customer will provide a cash deposit before goods are delivered, or as a retaining fee. CorevisysPOS tracks this outside of standard sales revenue to ensure accurate liability accounting.

### Workflow
1. **Receive Advance**: Navigate to the relevant Customer Ledger or Advance menu. Record the amount received and the receiving Account (e.g., Cash Drawer).
2. **Financial Impact**: The Account balance increases. The system logs a Liability (you owe the customer goods worth this amount).
3. **Adjustment**: 
   - When creating a POS/Standard Sale for this customer, the system recognizes the available advance balance.
   - When processing the payment for that sale, you can specify an `advance_adjusted` amount. This deducts the total invoice against their pre-paid wallet rather than requiring a new cash transaction.

## 2. Coupons
Coupons provide a structured way to execute marketing discounts without relying on cashiers to manually edit invoice totals.

### Types of Coupons
1. **General Discount Coupons**: Global codes (e.g., "SUMMER20") that apply a fixed amount or percentage discount to the entire cart.
2. **Customer Specific Coupons**: Unique codes bound to a specific `customer_id`. Only that customer can redeem the code.

### Usage
- Created in the backend with configurable Expiry Dates and Usage Limits.
- Applied directly at the POS interface.
- **Financial Impact**: Reduces the `grand_total` of the invoice in `db_sales`, thereby reducing the calculated Tax/GST proportionally before hitting the accounts ledger.
