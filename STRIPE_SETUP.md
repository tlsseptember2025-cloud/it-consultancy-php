# Stripe Card Payment Setup

This project adds Stripe card checkout alongside the existing Bank Transfer workflow.

## 1. Database

Run:

`database/migrations/2026_09_29_add_stripe_payment_fields.sql`

Run it once on each database that uses the same application schema (local/dev/demo). The Demo site does not expose the Stripe checkout route.

## 2. Environment variables

Add these to the existing `.env` file:

```ini
STRIPE_SECRET_KEY=sk_test_REPLACE_ME
STRIPE_PUBLISHABLE_KEY=pk_test_REPLACE_ME
STRIPE_WEBHOOK_SECRET=whsec_REPLACE_ME
```

Do not commit `.env`. It is already excluded by `.gitignore`.

## 3. Webhook endpoint

Configure a Stripe webhook endpoint pointing to:

`https://YOUR-DOMAIN/index.php?page=stripe-webhook`

For the development site, use the actual public development domain. For local-only testing, Stripe must be able to reach the webhook through a suitable development forwarding method.

Enable these events:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`

Copy the endpoint signing secret into `STRIPE_WEBHOOK_SECRET`.

## 4. Test mode

Start with Stripe test-mode keys. Test:

1. Customer accepts a proposal.
2. Customer sees **Pay by Card** and **Bank Transfer**.
3. Card checkout opens with the request amount.
4. Successful Stripe payment creates/updates the existing `payments` record.
5. Payment becomes `Paid` and records the Stripe IDs.
6. Request moves to `Awaiting Service Scheduling`.
7. Customer and admin receive notifications.
8. Refreshing the success page does not create a duplicate payment.
9. Replayed webhook events do not create a duplicate payment.
10. Cancelled checkout leaves the request at `Awaiting Payment`.
11. Existing bank-transfer upload/approval still works.

## 5. Live mode

Only after the complete test flow is verified, replace the test secret/webhook values with the live values and configure the live webhook endpoint.
