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

namespace App\Controller;

use App\Response\Envelope;
use App\Service\DatasourceService;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class DatasourcesController
 * @package App\Controller
 * @Route("/datasources", name="datasources_")
 */
class DatasourcesController extends ApiController
{

    /**
     * Get all data sources.
     *
     * Return all data sources used in IODA.
     *
     * <h3>BGP (bgp)</h3>
     * <ul>
     *     <li>
     *         Data is obtained by processing <em>all updates</em> from <em>all Route Views and
     *         RIPE RIS collectors</em>.
     *     </li>
     *     <li>
     *         Every 5 minutes, we calculate the number of full-feed peers that
     *         observe each prefix. A peer is <em>full-feed</em> if it has more than 400k IPv4 prefixes, and/or more than 10k IPv6 prefixes (i.e., suggesting that it shares its entire routing table).
     *     </li>
     *     <li>
     *         A prefix is <em>visible</em> if more than 50% of the full-feed peers observe it.
     *         We aggregate prefix visibility statistics by country, region and ASN.
     *     </li>
     * </ul>
     *
     * <h3>Active Probing (ping-slash24)</h3>
     * <ul>
     *     <li>
     *         We use a custom implementation of the <a href="https://www.isi.edu/~johnh/PAPERS/Quan13c.html">Trinocular</a> technique.
     *     </li>
     *     <li>
     *         We probe ~4.2M /24 blocks at least once every 10 minutes (as opposed to 11 minutes used in the Trinocular paper).
     *     </li>
     *     <li>
     *         Currently the alerts IODA shows use data from a team of
     *         20 probers located at SDSC. (Alerts based on data from our
     *         distributed probers that run on the Ark platform are coming soon.)
     *     </li>
     *     <li>
     *         The trinocular measurement and inference technique labels a /24 block as <em>up</em>,
     *         <em>down</em>, or <em>unknown</em>.
     *         In addition, we then aggregate <em>up</em> /24s into country, region and ASN
     *         statistics.
     *     </li>
     * </ul>
     *
     * <h3>Network Telescope (ucsd-nt)</h3>
     * <ul>
     *     <li>
     *         We analyze traffic data from both the <a href=https://www.caida.org/projects/network_telescope/>UCSD</a> and <a href=https://www.merit.edu/a-data-repository-for-cyber-security-research-and-education/>Merit</a> Network Telescopes.
     *         (Currently IODA uses only data from the UCSD Telescope for generating alerts.)
     *     </li>
     *     <li>
     *         We apply <a href=http://www.caida.org/publications/papers/2014/passive_ip_space_usage_estimation/>anti-spoofing heuristics and noise reduction filters</a> to the
     *         raw traffic.
     *     </li>
     *     <li>
     *            For each packet that passes the filters, we perform geolocation (using the Netacuity IP geolocation DB) and ASN lookups on the source IP address,
     *            and then compute the <em>number of unique source IPs per minute</em>, aggregated by  country, region, and ASN.
     *     </li>
     * </ul>
     *
     * @Route("/", methods={"GET"}, name="getall")
     * @SWG\Tag(name="Data Sources")
     * @SWG\Response(
     *     response=200,
     *     description="Return data source matched by the lookup term",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"datasources.lookup"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     ref=@Model(type=\App\Entity\DatasourceEntity::class, groups={"public"})
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     *
     * @var Request $request
     * @param DatasourceService $datasourceService
     * @return JsonResponse
     */
    public function datasourcesAll(Request $request, DatasourceService $datasourceService)
    {
        $env = new Envelope('datasources',
            'query',
            [],
            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }

        $env->setData($datasourceService->getAllDatasources());
        return $this->json($env);
    }

    /**
     * Get one data source by name
     *
     * Return a single data source used in IODA by data source name.
     *
     * @Route("/{datasource}", methods={"GET"}, name="findone")
     * @SWG\Tag(name="Data Sources")
     * @SWG\Parameter(
     *     name="datasource",
     *     in="path",
     *     type="string",
     *     description="Shortname of the data source: bgp, ucsd-nt, ping-slash24",
     *     default=null
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Return data source matched by the lookup term",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"datasources.lookup"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     ref=@Model(type=\App\Entity\DatasourceEntity::class, groups={"public"})
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     *
     *
     * @var string $datasource
     * @var Request $request
     * @var DatasourceService $datasourceService
     * @return JsonResponse
     */
    public function datasourceSingle(string $datasource, Request $request, DatasourceService $datasourceService)
    {
        $env = new Envelope('datasources',
            'query',
            [],
            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }

        try {
            $env->setData($datasourceService->getDatasource($datasource));
        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }
        return $this->json($env);
    }
}
