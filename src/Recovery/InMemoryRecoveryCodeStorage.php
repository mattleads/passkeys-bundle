<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Recovery;

/**
 * Non-persistent storage for tests and prototypes. Do not use in production.
 */
final class InMemoryRecoveryCodeStorage implements RecoveryCodeStorageInterface
{
    /** @var array<string, array<string, RecoveryCode>> userIdentifier => lookupKey => code */
    private array $codes = [];

    public function save(RecoveryCode ...$codes): void
    {
        foreach ($codes as $code) {
            $this->codes[$code->getUserIdentifier()][$code->getLookupKey()] = $code;
        }
    }

    public function findByLookupKey(string $userIdentifier, string $lookupKey): ?RecoveryCode
    {
        return $this->codes[$userIdentifier][$lookupKey] ?? null;
    }

    public function countUnused(string $userIdentifier): int
    {
        return \count(array_filter($this->codes[$userIdentifier] ?? [], static fn(RecoveryCode $c): bool => !$c->isUsed()));
    }

    public function deleteAllForUser(string $userIdentifier): void
    {
        unset($this->codes[$userIdentifier]);
    }
}
