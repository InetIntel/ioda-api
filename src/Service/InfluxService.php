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

/*
 * Portions of this source code are Copyright (c) 2021 Georgia Tech Research
 * Corporation. All Rights Reserved. Permission to copy, modify, and distribute
 * this software and its documentation for academic research and education
 * purposes, without fee, and without a written agreement is hereby granted,
 * provided that the above copyright notice, this paragraph and the following
 * three paragraphs appear in all copies. Permission to make use of this
 * software for other than academic research and education purposes may be
 * obtained by contacting:
*
 *  Office of Technology Licensing
 *  Georgia Institute of Technology
 *  926 Dalney Street, NW
 *  Atlanta, GA 30318
 *  404.385.8066
 *  techlicensing@gtrc.gatech.edu
 *
 * This software program and documentation are copyrighted by Georgia Tech
 * Research Corporation (GTRC). The software program and documentation are
 * supplied "as is", without any accompanying services from GTRC. GTRC does
 * not warrant that the operation of the program will be uninterrupted or
 * error-free. The end-user understands that the program was developed for
 * research purposes and is advised not to rely exclusively on the program for
 * any reason.
 *
 * IN NO EVENT SHALL GEORGIA TECH RESEARCH CORPORATION BE LIABLE TO ANY PARTY FOR
 * DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES, INCLUDING
 * LOST PROFITS, ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION,
 * EVEN IF GEORGIA TECH RESEARCH CORPORATION HAS BEEN ADVISED OF THE POSSIBILITY
 * OF SUCH DAMAGE. GEORGIA TECH RESEARCH CORPORATION SPECIFICALLY DISCLAIMS ANY
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE SOFTWARE PROVIDED
 * HEREUNDER IS ON AN "AS IS" BASIS, AND  GEORGIA TECH RESEARCH CORPORATION HAS
 * NO OBLIGATIONS TO PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR
 * MODIFICATIONS.
*/


namespace App\Service;

use App\Utils\QueryTime;
use App\Entity\DataEraEntity;

/* NOTE TO FUTURE SELVES
 *
 * datasource_ids are not going to be consistent across grafana deployments.
 * If we ever have to redeploy grafana from scratch, the IDs need to be
 * updated in the ioda_data_eras to match the ones in grafana.
 *
 * So if you have bugs where the API can't seem to run queries any more, then
 * maybe the datasource ID is wrong.
 */

class InfluxService
{
    const BGP_EXTRA_CLAUSE = " and r.ip_version == \"v4\" and r.visibility_threshold == \"min_50%_ff_peer_asns\"";

    const CODE_FIELDS = [
        "continent" => [ "continent_code" ],
        "country" => [ "country_code" ],
        "region" => [ "region_code" ],
        "county" => [ "county_code" ],
	    "asn" => [ "asn" ],
        "geoasn" => [],     // unused
    ];

    const MAPPINGS = [
        "ping-slash24-loss" => "|> map(fn:(r) => ({r with _value: if r[\"_field\"] == \"loss_pct\" then float(v: r._value) / 100.0 else float(v:r._value)}))",
        "ping-slash24-latency" => "|> map(fn: (r) => ({r with _value: float(v: r._value) / 1000.0}))",
    ];

