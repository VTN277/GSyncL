<?php

namespace App\GG\Services;
use App\GG\Account;
use App\GG\Repositories\AccountRepository;
use Illuminate\Support\Facades\Crypt;


class AccountService
{
    protected $accountRepository;

    public function __construct(AccountRepository $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    public function createFrom(Account $account, string $provider)
    {
        $token = $account->getToken();
        $userId = $account->getUserId();
        $providerId = $account->getProviderId();

        $payload = [
            'name' => $account->getName(),
            'email' => $account->getEmail(),
            'picture' => $account->getPicture(),
            'updated_at' => now()
        ];

        $payload['token'] = Crypt::encrypt($token->toArray());

        $accountModel = $this->accountRepository
            ->setColumns(['id'])
            ->findByAttributes([
                'user_id' => $userId,
                'provider_id' => $providerId,
                'provider_type' => $provider,
            ]);

        if (isset($accountModel)) {
            $accountId = $accountModel->id;

            $this->accountRepository->updateByAttributes(['id' => $accountId], $payload);

            return $accountId;
        }

        return $this->accountRepository->create(
            array_merge($payload, [
                'provider_type' => $provider,
                'provider_id' => $providerId,
                'user_id' => $userId,
                'created_at' => now()
            ])
        );
    }
}
