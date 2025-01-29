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

namespace App\TimeSeries\Backend;


use Symfony\Component\VarDumper\VarDumper;
use App\TimeSeries\TimeSeries;
use DateTime;

class InfluxV2Backend
{

    /**
     * @param string $query
     * @return array
     * @throws BackendException
     */
    private function sendQuery(string $query, string $secret,
        string $influx_uri): array {
        if(!$secret){
            throw new BackendException("Missing INFLUXDB_SECRET environment variable");
        }
        if(!$influx_uri){
            throw new BackendException("Missing INFLUXDB_API environment variable");
        }

        // create curl resource
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$influx_uri");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: */*',
            "Authorization: Bearer $secret"
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, fopen('/tmp/curl.log', 'a+'));

        // $output contains the output string
        $output = curl_exec($ch);
        // close curl resource to free up system resources
        curl_close($ch);
        return json_decode($output, true);
    }

    private function mergeFrames($frames, $entityCode, $mergeStrat) {
        $tsmap = array();

        if (count($frames) <= 1) {
            return $frames[0]["data"]["values"];
        }
        foreach($frames as $f) {
            $schemainfo = $f["schema"]["fields"][1]["labels"];
            $metric = $f["schema"]["fields"][1]["name"];

            $keystring="";
            foreach($schemainfo as $kp_key => $kp_value) {
                if ($keystring != "") {
                    $keystring = $keystring . "-" . $kp_key . "-" . $kp_value;
                } else {
                    $keystring = $kp_key . "-" . $kp_value;
                }
            }

            foreach($f["data"]["values"][0] as $ind => $timestamp) {
                // assumes there is a one-to-one mapping, which there
                // really should be
                $val = $f["data"]["values"][1][$ind];

                if ($mergeStrat == "append") {
                    if (!array_key_exists($timestamp, $tsmap)) {
                        $tsmap[$timestamp] = array();
                    }

                    if (!array_key_exists($keystring, $tsmap[$timestamp])) {
                        $tsmap[$timestamp][$keystring] = $schemainfo;
                        $tsmap[$timestamp][$keystring]["agg_values"] = array();
                    }
                    $tsmap[$timestamp][$keystring]["agg_values"][$metric] = $val;

                } else {
                    if (!array_key_exists($timestamp, $tsmap)) {
                        $tsmap[$timestamp] = $val;
                    } else if ($tsmap[$timestamp] == null) {
                        $tsmap[$timestamp] = $val;
                    }
                }

            }
        }

        if ($mergeStrat == "append") {
            $finaltsmap = array();
            foreach($tsmap as $ts => $measurements) {
                $finaltsmap[$ts] = array();
                foreach ($measurements as $k=>$vlist) {
                    // strip the key we used for grouping
                    array_push($finaltsmap[$ts], $vlist);
                }
            }
            $tsmap = $finaltsmap;
        }
        ksort($tsmap);
        $res = [ [], [] ];

        foreach($tsmap as $ts => $val) {
            array_push($res[0], $ts);
            array_push($res[1], $val);
        }
        return $res;
    }

    private function parseReturnValue($responseJson, $finalresult,
        $queriedStep, $mergeStrat) {
        if (!array_key_exists("results", $responseJson)) {
            return $finalresult;
        }
        foreach($responseJson["results"] as $entityCode => $res) {
            $raw_values = $this->mergeFrames($res["frames"], $entityCode,
                    $mergeStrat);
            if(count($raw_values)!=2){
                continue;
            }
            // find step
            if(count($raw_values[0])<=1){
                $step = $queriedStep;
            } else {
                $step = ($raw_values[0][1] -$raw_values[0][0])/1000;
            }

            $from = new DateTime();
            $until = new DateTime();
            $from->setTimestamp($raw_values[0][0]/1000);
            $until->setTimestamp(end($raw_values[0])/1000 + $step);

            // create new TimeSeries object accordingly
            if (!array_key_exists($entityCode, $finalresult)) {
                $newSeries = new TimeSeries();
                $newSeries->setFrom($from);
                $newSeries->setUntil($until);
                $newSeries->setStep($step);
                $newSeries->setValues($raw_values[1]);
                $finalresult[$entityCode] = $newSeries;
            } else {
                $series = $finalresult[$entityCode];
                // we should only ever be appending time periods that
                // immediately follow the existing series
                if ($series->getUntil() == $from) {
                    if ($series->getUntil() < $until) {
                        $series->setUntil($until);
                        $series->appendValues($raw_values[1]);
                    }
                    if ($series->getStep() == 0 || $series->getStep() > $step) {
                        if ($step != 0) {
                            $series->setStep($step);
                        }
                    }
                }
            }
        }

        return $finalresult;
    }

    /**
     * Influx service main entry point.
     *
     * @param string $query
     * @param array $finalres -- updated with the result of the query
     * @throws BackendException
     */
    public function queryInfluxV2(string $query, string $secret,
        string $influxuri, array $finalres, int $step,
        string $mergeStrat): array
    {
        // send query and process response
        $res = $this->sendQuery($query, $secret, $influxuri);
        return $this->parseReturnValue($res, $finalres, $step, $mergeStrat);
    }
}
