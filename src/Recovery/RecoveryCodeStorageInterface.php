<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Recovery;

interface RecoveryCodeStorageInterface
{
    public function save(RecoveryCode ...$codes): void;

    /** Return the (unused or used) code with this lookup key for the user, or null. */
    public function findByLookupKey(string $userIdentifier, string $lookupKey): ?RecoveryCode;

    public function countUnused(string $userIdentifier): int;

    public function deleteAllForUser(string $userIdentifier): void;
}
