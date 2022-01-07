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
use App\Entity\DatasourceEntity;
use App\TimeSeries\Backend\BackendException;
use App\TimeSeries\Backend\GraphiteBackend;
use App\TimeSeries\Backend\InfluxV2Backend;
use App\TimeSeries\Backend\OutagesBackend;
use App\TimeSeries\TimeSeriesSet;
use App\Utils\QueryTime;


class SignalsService
{
    /**
     * @var InfluxV2Backend
     */
    private $influxV2Backend;
    /**
     * @var OutagesBackend
     */
    private $outagesBackend;

    private $graphiteBackend;

    private $influxService;

    /**
     * All available down-sample steps an datasource can use.
     */
    const ALLOWED_STEPS = [
        60, 120, 300, 600, 900, 1800, // minute-level [1, 2, 5, 15, 30]
        3600, 7200, 21600, 43200,  //hour-level [1, 2, 6, 12]
        86400, 172800, //day-level [1, 2]
        604800, 1209600, 2419200, //week-level [1, 2, 4]
        31536000, 63072000, 315360000, //year-level [1, 2, 10]
    ];


    /**
     * Build graphite expression JSON object based on given $datasource.
     *
     * @param string $fqid
     * @param string $datasource_id
     * @return string
     */
    private function buildGraphiteExpression(string $fqid, string $datasource_id): string {
        $queryJsons = [
            "bgp" => "alias(bgp.prefix-visibility.$fqid.v4.visibility_threshold.min_50%_ff_peer_asns.visible_slash24_cnt,\"bgp\")",
            "ucsd-nt" => "alias(darknet.ucsd-nt.non-erratic.$fqid.uniq_src_ip,\"ucsd-nt\")",
            "ping-slash24" => "alias(sumSeries(keepLastValue(active.ping-slash24.$fqid.probers.team-1.caida-sdsc.*.up_slash24_cnt,1)),\"ping-slash24\")",
        ];
        return $queryJsons[$datasource_id];
    }

    /**
     * Group entities by common part of the fqid part. Only entities with the same common part can be grouped into
     * single graphite query.
     *
     * @param array $entities
     * @return array|array[]
     */
    private function groupEntitiesByFqid(array $entities): array {
        $entityType = $entities[0]->getType()->getType();
        $entityGroups = [];
        if($entityType=="asn") {
            $entityGroups = ["asn"=>$entities];
        }

        $common_code_range = [
            "asn" => [0,1],
            "country" => [2,1],
            "region" => [2,2],
            "county" => [2,3],
        ];

        foreach($entities as $entity){
            $fqid = $entity->getAttribute("fqid");
            $fields = explode(".", $fqid);
            $common_code = implode(".", array_slice($fields, $common_code_range[$entityType][0], $common_code_range[$entityType][1]));
            if(!array_key_exists($common_code, $entityGroups)){
                $entityGroups[$common_code] = [];
            }
            $entityGroups[$common_code][] = $entity;
        }

        return $entityGroups;
    }

    /**
     * Build graphite expression JSON object based on given $datasource.
     *
     * @param string $fqid
     * @param string $datasource_id
     * @return array
     */
    private function buildMultiEntityGraphiteExpression(array $entities, string $datasource_id): array
    {
        // NOTE: for a list of fqids, there should only be one portion that are different.
        // It also must be the last field that are different from each other
        // Examples:
        // - asn: asn.133191
        // - country: geo.netacuity.NA.US
        // - region: geo.netacuity.NA.US.4437
        // - county: geo.netacuity.NA.US.4437.3103

        $entityType = $entities[0]->getType()->getType();

        $queries = [];

        foreach($this->groupEntitiesByFqid($entities) as $group_entities){
            $common="";
            $unique=[];
            foreach($group_entities as $entity) {
                $fqid = $entity->getAttribute("fqid");
                $fields = explode(".", $fqid);
                if($common == ""){
                    $common = implode(".", array_slice($fields, 0, -1));
                }
                assert($entity->getCode() == end($fields));
                $unique[] = end($fields);
            }

            if($datasource_id=="ucsd-nt" && $common=="asn"){
                $common="routing.asn";
            }
            $fqid_combined = $common . ".{" . implode(",", $unique) . "}";

            $aliasIndex = [
                "asn" => 3,
                "country" => 5,
                "region" => 6,
                "county" => 7,
            ];

            $aliasIndexNt = [
                "asn" => 5,
                "country" => 6,
                "region" => 7,
                "county" => 8,
            ];

            $queryJsons = [
                "bgp" => "aliasByNode(bgp.prefix-visibility.$fqid_combined.v4.visibility_threshold.min_50%_ff_peer_asns.visible_slash24_cnt, $aliasIndex[$entityType])",
                "ucsd-nt" => "aliasByNode(darknet.ucsd-nt.non-erratic.$fqid_combined.uniq_src_ip, $aliasIndexNt[$entityType])",
                // NOTE: if see strange gaps in between bins, consider bring back keepLastValue function for ping-slash24
                "ping-slash24" => "aliasByNode(groupByNode(active.ping-slash24.$fqid_combined.probers.team-1.caida-sdsc.*.up_slash24_cnt,$aliasIndex[$entityType], 'sumSeries'), $aliasIndex[$entityType])",
            ];

            $queries[] = $queryJsons[$datasource_id];
        }

        return $queries;
    }

