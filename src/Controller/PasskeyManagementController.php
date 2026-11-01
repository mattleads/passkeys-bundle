<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Controller;

use MattLeads\PasskeysBundle\Contract\CanDeleteCredentialRecord;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * "Manage my passkeys": list, revoke, regenerate recovery codes. Override templates under
 * templates/bundles/MattLeadsPasskeysBundle/passkeys/.
 */
final class PasskeyManagementController extends AbstractController
{
    public const CSRF_REVOKE = 'passkeys_revoke';
    public const CSRF_RECOVERY_CODES = 'passkeys_recovery_codes';

    public function __construct(
        private readonly PublicKeyCredentialUserEntityRepositoryInterface $users,
        private readonly CredentialRecordRepositoryInterface $credentials,
        private readonly ?RecoveryCodeManager $recoveryCodes = null,
    ) {}

    public function index(): Response
    {
        $user = $this->requireUser();
        $userEntity = $this->userEntity($user);

        return $this->render('@MattLeadsPasskeys/passkeys/index.html.twig', [
            'credentials' => $this->credentials->findAllForUserEntity($userEntity),
            'can_revoke' => $this->credentials instanceof CanDeleteCredentialRecord,
            'recovery_enabled' => null !== $this->recoveryCodes,
            'recovery_remaining' => $this->recoveryCodes?->remaining($user->getUserIdentifier()),
        ]);
    }

    public function revoke(Request $request): Response
    {
        $user = $this->requireUser();
        if (!$this->isCsrfTokenValid(self::CSRF_REVOKE, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        if (!$this->credentials instanceof CanDeleteCredentialRecord) {
            throw new \LogicException(\sprintf('Your credential repository must implement %s to revoke passkeys.', CanDeleteCredentialRecord::class));
        }

        $credentialId = $request->request->getString('credential_id');
        $raw = base64_decode(strtr($credentialId, '-_', '+/'), true);
        $record = false === $raw || '' === $raw ? null : $this->credentials->findOneByCredentialId($raw);
        if (null === $record || $record->userHandle !== $this->userEntity($user)->id) {
            throw new NotFoundHttpException('Unknown passkey.');
        }

        $this->credentials->deleteCredentialRecord($record);
        $this->addFlash('success', 'Passkey revoked.');

        return $this->redirectToRoute('mattleads_passkeys_manage');
    }

    public function regenerateRecoveryCodes(Request $request): Response
    {
        $user = $this->requireUser();
        if (null === $this->recoveryCodes) {
            throw new NotFoundHttpException('Recovery codes are disabled.');
        }
        if (!$this->isCsrfTokenValid(self::CSRF_RECOVERY_CODES, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        return $this->render('@MattLeadsPasskeys/passkeys/recovery_codes.html.twig', [
            'codes' => $this->recoveryCodes->generate($user->getUserIdentifier()),
        ]);
    }

    private function requireUser(): UserInterface
    {
        return $this->getUser() ?? throw new AccessDeniedException('Authentication required.');
    }

    private function userEntity(UserInterface $user): PublicKeyCredentialUserEntity
    {
        return $this->users->findOneByUsername($user->getUserIdentifier())
            ?? throw new \LogicException(\sprintf('No WebAuthn user entity found for "%s". Your PublicKeyCredentialUserEntityRepositoryInterface::findOneByUsername() must resolve the security user identifier.', $user->getUserIdentifier()));
    }
}
