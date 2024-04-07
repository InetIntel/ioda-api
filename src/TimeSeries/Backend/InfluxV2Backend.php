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

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);
        // close curl resource to free up system resources
	curl_close($ch);
        return json_decode($output, true);
    }

    private function parseReturnValue($responseJson) {
	$res_array = [];
        if (!array_key_exists("results", $responseJson)) {
	    return $res_array;
	}
        foreach($responseJson["results"] as $entityCode => $res) {
            $raw_values = $res["frames"][0]["data"]["values"];
            if(count($raw_values)!=2){
                continue;
            }
            $res_array[$entityCode] = $raw_values;

            // find step
            if(count($raw_values[0])<=1){
                $step = 0;
            } else {
                $step = ($raw_values[0][1] -$raw_values[0][0])/1000;
            }

            $from = new DateTime();
            $until = new DateTime();
            $from->setTimestamp($raw_values[0][0]/1000);
            $until->setTimestamp(end($raw_values[0])/1000 + $step);

            // create new TimeSeries object accordingly
            $newSeries = new TimeSeries();
            $newSeries->setFrom($from);
            $newSeries->setUntil($until);
            $newSeries->setStep($step);
            $newSeries->setValues($raw_values[1]);
            $res_array[$entityCode] = $newSeries;
        }

        return $res_array;
    }

    /**
     * Influx service main entry point.
     *
     * @param string $query
     * @return array
     * @throws BackendException
     */
    public function queryInfluxV2(string $query, string $secret,
    		string $influxuri): array
    {
        // send query and process response
        $res = $this->sendQuery($query, $secret, $influxuri);

        return $this->parseReturnValue($res);
    }
}
