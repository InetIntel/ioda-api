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

namespace App\TimeSeries\Backend;



use App\Service\MetadataEntitiesService;
use App\Service\OutagesAlertsService;
use App\Service\OutagesEventsService;
use App\TimeSeries\TimeSeries;
use DateTime;

/// Time series data backend that turns outage events into time series
class OutagesBackend
{

    const ALLOWED_STEPS = [
        60, 120, 300, 900, 1800, // minute-level [1, 2, 5, 15, 30]
        3600, 7200, 21600, 43200,  //hour-level [1, 2, 6, 12]
        86400, 172800, //day-level [1, 2]
        604800, 1209600, 2419200, //week-level [1, 2, 4]
        31536000, 63072000, 315360000, //year-level [1, 2, 10]
    ];

    private $alertsService;
    private $eventsService;
    private $entitiesSerivce;

    public function __construct(OutagesAlertsService $alertsService, OutagesEventsService $eventsService, MetadataEntitiesService $entitiesService){
        $this->alertsService = $alertsService;
        $this->eventsService = $eventsService;
        $this->entitiesSerivce = $entitiesService;
    }

    /**
     * Calculate step based on time range and maximum allowed data points to return.
     *
     * @param int $from
     * @param int $until
     * @param int $maxPoints
     * @return int
     */
    private function findStep(int $from, int $until, int $maxPoints): int
    {
        $range = $until - $from;
        $newStep = $this::ALLOWED_STEPS[0]; // 1 min is the lowest
        foreach ($this::ALLOWED_STEPS as $step) {
            $newStep = $step;
            $numPoints = $range / $step;
            if($numPoints<$maxPoints){
                break;
            }
        }
        return $newStep;
    }

    private function isEventOverlapsRange($t1, $t2, $event){
        $from = $event->getFrom();
        $until = $event->getUntil();

        $overlap = false;
        if($from <= $t1){
            if($until>$t1){
                $overlap = true;
            }
        } else {
            // $from > $t1
            if($from <= $t2){
                $overlap = true;
            }
        }

        return $overlap;
    }

    private function convertEventsToTimeseries($from, $until, $maxPoints, $events, $method="multiply"){
        $step = $this->findStep($from, $until, $maxPoints);

        $values = [];
        $cur_event_index = 0;
        $cur_time = $from;

        // loop through all steps in the range
        while(true){

            // break the loop if we step out of the range
            if($cur_time>$until+$step){
                break;
            }

            // find out all events that overlaps in range [$cur_time, $cur_time+$step)
            // and use the maximum score for the score of this range.
            $scores = [];
            foreach($events as $event){
                if($event->getFrom()>$cur_time+$step){
                    break;
                }
                if($this->isEventOverlapsRange($cur_time, $cur_time+$step, $event)){
                    $scores[] = $event->getScore();
                } else {
                    $scores[]=0;
                }
            }

            if(empty($scores)){
                $rangeScore = 0;
            } else {
                if($method=="max"){
                    $rangeScore = max($scores);
                } else if ($method=="multiply"){
                    $rangeScore = array_product($scores);
                } else if ($method=="mean"){
                    $rangeScore = round(array_sum($scores)/count($scores));
                } else {
                    // default to max
                    $rangeScore = max($scores);
                }
            }

            $values[] = $rangeScore;
            $cur_time += $step;
        }

        $from_ts = new DateTime();
        $from_ts->setTimestamp($from);
        $until_ts = new DateTime();
        $until_ts->setTimestamp($until);

        $newSeries = new TimeSeries();
        $newSeries->setFrom($from_ts);
        $newSeries->setUntil($until_ts);
        $newSeries->setStep($step);
        $newSeries->setValues($values);

        return $newSeries;
    }

    /**
     * Construct graphite query, send request, and return one TimeSeries object.
     *
     * @param int $from
     * @param int $until
     * @param string $entityType
     * @param string $entityCode
     * @param int $maxPoints
     * @param $datasource
     * @return array
     */
    public function queryOutages(int $from, int $until, string $entityType, string $entityCode, int $maxPoints, $datasource): array
    {

        // build events
        $alerts = $this->alertsService->findAlerts($from, $until, $entityType, $entityCode, null, false, null, null, null);
        $events = $this->eventsService->buildEventsObjects($alerts, false, "ioda", $from, $until, null, false, null, "time", "asc");
        $eventsGroups = $this->eventsService->groupEventsByEntity($events);

        $entities = $this->entitiesSerivce->search($entityType, $entityCode);

        $tses = [];
        foreach($entities as $entity){
            $events = [];
            if(array_key_exists($entity->getId(), $eventsGroups)){
                $events = $eventsGroups[$entity->getId()];
            }
            $ts = $this->convertEventsToTimeseries($from, $until, $maxPoints, $events);
            $ts->setMetadataEntity($entity);
            $ts->setDatasource($datasource);

            $tses[] = $ts;
        }

        return $tses;
    }
}
