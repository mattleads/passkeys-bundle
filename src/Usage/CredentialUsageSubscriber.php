<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Usage;

use MattLeads\PasskeysBundle\Contract\TracksCredentialUsageInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Event\AuthenticatorAssertionResponseValidationSucceededEvent;

/**
 * Records when each passkey was last used, so users can spot a device they do not recognise.
 */
final class CredentialUsageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CredentialRecordRepositoryInterface $credentials,
        private readonly ?ClockInterface $clock = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [AuthenticatorAssertionResponseValidationSucceededEvent::class => 'onAssertionSucceeded'];
    }

    public function onAssertionSucceeded(AuthenticatorAssertionResponseValidationSucceededEvent $event): void
    {
        $record = $event->credentialRecord;
        if (!$record instanceof TracksCredentialUsageInterface) {
            return;
        }

        $record->markUsed($this->clock?->now() ?? new \DateTimeImmutable());

        if ($this->credentials instanceof CanSaveCredentialRecord) {
            $this->credentials->saveCredentialRecord($record);
        }
    }
}
