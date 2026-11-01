<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * @internal
 */
final class Configuration
{
    public static function build(NodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('Root node must be an array node.');
        }

        $root
            ->children()
                ->arrayNode('login_flow')
                    ->info('GET endpoint the hybrid login form asks "does this user have a passkey?" before deciding between the WebAuthn prompt and a password field.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('path')->defaultValue('/login/flow')->cannotBeEmpty()->end()
                        ->enumNode('unknown_user_flow')
                            ->info('Flow returned for identifiers that do not exist. "password" shows the same form as for password-only users, which avoids revealing whether an account exists.')
                            ->values(['password', 'passkey'])
                            ->defaultValue('password')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('recovery')
                    ->info('Single-use offline recovery codes for accounts that lost all their authenticators.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('storage')
                            ->info('Service id implementing RecoveryCodeStorageInterface. Defaults to the bundled Doctrine ORM storage when doctrine/orm is installed.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('count')->info('Codes generated per batch.')->defaultValue(10)->min(1)->max(50)->end()
                        ->integerNode('length')->info('Characters per code (Crockford base32, ~5 bits each).')->defaultValue(10)->min(8)->max(32)->end()
                        ->scalarNode('login_path')->defaultValue('/login/recovery')->cannotBeEmpty()->end()
                        ->scalarNode('identifier_parameter')->defaultValue('_identifier')->cannotBeEmpty()->end()
                        ->scalarNode('code_parameter')->defaultValue('_recovery_code')->cannotBeEmpty()->end()
                        ->scalarNode('default_target_path')->defaultValue('/')->cannotBeEmpty()->end()
                        ->booleanNode('csrf')->info('Require a CSRF token (id "passkeys_recovery") on the recovery login form.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('usage_tracking')
                    ->info('Stamp a last-used timestamp on credential records implementing TracksCredentialUsageInterface after each successful assertion.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('management')
                    ->info('Twig-based "manage my passkeys" pages (list, revoke, regenerate recovery codes). Requires symfony/twig-bundle.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('route_prefix')->defaultValue('/account/passkeys')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }
}
