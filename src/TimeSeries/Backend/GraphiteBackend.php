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

namespace App\TimeSeries\Backend;



use App\TimeSeries\TimeSeries;
use App\Utils\QueryTime;
use DateTime;

class GraphiteBackend
{
    const GRAPHITE_URL = 'http://charthouse-render.int.limbo.caida.org';
    const QUERY_TIMEOUT = 120;
    const DATA_CACHE_TIMEOUTS = [
        7200 => 60, // cache last two hours for 1 min
        86400 => 600, // cache last day for 10 min
    ];
    const DATA_CACHE_TIMEOUT_DEFAULT = 3600;

    /**
     * Make a query to the graphite backend service.
     *
     * @param string $path
     * @param array $params
     * @return string
     * @throws BackendException
     */
    private function sendQuery(string $path, array $params): string
    {
        $query = http_build_query($params);
        // hax to replace target[0]...target[1]... with []
        $query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);

        $url = GraphiteBackend::GRAPHITE_URL . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch,
            array(
                CURLOPT_TIMEOUT => GraphiteBackend::QUERY_TIMEOUT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_POSTFIELDS => $query,
            )
        );
        $result = curl_exec($ch);
        if ($result === false || !$result) {
            throw new BackendException(curl_error($ch) . " URL: " . $url . '?' . $query);
        }
        curl_close($ch);
        return $result;
    }

    private function calcTimeout($until){
        // if until is a relative time, then we want a low cache timeout
        $now = time();
        $timeout = GraphiteBackend::DATA_CACHE_TIMEOUT_DEFAULT;
        if ($until->isRelative()) {
            $t = $now;
        } else {
            $t = $until->getAbsoluteTime()->getTimestamp();
        }
        foreach (GraphiteBackend::DATA_CACHE_TIMEOUTS as $limit => $tout) {
            if ($t >= $now - $limit) {
                // our 'until' time falls inside this limit
                $timeout = $tout;
                break;
            }
        }

        return $timeout;
    }

    /**
     * Construct graphite query, send request, and return one TimeSeries object.
     *
     * @param QueryTime $from
     * @param QueryTime $until
     * @param $expression
     * @param int|null $maxPoints
     * @return TimeSeries
     * @throws BackendException
     */
    public function queryGraphite(QueryTime $from, QueryTime $until, array $expression, ?int $maxPoints): array
    {
        // calculate cache timeout, used in graphite query
        $timeout = $this->calcTimeout($until);

        if($maxPoints == null){
            $maxPoints = TimeSeries::DEFAULT_MAX_POINTS;
        }

        // send out acutal query
        $result = $this->sendQuery(
            '/render',
            [
                'format' => 'json-internal',
                'target' => $expression,
                'from' => $from->getGraphiteTime(),
                'until' => $until->getGraphiteTime(),
                'cacheTimeout' => $timeout,
                'aggFunc' => 'avg',
                'maxDataPoints' => $maxPoints,
            ]
        );
        $jsonResult = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BackendException('Invalid JSON from TS backend: ' . json_last_error_msg());
        }
        if (!is_array($jsonResult)) {
            throw new BackendException('Invalid response from TS backend');
        }

        $ts_array = [];

        foreach($jsonResult as $res){
            $ts_array[$res["name"]] = $this->convertJsonToTs($res);
        }
        return $ts_array;
    }

    /**
     * Convert single graphite JSON result to a timeseries object
     * @param $res
     * @return TimeSeries
     */
    private function convertJsonToTs($res): TimeSeries {
        $newSeries = new TimeSeries();
        $from = new DateTime();
        $from->setTimestamp((int)$res['start']);
        $until = new DateTime();
        $until->setTimestamp((int)$res['end']);
        $newSeries->setFrom($from);
        $newSeries->setUntil($until);
        $newSeries->setStep($res['step']);
        $newSeries->setNativeStep(
            array_key_exists('nativeStep', $res) ?
                $res['nativeStep'] : $res['step']
        );
        $newSeries->setValues($res['values']);

        return $newSeries;
    }
}