    /**
     * Calculate step based on the value of `from` and `until`.
     * @param QueryTime $from
     * @param QueryTime $until
     * @param int $maxPoints
     * @param DatasourceEntity $datasource
     * @return int
     */
    protected function calculateStep(QueryTime $from, QueryTime $until, ?int $maxPoints, int $nativeStep){

        // if no maxPoints specified, return the native step of the datasource
        if($maxPoints == null){
            return $nativeStep;
        }

        // find the smallest step that can get the number of data points below the specified $maxPoints
        $step = $nativeStep;
        $range = $until->getEpochTime() - $from->getEpochTime();
        foreach($this::ALLOWED_STEPS as $tmp_step){
            if($tmp_step<$nativeStep) {
                // ignore steps that are smaller than datasource's native step
                continue;
            }
            $step = $tmp_step;
            if($range/$tmp_step <= $maxPoints){
                break;
            }
        }
        return $step;
    }

    /**
     * Round down the `from` time based on the step.
     * @param $from
     * @param $step
     * @return QueryTime
     */
    private function roundDownFromTs($from, $step){
        $from_ts = $from->getEpochTime();
        $from_ts = floor($from_ts / $step) * $step;
        return new QueryTime($from_ts);
    }


    public function __construct(OutagesBackend $outagesBackend,
                                GraphiteBackend $graphiteBackend,
                                InfluxV2Backend $influxV2Backend,
                                InfluxService $influxService
    ) {
        $this->outagesBackend = $outagesBackend;
        $this->influxV2Backend = $influxV2Backend;
        $this->influxService = $influxService;
        $this->graphiteBackend= $graphiteBackend;
    }

    /**
     * Given a list of entities and a list of data sources to query, construct combined queries to improve overall
     * performance.
     *
     * @param QueryTime $from
     * @param QueryTime $until
     * @param array $entities
     * @param array $datasources
     * @param int|null $maxPoints
     * @param bool $graphite
     * @return array
     * @throws BackendException
     */
    public function queryForAll(QueryTime $from, QueryTime $until, array $entities, array $datasources, ?int $maxPoints, bool $graphite): array
    {
        $ts_array = [];

        $entityMap = [];
        foreach($entities as $entity){
            $entityMap[$entity->getCode()] = $entity;
        }

        $perf = [];

        foreach($datasources as $datasource){
            $now = microtime(true);
            $backend = $graphite? "graphite": "influxv2";
            $ds = $datasource->getDatasource();

            if($graphite) {
                $exp_json = $this->buildMultiEntityGraphiteExpression($entities, $ds);
                $arr = $this->graphiteBackend->queryGraphite($from, $until, $exp_json, $maxPoints);
            } else {
                $step = $this->calculateStep($from, $until, $maxPoints, $datasource->getNativeStep());
                $from = $this->roundDownFromTs($from, $step);
                $arr = $this->queryForInfluxV2($ds, $entities, $from, $until, $step);
            }

            $perf[] = [
                "datasource"=>$ds,
                "backend"=>$backend,
                "timeUsed"=>microtime(true) - $now
            ];

            // post-processing
            foreach(array_keys($arr) as $code){
                $ts = $arr[$code];
                $ts -> setMetadataEntity($entityMap[$code]);
                $ts -> setNativeStep($datasource->getNativeStep());
                if(count($ts -> getValues()) <= 2) {
                    // not enough to calculate a true step, use native step instead
                    $ts->setStep($ts-> getNativeStep());
                }
                $ts -> setDatasource($datasource->getDatasource());
                if(!array_key_exists($code, $ts_array)){
                    $ts_array[$code] = [];
                }
                $ts_array[$code][] = $ts;
            }
        }

        $ts_sets = [];
        foreach($entities as $entity){
            $ts_set = new TimeSeriesSet();
            $ts_set->setMetadataEntity($entity);

            $code = $entity->getCode();
            if(array_key_exists($code, $ts_array)){
                foreach($ts_array[$code] as $ts){
                    $ts_set->addOneSeries($ts);
                }
            }

            // add the timeseries set to result array
            $ts_sets[] = $ts_set;
        }
        return [$ts_sets, $perf];
    }

    /**
     * @param int $from
     * @param int $until
     * @param string $entityType
     * @param string $entityCode
     * @param $datasource
     * @param int|null $maxPoints
     * @return array
     */
    public function queryForEventsTimeSeries(int $from, int $until, string $entityType, string $entityCode, DatasourceEntity $datasource, ?int $maxPoints): array
    {
        if(null === $maxPoints){
            $maxPoints = 400;
        }
        return $this->outagesBackend->queryOutages($from, $until,$entityType, $entityCode, $maxPoints, $datasource->getDatasource());
    }

    /**
     * @throws BackendException
     */
    public function queryForInfluxV2(string $datasource, array $entities, QueryTime $from, QueryTime $until, int $step): array
    {
        $query = $this->influxService->buildFluxQuery($datasource, $entities, $from, $until, $step);
        return $this -> influxV2Backend -> queryInfluxV2($query);
    }
}
