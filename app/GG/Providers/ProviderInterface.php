<?php

namespace App\GG\Providers;
use App\GG\Account;

interface ProviderInterface
{
    public function callback();
    public function synchronize(string $resource, Account $account);
}
