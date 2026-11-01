<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Recovery;

/**
 * A single-use recovery code. Only a slow hash is stored; the plain code is shown to the user exactly once.
 *
 * Persistence-agnostic: the Doctrine mapping lives in config/doctrine, other storages may serialize it as they like.
 */
class RecoveryCode
{
    protected ?int $id = null;
    protected ?\DateTimeImmutable $usedAt = null;

    public function __construct(
        protected string $userIdentifier,
        protected string $lookupKey,
        protected string $codeHash,
        protected \DateTimeImmutable $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /** Keyed HMAC prefix used to find the candidate row without verifying every slow hash. */
    public function getLookupKey(): string
    {
        return $this->lookupKey;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->usedAt = $at;
    }
}
