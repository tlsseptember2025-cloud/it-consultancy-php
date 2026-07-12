<?php

class Agent
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM agents
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM agents
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists($email, $excludeId = null)
    {
        if ($excludeId) {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM agents
                WHERE email = ?
                AND id != ?
            ");

            $stmt->execute([$email, $excludeId]);

        } else {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM agents
                WHERE email = ?
            ");

            $stmt->execute([$email]);

        }

        return $stmt->fetch() ? true : false;
    }

    public function create($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO agents
            (
                name,
                email,
                password,
                phone,
                position,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            ),
            $data['phone'],
            $data['position'],
            $data['status']
        ]);
    }

    public function update($id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE agents
            SET
                name = ?,
                email = ?,
                phone = ?,
                position = ?,
                status = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['position'],
            $data['status'],
            $id
        ]);
    }

    public function updatePassword($id, $password)
    {
        $stmt = $this->pdo->prepare("
            UPDATE agents
            SET password = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            $id
        ]);
    }

    public function deactivate($id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE agents
            SET status = 'Inactive'
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}