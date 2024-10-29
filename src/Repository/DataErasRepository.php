<?php
/*
 * This source code is Copyright (c) 2024 Georgia Tech Research
 * Corporation. All Rights Reserved. Permission to copy, modify, and distribute
 * this software and its documentation for academic research and education
 * purposes, without fee, and without a written agreement is hereby granted,
 * provided that the above copyright notice, this paragraph and the following
 * three paragraphs appear in all copies. Permission to make use of this
 * software for other than academic research and education purposes may be
 * obtained by contacting:
 *
 *  Office of Technology Licensing
 *  Georgia Institute of Technology
 *  926 Dalney Street, NW
 *  Atlanta, GA 30318
 *  404.385.8066
 *  techlicensing@gtrc.gatech.edu
 *
 * This software program and documentation are copyrighted by Georgia Tech
 * Research Corporation (GTRC). The software program and documentation are
 * supplied "as is", without any accompanying services from GTRC. GTRC does
 * not warrant that the operation of the program will be uninterrupted or
 * error-free. The end-user understands that the program was developed for
 * research purposes and is advised not to rely exclusively on the program for
 * any reason.
 *
 * IN NO EVENT SHALL GEORGIA TECH RESEARCH CORPORATION BE LIABLE TO ANY PARTY FOR
 * DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES, INCLUDING
 * LOST PROFITS, ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION,
 * EVEN IF GEORGIA TECH RESEARCH CORPORATION HAS BEEN ADVISED OF THE POSSIBILITY
 * OF SUCH DAMAGE. GEORGIA TECH RESEARCH CORPORATION SPECIFICALLY DISCLAIMS ANY
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE SOFTWARE PROVIDED
 * HEREUNDER IS ON AN "AS IS" BASIS, AND  GEORGIA TECH RESEARCH CORPORATION HAS
 * NO OBLIGATIONS TO PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR
 * MODIFICATIONS.
 *
 * This source code is part of the IODA software. The original IODA software
 * is Copyright (c) 2013 The Regents of the University of California. All rights
 * reserved. Permission to copy, modify, and distribute this software for
 * academic research and education purposes is subject to the conditions and
 * copyright notices in the source code files and in the included LICENSE file.
 */

namespace App\Repository;

use App\Entity\DataEraEntity;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DataErasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataEraEntity::class);
    }

    public function findErasForTimeRange($datasource, $entitytype,
            $from, $until)
    {
        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata('App\Entity\DataEraEntity', 'era');

        if ($from <= 0) {
            return [];
        }

        $sql =
            'SELECT ' . $rsm->generateSelectClause() . '
            FROM ioda_data_eras era
            WHERE
                LOWER(era.datasource) = LOWER(:datasource) AND
                LOWER(era.entity_type) = LOWER(:entitytype) AND
                era.start_ts != 0 AND
                era.dev_flag = FALSE AND
                (
                  (era.start_ts <= :from AND (era.end_ts > :from OR era.end_ts < 0)) OR
                  (era.start_ts < :end AND (era.end_ts > :end OR era.end_ts < 0)) OR
                  (era.start_ts <= :from AND era.end_ts > :end)
                ) ORDER BY era.start_ts '
        ;

        $q = $em->createNativeQuery($sql, $rsm)->setParameters([
                'datasource' => $datasource,
                'entitytype' => $entitytype,
                'from' => $from,
                'end' => $until,
             ]);
        $res = $q->getResult();
        return $res;
    }
}

