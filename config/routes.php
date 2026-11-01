<?php

declare(strict_types=1);

use MattLeads\PasskeysBundle\Controller\PasskeyManagementController;
use MattLeads\PasskeysBundle\Controller\RecoveryLoginController;
use MattLeads\PasskeysBundle\Flow\LoginFlowController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import from your app:
 *
 *   # config/routes/mattleads_passkeys.yaml
 *   mattleads_passkeys:
 *       resource: '@MattLeadsPasskeysBundle/config/routes.php'
 *
 * Paths come from the bundle configuration (login_flow.path, recovery.login_path, management.route_prefix).
 * Routes whose feature is disabled are still declared but their controller service is removed; set
 * the feature's route path to something unreachable or do not import this file if you want none of them.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('mattleads_passkeys_login_flow', '%mattleads_passkeys.login_flow.path%')
        ->controller(LoginFlowController::class)
        ->methods(['GET']);

    $routes->add('mattleads_passkeys_recovery_login', '%mattleads_passkeys.recovery.login_path%')
        ->controller(RecoveryLoginController::class)
        ->methods(['GET', 'POST']); // POST is intercepted by RecoveryCodeAuthenticator before the controller runs

    $routes->add('mattleads_passkeys_manage', '%mattleads_passkeys.management.route_prefix%')
        ->controller([PasskeyManagementController::class, 'index'])
        ->methods(['GET']);

    $routes->add('mattleads_passkeys_revoke', '%mattleads_passkeys.management.route_prefix%/revoke')
        ->controller([PasskeyManagementController::class, 'revoke'])
        ->methods(['POST']);

    $routes->add('mattleads_passkeys_recovery_codes', '%mattleads_passkeys.management.route_prefix%/recovery-codes')
        ->controller([PasskeyManagementController::class, 'regenerateRecoveryCodes'])
        ->methods(['POST']);
};
