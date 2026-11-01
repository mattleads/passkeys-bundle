<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use MattLeads\PasskeysBundle\Bridge\Doctrine\DoctrineRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Flow\LoginFlowController;
use MattLeads\PasskeysBundle\Recovery\InMemoryRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Recovery\RecoveryCode;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeStorageInterface;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use MattLeads\PasskeysBundle\Usage\CredentialUsageSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

final class BundleTest extends TestCase
{
    public function testKernelBootsServicesAndRoutesAreRegistered(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        self::assertInstanceOf(RecoveryCodeManager::class, $container->get(RecoveryCodeManager::class));
        self::assertInstanceOf(RecoveryCodeAuthenticator::class, $container->get(RecoveryCodeAuthenticator::class));
        self::assertInstanceOf(CredentialUsageSubscriber::class, $container->get(CredentialUsageSubscriber::class));
        self::assertInstanceOf(LoginFlowController::class, $container->get(LoginFlowController::class));

        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);
        self::assertSame('/login/flow', $router->generate('mattleads_passkeys_login_flow'));
        self::assertSame('/login/recovery', $router->generate('mattleads_passkeys_recovery_login'));
        self::assertSame('/account/passkeys', $router->generate('mattleads_passkeys_manage'));
        self::assertSame('/account/passkeys/revoke', $router->generate('mattleads_passkeys_revoke'));

        $kernel->shutdown();
    }

    public function testLoginFlowEndpointEndToEnd(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();

        $passkeyUser = $kernel->handle(Request::create('/login/flow?identifier=alice'));
        self::assertSame(200, $passkeyUser->getStatusCode());
        self::assertSame(['flow' => 'passkey'], json_decode((string) $passkeyUser->getContent(), true));

        $passwordUser = $kernel->handle(Request::create('/login/flow?identifier=bob'));
        self::assertSame(['flow' => 'password'], json_decode((string) $passwordUser->getContent(), true));

        $unknown = $kernel->handle(Request::create('/login/flow?identifier=mallory'));
        self::assertSame(['flow' => 'password'], json_decode((string) $unknown->getContent(), true));

        $kernel->shutdown();
    }

    public function testRecoveryLoginPageRendersAndAcceptsAValidCode(): void
    {
        $kernel = new TestKernel(['recovery' => ['csrf' => false, 'storage' => InMemoryRecoveryCodeStorage::class, 'default_target_path' => '/account/passkeys']]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $page = $kernel->handle(Request::create('/login/recovery'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('name="_recovery_code"', (string) $page->getContent());

        /** @var RecoveryCodeManager $manager */
        $manager = $container->get(RecoveryCodeManager::class);
        [$code] = $manager->generate('alice');

        $bad = $kernel->handle(Request::create('/login/recovery', 'POST', ['_identifier' => 'alice', '_recovery_code' => 'AAAAA-AAAAA']));
        self::assertSame(302, $bad->getStatusCode());
        self::assertStringEndsWith('/login/recovery', (string) $bad->headers->get('Location'), 'failure redirects back to the form');

        $good = $kernel->handle(Request::create('/login/recovery', 'POST', ['_identifier' => 'alice', '_recovery_code' => $code]));
        self::assertSame(302, $good->getStatusCode());
        self::assertStringEndsWith('/account/passkeys', (string) $good->headers->get('Location'));
        self::assertSame(9, $manager->remaining('alice'));

        $kernel->shutdown();
    }

    public function testDoctrineStorageIsTheDefaultAndPersistsCodes(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        self::assertInstanceOf(DoctrineRecoveryCodeStorage::class, $container->get(RecoveryCodeStorageInterface::class));

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(RecoveryCode::class)]);

        /** @var RecoveryCodeManager $manager */
        $manager = $container->get(RecoveryCodeManager::class);
        $codes = $manager->generate('alice');
        self::assertSame(10, $manager->remaining('alice'));
        self::assertTrue($manager->verifyAndConsume('alice', $codes[2]));
        self::assertFalse($manager->verifyAndConsume('alice', $codes[2]));
        self::assertSame(9, $manager->remaining('alice'));

        $em->clear();
        self::assertSame(9, $manager->remaining('alice'), 'survives the identity map');
        $manager->generate('alice');
        self::assertSame(10, $manager->remaining('alice'));

        $kernel->shutdown();
    }

    public function testFeaturesCanBeDisabled(): void
    {
        $kernel = new TestKernel([
            'login_flow' => ['enabled' => false],
            'usage_tracking' => ['enabled' => false],
            'management' => ['enabled' => false],
            'recovery' => ['enabled' => false],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        foreach ([LoginFlowController::class, CredentialUsageSubscriber::class, RecoveryCodeManager::class, RecoveryCodeAuthenticator::class] as $id) {
            self::assertFalse($container->has($id), $id . ' should be removed');
        }

        $kernel->shutdown();
    }
}
