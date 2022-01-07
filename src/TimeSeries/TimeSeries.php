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
use App\TimeSeries\Backend\BackendException;
use DateTime;

class TimeSeries
{

    /**
     * Default maximum points per time series.
     */
    CONST DEFAULT_MAX_POINTS = 4000;

    /**
     * Time of the first data point in this time series.
     *
     * @var DateTime
     * @Groups("public")
     */
    protected $from;

    /**
     * Time after the last data point in this time series.
     * I.e., last_data_point + step.
     *
     * @var DateTime
     * @Groups("public")
     */
    protected $until;

    /**
     * Time (in seconds) between data points in this time series (after any
     * down-sampling).
     *
     * @var int
     * @Groups("public")
     */
    protected $step;

    /**
     * Time (in seconds) between data points in the original time series (before
     * any down-sampling).
     *
     * @var int
     * @Groups("public")
     */
    protected $nativeStep;

    /**
     * Array of time series data points. The first point is at $from, each point
     * is $step seconds apart, and the last point is at ($until-$from).
     *
     * TODO: consider using a more efficient array implementation
     * e.g., https://github.com/php-ds/extension
     *
     * @var int[]
     * @Groups("public")
     */
    protected $values;

    /**
     * @var string
     *  @Groups("public")
     */
    protected $datasource;

    /**
     * @var MetadataEntity
     * @Groups("public")
     */
    protected $metadataEntity;

    public function setDatasource(string $datasource)
    {
        $this->datasource = $datasource;
    }

    public function getDatasource(): string
    {
        return $this->datasource;
    }

    public function setMetadataEntity(MetadataEntity $metadataEntity)
    {
        $this->metadataEntity = $metadataEntity;
    }

    public function getMetadataEntity(): MetadataEntity
    {
        return $this->metadataEntity;
    }

    /**
     * @return DateTime
     */
    public function getFrom(): DateTime
    {
        return $this->from;
    }

    /**
     * @return int
     */
    public function getFromEpoch(): int
    {
        return $this->from->getTimestamp();
    }

    /**
     * @param DateTime $from
     */
    public function setFrom(DateTime $from): void
    {
        $this->from = $from;
    }

    /**
     * @param int $epoch
     */
    public function setFromEpoch(int $epoch): void
    {
        if (!$this->from) {
            $this->from = new DateTime();
        }
        $this->from->setTimestamp($epoch);
    }

    /**
     * @return DateTime
     */
    public function getUntil(): DateTime
    {
        return $this->until;
    }

    /**
     * @return int
     */
    public function getUntilEpoch(): int
    {
        return $this->until->getTimestamp();
    }

    /**
     * @param DateTime $until
     */
    public function setUntil(DateTime $until): void
    {
        $this->until = $until;
    }

    /**
     * @param int $epoch
     */
    public function setUntilEpoch(int $epoch): void
    {
        if (!$this->until) {
            $this->until = new DateTime();
        }
        $this->until->setTimestamp($epoch);
    }

    /**
     * @return int
     */
    public function getStep(): int
    {
        return $this->step;
    }

    /**
     * @param int $step
     */
    public function setStep(int $step): void
    {
        $this->step = $step;

        /* if the native step has not been set, use this as the default */
        if (!$this->nativeStep) {
            $this->setNativeStep($step);
        }
    }

    /**
     * @return int
     */
    public function getNativeStep(): int
    {
        return $this->nativeStep;
    }

    /**
     * @param int $nativeStep
     */
    public function setNativeStep(int $nativeStep): void
    {
        $this->nativeStep = $nativeStep;
    }

    /**
     * @return int[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @param int[] $values
     */
    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    /**
     * Add a time series data point
     *
     * @param int $value
     */
    public function addValue($value): void
    {
        if (!$this->values) {
            $this->values = array();
        }
        $this->values[] = $value;
    }

    public function getNumPoints(): int
    {
        return count($this->values);
    }

    /**
     * Sanity check timeseries values by comparing the number of value points set to the values against
     * the expected number of points we should get calculated from the values of $from, $until, and $step.
     *
     * @throws BackendException
     */
    public function sanityCheckValues(){
        $step = $this->getStep();
        $values = $this->getValues();
        $from = $this->getFrom()->getTimestamp();
        $until = $this->getUntil()->getTimestamp();

        if(count($this->getValues())<=1){
            // if we have zero or one data points, skip the checking
            return;
        }

        // take the ceiling of range/step as the expected data points.
        // for example, if we see range/step = 1.01, that means we have the until filter to be over the last step
        // range, meaning we will include that value in the results.
        // otherwise we exclude the data from the `until` timestamp
        $expect = ceil(($until-$from)/($step));
        if($expect != count($values)){
            throw new BackendException(
                sprintf("wrong number of data points %f, expect %f", count($values), $expect)
            );
        }
    }
}
