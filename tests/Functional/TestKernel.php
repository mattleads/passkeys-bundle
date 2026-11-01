<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MattLeads\PasskeysBundle\MattLeadsPasskeysBundle;
use MattLeads\PasskeysBundle\Recovery\InMemoryRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Webauthn\Bundle\WebauthnBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @param array<string, mixed> $bundleConfig */
    public function __construct(private readonly array $bundleConfig = [])
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new WebauthnBundle();
        yield new MattLeadsPasskeysBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'serializer' => ['enabled' => true],
            'property_info' => ['enabled' => true],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'auto_mapping' => false,
                'validate_xml_mapping' => true,
                'controller_resolver' => ['auto_mapping' => false],
            ],
        ]);
        $container->extension('webauthn', [
            'credential_repository' => InMemoryRepositories::class,
            'user_repository' => InMemoryRepositories::class,
            'creation_profiles' => ['default' => ['rp' => ['name' => 'Test', 'id' => 'localhost']]],
            'request_profiles' => ['default' => ['rp_id' => 'localhost']],
        ]);
        $firewall = ['lazy' => true, 'provider' => 'users'];
        if ($this->bundleConfig['recovery']['enabled'] ?? true) {
            $firewall['custom_authenticators'] = [RecoveryCodeAuthenticator::class];
        }
        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => ['alice' => ['password' => 'x', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => $firewall],
        ]);
        $container->extension('mattleads_passkeys', $this->bundleConfig);

        $container->services()->set(InMemoryRepositories::class)->public();
        $container->services()->set(InMemoryRecoveryCodeStorage::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2) . '/config/routes.php');
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_passkeys/project';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_passkeys/' . md5(serialize($this->bundleConfig)) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_passkeys/log';
    }
}
