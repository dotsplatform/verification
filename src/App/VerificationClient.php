<?php
/**
 * Description of VerificationClient.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Oleksandr Polosmak <o.polosmak@dotsplatform.com>
 */

namespace MA\App\Verification;


use  MA\App\Verification\DTO\UserDTO;
use GuzzleHttp\Exception\ClientException;
use MA\App\Verification\DTO\StoreAccountDTO;
use MA\App\Verification\DTO\VerificationAccountSettingsDTO;
use MA\App\Verification\Exception\TooManyVerificationAttempts;
use MA\App\Verification\Exception\VerificationCodeException;
use MA\App\Verification\Exception\VerificationHttpClientException;

class VerificationClient extends HttpClient
{
    private const STORE_ACCOUNT_URL_TEMPLATE = '/api/accounts';
    private const FIND_ACCOUNT_URL_TEMPLATE = '/api/accounts/%s';
    private const STORE_USER_URL_TEMPLATE = '/api/accounts/%s/users';
    private const FIND_USER_URL_TEMPLATE = '/api/accounts/%s/users/%s';
    private const START_VERIFICATION_URL_TEMPLATE = '/api/accounts/%s/verification/start';
    private const CONFIRM_URL_TEMPLATE = '/api/accounts/%s/verification/confirm';
    private const DELETE_USER_URL_TEMPLATE = '/api/accounts/%s/users/%s';

    public function storeAccount(StoreAccountDTO $dto): void
    {
        $url = $this->generateStoreAccountUrl();
        $body = [
            'id' => $dto->getId(),
            'name' => $dto->getName(),
            'lang' => $dto->getLang(),
            'settings' => $dto->getSettings()->toArray(),
        ];
        try {
            $this->post($url, $body);
        } catch (ClientException) {
            return;
        }
    }

    public function findAccountSettings(string $accountId): ?VerificationAccountSettingsDTO
    {
        $url = $this->generateFindAccountUrl($accountId);
        try {
            $response = $this->get($url);
            $settings = $response['settings'] ?? [];
            return VerificationAccountSettingsDTO::fromArray($settings);
        } catch (ClientException) {
            return null;
        }
    }

    public function storeUser(UserDTO $dto): void
    {
        $url = $this->generateStoreUserUrl($dto->getAccountId());
        $body = [
            'id' => $dto->getId(),
            'phone' => $dto->getPhone(),
            'level' => $dto->getLevel(),
            'standardCode' => $dto->getStandardCode(),
        ];
        try {
            $this->post($url, $body);
        } catch (ClientException) {
            return;
        }
    }

    public function findUser(
        string $accountId,
        string $userId,
    ): ?UserDTO {
        $url = $this->generateFindUserUrl($accountId, $userId);
        try {
            $response = $this->get($url);
            return UserDTO::fromArray($response);
        } catch (ClientException) {
            return null;
        }
    }

    public function deleteUser(
        string $accountId,
        string $userId,
    ): void {
        $url = $this->generateDeleteUserUrl($accountId, $userId);
        try {
            $this->delete($url);
        } catch (ClientException) {
        }
    }

    public function startVerification(
        string $accountId,
        string $phone,
    ): void {
        $url = $this->generateStartVerificationUrl($accountId);
        $body = [
            'phone' => $phone,
        ];
        try {
            $this->post($url, $body);
        } catch (VerificationHttpClientException $e) {
            if ($e->getCode() === 400) {
                throw new TooManyVerificationAttempts(
                    $e->getMessage(),
                    $e->getCode(),
                );
            }
        } catch (ClientException) {
            return;
        }
    }

    public function confirm(
        string $accountId,
        string $phone,
        string $code
    ): void {
        $url = $this->generateConfirmUrl($accountId);
        $body = [
            'phone' => $phone,
            'code' => $code,
        ];
        try {
            $this->post($url, $body);
        } catch (VerificationHttpClientException $e) {
            if ($e->getCode() === 400) {
                throw new VerificationCodeException(
                    $e->getMessage(),
                    $e->getCode(),
                );
            }
        } catch (ClientException) {
            return;
        }
    }

    private function generateStoreAccountUrl(): string
    {
        return self::STORE_ACCOUNT_URL_TEMPLATE;
    }

    private function generateFindAccountUrl(string $accountId): string
    {
        return sprintf(self::FIND_ACCOUNT_URL_TEMPLATE, $accountId);
    }

    private function generateStoreUserUrl(string $accountId): string
    {
        return sprintf(self::STORE_USER_URL_TEMPLATE, $accountId);
    }

    private function generateFindUserUrl(string $accountId, string $userId): string
    {
        return sprintf(self::FIND_USER_URL_TEMPLATE, $accountId, $userId);
    }

    private function generateDeleteUserUrl(string $accountId, string $userPhone): string
    {
        return sprintf(self::DELETE_USER_URL_TEMPLATE, $accountId, $userPhone);
    }

    private function generateStartVerificationUrl(string $accountId): string
    {
        return sprintf(self::START_VERIFICATION_URL_TEMPLATE, $accountId);
    }

    private function generateConfirmUrl(string $accountId): string
    {
        return sprintf(self::CONFIRM_URL_TEMPLATE, $accountId);
    }
}