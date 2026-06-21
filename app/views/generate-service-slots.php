<?php

require dirname(__DIR__, 2) . '/config/database.php';

$startDate = new DateTime();
$endDate = (new DateTime())->modify('+90 days');

while ($startDate <= $endDate) {

    // Skip Friday (5) and Saturday (6)
    $dayOfWeek = (int)$startDate->format('w');

    if ($dayOfWeek !== 5 && $dayOfWeek !== 6) {

        $times = [
            '08:00:00',
            '08:30:00',
            '09:00:00',
            '09:30:00',
            '10:00:00',
            '10:30:00',
            '11:00:00',
            '11:30:00',
            '12:00:00',
            '12:30:00',
            '13:00:00',
            '13:30:00',
            '14:00:00',
            '14:30:00'
        ];

        foreach ($times as $time) {

            for ($agent = 1; $agent <= 2; $agent++) {

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM service_slots
                    WHERE service_date = ?
                      AND service_time = ?
                      AND agent_id = ?
                ");

                $stmt->execute([
                    $startDate->format('Y-m-d'),
                    $time,
                    $agent
                ]);

                if ($stmt->fetchColumn() == 0) {

                    $insert = $pdo->prepare("
                        INSERT INTO service_slots
                        (
                            service_date,
                            service_time,
                            agent_id
                        )
                        VALUES (?, ?, ?)
                    ");

                    $insert->execute([
                        $startDate->format('Y-m-d'),
                        $time,
                        $agent
                    ]);
                }
            }
        }
    }

    $startDate->modify('+1 day');
}

echo "Service slots generated successfully.";