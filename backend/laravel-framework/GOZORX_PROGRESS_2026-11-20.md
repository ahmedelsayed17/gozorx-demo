# GOZORX - Progress Checkpoint
Date: 2026-11-20
Branch: backend-foundation

## Completed
- Added nullable receipt_uploaded_at timestamp to payment_transactions.
- PaymentTransaction model fillable and datetime cast updated.
- Receipt upload records a fresh timestamp on every upload/reupload.
- Receipt upload is blocked at or after deposit deadline.
- Reupload after rejection preserves review_note and original deadline.
- Admin review accepts only receipts uploaded at or before deadline.
- Expiry command skips timely receipts pending admin review.
- Rejected/late receipt orders expire and release reserved stock once.
- Reviewed PaymentController, ExpireUnpaidOrders, routes/api.php,
  PaymentTransaction model, and base payment migration.

## Test Results
- ExpireUnpaidOrdersTest: 3 passed, 13 assertions.
- PaymentAdminReviewTest: 11 passed, 48 assertions.
- Full suite: 53 passed, 213 assertions.

## Important Policies
- Each distinct individual product purchase creates an independent Order.
- Same product quantity is grouped in one order.
- Shipping cost is manually set by admin.
- Deposit is 30% of grand total including shipping, rounded half-up.
- Remaining balance is 70%.
- Stock reservation and 24-hour deposit deadline start after shipping is priced.
- Do not remove legacy SupplierOrder/OrderItem without dependency review.
- No commit/push without explicit approval.

## Next Steps
1. Inspect:
   database/migrations/2026_11_20_090018_add_receipt_uploaded_at_to_payment_transactions_table.php
2. Find where payment transactions are created.
3. Verify whether duplicate deposit transactions per order are prevented.
4. Find where legacy supplier_order_id is added.
5. Do not modify code until a concrete issue is identified.

## Working Rules
- Egyptian Arabic, detailed step-by-step instructions.
- One terminal command at a time; wait for output.
- Do not change business policies without asking.
- Do not commit or push without approval.
