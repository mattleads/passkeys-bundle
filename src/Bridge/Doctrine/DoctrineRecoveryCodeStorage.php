<?php

declare(strict_types=1);

namespace MattLeads\PasskeysBundle\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use MattLeads\PasskeysBundle\Recovery\RecoveryCode;
use MattLeads\PasskeysBundle\Recovery\RecoveryCodeStorageInterface;

final class DoctrineRecoveryCodeStorage implements RecoveryCodeStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(RecoveryCode ...$codes): void
    {
        foreach ($codes as $code) {
            $this->em->persist($code);
        }
        $this->em->flush();
    }

    public function findByLookupKey(string $userIdentifier, string $lookupKey): ?RecoveryCode
    {
        return $this->em->getRepository(RecoveryCode::class)->findOneBy(['userIdentifier' => $userIdentifier, 'lookupKey' => $lookupKey]);
    }

    public function countUnused(string $userIdentifier): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(RecoveryCode::class, 'c')
            ->where('c.userIdentifier = :user')->andWhere('c.usedAt IS NULL')
            ->setParameter('user', $userIdentifier)
            ->getQuery()->getSingleScalarResult();
    }

    public function deleteAllForUser(string $userIdentifier): void
    {
        $this->em->createQueryBuilder()
            ->delete(RecoveryCode::class, 'c')
            ->where('c.userIdentifier = :user')
            ->setParameter('user', $userIdentifier)
            ->getQuery()->execute();
    }
}
