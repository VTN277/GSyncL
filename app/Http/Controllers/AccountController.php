<?php

namespace App\Http\Controllers;

use App\GG\Account;
use App\GG\CalendarManager;
use App\GG\Repositories\AccountRepository;
use App\GG\Repositories\CalendarRepository;
use App\GG\Services\AccountService;
use App\GG\TokenFactory;
use Carbon\Carbon;
use DateTimeZone;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AccountController extends Controller
{
    protected $manager;

    public function __construct(CalendarManager $manager)
    {
        $this->manager = $manager;
    }

    public function auth(Request $request, string $driver): RedirectResponse
    {
        $provider = $this->manager->driver($driver);
        try {
            return $provider->redirect();
        } catch (\InvalidArgumentException $exception) {
            report($exception);

            abort(400, $exception->getMessage());
        }
    }

    public function callback(string $driver): RedirectResponse
    {

        $provider = $this->manager->driver($driver);
        $account = $provider->callback();

        $accountId = app(AccountService::class)->createFrom($account, $driver);

        $account->setId($accountId);


        if (!$provider->synchronize('Calendar', $account)) {
            //create new calendar and push event
        }

        return redirect()->to(
          config('services.' . $driver . '.redirect_callback', '/')
        );
    }

    public function getEvent()
    {
        // $provider = app(CalendarManager::class)->driver('google');

        // $accounts = app(AccountRepository::class)->get();

        // foreach ($accounts as $accountModel) {
        //     $provider->synchronize('Calendar', tap(new Account(), function ($account) use ($accountModel) {

        //         $token = Crypt::decrypt($accountModel->token);
        //         $syncToken = '';

        //         if (isset($accountModel->sync_token)) {
        //             $syncToken = Crypt::decryptString($accountModel->sync_token);
        //         }

        //         $account
        //             ->setId($accountModel->id)
        //             ->setProviderId($accountModel->provider_id)
        //             ->setUserId($accountModel->user_id)
        //             ->setName($accountModel->name)
        //             ->setEmail($accountModel->email)
        //             ->setPicture($accountModel->picture)
        //             ->setSyncToken($syncToken)
        //             ->setToken(TokenFactory::create($token));
        //     }));
        // }



        $accountId = 10;

        $accountModel = app(AccountRepository::class)->find($accountId);

        $provider = app(CalendarManager::class)->driver('google');

        $calendars = app(CalendarRepository::class)->getByAttributes([
            'account_id' => $accountId
        ]);

        $account = tap(new Account(), function ($account) use ($accountModel) {

            $token = Crypt::decrypt($accountModel->token);
            $syncToken = '';

            if (isset($accountModel->sync_token)) {
                $syncToken = Crypt::decryptString($accountModel->sync_token);
            }

            $account
                ->setId($accountModel->id)
                ->setProviderId($accountModel->provider_id)
                ->setUserId($accountModel->user_id)
                ->setName($accountModel->name)
                ->setEmail($accountModel->email)
                ->setPicture($accountModel->picture)
                ->setSyncToken($syncToken)
                ->setToken(TokenFactory::create($token));
        });

        $newAccount =  $provider->genNewToken($account);
        $accountId = app(AccountService::class)->createFrom($account, 'google');
        foreach ($calendars as $calendar) {
            $options = ['calendarId' => $calendar->provider_id];

            if (isset($calendar->sync_token)) {
                $options['syncToken'] = Crypt::decryptString($calendar->sync_token);
            }

            $provider->synchronize('Event', $newAccount, $options);
        }

    }

    public function pushEvent()
    {
        $accountId = 10;

        $accountModel = app(AccountRepository::class)->find($accountId);

        $provider = app(CalendarManager::class)->driver('google');

        $account = tap(new Account(), function ($account) use ($accountModel) {

            $token = Crypt::decrypt($accountModel->token);
            $syncToken = '';

            if (isset($accountModel->sync_token)) {
                $syncToken = Crypt::decryptString($accountModel->sync_token);
            }

            $account
                ->setId($accountModel->id)
                ->setProviderId($accountModel->provider_id)
                ->setUserId($accountModel->user_id)
                ->setName($accountModel->name)
                ->setEmail($accountModel->email)
                ->setPicture($accountModel->picture)
                ->setSyncToken($syncToken)
                ->setToken(TokenFactory::create($token));
        });
        $provider = app(CalendarManager::class)->driver('google');
        $provider->insertMyevent('c_3687a656498676cec44e4bbbd43ba0e852e93a39f0d88d4be59fa9bb8b607b75@group.calendar.google.com', $account);
    }
}
