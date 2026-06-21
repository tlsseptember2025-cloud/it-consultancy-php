<?php

function ensureConsultationSlots(PDO $pdo): void
{
    $targetDate = new DateTime('+90 days');

    $stmt = $pdo->query("
        SELECT MAX(slot_date)
        FROM consultation_slots
    ");

    $lastDate = $stmt->fetchColumn();

    if (!$lastDate) {
        $current = new DateTime('today');
    } else {
        $current = (new DateTime($lastDate))->modify('+1 day');
    }

    while ($current <= $targetDate) {

        // Sunday (0) to Thursday (4)
        $day = (int)$current->format('w');

        if ($day >= 0 && $day <= 4) {

            $times = [
                '17:00:00',
                '17:30:00',
                '18:00:00',
                '18:30:00',
                '19:00:00',
                '19:30:00'
            ];

            foreach ($times as $time) {

                for ($agent = 1; $agent <= 2; $agent++) {

                    $check = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM consultation_slots
                        WHERE slot_date = ?
                          AND slot_time = ?
                          AND agent_id = ?
                    ");

                    $check->execute([
                        $current->format('Y-m-d'),
                        $time,
                        $agent
                    ]);

                    if ($check->fetchColumn() == 0) {

                        $insert = $pdo->prepare("
                            INSERT INTO consultation_slots
                            (
                                slot_date,
                                slot_time,
                                agent_id
                            )
                            VALUES (?, ?, ?)
                        ");

                        $insert->execute([
                            $current->format('Y-m-d'),
                            $time,
                            $agent
                        ]);
                    }
                }
            }
        }

        $current->modify('+1 day');
    }
}

function ensureServiceSlots(PDO $pdo): void
{
    $targetDate = new DateTime('+90 days');

    $stmt = $pdo->query("
        SELECT MAX(service_date)
        FROM service_slots
    ");

    $lastDate = $stmt->fetchColumn();

    if (!$lastDate) {
        $current = new DateTime('today');
    } else {
        $current = (new DateTime($lastDate))->modify('+1 day');
    }

    while ($current <= $targetDate) {

        // Sunday (0) to Thursday (4)
        $day = (int)$current->format('w');

        if ($day >= 0 && $day <= 4) {

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

                    $check = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM service_slots
                        WHERE service_date = ?
                          AND service_time = ?
                          AND agent_id = ?
                    ");

                    $check->execute([
                        $current->format('Y-m-d'),
                        $time,
                        $agent
                    ]);

                    if ($check->fetchColumn() == 0) {

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
                            $current->format('Y-m-d'),
                            $time,
                            $agent
                        ]);
                    }
                }
            }
        }

        $current->modify('+1 day');
    }
}