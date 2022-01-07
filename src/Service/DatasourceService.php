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
        } elseif (strpos($fqid,"ping-slash24")!==false){
            $ds = "ping-slash24";
        }

        return $ds;
    }
}