    const FIELD_MAP = [
        "bgp" => [
            "continent" => [
                "measurement" => "geo_continent_visibility",
            ],
            "country" => [
                "measurement" => "geo_country_visibility",
            ],
            "county" => [
                "measurement" => "geo_county_visibility",
            ],
            "region" => [
                "measurement" => "geo_region_visibility",
            ],
            "asn" => [
                "measurement" => "asn_visibility",
            ],
            "geoasn_country" => [
                "measurement" => "geoasn_country_visibility",
            ],
            "geoasn_region" => [
                "measurement" => "geoasn_region_visibility",
            ],
            "extra" => self::BGP_EXTRA_CLAUSE,
        ],
        "ping-slash24" => [
            "continent" => [
                "measurement" => "geo_continent_slash24",
            ],
            "country" => [
                "measurement" => "geo_country_slash24",
            ],
            "county" => [
                "measurement" => "geo_county_slash24",
            ],
            "region" => [
                "measurement" => "geo_region_slash24",
            ],
            "asn" => [
                "measurement" => "asn_slash24",
            ],
            "geoasn_country" => [
                "measurement" => "geoasn_country_slash24",
            ],
            "geoasn_region" => [
                "measurement" => "geoasn_region_slash24",
            ],
            "extra" => "",
        ],
        "merit-nt" => [
            "continent" => [
                "measurement" => "geo_continent",
            ],
            "country" => [
                "measurement" => "geo_country",
            ],
            "county" => [
                "measurement" => "geo_county",
            ],
            "region" => [
                "measurement" => "geo_region",
            ],
            "asn" => [
                "measurement" => "origin_asn",
            ],
            "geoasn_country" => [
                "measurement" => "geoasn_country",
            ],
            "geoasn_region" => [
                "measurement" => "geoasn_region",
            ],
            "extra" => "",
        ],
        "gtr" => [
            "country" => [
                "measurement" => "google_tr",
            ],
            "extra" => " and r.product == \"WEB_SEARCH\"",
        ],
        "gtr-norm" => [
            "country" => [
                "measurement" => "google_tr",
            ],
            "extra" => " and r.product == \"WEB_SEARCH\"",
        ],
        "upstream-delay-penult-asns" => [
            "asn" => [
                "measurement" => "yarrp_penultimate_as_freq",
            ],
            "extra" => "",
        ],
        "ping-slash24-loss" => [
            "continent" => [
                "measurement" => "geo_continent_latency",
            ],
            "country" => [
                "measurement" => "geo_country_latency",
            ],
            "region" => [
                "measurement" => "geo_region_latency",
            ],
            "asn" => [
                "measurement" => "asn_latency",
            ],
            "extra" => " and (r._field == \"loss_pct\" or r._field == \"probe_count\")",
        ],
        "ping-slash24-latency" => [
            "continent" => [
                "measurement" => "geo_continent_latency",
            ],
            "country" => [
                "measurement" => "geo_country_latency",
            ],
            "region" => [
                "measurement" => "geo_region_latency",
            ],
            "asn" => [
                "measurement" => "asn_latency",
            ],
            "extra" => " and (r._field != \"loss_pct\" and r._field != \"probe_count\")",
        ],
        "upstream-delay-penult-e2e-latency" => [
            "extra" => "",
        ]
    ];

    private function buildGTRNormalisedFluxSingleQuery(string $entityCode,
        int $step, string $field, string $code_field, string $measurement,
        string $bucket, string $prod)
    {
    $q = <<< END
fetched = from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r._measurement == "$measurement" and
    r._field == "$field" and
    r.$code_field == "$entityCode" and
    (r.product == "$prod")
  )
maxprod = fetched |> max() |> findColumn(fn: (key) => true, column: "_value")

fetched |> map(fn: (r) => ({r with _value: r._value / maxprod[0]}))
        |> aggregateWindow(every: ${step}s, fn: mean, timeSrc: "_start",
                           createEmpty: true)
        |> yield(name: "mean")
END;
        return $q;
    }

    private function buildGTRBlendFluxSingleQuery(string $entityCode,
        int $step, string $field,
        string $code_field, string $measurement, string $bucket)
    {

        $q = <<< END
allfetched = from(bucket: "ioda_gtr")
|> range(start: v.timeRangeStart, stop:v.timeRangeStop)
|> filter(fn: (r) =>
        r._measurement == "$measurement" and
        r._field == "$field" and
        r.$code_field == "$entityCode" and
        (r.product == "WEB_SEARCH" or r.product == "GMAIL" or r.product == "MAPS")
        )

maxweb = allfetched |> filter(fn: (r) => r.product == "WEB_SEARCH") |> max() |> findColumn(fn: (key) => true, column: "_value")
maxmail = allfetched |> filter(fn: (r) => r.product == "GMAIL") |> max() |> findColumn(fn: (key) => true, column: "_value")
maxmaps = allfetched |> filter(fn: (r) => r.product == "MAPS") |> max() |> findColumn(fn: (key) => true, column: "_value")

allfetched
|> pivot(rowKey: ["_time"], columnKey: ["product"], valueColumn: "_value")
|> map(fn: (r) => ({r with MAPS: r.MAPS / maxmaps[0]}))
|> map(fn: (r) => ({r with GMAIL: r.GMAIL / maxmail[0]}))
|> map(fn: (r) => ({r with WEB_SEARCH: r.WEB_SEARCH / maxweb[0]}))
|> map(fn: (r) => ({r with _value: (r.MAPS + r.GMAIL + r.WEB_SEARCH) / 3.0}))
|> drop(columns: ["MAPS", "GMAIL", "WEB_SEARCH"])
END;

        return $q;
    }

    private function buildGTRBlendFluxQueries(array $entities, int $step,
            int $datasource_id, string $field, array $code_fields,
        string $measurement, string $bucket)
    {
        $fluxQueries = [];

        $code_field = $code_fields[0];

        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $ent_index = $entityCode . "|->BLENDED";
            $q = $this->buildGTRBlendFluxSingleQuery($entityCode, $step,
                    $field, $code_field, $measurement, $bucket);
            $q = str_replace("\n", '', $q);
            $q = str_replace("\t", '    ', $q);
            $q = str_replace("\"", '\\"', $q);
            $fluxQueries[$ent_index] = $q;
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
            {
                "query": "$fluxQuery",
                    "refId":"$entityCode",
                    "datasourceId": $datasource_id,
                    "intervalMs": 60000,
                    "maxDataPoints": 31536000
            }
            END;
        }
        return $queries;
    }

    private function buildGTRRawFluxSingleQuery(string $entityCode,
            int $step, string $field,
        string $code_field, string $measurement, string $bucket,
        string $prod) {

        $q = <<< END
from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r._measurement == "$measurement" and
    r._field == "$field" and
    r.$code_field == "$entityCode" and
    r.product == "$prod"
  )
  |> aggregateWindow(every: ${step}s, fn: mean, timeSrc: "_start",
    createEmpty: true)
  |> yield(name: "mean")
