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


class TopoService
{
    const DB_PATH_PFX = '/var/topojson';

    const ENTITY_TYPE_TO_DB = [
        "continent" => ["natural-earth", "ne_10m_admin_0.continents.v3.1.0"],
        "country" => ["natural-earth", "ne_10m_admin_0.countries.v3.1.0"],
        "region" => ["natural-earth", "ne_10m_admin_1.regions.v3.0.0"],
        "county" => ["gadm", "gadm.counties.v2.0"],
    ];

    const ENTITY_TYPE_TO_ID_FIELD = [
        "continent" => "usercode",
        "country" => "usercode",
        "region" => "id",
        "county" => "id",
    ];

    public function getTopoJson(string $entityType): array
    {
        if (!array_key_exists($entityType, self::ENTITY_TYPE_TO_DB)) {
            throw new \InvalidArgumentException("Invalid entity type '$entityType'");
        }
        // build the file path
        // TODO move this data to swift?
        $db = self::ENTITY_TYPE_TO_DB[$entityType][0];
        $table = self::ENTITY_TYPE_TO_DB[$entityType][1];
        $file = implode('/', [TopoService::DB_PATH_PFX, $db, $table]) . '.processed.topo.json';
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("Could not load TopoJson for $db/$table ($file)");
        }
        // TODO: if this is too slow, remove envelope and return file contents
        // TODO: directly to save the json decode/encode step
        return json_decode(file_get_contents($file), true);
    }
    public function getIdField(string $entityType): string
    {
        if (!array_key_exists($entityType, self::ENTITY_TYPE_TO_ID_FIELD)) {
            throw new \InvalidArgumentException("Invalid entity type '$entityType'");
        }
        return self::ENTITY_TYPE_TO_ID_FIELD[$entityType];
    }
}
