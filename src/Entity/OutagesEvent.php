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

class OutagesEvent
{
    /**
     * Constructor
     * @param $from
     * @param $until
     * @param $alerts
     * @param $score
     * @param $format
     * @param $includeAlerts
     * @param $overlap
     * @param bool $mergedEvent
     */
    public function __construct($from, $until, $alerts, $score, $format, $includeAlerts, $overlap, $mergedEvent=False)
    {
        $this->from = $from;
        $this->until = $until;
        $this->alerts = $alerts;
        $this->score = $score;
        $this->format = $format;
        $this->includeAlerts = $includeAlerts;
        $this->overlap = $overlap;
        if($mergedEvent){
            $this->datasource = "overall";
        } else {
            $this->datasource = $alerts[0]->getDatasource();
        }
    }

    /////////////////////
    /////////////////////
    // GETTERS SETTERS //
    /////////////////////
    /////////////////////

    /**
     * @return bool
     */
    public function isIncludeAlerts(): bool
    {
        return $this->includeAlerts;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @param string $format
     * @return OutagesEvent
     */
    public function setFormat(string $format): OutagesEvent
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @return MetadataEntity
     */
    public function getEntity(): ?MetadataEntity
    {
        if(!$this->alerts){
            return null;
        }
        return $this->alerts[0]->getEntity();
    }

    /**
     * @param MetadataEntity $entity
     * @return OutagesEvent
     */
    public function setEntity(MetadataEntity $entity): OutagesEvent
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return int
     */
    public function getFrom(): int
    {
        return $this->from;
    }

    /**
     * @param int $from
     * @return OutagesEvent
     */
    public function setFrom(int $from): OutagesEvent
    {
        $this->from = $from;
        return $this;
    }

    /**
     * @return int
     */
    public function getUntil(): int
    {
        return $this->until;
    }

    /**
     * @param int $until
     * @return OutagesEvent
     */
    public function setUntil(int $until): OutagesEvent
    {
        $this->until = $until;
        return $this;
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @param float $score
     * @return OutagesEvent
     */
    public function setScore(float $score): OutagesEvent
    {
        $this->score = $score;
        return $this;
    }

    /**
     * @return OutagesAlert[]
     */
    public function getAlerts(): ?array
    {
        return $this->alerts;
    }

    /**
     * @param OutagesAlert[] $alerts
     * @return OutagesEvent
     */
    public function setAlerts(array $alerts): OutagesEvent
    {
        $this->alerts = $alerts;
        $this->datasource = $alerts[0]->getDatasource();
        return $this;
    }

    public function setDatasource(string $datasource){
        $this->datasource = $datasource;
    }

    /**
     * @return string
     */
    public function getDatasource(): string
    {
        return $this->datasource;
    }

    /**
     * @return bool
     */
    public function isOverlap(): bool
    {
        return $this->overlap;
    }

    /**
     * @param bool $overlap
     * @return OutagesEvent
     */
    public function setOverlap(bool $overlap): OutagesEvent
    {
        $this->overlap = $overlap;
        return $this;
    }

    //////////////////////////
    //////////////////////////
    // VARIABLE DEFINITIONS //
    //////////////////////////
    //////////////////////////

    /**
     * @Groups({"public"})
     * @var MetadataEntity
     */
    private $entity;

    /**
     * @Groups({"public"})
     * @var integer
     */
    private $from;

    /**
     * @Groups({"public"})
     * @var integer
     */
    private $until;

    /**
     * @Groups({"public"})
     * @var float
     */
    private $score;

    /**
     * @Groups({"public"})
     * @var OutagesAlert[]
     */
    private $alerts;

    /**
     * @Groups({"public"})
     * @var string
     */
    private $datasource;

    /**
     * @var string
     */
    private $format;

    /**
     * @var bool
     */
    private $includeAlerts;

    /**
     * @var bool
     */
    private $overlap;

}
