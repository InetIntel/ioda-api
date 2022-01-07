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

namespace App\Service;


use App\Entity\OutagesEvent;
use App\Entity\OutagesSummary;
use App\Repository\OutagesAlertsRepository;

class OutagesEventsService
{
    /**
     * @var OutagesAlertsRepository
     */
    private $repo;

    /**
     * @var DatasourceService
     */
    private $datasourceService;

    public function __construct(OutagesAlertsRepository $outagesAlertsRepository, DatasourceService $datasourceService)
    {
        $this->repo = $outagesAlertsRepository;
        $this->datasourceService = $datasourceService;
    }

    private function cmpEvent($a, $b) {
        return $a['from'] - $b['from'];
    }

    private function cmpEventObj($a, $b) {
        return $a->getFrom() - $b->getFrom();
    }

    /**
     * Merge overlapping events and calculate overall scores.
     *
     * @param $eventMap
     * @return array
     */
    private function mergeOverlappingEvents($eventMap): array
    {
        if (!count($eventMap)) {
            return [];
        }
        // prepare merged-event data fields (fqScore)
        $allEvents = [];
        foreach (array_keys($eventMap) as $aId) {
            foreach ($eventMap[$aId] as $event) {
                $fqid = $event['fqid'];
                $event['fqScores'] = [
                    $fqid => $event['score'],
                ];
                $event['score'] = 1; // used in multiplication below
                $allEvents[] = $event;
            }
        }
        if (!count($allEvents)) {
            return [];
        }

        // sort by from time
        usort($allEvents, [
            'App\Service\OutagesEventsService',
            'cmpEvent'
        ]);

        $merged = [];
        $merged[] = array_shift($allEvents); // get the first event out
        foreach ($allEvents as $event) {
            $lastEvent = array_pop($merged); // pop the latest event (last one is the latest)

            if ($lastEvent['until'] < $event['from']) {
                // current event starts after the last event ends
                // no overlap, just add as-is
                $merged[] = $lastEvent; // put back the popped last event
                $merged[] = $event; // push the current event into merged events array
            } else {
                // current event starts before the last merged event ends
                // modify the last merged event and push it back

                // update end time
                if ($lastEvent['until'] < $event['until']) {
                    // need to extend last merged event's ends time
                    $lastEvent['until'] = $event['until'];
                }

                // update score from each individual data source.
                // we use the *largest score* for a datasource as the merged-event's score for that data source.
                $eFqid = $event['fqid'];
                if (!array_key_exists($eFqid, $lastEvent['fqScores']) ||
                    $lastEvent['fqScores'][$eFqid] < $event['fqScores'][$eFqid]) {
                    $lastEvent['fqScores'][$eFqid] = $event['fqScores'][$eFqid];
                }

                $lastEvent['alerts'] = array_merge($lastEvent['alerts'], $event["alerts"]);

                // put back the popped last merged event
                // essentially, we updated the last merged event in this block
                $merged[] = $lastEvent;
            }
        }

        // for each merged event, we multiply the largest score of each data source to get the overall score
        foreach ($merged as &$event) {
            foreach (array_keys($event['fqScores']) as $fqid) {
                $event['score'] *= $event['fqScores'][$fqid];
            }
        }

        return $merged;
    }

    /**
     * Group alerts by entity.
     *
     * @param $alerts
     * @return array
     */
    public function groupAlertsByEntity($alerts): array
    {
        $res = [];
        foreach($alerts as $alert){
            $entity_id = $alert->getMetaType() . $alert->getMetaCode();
            if(!array_key_exists($entity_id, $res)){
                $res[$entity_id] = array();
            }
            $res[$entity_id][] = $alert;
        }
        return $res;
    }

    /**
     * Group alerts by entity.
     *
     * @param $events
     * @return array
     */
    public function groupEventsByEntity($events): array
    {
        $res = [];
        foreach($events as $event){
            $entity_id = $event->getEntity()->getId();
            if(!array_key_exists($entity_id, $res)){
                $res[$entity_id] = array();
            }
            $res[$entity_id][] = $event;
        }
        return $res;
    }

