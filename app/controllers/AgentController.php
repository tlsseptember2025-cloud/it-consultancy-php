<?php

require_once __DIR__ . '/../models/Agent.php';

class AgentController
{
    private Agent $agent;

    public function __construct(PDO $pdo)
    {
        $this->agent = new Agent($pdo);
    }

    public function index()
    {
        return $this->agent->getAll();
    }

    public function show($id)
    {
        return $this->agent->getById($id);
    }

    public function store($data)
    {
        if ($this->agent->emailExists($data['email'])) {
            return [
                'success' => false,
                'message' => 'Email address already exists.'
            ];
        }

        $this->agent->create($data);

        return [
            'success' => true,
            'message' => 'Agent created successfully.'
        ];
    }

    public function update($id, $data)
    {
        if ($this->agent->emailExists($data['email'], $id)) {
            return [
                'success' => false,
                'message' => 'Email address already exists.'
            ];
        }

        $this->agent->update($id, $data);

        return [
            'success' => true,
            'message' => 'Agent updated successfully.'
        ];
    }

    public function resetPassword($id, $password)
    {
        $this->agent->updatePassword($id, $password);

        return [
            'success' => true,
            'message' => 'Password updated successfully.'
        ];
    }

    public function deactivate($id)
    {
        $this->agent->deactivate($id);

        return [
            'success' => true,
            'message' => 'Agent deactivated successfully.'
        ];
    }
}