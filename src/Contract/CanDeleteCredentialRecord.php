<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Contract;

use Webauthn\CredentialRecord;

/**
 * Implement on your credential repository to enable "revoke this passkey" in the management UI.
 */
interface CanDeleteCredentialRecord
{
    public function deleteCredentialRecord(CredentialRecord $credentialRecord): void;
}
