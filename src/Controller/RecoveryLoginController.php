<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Controller;

use MattLeads\PasskeysBundle\Security\RecoveryCodeAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Renders the recovery-code login form. The POST is handled by RecoveryCodeAuthenticator on the firewall.
 */
final class RecoveryLoginController extends AbstractController
{
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('@MattLeadsPasskeys/recovery/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_identifier' => $authenticationUtils->getLastUsername(),
            'csrf_token_id' => RecoveryCodeAuthenticator::CSRF_TOKEN_ID,
        ]);
    }
}
