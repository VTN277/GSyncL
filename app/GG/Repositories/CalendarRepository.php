<?php

namespace App\GG\Repositories;

class CalendarRepository extends DBRepository
{
    /**
     * @return string
     */
    public function getTable(): string
    {
        return 'calendars';
    }
}
