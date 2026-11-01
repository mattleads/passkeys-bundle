<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Functional;

use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Minimal repositories so the WebAuthn bundle can boot in tests.
 */
final class InMemoryRepositories implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord, PublicKeyCredentialUserEntityRepositoryInterface
{
    /** @var array<string, CredentialRecord> */
    public array $records = [];
    /** @var array<string, PublicKeyCredentialUserEntity> */
    public array $users = [];

    public function __construct()
    {
        $this->users['alice'] = new PublicKeyCredentialUserEntity('alice', 'handle-alice', 'Alice');
        $this->users['bob'] = new PublicKeyCredentialUserEntity('bob', 'handle-bob', 'Bob');
        $this->records['cred-alice'] = new CredentialRecord('cred-alice', 'public-key', ['internal'], 'none', new \Webauthn\TrustPath\EmptyTrustPath(), \Symfony\Component\Uid\Uuid::v4(), 'pk', 'handle-alice', 1);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        return array_values(array_filter($this->records, static fn(CredentialRecord $r): bool => $r->userHandle === $publicKeyCredentialUserEntity->id));
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        return $this->records[$publicKeyCredentialId] ?? null;
    }

    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $this->records[$credentialRecord->publicKeyCredentialId] = $credentialRecord;
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        return $this->users[$username] ?? null;
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        foreach ($this->users as $user) {
            if ($user->id === $userHandle) {
                return $user;
            }
        }

        return null;
    }
}
