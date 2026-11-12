# GOZORX — Initial Front-End Prototype

This is a responsive, browser-based front-end prototype for the GOZORX agricultural B2B marketplace.

## Run
Open `index.html` in a modern browser. Internet access is used only for the Google Fonts stylesheet; the app itself is static.

## Included demo flows
- Landing page and agricultural category overview
- Product catalog with search and category filtering
- Cart with MOQ-based quantity increments
- Demo checkout and payment-method selection
- RFQ submission form
- Nursery mixed-needs request form
- Supplier application form
- Demo admin screen to approve/reject supplier applications
- Arabic-first layout with a language toggle placeholder

## Important: not production-ready
This package is a UI prototype only. It does not include a server, database, real authentication, secure admin roles, product persistence, actual supplier onboarding, payment gateway, bank transfer verification, shipping calculations, email/SMS notifications, or legally binding order/cancellation workflows. Form submissions are held only in browser memory and are lost on refresh.

## Proposed production architecture
- Frontend: Next.js / React
- Backend: Laravel REST API
- Database: PostgreSQL
- Cache/queues: Redis
- Object storage: S3-compatible storage
- Authentication: secure server-side sessions / token strategy with role-based permissions
- Payment: Egyptian payment provider selected after commercial and technical review

## Critical production rules
1. Supplier accounts must remain `pending_approval` until an authorized admin approves them.
2. Product create/update/publish endpoints must enforce supplier approval on the backend.
3. Platform collects customer payment and settles supplier payable after fulfillment and applicable dispute checks.
4. Non-conforming orders are supplier-responsibility cases; conforming orders rejected by the buyer without an accepted reason are buyer-responsibility cases. Exact refund, return, evidence and fee rules must be approved in legal/commercial policy before launch.
5. RFQ supplier quotations must be private to the requester and authorized platform roles.
6. Nursery requests must be visible only to authorized GOZORX operations staff.

## Next implementation stage
Build the Laravel API, PostgreSQL schema, role/permission system, supplier approval workflow, product moderation, order/payment ledger, RFQ, nursery request workflows, then connect the Next.js frontend.
