# IT Consultancy Management System

## Version 1 Completion Checklist

### Records Lifecycle & Retention Policy (UAE)

``` text
Active Request
      ↓
Closed
(90-day operational retention)
      ↓
Archived
(5-year minimum retention)
      ↓
Retention Expired
      ↓
Administrator Decision
    ├── Extend Retention
    ├── Legal Hold
    ├── Export
    └── Delete
```

### Design Decisions

-   Closed requests remain available in the Closed Requests queue.
-   After 90 days, eligible requests move to the Archived Requests
    queue.
-   Archived requests are retained for a minimum of 5 years.
-   Records are not deleted automatically.
-   After the retention period expires, records move to a Retention
    Expired queue.
-   Administrator options:
    -   Extend retention.
    -   Legal hold.
    -   Export.
    -   Permanently delete.

### Future System Settings

-   Closed Retention: 90 days
-   Archive Retention: 5 years
-   Deletion Policy: Manual Approval
-   Legal Hold: Enabled


---

# Future Improvement – Customer Response Portal (Planned)

## Current Version (Version 1)

After the consultation could not be completed:

1. Agent records the contact result.
2. Administrator sends Verification Email #1.
3. If no response is received, Administrator sends Verification Email #2.
4. If the customer responds, the Administrator manually records:
   - Response Method
   - Customer Decision
   - Administrator Notes
5. The workflow continues based on the recorded customer decision.

This workflow is fully functional and remains part of Version 1.

---

## Planned Improvement (Version 1.1 / Version 2)

Replace manual customer response recording with a secure customer response portal.

### New Workflow

Agent marks consultation as:

- No Answer
- Wrong Number
- Customer Unavailable

↓

Administrator sends Verification Email #1

↓

No response received

↓

Administrator sends Verification Email #2

↓

Customer clicks secure link included in the verification email

↓

Customer Response Form

Customer completes:

- Customer Decision
- Additional Comments

The system automatically records:

- Response Method = Email Link
- Submission Date & Time
- Customer IP Address

↓

Customer response is stored in a dedicated table:

customer_responses

Status:

- Pending Review

↓

Administrator receives notification

↓

Request appears in:

Awaiting Customer Response

↓

Administrator opens:

Review Customer Response

The administrator reviews the customer's submitted response and processes the workflow accordingly.

---

## Proposed Database Table

customer_responses

- id
- request_id
- customer_decision
- customer_comments
- response_method
- submitted_at
- ip_address
- status

Status values:

- Pending Review
- Processed

---

## Administrator Review Page

The administrator will no longer manually enter customer responses.

Instead, the page will automatically display:

- Response Method
- Customer Decision
- Customer Comments
- Submitted Date
- IP Address (optional)

Administrator actions:

- Approve / Process Response

---

## Benefits

- Customer decisions originate directly from the customer.
- Eliminates duplicate manual data entry.
- Provides a complete audit trail.
- Automatically records submission date and time.
- Automatically records response source (Email Link).
- Supports future self-service customer portal enhancements.
- Keeps the administrator responsible only for reviewing and processing customer responses.

---

## Records Lifecycle & Retention Policy

### Closed Requests

- Requests remain in the **Closed Requests** queue for **90 days**.

↓

### Archived Requests

- After 90 days, eligible requests are automatically moved to the **Archived Requests** queue.
- Archived requests are read-only.
- Archived requests remain searchable.

↓

### Minimum Retention Period

- Archived requests are retained for a **minimum of 5 years**.

↓

### Retention Review

When a request reaches **5 years**, it moves to a new queue:

**Retention Review**

The administrator can choose:

- Extend retention by **1 year**
- Export the record
- Place the record on Legal Hold
- Permanently delete the record (if permitted by company policy)

↓

### Annual Review

If retention is extended:

- The request returns to Archived.
- It is reviewed again every year.

This continues until:

- The administrator deletes the record, or
- The record reaches **7 years**.

↓

### Final Review (7 Years)

At **7 years**, the administrator performs the final retention review.

Available actions:

- Export the record
- Place the record on Legal Hold
- Permanently delete the record

No automatic deletion occurs.

Deletion always requires administrator approval.