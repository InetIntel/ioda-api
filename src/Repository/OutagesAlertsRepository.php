<?php
/**
 * This software is Copyright (c) 2013 The Regents of the University of
 * California. All Rights Reserved. Permission to copy, modify, and distribute this
 * software and its documentation for academic research and education purposes,
 * without fee, and without a written agreement is hereby granted, provided that
 * the above copyright notice, this paragraph and the following three paragraphs
 * appear in all copies. Permission to make use of this software for other than
 * academic research and education purposes may be obtained by contacting:
 *
 * Office of Innovation and Commercialization
 * 9500 Gilman Drive, Mail Code 0910
 * University of California
 * La Jolla, CA 92093-0910
 * (858) 534-5815
 * invent@ucsd.edu
 *
 * This software program and documentation are copyrighted by The Regents of the
 * University of California. The software program and documentation are supplied
 * "as is", without any accompanying services from The Regents. The Regents does
 * not warrant that the operation of the program will be uninterrupted or
 * error-free. The end-user understands that the program was developed for research
 * purposes and is advised not to rely exclusively on the program for any reason.
 *
 * IN NO EVENT SHALL THE UNIVERSITY OF CALIFORNIA BE LIABLE TO ANY PARTY FOR
 * DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES, INCLUDING LOST
 * PROFITS, ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION, EVEN IF
 * THE UNIVERSITY OF CALIFORNIA HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE. THE UNIVERSITY OF CALIFORNIA SPECIFICALLY DISCLAIMS ANY WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
 * FITNESS FOR A PARTICULAR PURPOSE. THE SOFTWARE PROVIDED HEREUNDER IS ON AN "AS
 * IS" BASIS, AND THE UNIVERSITY OF CALIFORNIA HAS NO OBLIGATIONS TO PROVIDE
 * MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR MODIFICATIONS.
 */

namespace App\Repository;

use App\Entity\OutagesAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;


class OutagesAlertsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OutagesAlert::class);
    }

    /**
     * @param integer $from
     * @param integer $until
     * @param string|null $entityType
     * @param string|null $entityCode
     * @param string|null $datasource
     * @param string|null $relatedType
     * @param string|null $relatedCode
     * @return mixed
     */
    public function findAlerts(
        $from, $until,
        ?string $entityType, ?string $entityCode, ?string $datasource=null,
        ?string $relatedType=null, ?string $relatedCode=null)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->orderBy('a.time', 'ASC');


        if ($from) {
            $qb->andWhere('a.time >= :from')->setParameter('from', $from);
        }
        if ($until) {
            $qb->andWhere('a.time <= :until')->setParameter('until', $until);
        }
        if ($datasource){
            $qb->andWhere('a.fqid LIKE :datasource')->setParameter('datasource', "%".$datasource."%");
        }
        if (isset($entityType)) {
            $qb->andWhere('a.metaType = :metaType')
                ->setParameter('metaType', $entityType);
        }
        if (isset($entityCode)) {
            $codes = explode(",", $entityCode);
            $qb->andWhere('a.metaCode IN (:codes)')
                ->setParameter('codes', $codes);
        }
        if (isset($relatedType)) {
            $qb->andWhere('a.relatedType = :relatedType')
                ->setParameter('relatedType', $relatedType);
        }
        if (isset($relatedCode)) {
            $qb->andWhere('a.relatedCode = :relatedCode')
                ->setParameter('relatedCode', $relatedCode);
        }

        return $qb->getQuery()->getResult();
    }
}