END;
    return $q;
    }

    private function buildGTRFluxQueries(array $entities, int $step,
        int $datasource_id, string $field, array $code_fields,
        string $measurement,
        string $bucket, string $datasource, ?string $productList)
    {

        $fluxQueries = [];
        if ($productList == null) {
            $products = ["WEB_SEARCH"];
        } else {
            $products = explode(",", $productList);
        }

        // should only be a single code field for GTR anyway
        if (count($code_fields) === 0) {
            return [];
        }
        $code_field = $code_fields[0];

        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            foreach($products as $prod) {
                $prod = strtoupper($prod);
                if ($prod == "SEARCH") {
                    $prod = "WEB_SEARCH";
                } else if ($prod == "MAIL") {
                    $prod = "GMAIL";
                }
                $ent_index = $entityCode . "|->" . $prod;

                if ($prod == "BLENDED") {
                    if ($datasource == "gtr") {
                        continue;
                    }
                    $q = $this->buildGTRBlendFluxSingleQuery($entityCode,
                            $step, $field, $code_field, $measurement,
                            $bucket);
                } else if ($datasource == "gtr") {
                    $q = $this->buildGTRRawFluxSingleQuery($entityCode,
                            $step, $field, $code_field, $measurement,
                            $bucket, $prod);
                } else if ($datasource == "gtr-norm") {
                    $q = $this->buildGTRNormalisedFluxSingleQuery($entityCode,
                            $step, $field, $code_field, $measurement,
                            $bucket, $prod);
                } else {
                    continue;
                }

                $q = str_replace("\n", ' ', $q);
                $q = str_replace("\t", '    ', $q);
                $q = str_replace("\"", '\\"', $q);
                $fluxQueries[$ent_index] = $q;
            }
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
            {
                "query": "$fluxQuery",
                    "refId":"$entityCode",
                    "datasourceId": $datasource_id,
                    "intervalMs": 60000,
                    "maxDataPoints": 31536000
            }
            END;
        }
        return $queries;
    }

    private function buildGeoasnFluxQueries(array $entities, int $step,
            string $datasource, int $datasource_id, string $field,
            string $bucket, string $extra, string $qterm)
    {
        $fluxQueries = [];

        if ($qterm !== "") {
            $extra = $extra . " and " . $qterm;
        }
        if ($field !== "") {
            $fieldClause = "r._field == \"$field\" and ";
        } else {
            $fieldClause = "";
        }
        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $entityType = $entity->getType();
            $splitCode = explode("-", $entityCode);

            if (count($splitCode) != 2) {
                error_log("Invalid entity code for $entityType: $entityCode");
                continue;
            }

            if (ctype_digit(substr($splitCode[1], 0, 1))) {
                $measurement=self::FIELD_MAP[$datasource]["geoasn_region"]["measurement"];
                $code_field = array_merge(self::CODE_FIELDS["asn"],
                        self::CODE_FIELDS["region"]);
            } else {
                $measurement=self::FIELD_MAP[$datasource]["geoasn_country"]["measurement"];
                $code_field = array_merge(self::CODE_FIELDS["asn"],
                        self::CODE_FIELDS["country"]);
            }

            $codeclause = "";
            $ind = 0;
            foreach($code_field as $codef) {
                $seekcode = $splitCode[$ind];
                if ($ind === 0) {
                    $codeclause = "r.$codef == \"$seekcode\"";
                } else {
                    $codeclause = $codeclause . " and r.$codef == \"$seekcode\"";
                }
                $ind++;
            }

            $q = <<< END
                from(bucket: "$bucket")
                |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
                |> filter(fn: (r) =>
                        r._measurement == "$measurement" and
                        $fieldClause
                        $codeclause
                        $extra
                        )
                |> aggregateWindow(every: ${step}s, fn: mean, timeSrc: "_start",
                        createEmpty: true)
                |> yield(name: "mean")
                END;
            $q = str_replace("\n", '', $q);
            $q = str_replace("\"", '\\"', $q);
            $fluxQueries[$entityCode] = $q;
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
            {
                "query": "$fluxQuery",
                    "refId":"$entityCode",
                    "datasourceId": $datasource_id,
                    "intervalMs": 60000,
                    "maxDataPoints": 31536000
            }
            END;
        }
        return $queries;
    }

    private function buildUpstreamDelayLatencyFluxQueries(
            array $entities, int $step, int $datasource_id, string $bucket,
            string $qterm) {

        $fluxQueries = [];
        if ($qterm !== "") {
            $qterm = " and " . $qterm;
        }
        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $entityType = $entity->getType();
            if ($entityType->getType() !== "asn") {
                continue;
            }
            $q = <<< END
from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r.target_as == "$entityCode"
    $qterm
  )
  |> aggregateWindow(every: ${step}s, fn: median, timeSrc: "_start",
    createEmpty: true)
  |> yield(name: "latencies")
