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

namespace App\TimeSeries;


use App\Entity\MetadataEntity;
use Symfony\Component\Serializer\Annotation\Groups;

class TimeSeriesSet
{

    /**
     * @var TimeSeries[]
     * @Groups("public")
     */
    protected $series;

    /**
     * @var MetadataEntity
     * @Groups("public")
     */
    protected $metadataEntity;

    public function __construct()
    {
        $this->series = [];
    }

    /**
     * @return TimeSeries[]
     */
    public function getSeries(): array
    {
        return $this->series;
    }

    /**
     * @param TimeSeries[] $series
     */
    public function setSeries(array $series): void
    {
        $this->series = $series;
    }

    /**
     * @return MetadataEntity
     */
    public function getMetadataEntity(): MetadataEntity
    {
        return $this->metadataEntity;
    }

    /**
     * @param MetadataEntity $metadataEntity
     */
    public function setMetadataEntity(MetadataEntity $metadataEntity)
    {
        $this->metadataEntity = $metadataEntity;
    }

    /**
     * @param TimeSeries $series
     */
    public function addOneSeries(TimeSeries $series): void
    {
        $this->series[$series->getDatasource()] = $series;
    }

    /**
     * Down-samples each series in the set, attempting to stay within the
     * specified number of points
     *
     * @param int $maxPoints
     * @param string $aggrFunc
     */
    public function downSample(int $maxPoints, string $aggrFunc): void
    {
        if (!$this->series || !count($this->series)) {
            return;
        }
        // first we need to know the total number of points that we have
        $numPoints = 0;
        foreach ($this->series as $series) {
            $numPoints += $series->getNumPoints();
        }
        // if we have 0 points, just give up
        if (!$numPoints) {
            return;
        }
        // now we need to know how much to reduce each series by
        $reductionRatio = $maxPoints / $numPoints;
        if ($reductionRatio > 1) { // don't need to reduce
            return;
        }
        foreach ($this->series as $series) {
            $series->downSample($reductionRatio, $aggrFunc);
        }
    }
}
