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


namespace App\Service;

use App\Utils\QueryTime;

/* NOTE TO FUTURE SELVES
 *
 * datasource_ids are not going to be consistent across grafana deployments.
 * If we ever have to redeploy grafana from scratch, the IDs here will no longer
 * match the data sources in grafana.
 *
 * So if you have bugs where the API can't seem to run queries any more, then
 * maybe the datasource ID is wrong.
 */

class InfluxService
{
    const BGP_EXTRA_CLAUSE = " and r.ip_version == \"v4\" and r.visibility_threshold == \"min_50%_ff_peer_asns\"";
    const NT_EXTRA_GEO = " and r.geo_db == \"netacuity\" ";

    const FIELD_MAP = [
        "bgp" => [
            "continent" => [
                "measurement" => "geo_continent_visibility",
                "code_field" => "continent_code",
                "extra" => self::BGP_EXTRA_CLAUSE,
                "aggr" => "",
            ],
            "country" => [
                "measurement" => "geo_country_visibility",
                "code_field" => "country_code",
                "extra" => self::BGP_EXTRA_CLAUSE,
                "aggr" => "",
            ],
            "county" => [
                "measurement" => "geo_county_visibility",
                "code_field" => "county_code",
                "extra" => self::BGP_EXTRA_CLAUSE,
                "aggr" => "",
            ],
            "region" => [
                "measurement" => "geo_region_visibility",
                "code_field" => "region_code",
                "extra" => self::BGP_EXTRA_CLAUSE,
                "aggr" => "",
            ],
            "asn" => [
                "measurement" => "asn_visibility",
                "code_field" => "asn",
                "extra" => self::BGP_EXTRA_CLAUSE,
                "aggr" => "",
            ],
            "datasource_id" => 3,
            "field" => "visible_slash24_cnt",
            "bucket" => "ioda_bgp",
        ],
        "ping-slash24" => [
            "continent" => [
                "measurement" => "geo_continent_slash24",
                "code_field" => "continent_code",
                "extra" => "",
                "aggr" => "",
            ],
            "country" => [
                "measurement" => "geo_country_slash24",
                "code_field" => "country_code",
                "extra" => "",
                "aggr" => "",
            ],
            "county" => [
                "measurement" => "geo_county_slash24",
                "code_field" => "county_code",
                "extra" => "",
                "aggr" => "",
            ],
            "region" => [
                "measurement" => "geo_region_slash24",
                "code_field" => "region_code",
                "extra" => "",
                "aggr" => "",
            ],
            "asn" => [
                "measurement" => "asn_slash24",
                "code_field" => "asn",
                "extra" => "",
                "aggr" => "",
            ],
            "datasource_id" => 9,
            "field" => "up_slash24_cnt",
            "bucket" => "ioda_trinocular_summed",
        ],
        "ucsd-nt" => [
            "continent" => [
                "measurement" => "geo_continent",
                "code_field" => "continent_code",
                "extra" => self::NT_EXTRA_GEO,
                "aggr" => "",
            ],
            "country" => [
                "measurement" => "geo_country",
                "code_field" => "country_code",
                "extra" => self::NT_EXTRA_GEO,
                "aggr" => "",
            ],
            "county" => [
                "measurement" => "geo_county",
                "code_field" => "county_code",
                "extra" => self::NT_EXTRA_GEO,
                "aggr" => "",
            ],
            "region" => [
                "measurement" => "geo_region",
                "code_field" => "region_code",
                "extra" => self::NT_EXTRA_GEO,
                "aggr" => "",
            ],
            "asn" => [
                "measurement" => "origin_asn",
                "code_field" => "asn",
                "extra" => "",
                "aggr" => "",
            ],
            "datasource_id" => 7,
            "field" => "uniq_src_ip",
            "bucket" => "ioda_ucsd_nt_non_erratic",
        ]
    ];

    /**
     * Build Flux query for BGP data source.
     * @param string $datasource
     * @param string $entityType
     * @param string $entityCode
     * @return array|string|string[]
     */
    public function buildFluxQuery(string $datasource, array $entities, QueryTime $from, QueryTime $until, int $step)
    {
        $entityType = $entities[0]->getType()->getType();

        $field = self::FIELD_MAP[$datasource]["field"];
        $code_field =  self::FIELD_MAP[$datasource]["$entityType"]["code_field"];
        $measurement = self::FIELD_MAP[$datasource]["$entityType"]["measurement"];
        $bucket = self::FIELD_MAP[$datasource]["bucket"];
        $extra = self::FIELD_MAP[$datasource]["$entityType"]["extra"];
        $aggr = self::FIELD_MAP[$datasource]["$entityType"]["aggr"];

        $datasource_id = self::FIELD_MAP[$datasource]["datasource_id"];

        $fluxQueries = [];
        foreach($entities as $entity){
            $entityCode = $entity->getCode();
            $q = <<< END
from(bucket: "$bucket")
  |> range(start: v.timeRangeStart, stop:v.timeRangeStop)
  |> filter(fn: (r) =>
    r._measurement == "$measurement" and
    r._field == "$field" and
    r.$code_field == "$entityCode"
    $extra
  )
  $aggr
  |> aggregateWindow(every: ${step}s, fn: mean, createEmpty: true)
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

        $combined_queries = implode(",", $queries);

        $from_ts = $from->getEpochTime()*1000;
        $until_ts = $until->getEpochTime()*1000;

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
