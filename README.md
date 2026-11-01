# Passkeys Bundle

[![CI](https://github.com/mattleads/passkeys-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/mattleads/passkeys-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/mattleads/passkeys-bundle/v)](https://packagist.org/packages/mattleads/passkeys-bundle)
[![Total Downloads](https://poser.pugx.org/mattleads/passkeys-bundle/downloads)](https://packagist.org/packages/mattleads/passkeys-bundle)
[![License](https://poser.pugx.org/mattleads/passkeys-bundle/license)](LICENSE)

Batteries-included passkeys for Symfony, built **on top of** [`web-auth/webauthn-symfony-bundle`](https://github.com/web-auth/webauthn-symfony-bundle).

The WebAuthn bundle gives you the cryptographic ceremonies. This bundle adds the product around them:

| Feature | What you get |
|---|---|
| **Hybrid login flow** | One form. `GET /login/flow?identifier=…` tells the page whether to start a passkey ceremony or reveal a password field. |
| **Conditional UI (autofill)** | Passkeys offered straight from the browser's username autofill. |
| **Recovery codes** | Single-use offline codes with slow hashing + peppered lookup, a `RecoveryCodeAuthenticator` for your firewall, Doctrine storage included. |
| **Last-used tracking** | Implement one interface on your credential entity; every successful assertion stamps `lastUsedAt`. |
| **Management pages** | List, revoke, regenerate recovery codes — Twig templates you can override. |
| **Stimulus controllers** | `hybrid-login`, `passkey`, `confirm` — no `alert()`, events for your own UI, works with AssetMapper (no Node). |

## Requirements

* PHP 8.2+, Symfony 6.4 / 7.x / 8.x
* `web-auth/webauthn-symfony-bundle` ^5.2 configured with your own user-entity and credential repositories
* Optional: Doctrine ORM (recovery-code storage), Twig (pages), AssetMapper or Encore + StimulusBundle (controllers)

## Installation

```bash
composer require mattleads/passkeys-bundle
```

```php
// config/bundles.php
MattLeads\PasskeysBundle\MattLeadsPasskeysBundle::class => ['all' => true],
```

```yaml
# config/routes/mattleads_passkeys.yaml
mattleads_passkeys:
    resource: '@MattLeadsPasskeysBundle/config/routes.php'
```

If you use the Doctrine storage for recovery codes (the default when Doctrine ORM is installed), create the table:

```bash
bin/console make:migration && bin/console doctrine:migrations:migrate
```

## Configuration

```yaml
# config/packages/mattleads_passkeys.yaml
mattleads_passkeys:
    login_flow:
        enabled: true
        path: /login/flow
        unknown_user_flow: password     # do not reveal whether an account exists
    recovery:
        enabled: true
        storage: null                   # service id implementing RecoveryCodeStorageInterface; null = bundled Doctrine storage
        count: 10
        length: 10
        login_path: /login/recovery
        identifier_parameter: _identifier
        code_parameter: _recovery_code
        default_target_path: /
        csrf: true
    usage_tracking:
        enabled: true
    management:
        enabled: true
        route_prefix: /account/passkeys
        add_passkey:                    # WebAuthn creation-controller routes → shows "Add a passkey" (see below)
            options_route: webauthn.controller.creation.request.add_passkey
            result_route: webauthn.controller.creation.response.add_passkey
```

### Firewall and WebAuthn endpoints

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            entry_point: form_login
            form_login:                      # password half of the hybrid form (remove for passwordless-only)
                login_path: app_login
                check_path: app_login
                enable_csrf: true
            webauthn:                        # from web-auth/webauthn-symfony-bundle
                authentication:
                    routes: { options_path: /login/passkey/options, result_path: /login/passkey/result }
            custom_authenticators:
                - MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator
            login_throttling: ~              # strongly recommended: recovery codes are short
            logout: { path: app_logout }
```

To let a **logged-in user add a passkey**, use the WebAuthn bundle's standalone creation controllers with the
current-user guesser. (The firewall's `registration:` block always reads the username from the request body — it is
for passwordless sign-up, not for adding a passkey to an existing account.)

```yaml
# config/packages/webauthn.yaml
webauthn:
    controllers:
        enabled: true
        creation:
            add_passkey:
                options_path: /account/passkeys/add/options
                result_path: /account/passkeys/add/result
                user_entity_guesser: Webauthn\Bundle\Security\Guesser\CurrentUserEntityGuesser
                profile: default
```

```yaml
# config/packages/mattleads_passkeys.yaml
mattleads_passkeys:
    management:
        add_passkey:
            options_route: webauthn.controller.creation.request.add_passkey
            result_route: webauthn.controller.creation.response.add_passkey
```

The authenticator uses the normal Passport flow, so login throttling, user checkers, remember-me and CSRF all apply.

## The three things your app provides

### 1. A user-entity repository (WebAuthn bundle requirement)

`PublicKeyCredentialUserEntityRepositoryInterface::findOneByUsername()` **must** resolve the same string as `UserInterface::getUserIdentifier()`. The flow endpoint, the management pages and the usage tracker rely on it.

### 2. A credential repository that can save and (optionally) delete

```php
use MattLeads\PasskeysBundle\Contract\CanDeleteCredentialRecord;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;

final class CredentialRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord, CanDeleteCredentialRecord
{
    public function deleteCredentialRecord(CredentialRecord $record): void { /* remove + flush */ }
    // …
}
```

Implement `CanDeleteCredentialRecord` to enable the **Revoke** button.

### 3. (Optional) a credential entity that tracks usage

```php
use MattLeads\PasskeysBundle\Contract\TracksCredentialUsageInterface;

#[ORM\Entity]
class Credential extends \Webauthn\CredentialRecord implements TracksCredentialUsageInterface
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function markUsed(\DateTimeImmutable $at): void { $this->lastUsedAt = $at; }
    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
}
```

## Frontend

### AssetMapper (no Node)

```bash
bin/console importmap:require @simplewebauthn/browser
```

With `symfony/stimulus-bundle` the controllers are auto-registered from the bundle's `assets/package.json` (UX convention). Otherwise register them manually:

```js
// assets/bootstrap.js
import HybridLogin from '@mattleads/passkeys-bundle/dist/hybrid_login_controller.js';
app.register('mattleads--passkeys-bundle--hybrid-login', HybridLogin);
```

### Login template

```twig
{{ include('@MattLeadsPasskeys/login/hybrid.html.twig', {
    login_check_path: path('app_login'),
    options_url: path('webauthn.controller.security.main.request.options'),
    result_url:  path('webauthn.controller.security.main.request.result'),
    flow_url:    path('mattleads_passkeys_login_flow'),
    recovery_url: path('mattleads_passkeys_recovery_login'),
    csrf_token: csrf_token('authenticate'),
    last_identifier: last_username,
    error: error,
}) }}
```

Override any template under `templates/bundles/MattLeadsPasskeysBundle/` — start with `layout.html.twig` to plug the pages into your own base layout.

## Recovery codes — how they are stored

Each code is stored as a **slow hash** (bcrypt via Symfony's `NativePasswordHasher`) plus a 128-bit **lookup key** = `HMAC-SHA256(code, kernel.secret)` truncated. Verification is one indexed lookup and one hash check instead of N hash checks, and a leaked database alone cannot be brute-forced offline without the secret. Codes use Crockford base32 (no `I`, `L`, `O`, `U`), are case-insensitive and ignore dashes/spaces. Generating a new batch invalidates the old one; each code works once.

Use `RecoveryCodeManager` directly when you need codes outside the bundled pages:

```php
$codes = $recoveryCodes->generate($user->getUserIdentifier()); // show once
$recoveryCodes->remaining($user->getUserIdentifier());
$recoveryCodes->verifyAndConsume($identifier, $input);          // bool
```

Bring your own storage by implementing `RecoveryCodeStorageInterface` and setting `recovery.storage`.

## Security notes

* The flow endpoint returns `password` for unknown identifiers by default, so it cannot be used to enumerate accounts. It does reveal that an *existing* account has a passkey; if that matters, set `unknown_user_flow: passkey` and handle the failed ceremony gracefully.
* Enable `login_throttling` on the firewall. A 10-character Crockford code has ~50 bits of entropy; throttling makes online guessing irrelevant.
* Revocation and recovery-code regeneration are CSRF-protected POSTs (`passkeys_revoke`, `passkeys_recovery_codes`).
* WebAuthn requires a secure context: HTTPS everywhere except `localhost`.

## Demo application

A runnable end-to-end example (password sign-up, hybrid login, add/revoke passkeys, recovery codes, throttling) lives in [`mattleads/passkeys-demo`](https://github.com/mattleads/passkeys-demo).

## Testing

```bash
composer install
composer check   # php-cs-fixer, phpstan (level 8), phpunit (unit + kernel with sqlite)
```

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: see [SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE).