    /**
     * @param $alerts
     * @param $includeAlerts
     * @param $format
     * @param $from
     * @param $until
     * @param $limit
     * @param bool $mergeOverlap
     * @param int $page
     * @param string $orderByAttr
     * @param string $orderByOrder
     * @return OutagesEvent[]
     */
    public function buildEventsObjects($alerts, $includeAlerts, $format, $from, $until, $limit, $mergeOverlap=false, $page=0, $orderByAttr="time", $orderByOrder="asc"): array
    {
        $res = [];
        $raw_events = [];

        $alertGroups = $this->groupAlertsByEntity($alerts);
        foreach($alertGroups as $entity_id => $alerts) {
            $eventMap = $this->buildEvents($alerts, $from, $until);
            if ($mergeOverlap) {
                $raw_events = array_merge($raw_events, $this->mergeOverlappingEvents($eventMap));
            } else {
                foreach ($eventMap as $id => $events) {
                    // id = datasource + entity_type + entity_code
                    foreach ($events as $event) {
                        $raw_events[] = $event;
                    }
                }
            }
        }
        foreach($raw_events as $event){
            $res[] = new OutagesEvent($event['from'], $event['until'],
                $event['alerts'], $event['score'], $format, $includeAlerts, $event['X-Overlaps-Window'], $mergeOverlap);
        }

        // source events
        if($orderByAttr=="score"){
            usort($res,
                function ($a, $b) {
                    return ($a->getScore() > $b->getScore() );
                }
            );
        } else if ($orderByAttr=="name") {
            usort($res,
                function ($a, $b) {
                    return strcmp($a->getEntity()->getName(), $b->getEntity()->getName() );
                }
            );
        } else if ($orderByAttr=="time") {
            usort($res,
                function ($a, $b) {
                    return ($a->getFrom() > $b->getFrom() );
                }
            );
        }

        if ($orderByOrder == "desc"){
            $res = array_reverse($res);
        }

        if ($limit) {
            $res = array_slice($res, $limit*$page, $limit);
        }

        return $res;
    }

    /**
     * Compute summary scores.
     *
     * In a summary, the score of each data source is the sum of all scores of events for the corresponding data source.
     * The overall score for the summary of the sum of all the overall scores for the merged events.
     *
     * @param array $events
     *
     * @return array
     */
    private function computeSummaryScores(array &$events): array
    {
        $res = [];
        foreach (array_keys($events) as $aId) {
            foreach ($events[$aId] as &$e) {
                $ds = $this->datasourceService->fqidToDatasourceName($e['fqid']);
                if (!array_key_exists($ds, $res)) {
                    $res[$ds] = 0;
                }
                $res[$ds] += $e['score'];
            }
        }

        $merged = $this->mergeOverlappingEvents($events);
        $total = 0;
        foreach ($merged as $event) {
            $total += $event['score'];
        }
        $res["overall"] = $total;
        return $res;
    }

    private function countEventsFromMap($eventMap): int {
        $count=0;
        foreach($eventMap as $aid => $events){
            $count += count($events);
        }
        return $count;
    }

