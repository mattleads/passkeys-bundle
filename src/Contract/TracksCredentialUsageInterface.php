<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Contract;

/**
 * Implement on your CredentialRecord entity to get "last used" timestamps for free.
 */
interface TracksCredentialUsageInterface
{
    public function markUsed(\DateTimeImmutable $at): void;

    public function getLastUsedAt(): ?\DateTimeImmutable;
}
