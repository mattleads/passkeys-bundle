<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Security;

use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Form login with "identifier + single-use recovery code" instead of a password.
 *
 * Add it to your firewall:
 *
 *     firewalls:
 *         main:
 *             custom_authenticators: [MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator]
 *             login_throttling: ~   # strongly recommended: codes are short
 *
 * Because it goes through the regular Passport flow, login throttling, user checkers and remember-me all apply.
 */
final class RecoveryCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const CSRF_TOKEN_ID = 'passkeys_recovery';

    public function __construct(
        private readonly RecoveryCodeManager $recoveryCodes,
        private readonly HttpUtils $httpUtils,
        private readonly string $loginPath = '/login/recovery',
        private readonly string $identifierParameter = '_identifier',
        private readonly string $codeParameter = '_recovery_code',
        private readonly string $defaultTargetPath = '/',
        private readonly bool $csrfEnabled = true,
    ) {}

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $this->httpUtils->checkRequestPath($request, $this->loginPath);
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim($request->request->getString($this->identifierParameter));
        $code = $request->request->getString($this->codeParameter);

        if ('' === $identifier || '' === $code) {
            throw new BadCredentialsException('Identifier and recovery code are required.');
        }

        $badges = [new RememberMeBadge()];
        if ($this->csrfEnabled) {
            $badges[] = new CsrfTokenBadge(self::CSRF_TOKEN_ID, $request->request->getString('_csrf_token'));
        }

        return new Passport(
            new UserBadge($identifier),
            new CustomCredentials(
                fn(string $code, UserInterface $user): bool => $this->recoveryCodes->verifyAndConsume($user->getUserIdentifier(), $code),
                $code,
            ),
            $badges,
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $target = $request->hasSession() ? $this->getTargetPath($request->getSession(), $firewallName) : null;
        if (null !== $target) {
            $this->removeTargetPath($request->getSession(), $firewallName);
        }

        return new RedirectResponse($target ?? $this->httpUtils->generateUri($request, $this->defaultTargetPath));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->httpUtils->generateUri($request, $this->loginPath);
    }
}
