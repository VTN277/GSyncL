<?php

namespace App\GG\Providers\Synchronizers;

use App\GG\Account;
use App\GG\Providers\ProviderInterface;
use App\GG\Repositories\AccountRepository;
use App\GG\Repositories\CalendarRepository;
use App\GG\Repositories\EventRepository;
use Carbon\Carbon;
use Google\Service\Localservices\Resource\AccountReports;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class GoogleSynchronizer
{
    protected $repositories = [];

    protected $httpClient;

    protected $provider;

    public function __construct(ProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    protected function call(string $method, string $uri = '', array $options = []): array
    {
        $response = $this->httpClient()->request($method, $uri, [
            'headers' => $this->headers($options['headers'] ?? []),
            'query' => $options['query'],
        ]);

        $body = (string) $response->getBody();

        return json_decode($body, true);
    }

    public function synchronizeCalendars(Account $account, array $options = [])
    {
        $token = $account->getToken();
        $accountId = $account->getId();
        $syncToken = $account->getSyncToken();

        if ($token->isExpired()) {
            return false;
        }

        $query = array_merge([
            'maxResults' => 100,
            'minAccessRole' => 'owner', // The user can read and modify events and access control lists.
        ], $options['query'] ?? []);

        if (isset($syncToken)) {
            $query = [
                'syncToken' => $syncToken,
            ];
        }

        $body = $this->call('GET', "/calendar/{$this->provider->getVersion()}/users/me/calendarList", [
            'headers' => ['Authorization' => 'Bearer ' . $token->getAccessToken()],
            'query' => $query
        ]);

        $nextSyncToken = $body['nextSyncToken'];
        $calendarIterator = new \ArrayIterator($body['items']);

        $calendarRepository = app(CalendarRepository::class);

        // Check user calendars
        $providersIds = $calendarRepository
            ->setColumns(['provider_id'])
            ->getByAttributes(['account_id' => $accountId, 'provider_type' => $this->provider->getProviderName()])
            ->pluck('provider_id');

        $now = now();

        while ($calendarIterator->valid()) {
            $calendar = $calendarIterator->current();
            $calendarId = $calendar['id'];
            $attributes = [
                'provider_id' => $calendarId,
                'provider_type' => $this->provider->getProviderName(),
                'account_id' => $accountId,
            ];

            // Delete account calendar by ID
            if (key_exists('deleted', $calendar) && $calendar['deleted'] === true && $providersIds->contains($calendarId)) {
                $calendarRepository->deleteWhere($attributes);

            // Update account calendar by ID
            } else if ($providersIds->contains($calendarId)) {
                $calendarRepository->updateByAttributes(
                    $attributes,
                    [
                        'summary' => $calendar['summary'],
                        'timezone' => $calendar['timeZone'],
                        'description' => $calendar['description'] ?? null,
                        'updated_at' => $now,
                    ]
                );
            // Create account calendar
            } else {
                $calendarRepository->insert(
                    array_merge($attributes, [
                        'summary' => $calendar['summary'],
                        'timezone' => $calendar['timeZone'],
                        'description' => $calendar['description'] ?? null,
                        'selected' => $calendar['selected'] ?? false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }

            $calendarIterator->next();
        }

        $this->repository(AccountRepository::class)->updateByAttributes(
            ['id' => $accountId],
            ['sync_token' => Crypt::encryptString($nextSyncToken), 'updated_at' => $now]
        );
    }

    public function synchronizeEvents(Account $account, array $options = [])
    {
        $token = $account->getToken();
        $accountId = $account->getId();
        $calendarId = $options['calendarId'] ?? 'primary';
        $pageToken = $options['pageToken'] ?? null;
        $syncToken = $options['syncToken'] ?? null;

        $now = now();

        $query = Arr::only($options, ['timeMin', 'timeMax', 'maxResults']);
        $query = array_merge($query, [
            'maxResults' => 1,
            'timeMin' => $now->copy()->startOfMonth()->toRfc3339String(),
            'timeMax' => $now->copy()->addMonth()->toRfc3339String()
        ]);

        $calendarRepository = $this->repository(CalendarRepository::class);

        if ($token->isExpired()) {
            return false;
        }

        // if (isset($syncToken)) {
        //     $query = [
        //         'syncToken' => $syncToken,
        //     ];
        // }

        $eventRepository = $this->repository(EventRepository::class);

        $eventIds = $eventRepository
            ->setColumns(['provider_id'])
            ->getByAttributes([
                'calendar_id' => $calendarId,
                'provider_type' => $this->provider->getProviderName()
            ])
            ->pluck('provider_id');

        $url = "/calendar/{$this->provider->getVersion()}/calendars/${calendarId}/events";

        do {
            if (isset($pageToken) && empty($syncToken)) {
                $query = [
                    'pageToken' => $pageToken
                ];
            }

            Log::debug('Synchronize Events', [
                'query' => $query
            ]);


            $body = $this->call('GET', $url, [
                'headers' => ['Authorization' => 'Bearer ' . $token->getAccessToken()],
                'query' => $query
            ]);

            $items = $body['items'];

            $pageToken = $body['nextPageToken'] ?? null;

            // Skip loop
            if (count($items) === 0) {
                break;
            }

            $itemIterator = new \ArrayIterator($items);

            while ($itemIterator->valid()) {
                $event = $itemIterator->current();

                $this->synchronizeEvent($event, $calendarId, $eventIds);

                $itemIterator->next();
            }

        } while (is_null($pageToken) === false);

        $syncToken = $body['nextSyncToken'];
        $now = now();

        $calendarRepository->updateByAttributes(
            ['provider_id' => $calendarId, 'account_id' => $accountId],
            [
                'sync_token' => Crypt::encryptString($syncToken),
                'last_sync_at' => $now,
                'updated_at' => $now
            ]
        );
    }

    protected function synchronizeEvent($event, $calendarId, Collection $eventIds): void
    {
        $eventId = $event['id'];

        $eventRepository = $this->repository(EventRepository::class);

        $attributes = [
            'calendar_id' => $calendarId,
            'provider_id' => $eventId,
            'provider_type' => $this->provider->getProviderName()
        ];

        // Delete event if status is cancelled
        if ($event['status'] === 'cancelled') {

            if ($eventIds->contains($eventId)) {
                Log::debug('Delete Event', $attributes);

                $eventRepository->deleteWhere($attributes);
            }

            return;
        }

        $eventStart = $this->parseDateTime($event['start'] ?? null);
        $eventEnd = $this->parseDateTime($event['end'] ?? null);

        $isAllDay = isset($event['start']['date']);

        // Update event bu ID
        if ($eventIds->contains($eventId)) {
            Log::debug('Update Event', $attributes);

            $eventRepository->updateByAttributes(
                $attributes,
                [
                    'summary' => $event['summary'],
                    'is_all_day' => $isAllDay,
                    'description' => $event['description'] ?? null,
                    'start_at' => $eventStart,
                    'end_at' => $eventEnd,
                    'updated_at' => new \DateTime(),
                ]
            );
        // Create event
        } else {
            Log::debug('Create Event', $attributes);

            $eventRepository->insert(
                array_merge($attributes, [
                    'summary' => $event['summary'],
                    'description' => $event['description'] ?? null,
                    'start_at' => $eventStart,
                    'end_at' =>  $eventEnd,
                    'is_all_day' => $isAllDay,
                    'created_at' => new \DateTime(),
                    'updated_at' => new \DateTime(),
                ])
            );
        }
    }

    protected function parseDateTime($eventDateTime): Carbon
    {
        if (isset($eventDateTime)) {
            $eventDateTime = $eventDateTime['dateTime'] ?? $eventDateTime['date'];
        }

        return Carbon::parse($eventDateTime)->setTimezone('UTC');
    }

    protected function repository(string $name)
    {
        if (key_exists($name, $this->repositories) === true) {
            return $this->repositories[$name];
        }

        $this->repositories[$name] = app()->get($name);

        return $this->repositories[$name];
    }

    protected function httpClient(): Client
    {
        if (empty($this->httpClient)) {
            $this->httpClient = app(Client::class, [
                'config' => [
                    'base_uri' => 'https://www.googleapis.com',
                    'headers' => $this->headers()
                ]
            ]);
        }

        return $this->httpClient;
    }

    public function setHttpClient(Client $httpClient): static
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    protected function headers(array $headers = []): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip',
            'User-Agent' => config('app.name') . ' (gzip)',

        ], $headers);
    }
}
