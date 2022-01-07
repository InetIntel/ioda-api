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

/**
 * @ORM\Entity
 * @ORM\Table(name="mddb_entity_attribute")
 */
class MetadataEntityAttribute
{

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
     * @Groups({"public"})
     * @ORM\Column(type="string")
     * @var string
     */
    private $key;

    /**
     * @Groups({"public"})
     * @ORM\Column(type="string")
     * @var string
     */
    private $value;

    /**
     * @ORM\ManyToOne(targetEntity="MetadataEntity")
     * @ORM\JoinColumn(name="metadata_id", referencedColumnName="id")
     * @var MetadataEntity
     */
    private $entity;

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
     * @return MetadataEntityAttribute
     */
    public function setId(int $id): MetadataEntityAttribute
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @return MetadataEntityAttribute
     */
    public function setKey(string $key): MetadataEntityAttribute
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return MetadataEntityAttribute
     */
    public function setValue(string $value): MetadataEntityAttribute
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return MetadataEntity
     */
    public function getEntity(): MetadataEntity
    {
        return $this->entity;
    }

    /**
     * @param MetadataEntity $entity
     * @return MetadataEntityAttribute
     */
    public function setEntity(MetadataEntity $entity): MetadataEntityAttribute
    {
        $this->entity = $entity;
        return $this;
    }

}
