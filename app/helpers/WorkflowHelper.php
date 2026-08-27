<?php

function workflowBadge(string $stage): string
{
    switch ($stage) {

        case 'Consultation Confirmed':
            $class = 'bg-primary';
            break;

        case 'Consultation Decision Required':
            $class = 'bg-warning text-dark';
            break;

        case 'Awaiting Customer Response':
        case 'Waiting Customer Response':
            $class = 'bg-secondary';
            break;

        case 'Proposal Draft':
            $class = 'bg-dark';
            break;

        case 'Customer Contact':
            $class = 'bg-info text-dark';
            break;

        case 'Needs Admin Review':
            $class = 'bg-danger';
            break;

        case 'Consultation In Progress':
            $class = 'bg-warning text-dark';
            break;

        case 'Completed':
            $class = 'bg-success';
            break;

        default:
            $class = 'bg-light text-dark border';
            break;
    }

    return sprintf(
        '<span class="badge %s">%s</span>',
        $class,
        htmlspecialchars($stage)
    );
}