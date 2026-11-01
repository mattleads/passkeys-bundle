<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Unit\Recovery;

use MattLeads\PasskeysBundle\Recovery\InMemoryRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

final class RecoveryCodeManagerTest extends TestCase
{
    private InMemoryRecoveryCodeStorage $storage;
    private RecoveryCodeManager $manager;

    protected function setUp(): void
    {
        $this->storage = new InMemoryRecoveryCodeStorage();
        // cost 4 keeps bcrypt fast in tests; production uses the hasher's default cost
        $this->manager = new RecoveryCodeManager($this->storage, new NativePasswordHasher(cost: 4), 'test-pepper', new MockClock('2026-11-01 12:00:00'), count: 8, length: 10);
    }

    public function testGenerateReturnsFormattedUniqueCodesAndStoresOnlyHashes(): void
    {
        $codes = $this->manager->generate('alice');

        self::assertCount(8, $codes);
        self::assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{5}-[0-9A-HJKMNP-TV-Z]{5}$/', $code, 'Crockford base32, no I/L/O/U');
        }
        self::assertSame(8, $this->manager->remaining('alice'));

        $stored = $this->storage->findByLookupKey('alice', $this->lookupKeyOf($codes[0]));
        self::assertNotNull($stored);
        self::assertStringNotContainsString(str_replace('-', '', $codes[0]), $stored->getCodeHash());
        self::assertSame('2026-11-01 12:00:00', $stored->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testVerifyConsumesTheCodeExactlyOnce(): void
    {
        [$code] = $this->manager->generate('alice');

        self::assertTrue($this->manager->verifyAndConsume('alice', $code));
        self::assertSame(7, $this->manager->remaining('alice'));
        self::assertFalse($this->manager->verifyAndConsume('alice', $code), 'single use');
    }

    public function testVerifyIsForgivingAboutCaseSeparatorsAndWhitespace(): void
    {
        [$code] = $this->manager->generate('alice');
        $messy = ' ' . strtolower(str_replace('-', ' ', $code)) . ' ';

        self::assertTrue($this->manager->verifyAndConsume('alice', $messy));
    }

    public function testWrongCodeWrongUserAndMalformedInputFail(): void
    {
        $alice = $this->manager->generate('alice');
        $this->manager->generate('bob');

        self::assertFalse($this->manager->verifyAndConsume('bob', $alice[0]), 'codes are bound to a user');
        self::assertFalse($this->manager->verifyAndConsume('alice', 'AAAAA-AAAAA'));
        self::assertFalse($this->manager->verifyAndConsume('alice', ''));
        self::assertFalse($this->manager->verifyAndConsume('alice', 'too-short'));
        self::assertSame(8, $this->manager->remaining('alice'), 'failed attempts do not burn codes');
    }

    public function testRegeneratingInvalidatesThePreviousBatch(): void
    {
        [$old] = $this->manager->generate('alice');
        $new = $this->manager->generate('alice');

        self::assertFalse($this->manager->verifyAndConsume('alice', $old));
        self::assertTrue($this->manager->verifyAndConsume('alice', $new[3]));
        self::assertSame(7, $this->manager->remaining('alice'));
    }

    public function testRevokeAll(): void
    {
        $this->manager->generate('alice');
        $this->manager->revokeAll('alice');
        self::assertSame(0, $this->manager->remaining('alice'));
    }

    public function testEmptyPepperIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecoveryCodeManager($this->storage, new NativePasswordHasher(cost: 4), '');
    }

    private function lookupKeyOf(string $formatted): string
    {
        return substr(hash_hmac('sha256', RecoveryCodeManager::normalize($formatted), 'test-pepper'), 0, 32);
    }
}
