<?php

namespace App\GG\Repositories;

class EventRepository extends DBRepository
{
    /**
     * @return string
     */
    public function getTable(): string
    {
        return 'calendar_events';
    }
}
