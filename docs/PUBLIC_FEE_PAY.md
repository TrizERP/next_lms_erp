# Parent fee payment without login

Link to send parents: `https://<frontend-host>/pay/<school ShortCode>` (ShortCode from `school_setup`, case-insensitive; it must be unique to one institute).

Flow: mobile number → child (skipped when only one) → choose months / amount → pay → receipt.
The school's gateway comes from `fees_online_maping.bank_name`; nothing is configured per link.

| Piece | Where |
|---|---|
| Page | `lms_k12/app/pay/[code]/` (exempted from the login gate in `ConditionalApp.tsx`) |
| API | `routes/public_pay.php` → `public_fee_pay_api_controller` (`/api/public-pay/{code}/…`) |
| Limits | `RouteServiceProvider::mapPublicFeePayRoutes()` (per school+mobile, plus a per-address ceiling) |

## Gateways

- **Razorpay**: in-page checkout. `pay` creates the order, the browser confirms with `razorpay/confirm`, which verifies the signature, books the fee through `pay_fees()` (row-locked, idempotent) and returns the receipt. If the parent closes the tab before confirming, the existing `razorpay_fetch_payment_status` cron books it after 30 minutes.
- **Every other gateway** (hdfc, axis, aggre_pay, icici, icici_orange, payphi, hdfcrazorpay): the browser POSTs to `checkout`, which runs the legacy `*_request_handler` and returns the page that sends the parent to the bank. The bank's existing response handler books the fee and shows the legacy receipt page.

## Safety model

- The school is named by the link, never by the request.
- Every call re-proves the child: the student must belong to that school and the typed mobile must match (last 10 digits).
- The payable amount is recomputed on the server. `pay_fees()` spreads an amount over months in order, so fixed-fee schools accept only "first N unpaid months in full"; others accept up to the amount due.
- Earlier-year dues are cleared first, as the legacy year dropdown forced.
- Lookups reveal a child's name to anyone who knows the registered mobile number. Limits are keyed on school+mobile because the Next.js relay hides the visitor's address.

## Known gaps

- Bulk/other discounts (`fees_bulk_discount`, used by some hdfcrazorpay/icici schools) are not applied on this page; it always posts discount 0.
- Redirect-style gateways still end on the legacy Blade receipt page.
- Not exercised against live gateways; only the gates in front of the data are covered by `tests/Feature/Fees/PublicFeePayTest.php`.