END;
            $q = str_replace("\n", '', $q);
            $q = str_replace("\"", '\\"', $q);
            $fluxQueries[$entityCode] = $q;
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
    {
      "query": "$fluxQuery",
      "refId":"$entityCode",
      "datasourceId": $datasource_id,
      "intervalMs": 60000,
      "maxDataPoints": 31536000
    }
END;
    }
    return $queries;
    }

    private function buildUpstreamDelayPenultimateASFluxQueries(
            array $entities, int $step, int $datasource_id, string $bucket,
            string $qterm) {

        $fluxQueries = [];
        if ($qterm !== "") {
            $qterm = " and " . $qterm;
        }
        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $entityType = $entity->getType();
            if ($entityType->getType() !== "asn") {
                continue;
            }
            $q = <<< END
from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r._measurement == "yarrp_penultimate_as_freq" and
    r._field == "penultimate_as_count" and
    r.target_as == "$entityCode"
    $qterm
  )
  |> aggregateWindow(every: ${step}s, fn: median, timeSrc: "_start",
    createEmpty: true)
  |> yield(name: "median")
END;
            $q = str_replace("\n", '', $q);
            $q = str_replace("\"", '\\"', $q);
            $fluxQueries[$entityCode] = $q;
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
    {
      "query": "$fluxQuery",
      "refId":"$entityCode",
      "datasourceId": $datasource_id,
      "intervalMs": 60000,
      "maxDataPoints": 31536000
    }
