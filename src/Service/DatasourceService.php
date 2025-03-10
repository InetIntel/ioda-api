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
use App\Entity\DatasourceEntity;


class DatasourceService
{

    /**
     * @var DatasourceEntity[]
     */
    private $RAW_DATA_DATASOURCES;

    private $EVENTS_DATASOURCE;

    public function __construct()
    {
        $this->RAW_DATA_DATASOURCES = [
            "ucsd-nt" => new DatasourceEntity(
                "ucsd-nt",
                "UCSD Network Telescope",
                "Unique Source IPs",
                60,
                "influxv2"
            ),
            "gtr" => new DatasourceEntity(
                "gtr",
                "Google Transparency Report",
                "Traffic",
                1800,
                "influxv2"
            ),
            "gtr-norm" => new DatasourceEntity(
                "gtr-norm",
                "Google Transparency Report (Normalized)",
                "Normalized Traffic",
                1800,
                "influxv2"
            ),
            /* XXX NOTE, merit measurements are every 60 seconds, but
             * Alberto has requested that we use a 300 second step for now.
             * This means you won't be able to use the API to get the "raw"
             * measurements, but this should make for cleaner lines on our
             * UI graphs -- eventually, we should fix the UI to always
             * query for a suitable number of datapoints OR add an minStep
             * parameter to the API (probably the latter...)
             */
            "merit-nt" => new DatasourceEntity(
                "merit-nt",
                "Merit Network Telescope",
                "Unique Source IPs",
                300,    // XXX TEMPORARY
                "influxv2"
            ),
            "bgp" => new DatasourceEntity(
                "bgp",
                "BGP",
                "Visible /24s",
                300,
                "influxv2"
            ),
            "ping-slash24" => new DatasourceEntity(
                "ping-slash24",
                "Active Probing",
                "Up /24s",
                600,
                "influxv2"
            ),
            "upstream-delay-penult-asns" => new DatasourceEntity(
                "upstream-delay-penult-asns",
                "Upstream Delay Penultimate ASNs",
                "Frequency",
                "3600",
                "influxv2"
            ),
            "upstream-delay-penult-e2e-latency" => new DatasourceEntity(
                "upstream-delay-penult-e2e-latency",
                "Upstream Delay Penultimate ASN End-to-End Latency",
                "Milliseconds",
                "3600",
                "influxv2"
            )
        ];
        $this->EVENTS_DATASOURCE =
            new DatasourceEntity(
                "outages",
                "IODA outages score time series",
                "IODA overall score",
                600,
                "outages"
            );
    }

    public function getAllDatasources(){
        return array_values($this->RAW_DATA_DATASOURCES);
    }

    public function getEventsDatasource(){
        return $this->EVENTS_DATASOURCE;
    }

    public function getDatasource(String $name){
        if (!array_key_exists($name, $this->RAW_DATA_DATASOURCES)) {
            throw new \InvalidArgumentException("Unknown datasource '$name'");
        }
        return $this->RAW_DATA_DATASOURCES[$name];
    }

    public function getDatasourceNames(){
        return array_keys($this->RAW_DATA_DATASOURCES);
    }

    public function isValidDatasource(string $ds_name): bool {
        return array_key_exists($ds_name,$this->RAW_DATA_DATASOURCES);
    }

    public function fqidToDatasourceName($fqid){
        $ds = null;
        if(strpos($fqid,"bgp")!==false){
            $ds = "bgp";
        } elseif (strpos($fqid,"ucsd-nt")!==false){
            $ds = "ucsd-nt";
        } elseif (strpos($fqid,"merit-nt")!==false){
            $ds = "merit-nt";
        } elseif (strpos($fqid,"gtr")!==false){
            $ds = "gtr";
        } elseif (strpos($fqid,"ping-slash24")!==false){
            $ds = "ping-slash24";
        } elseif (strpos($fqid,"yarrp")!==false){
            $ds = "upstream-delay-e2e-latency";
        }

        return $ds;
    }
}
