<?php

if (!isset($_SESSION['user'])) {
    header("Location: ?page=login");
    exit;
}

require dirname(__DIR__, 2) . '/config/database.php';

$startDate = new DateTime();
$endDate   = (new DateTime())->modify('+60 days');

$times = [
    '17:00:00',
    '17:30:00',
    '18:00:00',
    '18:30:00',
    '19:00:00',
    '19:30:00'
];

$created = 0;

for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {

    // PHP: 0 = Sunday, 4 = Thursday
    $dayOfWeek = (int)$date->format('w');

    // Sunday -> Thursday only
    if ($dayOfWeek > 4) {
        continue;
    }

    foreach ($times as $time) {

        // Agent 1 and Agent 2
        for ($agent = 1; $agent <= 2; $agent++) {

            $check = $pdo->prepare("
                SELECT id
                FROM consultation_slots
                WHERE slot_date = ?
                  AND slot_time = ?
                  AND agent_id = ?
            ");

            $check->execute([
                $date->format('Y-m-d'),
                $time,
                $agent
            ]);

            if (!$check->fetch()) {

                $insert = $pdo->prepare("
                    INSERT INTO consultation_slots
                    (
                        slot_date,
                        slot_time,
                        agent_id,
                        is_booked
                    )
                    VALUES
                    (
                        ?, ?, ?, 0
                    )
                ");

                $insert->execute([
                    $date->format('Y-m-d'),
                    $time,
                    $agent
                ]);

                $created++;

            }

        }

    }

}

echo "<h2>Done!</h2>";
echo "<p>{$created} consultation slots generated.</p>";