END;
    }
    return $queries;
    }

    private function buildStandardFluxQueries(array $entities, int $step,
        int $datasource_id, string $field, array $code_field,
            string $measurement, string $bucket, string $extra, string $qterm,
            string $mapping)
    {

        $fluxQueries = [];

        if ($qterm !== "") {
            $extra = $extra . " and " . $qterm;
        }

        if ($field !== "") {
            $fieldClause = "r._field == \"$field\" and ";
        } else {
            $fieldClause = "";
        }

        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $entityType = $entity->getType();

            $splitCode = explode("-", $entityCode);

            if (count($code_field) != count($splitCode)) {
                error_log("Invalid entity code for $entityType: $entityCode");
                continue;
            }

            $codeclause = "";
            $ind = 0;
            foreach($code_field as $codef) {
                $seekcode = $splitCode[$ind];
                if ($ind === 0) {
                    $codeclause = "r.$codef == \"$seekcode\"";
                } else {
                    $codeclause = $codeclause . " and r.$codef == \"$seekcode\"";
                }
                $ind++;
            }

            $q = <<< END
from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r._measurement == "$measurement" and
    $fieldClause
    $codeclause
    $extra
  )
  $mapping
  |> aggregateWindow(every: ${step}s, fn: mean, timeSrc: "_start",
    createEmpty: true)
  |> yield(name: "mean")
END;
            $q = str_replace("\n", '', $q);
            $q = str_replace("\"", '\\"', $q);
            $fluxQueries[$entityCode] = $q;
        }

        $queries = [];
        foreach($fluxQueries as $entityCode => $fluxQuery){
            // NOTE: the maxDataPoints needs to be set to a very large value to avoid grafana stripping data off
            //       currently set to be 31536000, which should be equivalent to 10 years
            $queries[] = <<<END
    {
      "query": "$fluxQuery",
      "refId":"$entityCode",
      "datasourceId": $datasource_id,
      "intervalMs": 60000,
      "maxDataPoints": 31536000
    }
END;
    }
    return $queries;
    }

    /**
     * Build Flux query for BGP data source.
     * @param string $datasource
     * @param string $entityType
     * @param string $entityCode
     * @return array|string|string[]
     */
    public function buildFluxQuery(string $datasource, array $entities, QueryTime $from, QueryTime $until, int $step, DataEraEntity $era, ?string $extraParams)
    {
        $from_ts = $from->getEpochTime()*1000;
        $until_ts = $until->getEpochTime()*1000;

        $query = <<<END
{
  "queries": [
  ],
  "from": "$from_ts",
  "to": "$until_ts"
}
END;
        if (count($entities) == 0) {
            return $query;
        }

        $entityType = $era->getEntityType();

        $field = $era->getField();
        $bucket = $era->getBucket();
        $queryterm = $era->getQueryTerm();
        $extra = self::FIELD_MAP[$datasource]["extra"];
        if (array_key_exists($datasource, self::MAPPINGS)) {
            $mapping = self::MAPPINGS[$datasource];
        } else {
            $mapping = "";
        }
        $datasource_id = $era->getGrafanaSource();

        if ($datasource == "upstream-delay-penult-e2e-latency") {
            $queries = $this->buildUpstreamDelayLatencyFluxQueries($entities,
                    $step, $datasource_id, $bucket, $queryterm);

        } else if ($datasource == "upstream-delay-penult-asns") {
            $queries = $this->buildUpstreamDelayPenultimateASFluxQueries(
                    $entities, $step, $datasource_id, $bucket, $queryterm);
        } else if ($entityType == "geoasn") {
            $queries = $this->buildGeoasnFluxQueries($entities, $step,
                    $datasource, $datasource_id, $field, $bucket, $extra,
                    $queryterm);
        } else {
            $code_field = self::CODE_FIELDS["$entityType"];
            $measurement = self::FIELD_MAP[$datasource]["$entityType"]["measurement"];

            if ($datasource == "gtr" or ($datasource == "gtr-norm"
                                && $extraParams != null)) {

                $queries = $this->buildGTRFluxQueries($entities, $step,
                        $datasource_id, $field, $code_field, $measurement,
                        $bucket, $datasource, $extraParams);
            } else if ($datasource == "gtr-norm") {
                $queries = $this->buildGTRBlendFluxQueries($entities, $step,
                        $datasource_id, $field, $code_field, $measurement,
                        $bucket);
            } else {
                $queries = $this->buildStandardFluxQueries($entities, $step,
                    $datasource_id, $field, $code_field, $measurement,
                    $bucket, $extra, $queryterm, $mapping);
            }
        }

        $combined_queries = implode(",", $queries);


        $query = <<<END
{
  "queries": [
  $combined_queries
  ],
  "from": "$from_ts",
  "to": "$until_ts"
}
END;
        return $query;
    }
}
