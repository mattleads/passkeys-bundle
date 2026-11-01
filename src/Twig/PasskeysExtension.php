<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class PasskeysExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('mattleads_passkeys_base64url', [self::class, 'base64url'])];
    }

    public static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
