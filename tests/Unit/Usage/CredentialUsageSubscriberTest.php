<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Unit\Usage;

use MattLeads\PasskeysBundle\Contract\TracksCredentialUsageInterface;
use MattLeads\PasskeysBundle\Usage\CredentialUsageSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\Event\AuthenticatorAssertionResponseValidationSucceededEvent;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\TrustPath\EmptyTrustPath;

final class CredentialUsageSubscriberTest extends TestCase
{
    public function testMarksTrackedRecordsAndSavesThem(): void
    {
        $record = new TrackedRecord();
        $repository = new class implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord {
            public ?CredentialRecord $saved = null;

            public function findAllForUserEntity(\Webauthn\PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
            {
                return [];
            }

            public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
            {
                return null;
            }

            public function saveCredentialRecord(CredentialRecord $credentialRecord): void
            {
                $this->saved = $credentialRecord;
            }
        };

        $subscriber = new CredentialUsageSubscriber($repository, new MockClock('2026-11-01 09:30:00'));
        $subscriber->onAssertionSucceeded($this->event($record));

        self::assertSame('2026-11-01 09:30:00', $record->getLastUsedAt()?->format('Y-m-d H:i:s'));
        self::assertSame($record, $repository->saved);
    }

    public function testIgnoresRecordsThatDoNotTrackUsage(): void
    {
        $repository = $this->createMock(CredentialRecordRepositoryInterface::class);
        $subscriber = new CredentialUsageSubscriber($repository);

        $subscriber->onAssertionSucceeded($this->event(self::plainRecord()));
        $this->addToAssertionCount(1); // no exception, no save attempted on a repo that cannot save
    }

    public function testSubscribesToTheAssertionSucceededEvent(): void
    {
        self::assertArrayHasKey(AuthenticatorAssertionResponseValidationSucceededEvent::class, CredentialUsageSubscriber::getSubscribedEvents());
    }

    private function event(CredentialRecord $record): AuthenticatorAssertionResponseValidationSucceededEvent
    {
        return new AuthenticatorAssertionResponseValidationSucceededEvent(
            $this->createStub(AuthenticatorAssertionResponse::class),
            PublicKeyCredentialRequestOptions::create('challenge-bytes', 'localhost'),
            'localhost',
            'handle-1',
            $record,
        );
    }

    public static function plainRecord(): CredentialRecord
    {
        return new CredentialRecord('cred-id', 'public-key', [], 'none', new EmptyTrustPath(), Uuid::v4(), 'pk', 'handle-1', 0);
    }
}

final class TrackedRecord extends CredentialRecord implements TracksCredentialUsageInterface
{
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        parent::__construct('cred-id', 'public-key', [], 'none', new EmptyTrustPath(), Uuid::v4(), 'pk', 'handle-1', 0);
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->lastUsedAt = $at;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
