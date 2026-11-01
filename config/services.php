<?php

declare(strict_types=1);

use MattLeads\PasskeysBundle\Bridge\Doctrine\DoctrineRecoveryCodeStorage;
use MattLeads\PasskeysBundle\Controller\PasskeyManagementController;
use MattLeads\PasskeysBundle\Controller\RecoveryLoginController;
use MattLeads\PasskeysBundle\Flow\LoginFlowController;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeManager;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeStorageInterface;
use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use MattLeads\PasskeysBundle\Twig\PasskeysExtension;
use MattLeads\PasskeysBundle\Usage\CredentialUsageSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(false)
            ->autoconfigure(false);

    // --- recovery codes -----------------------------------------------------------------------------------------
    $services->set(DoctrineRecoveryCodeStorage::class)
        ->args([service('doctrine.orm.entity_manager')]);

    $services->set(RecoveryCodeManager::class)
        ->args([
            '$storage' => service(RecoveryCodeStorageInterface::class),
            '$hasher' => inline_service(NativePasswordHasher::class),
            '$pepper' => param('kernel.secret'),
            '$clock' => service('clock')->nullOnInvalid(),
            '$count' => abstract_arg('from bundle config'),
            '$length' => abstract_arg('from bundle config'),
        ]);

    $services->set(RecoveryCodeAuthenticator::class)
        ->args([
            '$recoveryCodes' => service(RecoveryCodeManager::class),
            '$httpUtils' => service('security.http_utils'),
            '$loginPath' => abstract_arg('from bundle config'),
            '$identifierParameter' => abstract_arg('from bundle config'),
            '$codeParameter' => abstract_arg('from bundle config'),
            '$defaultTargetPath' => abstract_arg('from bundle config'),
            '$csrfEnabled' => abstract_arg('from bundle config'),
        ]);

    // --- usage tracking -----------------------------------------------------------------------------------------
    $services->set(CredentialUsageSubscriber::class)
        ->args([
            service(CredentialRecordRepositoryInterface::class),
            service('clock')->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber');

    if (class_exists(Twig\Extension\AbstractExtension::class)) {
        $services->set(PasskeysExtension::class)->tag('twig.extension');
    }

    // --- controllers --------------------------------------------------------------------------------------------
    $services->set(LoginFlowController::class)
        ->args([
            '$users' => service(PublicKeyCredentialUserEntityRepositoryInterface::class),
            '$credentials' => service(CredentialRecordRepositoryInterface::class),
            '$unknownUserFlow' => abstract_arg('from bundle config'),
        ])
        ->tag('controller.service_arguments')
        ->public();

    $services->set(PasskeyManagementController::class)
        ->args([
            '$users' => service(PublicKeyCredentialUserEntityRepositoryInterface::class),
            '$credentials' => service(CredentialRecordRepositoryInterface::class),
            '$recoveryCodes' => null,
        ])
        ->autowire()
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public();

    $services->set(RecoveryLoginController::class)
        ->autowire()
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->public();
};
