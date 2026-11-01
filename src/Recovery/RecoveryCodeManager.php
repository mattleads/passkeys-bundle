<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Recovery;

use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Generates, verifies and burns single-use recovery codes.
 *
 * Storage design: each code is stored as a slow hash (bcrypt/argon via the PasswordHasher) plus a keyed
 * HMAC "lookup key" derived with the application secret. Verification therefore costs one indexed lookup and
 * one slow-hash check instead of N slow-hash checks, and a leaked database alone is not enough to brute-force
 * codes offline because the lookup key needs the pepper.
 */
final class RecoveryCodeManager
{
    /** Crockford base32: no I, L, O, U — unambiguous when read from paper. */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private readonly RecoveryCodeStorageInterface $storage,
        private readonly PasswordHasherInterface $hasher,
        #[\SensitiveParameter]
        private readonly string $pepper,
        private readonly ?ClockInterface $clock = null,
        private readonly int $count = 10,
        private readonly int $length = 10,
    ) {
        if ('' === $pepper) {
            throw new \InvalidArgumentException('RecoveryCodeManager needs a non-empty pepper (kernel.secret).');
        }
    }

    /**
     * Replace all of the user's codes with a fresh batch and return the plain codes — show them once, never store them.
     *
     * @return list<string> formatted codes, e.g. "7K3QM-9XZ2A"
     */
    public function generate(string $userIdentifier): array
    {
        $this->storage->deleteAllForUser($userIdentifier);

        $plain = [];
        $entities = [];
        $now = $this->now();
        while (\count($plain) < $this->count) {
            $raw = $this->randomCode();
            $lookupKey = $this->lookupKey($raw);
            if (isset($entities[$lookupKey])) {
                continue; // astronomically unlikely collision inside one batch
            }
            $plain[] = $this->format($raw);
            $entities[$lookupKey] = new RecoveryCode($userIdentifier, $lookupKey, $this->hasher->hash($raw), $now);
        }

        $this->storage->save(...array_values($entities));

        return $plain;
    }

    /**
     * Verify a code and burn it. Returns false for unknown, already-used or malformed codes.
     */
    public function verifyAndConsume(string $userIdentifier, #[\SensitiveParameter] string $code): bool
    {
        $raw = self::normalize($code);
        if (\strlen($raw) !== $this->length) {
            return false;
        }

        $entity = $this->storage->findByLookupKey($userIdentifier, $this->lookupKey($raw));
        if (null === $entity || $entity->isUsed() || !$this->hasher->verify($entity->getCodeHash(), $raw)) {
            return false;
        }

        $entity->markUsed($this->now());
        $this->storage->save($entity);

        return true;
    }

    public function remaining(string $userIdentifier): int
    {
        return $this->storage->countUnused($userIdentifier);
    }

    public function revokeAll(string $userIdentifier): void
    {
        $this->storage->deleteAllForUser($userIdentifier);
    }

    /** Upper-case and strip separators/whitespace so "7k3qm 9xz2a" and "7K3QM-9XZ2A" are the same code. */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^0-9a-zA-Z]+/', '', $code));
    }

    private function randomCode(): string
    {
        $out = '';
        for ($i = 0; $i < $this->length; ++$i) {
            $out .= self::ALPHABET[random_int(0, 31)];
        }

        return $out;
    }

    private function format(string $raw): string
    {
        return implode('-', str_split($raw, max(1, (int) ceil($this->length / 2))));
    }

    private function lookupKey(string $raw): string
    {
        return substr(hash_hmac('sha256', $raw, $this->pepper), 0, 32);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock?->now() ?? new \DateTimeImmutable();
    }
}
