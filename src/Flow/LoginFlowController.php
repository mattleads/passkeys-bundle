<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Flow;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;

/**
 * GET /login/flow?identifier=alice@example.com  →  {"flow":"passkey"} | {"flow":"password"}.
 *
 * Lets a single login form decide whether to start a WebAuthn ceremony or reveal a password field.
 * Unknown identifiers get the configured fallback (default "password") so the endpoint does not confirm
 * whether an account exists.
 */
final class LoginFlowController
{
    public const FLOW_PASSKEY = 'passkey';
    public const FLOW_PASSWORD = 'password';

    public function __construct(
        private readonly PublicKeyCredentialUserEntityRepositoryInterface $users,
        private readonly CredentialRecordRepositoryInterface $credentials,
        private readonly string $unknownUserFlow = self::FLOW_PASSWORD,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $identifier = trim($request->query->getString('identifier'));
        if ('' === $identifier) {
            return new JsonResponse(['error' => 'Missing "identifier" query parameter.'], Response::HTTP_BAD_REQUEST);
        }

        $userEntity = $this->users->findOneByUsername($identifier);
        if (null === $userEntity) {
            $flow = $this->unknownUserFlow;
        } else {
            $flow = [] === $this->credentials->findAllForUserEntity($userEntity) ? self::FLOW_PASSWORD : self::FLOW_PASSKEY;
        }

        $response = new JsonResponse(['flow' => $flow]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
