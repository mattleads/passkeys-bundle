<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle;

use MattLeads\PasskeysBundle\Bridge\Doctrine\DoctrineRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Controller\PasskeyManagementController;
use MattLeads\PasskeysBundle\Controller\RecoveryLoginController;
use MattLeads\PasskeysBundle\DependencyInjection\Configuration;
use MattLeads\PasskeysBundle\Flow\LoginFlowController;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeStorageInterface;
use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use MattLeads\PasskeysBundle\Usage\CredentialUsageSubscriber;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MattLeadsPasskeysBundle extends AbstractBundle
{
    protected string $extensionAlias = 'mattleads_passkeys';

    public function configure(DefinitionConfigurator $definition): void
    {
        Configuration::build($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Expose the Stimulus controllers to AssetMapper under the UX package name, so StimulusBundle can load
        // "@mattleads/passkeys-bundle" controllers declared in assets/controllers.json without a Node build.
        if ($this->isAssetMapperAvailable($builder)) {
            $container->extension('framework', [
                'asset_mapper' => [
                    'paths' => [\dirname(__DIR__) . '/assets/dist' => '@mattleads/passkeys-bundle/dist'],
                ],
            ]);
        }

        // Register the recovery-code mapping whenever Doctrine ORM is around; it is inert if the Doctrine storage is not used.
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', [
                'orm' => [
                    'mappings' => [
                        'MattLeadsPasskeysBundle' => [
                            'type' => 'xml',
                            'dir' => \dirname(__DIR__) . '/config/doctrine',
                            'prefix' => 'MattLeads\\PasskeysBundle\\Recovery',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);
        }
    }

    private function isAssetMapperAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(AssetMapperInterface::class) || !$builder->hasParameter('kernel.bundles_metadata')) {
            return false;
        }
        /** @var array<string, array{path: string}> $bundlesMetadata */
        $bundlesMetadata = $builder->getParameter('kernel.bundles_metadata');

        return isset($bundlesMetadata['FrameworkBundle']) && is_file($bundlesMetadata['FrameworkBundle']['path'] . '/Resources/config/asset_mapper.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        /** @var array{enabled: bool, path: string, unknown_user_flow: string} $flow */
        $flow = $config['login_flow'];
        /** @var array{enabled: bool, storage: ?string, count: int, length: int, login_path: string, identifier_parameter: string, code_parameter: string, default_target_path: string, csrf: bool} $recovery */
        $recovery = $config['recovery'];
        /** @var array{enabled: bool} $usage */
        $usage = $config['usage_tracking'];
        /** @var array{enabled: bool, route_prefix: string, add_passkey: array{options_route: ?string, result_route: ?string}} $management */
        $management = $config['management'];

        $builder->setParameter('mattleads_passkeys.login_flow.enabled', $flow['enabled']);
        $builder->setParameter('mattleads_passkeys.login_flow.path', $flow['path']);
        $builder->setParameter('mattleads_passkeys.recovery.enabled', $recovery['enabled']);
        $builder->setParameter('mattleads_passkeys.recovery.login_path', $recovery['login_path']);
        $builder->setParameter('mattleads_passkeys.management.enabled', $management['enabled']);
        $builder->setParameter('mattleads_passkeys.management.route_prefix', rtrim($management['route_prefix'], '/'));

        // --- login flow -------------------------------------------------------------------------------------------
        if ($flow['enabled']) {
            $builder->getDefinition(LoginFlowController::class)->setArgument('$unknownUserFlow', $flow['unknown_user_flow']);
        } else {
            $builder->removeDefinition(LoginFlowController::class);
        }

        // --- recovery codes ---------------------------------------------------------------------------------------
        if ($recovery['enabled']) {
            $storageId = $recovery['storage'];
            if (null === $storageId) {
                /** @var array<string, class-string> $bundles */
                $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
                if (!isset($bundles['DoctrineBundle'])) {
                    throw new \InvalidArgumentException('mattleads_passkeys.recovery.storage must be set to a service implementing ' . RecoveryCodeStorageInterface::class . ' when Doctrine ORM is not installed.');
                }
                $storageId = DoctrineRecoveryCodeStorage::class;
            } else {
                $builder->removeDefinition(DoctrineRecoveryCodeStorage::class);
            }
            $builder->setAlias(RecoveryCodeStorageInterface::class, $storageId);

            $builder->getDefinition(RecoveryCodeManager::class)
                ->setArgument('$count', $recovery['count'])
                ->setArgument('$length', $recovery['length']);

            $builder->getDefinition(RecoveryCodeAuthenticator::class)
                ->setArgument('$loginPath', $recovery['login_path'])
                ->setArgument('$identifierParameter', $recovery['identifier_parameter'])
                ->setArgument('$codeParameter', $recovery['code_parameter'])
                ->setArgument('$defaultTargetPath', $recovery['default_target_path'])
                ->setArgument('$csrfEnabled', $recovery['csrf']);
        } else {
            foreach ([RecoveryCodeManager::class, RecoveryCodeAuthenticator::class, DoctrineRecoveryCodeStorage::class, RecoveryLoginController::class] as $id) {
                $builder->removeDefinition($id);
            }
        }

        // --- usage tracking ---------------------------------------------------------------------------------------
        if (!$usage['enabled']) {
            $builder->removeDefinition(CredentialUsageSubscriber::class);
        }

        // --- management UI ----------------------------------------------------------------------------------------
        if (!$management['enabled']) {
            $builder->removeDefinition(PasskeyManagementController::class);
        } else {
            $controller = $builder->getDefinition(PasskeyManagementController::class)
                ->setArgument('$addPasskeyOptionsRoute', $management['add_passkey']['options_route'])
                ->setArgument('$addPasskeyResultRoute', $management['add_passkey']['result_route']);
            if ($recovery['enabled']) {
                $controller->setArgument('$recoveryCodes', new Reference(RecoveryCodeManager::class));
            }
        }
    }
}
