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

class OutagesSummary
{
    /**
     * Constructor
     */
    public function __construct($scores, $entity, $event_cnt)
    {
        $this->scores = $scores;
        $this->entity = $entity;
        $this->event_cnt = $event_cnt;
    }

    /////////////////////
    /////////////////////
    // GETTERS SETTERS //
    /////////////////////
    /////////////////////

    /**
     * @return array
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    /**
     * @param array $scores
     * @return OutagesSummary
     */
    public function setScores(array $scores): OutagesSummary
    {
        $this->scores = $scores;
        return $this;
    }

    /**
     * @return MetadataEntity
     */
    public function getEntity(): ?MetadataEntity
    {
        return $this->entity;
    }

    /**
     * @param MetadataEntity $entity
     * @return OutagesSummary
     */
    public function setEntity(MetadataEntity $entity): OutagesSummary
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return int
     */
    public function getEventCnt(): int
    {
        return $this->event_cnt;
    }

    /**
     * @param int $event_cnt
     * @return OutagesSummary
     */
    public function setEventCnt(int $event_cnt): OutagesSummary
    {
        $this->event_cnt = $event_cnt;
        return $this;
    }



    //////////////////////////
    //////////////////////////
    // VARIABLE DEFINITIONS //
    //////////////////////////
    //////////////////////////

    /**
     * @Groups({"public"})
     * @var array
     */
    private $scores;

    /**
     * @Groups({"public"})
     * @var integer
     */
    private $event_cnt;

    /**
     * @Groups({"public"})
     * @var MetadataEntity
     */
    private $entity;
}
