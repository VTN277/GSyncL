<?php

namespace App\GG;

class Account
{
    protected $id;
    protected $providerId;
    protected $name;
    protected $email;
    protected $picture;
    protected $token;
    protected $syncToken;
    protected $userId;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Account
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPicture(): string
    {
        return $this->picture;
    }

    public function setPicture(string $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function getToken(): Token
    {
        return $this->token;
    }

    public function setToken(Token $token)
    {
        $this->token = $token;

        return $this;
    }

    public function setProviderId(string $providerId): self
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function setSyncToken(?string $syncToken): self
    {
        $this->syncToken = $syncToken;

        return $this;
    }

    public function getSyncToken(): ?string
    {
        return $this->syncToken;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }
}

