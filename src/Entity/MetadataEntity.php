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

use App\Entity\MetadataEntityAttribute;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EntitiesRepository")
 * @ORM\Table(name="mddb_entity")
 */
class MetadataEntity
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
    private $code;

    /**
     * @Groups({"public"})
     * @ORM\Column(type="string")
     * @var string
     */
    private $name;

    /**
     * @Groups({"public"})
     * @ORM\ManyToOne(targetEntity="MetadataEntityType")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id")
     * @var MetadataEntityType
     */
    private $type;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\ManyToMany(targetEntity="MetadataEntity")
     * @ORM\JoinTable(name="mddb_entity_relationship",
     *     joinColumns={@ORM\JoinColumn(name="from_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="to_id", referencedColumnName="id")}
     *   )
     */
    private $relationships;

    /**
     * @Groups({"public"})
     * @var \Doctrine\Common\Collections\Collection
     * @ORM\OneToMany(targetEntity="MetadataEntityAttribute", mappedBy="entity")
     */
    private $attributes;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $attrs;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->relationships = new \Doctrine\Common\Collections\ArrayCollection();
        $this->attributes = new \Doctrine\Common\Collections\ArrayCollection();
    }

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
     * @return MetadataEntity
     */
    public function setId(int $id): MetadataEntity
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return MetadataEntity
     */
    public function setCode(string $code): MetadataEntity
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return MetadataEntity
     */
    public function setName(string $name): MetadataEntity
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return MetadataEntityType
     */
    public function getType(): MetadataEntityType
    {
        return $this->type;
    }

    /**
     * @param MetadataEntityType $type
     * @return MetadataEntity
     */
    public function setType(MetadataEntityType $type): MetadataEntity
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Add relationships
     *
     * @param MetadataEntity $metadata
     * @return MetadataEntity
     */
    public function addRelationship(MetadataEntity $metadata)
    {
        if (!$this->relationships->contains($metadata)) {
            $this->relationships[] = $metadata;
            $metadata->addRelationship($this);
        }

        return $this;
    }

    /**
     * Remove relationships
     *
     * @param MetadataEntity $metadata
     */
    public function removeRelationship(MetadataEntity $metadata)
    {
        if ($this->relationships->contains($metadata)) {
            $this->relationships->removeElement($metadata);
            $metadata->removeRelationship($this);
        }
    }

    /**
     * Get relationships
     *
     * @return array
     */
    public function getRelationships()
    {
        return $this->relationships->getValues();
    }

    /**
     * Get attribute
     *
     * @return string
     */
    public function getAttribute($key)
    {
        $this->initAttrs();
        return $this->attrs[$key];
    }

    /**
     * Get all attributes
     *
     * @return array
     */
    public
    function getAttributes()
    {
        return $this->attributes->getValues();
    }

    private function initAttrs()
    {
        if (!isset($this->attrs)) {
            $this->attrs = [];
            foreach ($this->getAttributes() as $attribute) {
                $this->attrs[$attribute->getKey()] = $attribute->getValue();
            }
            return true;
        }
        return false;
    }
}
