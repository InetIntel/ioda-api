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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DataErasRepository")
 * @ORM\Table(name="ioda_data_eras")
 */
class DataEraEntity
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @var integer
     */
    private $era_id;

    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     * @var string
     */
    private $datasource;

    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     * @var string
     */
    private $entity_type;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $query_term;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $bucket;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $field;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    private $grafanasource;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    private $start_ts;

    /**
     * @ORM\Column(type="integer")
     * @var integer
     */
    private $end_ts;

    public function __construct() {

    }

    /**
     * @return int
     */
    public function getEraId(): int
    {
        return $this->era_id;
    }

    /**
     * @param int $id
     * @return DataEraEntity
     */
    public function setEraId(int $id): DataEraEntity
    {
        $this->era_id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getDatasource(): string
    {
        return $this->datasource;
    }

    /**
     * @param string $source
     * @return DataEraEntity
     */
    public function setDatasource(string $source): DataEraEntity
    {
        $this->datasource = $source;
        return $this;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entity_type;
    }

    /**
     * @param string $type
     * @return DataEraEntity
     */
    public function setEntityType(string $type): DataEraEntity
    {
        $this->entity_type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getQueryTerm(): string
    {
        if ($this->query_term) {
            return str_replace("@", "\"", $this->query_term);
        }
        return "";
    }

    /**
     * @param string $term
     * @return DataEraEntity
     */
    public function setQueryTerm(string $term): DataEraEntity
    {
        $this->query_term = $term;
        return $this;
    }

    /**
     * @return string
     */
    public function getBucket(): string
    {
        return $this->bucket;
    }

    /**
     * @param string $bucket
     * @return DataEraEntity
     */
    public function setBucket(string $bucket): DataEraEntity
    {
        $this->bucket = $bucket;
        return $this;
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @param string $field
     * @return DataEraEntity
     */
    public function setField(string $field): DataEraEntity
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @return int
     */
    public function getGrafanaSource(): int
    {
        return $this->grafanasource;
    }

    /**
     * @param int $source
     * @return DataEraEntity
     */
    public function setGrafanaSource(int $source): DataEraEntity
    {
        $this->grafanasource = $source;
        return $this;
    }

    /**
     * @return int
     */
    public function getStartTime(): int
    {
        return $this->start_ts;
    }

    /**
     * @param int $start
     * @return DataEraEntity
     */
    public function setStartTime(int $start): DataEraEntity
    {
        $this->start_ts = $start;
        return $this;
    }

    /**
     * @return int
     */
    public function getEndTime(): int
    {
        if (! $this->end_ts || $this->end_ts <= 0) {
            return time() + 86400;
        }
        return $this->end_ts;
    }

    /**
     * @param int $end
     * @return DataEraEntity
     */
    public function setEndTime(int $end): DataEraEntity
    {
        $this->end_ts = $end;
        return $this;
    }
}
