<?php
/*
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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Entity\MetadataEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OutagersAlertsRepository")
 * @ORM\Table(name="alerts_with_entity_view")
 */
class OutagesAlert
{

    /////////////////////
    /////////////////////
    // GETTERS SETTERS //
    /////////////////////
    /////////////////////

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return OutagesAlert
     */
    public function setId(int $id): OutagesAlert
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getFqid(): string
    {
        return $this->fqid;
    }

    /**
     * @param string $fqid
     * @return OutagesAlert
     */
    public function setFqid(string $fqid): OutagesAlert
    {
        $this->fqid = $fqid;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return OutagesAlert
     */
    public function setName(string $name): OutagesAlert
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getQueryTime(): int
    {
        return $this->queryTime;
    }

    /**
     * @param int $queryTime
     * @return OutagesAlert
     */
    public function setQueryTime(int $queryTime): OutagesAlert
    {
        $this->queryTime = $queryTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @param int $time
     * @return OutagesAlert
     */
    public function setTime(int $time): OutagesAlert
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @param string $level
     * @return OutagesAlert
     */
    public function setLevel(string $level): OutagesAlert
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return OutagesAlert
     */
    public function setMethod(string $method): OutagesAlert
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getCondition(): ?string
    {
        return $this->condition;
    }

    /**
     * @param string $condition
     * @return OutagesAlert
     */
    public function setCondition(string $condition): OutagesAlert
    {
        $this->condition = $condition;
        return $this;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * @param float $value
     * @return OutagesAlert
     */
    public function setValue(float $value): OutagesAlert
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return float
     */
    public function getHistoryValue(): float
    {
        return $this->historyValue;
    }

    /**
     * @param float $historyValue
     * @return OutagesAlert
     */
    public function setHistoryValue(float $historyValue): OutagesAlert
    {
        $this->historyValue = $historyValue;
        return $this;
    }

    /**
     * @return string
     */
    public function getMetaType(): string
    {
        return $this->metaType;
    }

    /**
     * @param string $metaType
     * @return OutagesAlert
     */
    public function setMetaType(string $metaType): OutagesAlert
    {
        $this->metaType = $metaType;
        return $this;
    }

    /**
     * @return string
     */
    public function getMetaCode(): string
    {
        return $this->metaCode;
    }

    /**
     * @param string $metaCode
     * @return OutagesAlert
     */
    public function setMetaCode(string $metaCode): OutagesAlert
    {
        $this->metaCode = $metaCode;
        return $this;
    }

    /**
     * @return MetadataEntity|null
     */
    public function getEntity(): ?MetadataEntity
    {
        return $this->entity;
    }

    /**
     * @param MetadataEntity $entity
     * @return OutagesAlert
     */
    public function setEntity(MetadataEntity $entity): OutagesAlert
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     */
    public function getDatasource()
    {
        return $this->datasource;
    }

    /**
     */
    public function setDatasource($datasource)
    {
        $this->datasource = $datasource;
        return $this;
    }

    //////////////////////////
    //////////////////////////
    // VARIABLE DEFINITIONS //
    //////////////////////////
    //////////////////////////

    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @var integer
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $fqid;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $name;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"public"})
     * @var integer
     */
    private $queryTime;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"public"})
     * @var integer
     */
    private $time;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $level;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $method;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $condition;

    /**
     * @ORM\Column(type="float")
     * @Groups({"public"})
     * @var float
     */
    private $value;

    /**
     * @ORM\Column(type="float")
     * @Groups({"public"})
     * @var float
     */
    private $historyValue;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $metaType;

    /**
     * @ORM\Column(type="string")
     * @Groups({"public"})
     * @var string
     */
    private $metaCode;

    /**
     * @ORM\Column(type="string")
     * @Groups({"private"})
     * @var string
     */
    private $relatedType;

    /**
     * @ORM\Column(type="string")
     * @Groups({"private"})
     * @var string
     */
    private $relatedCode;

    /**
     * @Groups({"public"})
     * @ORM\ManyToOne(targetEntity="MetadataEntity")
     * @ORM\JoinColumn(name="meta_id", referencedColumnName="id")
     * @var MetadataEntity
     */
    private $entity;

    /**
     * @Groups({"public"})
     * @var string
     */
    private $datasource;

}
