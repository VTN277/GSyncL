<?php

namespace App\GG;

use App\GG\Providers\GoogleProvider;
use App\GG\Providers\ProviderInterface;
use Illuminate\Support\Manager;

class CalendarManager extends Manager {

    protected function createGoogleDriver(): ProviderInterface
    {
        $config = $this->config->get('services.google');

        return $this->buildProvider(GoogleProvider::class, $config);
    }

    protected function buildProvider($provider, $config): ProviderInterface
    {
        return new $provider(
            $this->container->make('request'),
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri'],
            $config['scopes']
        );
    }

	public function getDefaultDriver() : string {
        throw new \InvalidArgumentException('No Calendar driver was specified.');
	}
}

