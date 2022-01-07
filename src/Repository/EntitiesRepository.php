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

use App\Entity\MetadataEntity;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class EntitiesRepository extends ServiceEntityRepository
{
    const METADATA_DATA_CACHE_TIMEOUT = 3600;

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MetadataEntity::class);
    }

    /**
     * Return a sets of entities
     * @param null $type
     * @param null $code
     * @param null $name
     * @param null $relatedType
     * @param null $relatedCode
     * @param null $limit
     * @param bool $wildcard
     * @return int|mixed|string
     */
    public function findMetadata($type=null, $code=null, $name=null, $limit=null, $page=null, $wildcard=false,
                                 $relatedType=null, $relatedCode=null)
    {
        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata('App\Entity\MetadataEntity', 'm');

        $parameters = array_filter(
            [
                'm.code != :unknown',
                (!empty($type) ? 'mt.type ILIKE :type' : null),
                (!empty($code) ? 'm.code IN (:codes)' : null),
                (!empty($name) ? 'm.name ILIKE :wildcard_name' : null),
                (!empty($relatedType) ? 'omt.type ILIKE :relatedType' : null),
                (!empty($relatedCode) ? 'om.code ILIKE :relatedCode' : null),
            ]
        );

        $offset=null;
        if(isset($limit) && isset($page)){
            $offset = $limit * $page;
        }

        $sql =
            'SELECT ' . $rsm->generateSelectClause() . '
            FROM
                mddb_entity m
                INNER JOIN mddb_entity_type mt ON m.type_id = mt.id
                '
            .(!empty($relatedType)? '
                INNER JOIN mddb_entity_relationship r ON m.id = r.from_id
                INNER JOIN mddb_entity om ON om.id = r.to_id
                INNER JOIN mddb_entity_type omt ON om.type_id = omt.id
                ': '')
            . (!empty($parameters) ? ' WHERE ' . implode(' AND ', $parameters) : '')
            . (!empty($name) ? ' ORDER BY 
            CASE
            WHEN LOWER(m.code) = LOWER(:name) THEN 1
            WHEN LOWER(m.name) = LOWER(:name) THEN 2
            WHEN mt.type ILIKE :country  THEN 3
            WHEN mt.type ILIKE :region  THEN 4
            END ASC, m.name
            ': '' )
            . (($limit) ? ' LIMIT ' . $limit: '')
            . (($offset) ? ' OFFSET ' . $offset: '')
        ;

        $codes=null;
        if (isset($code)) {
            $codes = explode(",", $code);
        }

        $q = $em->createNativeQuery($sql, $rsm)
            ->setParameters([
                'unknown' => '??',
                'type' => $type,
                'codes' => $codes,
                'name' => $name,
                'wildcard_name' => (!empty($wildcard) ? '%'.$name.'%' : $name),
                'country' => 'country',
                'region' => 'region',
                'relatedType' => $relatedType,
                'relatedCode' => $relatedCode,
            ]);

        $res = $q->getResult();

        // force results to never look up related entities
        // this effectively disables the getRelationships method
        /** @var $prop \ReflectionProperty */
        $prop = $this->getClassMetadata()->reflFields["relationships"];
        foreach ($res as &$entity) {
            $prop->getValue($entity)->setInitialized(true);
        }

        return $res;
    }
}
