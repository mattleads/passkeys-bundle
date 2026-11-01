<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Unit\Security;

use MattLeads\PasskeysBundle\Recovery\InMemoryRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\HttpUtils;

final class RecoveryCodeAuthenticatorTest extends TestCase
{
    private RecoveryCodeManager $manager;
    private RecoveryCodeAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->manager = new RecoveryCodeManager(new InMemoryRecoveryCodeStorage(), new NativePasswordHasher(cost: 4), 'pepper', count: 3);
        $this->authenticator = new RecoveryCodeAuthenticator($this->manager, new HttpUtils(), '/login/recovery', '_identifier', '_recovery_code', '/home');
    }

    public function testSupportsOnlyPostToTheLoginPath(): void
    {
        self::assertTrue($this->authenticator->supports(Request::create('/login/recovery', 'POST')));
        self::assertFalse($this->authenticator->supports(Request::create('/login/recovery', 'GET')));
        self::assertFalse($this->authenticator->supports(Request::create('/login', 'POST')));
    }

    public function testPassportVerifiesAndConsumesTheCode(): void
    {
        [$code] = $this->manager->generate('alice');
        $passport = $this->authenticator->authenticate($this->post(['_identifier' => 'alice', '_recovery_code' => $code, '_csrf_token' => 't']));

        self::assertSame('alice', $passport->getBadge(UserBadge::class)?->getUserIdentifier());
        self::assertTrue($passport->hasBadge(CsrfTokenBadge::class));

        $credentials = $passport->getBadge(CustomCredentials::class);
        self::assertInstanceOf(CustomCredentials::class, $credentials);
        $credentials->executeCustomChecker(new InMemoryUser('alice', null));
        self::assertTrue($credentials->isResolved());
        self::assertSame(2, $this->manager->remaining('alice'));
    }

    public function testWrongCodeFailsTheCustomChecker(): void
    {
        $this->manager->generate('alice');
        $passport = $this->authenticator->authenticate($this->post(['_identifier' => 'alice', '_recovery_code' => 'AAAAA-AAAAA']));
        $credentials = $passport->getBadge(CustomCredentials::class);
        self::assertInstanceOf(CustomCredentials::class, $credentials);

        $this->expectException(BadCredentialsException::class);
        $credentials->executeCustomChecker(new InMemoryUser('alice', null));
    }

    public function testMissingFieldsAreBadCredentials(): void
    {
        $this->expectException(BadCredentialsException::class);
        $this->authenticator->authenticate($this->post(['_identifier' => 'alice']));
    }

    public function testCsrfCanBeDisabled(): void
    {
        $authenticator = new RecoveryCodeAuthenticator($this->manager, new HttpUtils(), csrfEnabled: false);
        $passport = $authenticator->authenticate($this->post(['_identifier' => 'alice', '_recovery_code' => 'XXXXX-XXXXX']));
        self::assertFalse($passport->hasBadge(CsrfTokenBadge::class));
    }

    public function testSuccessRedirectsToTargetPathOrDefault(): void
    {
        $request = $this->post([]);
        $response = $this->authenticator->onAuthenticationSuccess($request, $this->createStub(TokenInterface::class), 'main');
        self::assertSame('http://localhost/home', $response->headers->get('Location'));

        $request->getSession()->set('_security.main.target_path', '/account');
        $response = $this->authenticator->onAuthenticationSuccess($request, $this->createStub(TokenInterface::class), 'main');
        self::assertSame('/account', $response->headers->get('Location'));
    }

    /** @param array<string, string> $params */
    private function post(array $params): Request
    {
        $request = Request::create('/login/recovery', 'POST', $params);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
