<?php

namespace App\Auth\Service;

use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;

class RegisterService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Register a new user.
     *
     * @param array<string, mixed> $data
     * @return User
     */
    public function register(array $data): User
    {
        return $this->userRepository->create($data);
    }
}