    /**
     * Summarize all events during the time period by entities. One summary per
     * entitiy for the entire time period.
     *
     * @param array $alerts an array of alerts to build events from
     * @param int $from
     * @param int $until
     * @param int $limit
     * @param int|null $page
     * @param string|null $orderByAttr
     * @param string|null $orderByOrder
     * @return OutagesEvent[]
     */
    public function buildEventsSummary($alerts, $from, $until, $limit, $page=0, $orderByAttr="score", $orderByOrder="asc"): array
    {
        if($orderByAttr=="time"){
            $orderByAttr="score";
        }
        $res = [];

        // first group all alerts by entity and process each entity's alerts
        // separately to build events and then summary
        $alertGroups = $this->groupAlertsByEntity($alerts);
        foreach($alertGroups as $entity_id => $alerts){
            // all alerts here have the entity
            $eventmap = $this->buildEvents($alerts, $from, $until);
            if(!$eventmap){
                continue;
            }
            $scores = $this->computeSummaryScores($eventmap);
            $res[] = new OutagesSummary($scores, $alerts[0]->getEntity(), $this->countEventsFromMap($eventmap));
        }


        if($orderByAttr=="score"){
            usort($res,
                function ($a, $b) {
                    return ($a->getScores()["overall"] > $b->getScores()["overall"] );
                }
            );
        } else if ($orderByAttr=="name") {
            usort($res,
                function ($a, $b) {
                    return strcmp($a->getEntity()->getName(), $b->getEntity()->getName() );
                }
            );
        }

        if($orderByOrder=="desc"){
            $res=array_reverse($res);
        }

        if ($limit) {
            $res = array_slice($res, $limit*$page, $limit);
        }
        return $res;
    }

    private function buildEvents($alerts, $from, $until, $orderBy="score"){
        // sort alerts by time
        // usort($alerts, ["App\Outages\OutagesEventsService","cmpAlert"]);

        # EVENT: "location" "start" "duration" "uncertainty" "status" "fraction" "score" "location_name" "overlaps_window"

        $events = []; // events[fqid] -> [complete events]
        $curEvents = []; // curEvents[fqid] -> ongoing event
        foreach ($alerts as &$a) {
            $fqid = $a->getFqid();
            $aId = $fqid . $a->getMetaType() . $a->getMetaCode(); // event identifier
            $level = $a->getLevel();
            $time = $a->getTime();
            $drop = (abs($a->getHistoryValue() -
                        $a->getValue()) /
                    max($a->getHistoryValue(), $a->getValue())) * 100;

            // are we tracking any events for this ID?
            if (!array_key_exists($aId, $events)) {
                $events[$aId] = [];
                $curEvents[$aId] = null;
            }
            $cE = $curEvents[$aId];
            if ($cE) {
                // we have an ongoing event for this ID, update it
                // two cases:
                // - normal alert => update score and finish event
                // - !normal alert => update score only

                // update the score based on the prevDrop and prevTime
                $interAlertMins = ($time - $cE['prevTime']) / 60;
                $cE['score'] += $cE['prevDrop'] * $interAlertMins;
                $cE['prevTime'] = $time;
                $cE['prevDrop'] = $drop;
                $cE['alerts'][] = $a;

                if ($level === "normal") {
                    $cE['until'] = $time;
                    unset($cE['prevTime']);
                    unset($cE['prevDrop']);
                    $curEvents[$aId] = null;
                    $events[$aId][] = $cE;
                }
            } else {
                // this is the first alert for this event
                if ($level == "normal") {
                    // we can't do anything with a normal-only event
                } else {
                    $curEvents[$aId] = [
                        'fqid' => $fqid,
                        'metaType' => $a->getMetaType(),
                        'metaCode' => $a->getMetaCode(),
                        'from' => $time,
                        'alerts' => [$a],
                        'X-Overlaps-Window' => false,
                        // until will be set at end
                        'score' => 0,

                        // temp values
                        'prevTime' => $time,
                        'prevDrop' => $drop,
                    ];
                }
            }
        }

        // finish any ongoing events by pretending we got a normal at windowEnd
        foreach (array_keys($curEvents) as $aId) {
            $cE = $curEvents[$aId];
            if ($cE) {
                $interAlertMins = ($until - $cE['prevTime']) / 60;
                $cE['score'] += $cE['prevDrop'] * $interAlertMins;
                unset($cE['prevTime']);
                unset($cE['prevDrop']);
                $cE['until'] = $until;
                $cE['X-Overlaps-Window'] = true;
                $events[$aId][] = $cE;
            }
        }

        // remove event keys with empty list of events
        foreach($events as $aid => $e) {
            if(count($e)==0){
                unset ($events[$aid]);
            }
        }

        return $events;
    }
}
