<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Unit\Flow;

use MattLeads\PasskeysBundle\Flow\LoginFlowController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

final class LoginFlowControllerTest extends TestCase
{
    public function testUserWithPasskeysGetsPasskeyFlow(): void
    {
        $controller = $this->controller(new PublicKeyCredentialUserEntity('alice', 'handle-1', 'Alice'), [$this->createStub(CredentialRecord::class)]);

        $response = $controller(Request::create('/login/flow', 'GET', ['identifier' => 'alice']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['flow' => 'passkey'], json_decode((string) $response->getContent(), true));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testUserWithoutPasskeysGetsPasswordFlow(): void
    {
        $controller = $this->controller(new PublicKeyCredentialUserEntity('alice', 'handle-1', 'Alice'), []);

        $response = $controller(Request::create('/login/flow', 'GET', ['identifier' => ' alice ']));

        self::assertSame(['flow' => 'password'], json_decode((string) $response->getContent(), true));
    }

    public function testUnknownUserGetsTheConfiguredFallback(): void
    {
        self::assertSame(['flow' => 'password'], json_decode((string) $this->controller(null, [])(Request::create('/login/flow', 'GET', ['identifier' => 'nobody']))->getContent(), true));
        self::assertSame(['flow' => 'passkey'], json_decode((string) $this->controller(null, [], 'passkey')(Request::create('/login/flow', 'GET', ['identifier' => 'nobody']))->getContent(), true));
    }

    public function testMissingIdentifierIsABadRequest(): void
    {
        $response = $this->controller(null, [])(Request::create('/login/flow'));
        self::assertSame(400, $response->getStatusCode());
    }

    /** @param list<CredentialRecord> $records */
    private function controller(?PublicKeyCredentialUserEntity $entity, array $records, string $unknownFlow = 'password'): LoginFlowController
    {
        $users = $this->createMock(PublicKeyCredentialUserEntityRepositoryInterface::class);
        $users->method('findOneByUsername')->willReturnCallback(static fn(string $u): ?PublicKeyCredentialUserEntity => 'alice' === $u ? $entity : null);
        $credentials = $this->createMock(CredentialRecordRepositoryInterface::class);
        $credentials->method('findAllForUserEntity')->willReturn($records);

        return new LoginFlowController($users, $credentials, $unknownFlow);
    }
}
