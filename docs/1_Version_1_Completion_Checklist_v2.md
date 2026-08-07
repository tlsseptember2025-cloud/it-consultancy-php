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
