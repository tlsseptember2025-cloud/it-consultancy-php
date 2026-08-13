<?php

class RequestEventHelper
{
    /**
     * Add a new request event.
     */

        /*
    |--------------------------------------------------------------------------
    | Event Types
    |--------------------------------------------------------------------------
    */

    public const TYPE_REQUEST = 'Request';

    public const TYPE_CONTACT = 'Customer Contact';

    public const TYPE_CONSULTATION = 'Consultation';

    public const TYPE_PROPOSAL = 'Proposal';

    public const TYPE_PAYMENT = 'Payment';

    public const TYPE_SERVICE = 'Service';

    public const TYPE_REFUND = 'Refund';

    public const TYPE_ARCHIVE = 'Archive';

    public const TYPE_SYSTEM = 'System';

    public const EVENT_CUSTOMER_RESPONSE_RECORDED = 'CUSTOMER_RESPONSE_RECORDED';


    /*
    |--------------------------------------------------------------------------
    | Event Sources
    |--------------------------------------------------------------------------
    */

    public const SOURCE_CUSTOMER = 'Customer';

    public const SOURCE_AGENT = 'Agent';

    public const SOURCE_ADMINISTRATOR = 'Administrator';

    public const SOURCE_SYSTEM = 'System';


    /*
    |--------------------------------------------------------------------------
    | Event Codes
    |--------------------------------------------------------------------------
    */

    public const EVENT_REQUEST_CREATED = 'REQUEST_CREATED';

    public const EVENT_AGENT_ASSIGNED = 'AGENT_ASSIGNED';

    public const EVENT_AGENT_REASSIGNED = 'AGENT_REASSIGNED';

    public const EVENT_CONTACT_ATTEMPT = 'CONTACT_ATTEMPT';

    public const EVENT_CONTACT_ATTEMPT_APPROVED = 'CONTACT_ATTEMPT_APPROVED';

    public const EVENT_CUSTOMER_REQUESTED_NEW_CONSULTATION = 'CUSTOMER_REQUESTED_NEW_CONSULTATION';

    public const EVENT_CUSTOMER_REQUESTED_CLOSURE = 'CUSTOMER_REQUESTED_CLOSURE';

    public const EVENT_CUSTOMER_ANSWERED = 'CUSTOMER_ANSWERED';

    public const EVENT_NO_ANSWER = 'NO_ANSWER';

    public const EVENT_ADMIN_CLOSURE_CONFIRMED = 'ADMIN_CLOSURE_CONFIRMED';

    public const EVENT_CONSULTATION_CLOSED = 'CONSULTATION_CLOSED';

    public const EVENT_WRONG_NUMBER = 'WRONG_NUMBER';

    public const EVENT_CONSULTATION_SCHEDULED = 'CONSULTATION_SCHEDULED';

    public const EVENT_CONSULTATION_CONFIRMED = 'CONSULTATION_CONFIRMED';

    public const EVENT_CONSULTATION_COMPLETED = 'CONSULTATION_COMPLETED';

    public const EVENT_CONSULTATION_RESCHEDULE_APPROVED = 'CONSULTATION_RESCHEDULE_APPROVED';

    public const EVENT_CONSULTATION_RESCHEDULED = 'CONSULTATION_RESCHEDULED';

    public const EVENT_PROPOSAL_SENT = 'PROPOSAL_SENT';

    public const EVENT_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';

    public const EVENT_SERVICE_STARTED = 'SERVICE_STARTED';

    public const EVENT_SERVICE_RESCHEDULED = 'SERVICE_RESCHEDULED';

    public const EVENT_SERVICE_COMPLETED = 'SERVICE_COMPLETED';

    public const EVENT_ARCHIVED = 'ARCHIVED';

    public const EVENT_RESTORED = 'RESTORED';

    public static function add(
    PDO $pdo,
    int $requestId,
    string $eventCode,
    string $eventType,
    string $eventTitle,
    ?string $eventDescription,
    string $eventSource,
    ?int $sourceId = null,
    bool $customerVisible = false
    ): int {

        $stmt = $pdo->prepare("
            INSERT INTO request_events
            (
                request_id,
                event_code,
                event_type,
                event_title,
                event_description,
                event_source,
                source_id,
                customer_visible
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

      if (!$stmt->execute([
        $requestId,
        $eventCode,
        $eventType,
        $eventTitle,
        $eventDescription,
        $eventSource,
        $sourceId,
        $customerVisible ? 1 : 0
    ])) {

    return 0;

}

return (int)$pdo->lastInsertId();
    
}

/**
 * Add a request event using the currently logged-in user role.
 */
public static function addCurrentUser(
    PDO $pdo,
    int $requestId,
    string $eventCode,
    string $eventType,
    string $eventTitle,
    ?string $eventDescription,
    bool $customerVisible = false
): int {

    $eventSource = self::SOURCE_SYSTEM;
    $sourceId = null;

    if (isset($_SESSION['user'])) {

        $eventSource = self::SOURCE_ADMINISTRATOR;

    } elseif (isset($_SESSION['agent'])) {

        $eventSource = self::SOURCE_AGENT;

        if (is_array($_SESSION['agent']) && isset($_SESSION['agent']['id'])) {
            $sourceId = (int) $_SESSION['agent']['id'];
        } elseif (is_numeric($_SESSION['agent'])) {
            $sourceId = (int) $_SESSION['agent'];
        }

    } elseif (isset($_SESSION['customer'])) {

        $eventSource = self::SOURCE_CUSTOMER;

        if (
            is_array($_SESSION['customer'])
            && isset($_SESSION['customer']['id'])
        ) {
            $sourceId = (int) $_SESSION['customer']['id'];
        } elseif (is_numeric($_SESSION['customer'])) {
            $sourceId = (int) $_SESSION['customer'];
        }
    }

    return self::add(
        $pdo,
        $requestId,
        $eventCode,
        $eventType,
        $eventTitle,
        $eventDescription,
        $eventSource,
        $sourceId,
        $customerVisible
    );
}

    /**
     * Get all events for a request.
     */
    public static function get(
        PDO $pdo,
        int $requestId
    ): array {

        $stmt = $pdo->prepare("
            SELECT *
            FROM request_events
            WHERE request_id = ?
            ORDER BY created_at DESC, id DESC
        ");

        $stmt->execute([$requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    /**
 * Get customer-visible events for a request.
 */
public static function getCustomerVisible(
    PDO $pdo,
    int $requestId
): array {

    $stmt = $pdo->prepare("
        SELECT *
        FROM request_events
        WHERE request_id = ?
          AND customer_visible = 1
        ORDER BY created_at DESC, id DESC
    ");

    $stmt->execute([$requestId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Get the latest event.
     */
    public static function latest(
        PDO $pdo,
        int $requestId
    ): ?array {

        $stmt = $pdo->prepare("
            SELECT *
            FROM request_events
            WHERE request_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$requestId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    }

    /**
     * Count events.
     */
    public static function count(
        PDO $pdo,
        int $requestId
    ): int {

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM request_events
            WHERE request_id = ?
        ");

        $stmt->execute([$requestId]);

        return (int)$stmt->fetchColumn();

    }

    /**
     * Delete all events.
     * Normally only used when deleting a request.
     */
    public static function delete(
        PDO $pdo,
        int $requestId
    ): bool {

        $stmt = $pdo->prepare("
            DELETE
            FROM request_events
            WHERE request_id = ?
        ");

        return $stmt->execute([$requestId]);

    }
}