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

use Swagger\Annotations as SWG;
use App\Service\OutagesAlertsService;
use App\Service\DatasourceService;
use App\Service\OutagesEventsService;
use App\Response\Envelope;
use App\Response\RequestParameter;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class OutagesController
 * @package App\Controller
 * @Route("/outages", name="outages_")
 */
class OutagesController extends ApiController
{
    private function parseTimestampParameter($text){
        if (ctype_digit($text)) { // text is positive integer
            if ($text <= '2147483647') { // for sql Integer type
                return intval($text);
            }
        }
        return null;
    }

    private $datasourceService;
    private $metadataEntitiesService;

    public function __construct(DatasourceService $datasourceService){
        $this->datasourceService = $datasourceService;
    }

    private function parseRelatedTo(?string $relatedTo){
        // parse relatedTo parameter to entity type and code
        if ($relatedTo) {
            // sanity-checking related field
            $relatedTo = explode('/', $relatedTo);
            if (count($relatedTo) > 2) {
                throw new \InvalidArgumentException(
                    "relatedTo parameter must be in the form 'type[/code]'"
                );
            }
            if (count($relatedTo) == 1) {
                $relatedTo[] = null;
            }
        } else {
            $relatedTo = [null, null];
        }

        return $relatedTo;
    }

    private function parseOrderBy(?string $orderBy, string $type){
        // parse relatedTo parameter to entity type and code
        if ($orderBy) {
            // sanity-checking related field
            $orderBy = explode('/', $orderBy);
            if (count($orderBy) > 2) {
                throw new \InvalidArgumentException(
                    "orderBy parameter must be in the form 'attr[/[asc|desc]]'"
                );
            }
            if (count($orderBy) == 1) {
                $orderBy[] = "asc";
            }
            if (!in_array($orderBy[0], ["time", "score", "name"])){
                throw new \InvalidArgumentException(
                    "orderBy attribute must be in ['time', 'score', 'name']"
                );
            }
            if (!in_array($orderBy[1], ["asc","desc"])){
                throw new \InvalidArgumentException(
                    "orderBy order must be asc or desc"
                );
            }

        } else {
            if($type=="events"){
                $orderBy = ["time", "asc"];
            } else if ($type == "summary"){
                $orderBy = ["score", "desc"];
            } else {
                $orderBy = [null, null];
            }
        }
        return $orderBy;
    }

    private function sanitizeInputs($from, $until, $datasource, $format, $limit, $page){
        if(!isset($from)){
            throw new \InvalidArgumentException(
                "'from' timestamp must be set"
            );
        }

        if(!isset($until)){
            throw new \InvalidArgumentException(
                "'until' timestamp must be set"
            );
        }
        if ($datasource && !$this->datasourceService->isValidDatasource($datasource)){
            throw new \InvalidArgumentException(
                sprintf("invalid datasource %s (must be one of the following [%s])", $datasource, join(", ", $this->datasourceService->getDatasourceNames()))
            );
        }

        if ($format && !in_array($format, ["codf", "ioda"])) {
            throw new \InvalidArgumentException(
                sprintf("invalid format %s (must be one of the following ['codf', 'ioda'])", $format)
            );
        }

        if(isset($limit) && $limit<=0){
            throw new \InvalidArgumentException(
                sprintf("limit must be greater than 0")
            );
        }

        if(isset($page)){
            if(!$limit){
                throw new \InvalidArgumentException(
                    sprintf("limit also needs to be set if page is set")
                );
            }
            if($page<0){
                throw new \InvalidArgumentException(
                    sprintf("page must be greater than or equals to 0")
                );
            }
        }
    }

    /**
     * Retrieve IODA outage alerts.
     *
     *
     * <p>
     * The Alert API allows querying IODA's outage <em>alerts</em>.
     * </p>
     *
     * <br/>
     *
     * <p>
     * Outage alerts are generated by IODA using three data sources -— UCSD
     * Network Telescope (ucsd-nt), BGP (bgp), and active probing
     * (ping-slash24). For more information about the data sources see the
     * datasources API documentation. Alerts are generated by IODA when there is
     * a significant drop (or subsequent increase) in the timeseries of an IODA
     * data source for a group of addresses (such as addresses belonging to the
     * same geographic region or Autonomous System).
     * </p>
     *
     * <br/>
     *
     * <p>
     * IODA’s Outages Alert API has been designed to allow users to query for
     * subsets of outage alerts that users are interested in. Instead of
     * retrieving all outage alerts that IODA has ever generated, users will
     * typically be interested in specific subsets of alerts. For instance,
     * users may seek only the alerts that affect a specific country or
     * Autonomous System, or alerts that were generated during specific periods.
     * In fact, we highly recommend that users use the available filter
     * parameters to limit the number of results that are returned by the API.
     * </p>
     *
     * <br/>
     *
     * <p>
     * <em>Most users' use-cases will be addressed by the event API since this
     * API returns outage data that has been processed to remove some sources of
     * noise from the underlying alerts that IODA generates. However, the event
     * API itself internally uses the alert API. </em>
     * </p>
     *
     * <h2> Usage Examples </h2>
     *
     * <h3>Finding Alerts By Time</h3>
     *
     * <p>
     * The Alert API allows a user to specify the time range in which they are
     * interested, using the from and until parameters. The response will
     * include alerts that were generated during the user-specified time range.
     * The from and until parameters should both be provided in Unix Epoch
     * seconds.
     * </p>
     *
     * <p>
     * For example, the following GET request will return all alerts generated
     * by IODA between 10:00:00 to 11:00:00 on Jan 10, 2019:
     * </p>
     * <pre> /outages/alerts?from=1547114400&until=1547118000 </pre>
     *
     * <h3> Finding Alerts By Geographic Region or ASN </h3>
     *
     * <p>
     * Users can specify the geographic region or Autonomous System they seek
     * using the meta parameter and the API will return the relevant subset of
     * alerts. Each alert generated by IODA includes a meta field that indicates
     * the group of addresses that the alert affects: the group may be addresses
     * geolocating to a country, addresses geolocating to a region within a
     * country, or addresses belonging to a specific Autonomous System.
     * </p>
     *
     * <p>
     * For example, the following GET request will return alerts generated in
     * the U.S. between 10:00:00 to 11:00:00 on Jan 10, 2019:
     * </p>
     * <pre>/outages/alerts/country/US?from=1547114400&until=1547118000</pre>
     *
     * </p>
     * The following GET request will return alerts generated in AS 3307 between
     * 10:00:00 to 11:00:00 on Jan 10, 2019::
     * </p>
     * <pre>/outages/alerts/asn/3307?from=1547114400&until=1547118000</pre>
     *
     * @Route("/alerts/{entityType}/{entityCode}", methods={"GET"}, name="alerts", defaults={"entityType"=null,"entityCode"=null})
     * @SWG\Tag(name="Outages")
     * @SWG\Parameter(
     *     name="entityType",
     *     in="path",
     *     type="string",
     *     description="Type of the entity, e.g. country, region, asn",
     *     enum={"continent", "country", "region", "county", "asn"},
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="entityCode",
     *     in="path",
     *     type="string",
     *     description="Code of the entity, e.g. for United States the code is 'US'; use comma ',' to separate multiple codes",
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="from",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp from when the alerts should begin after",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="until",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp until when the alerts should end before",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="datasource",
     *     in="query",
     *     type="string",
     *     description="Filter alerts by datasource",
     *     enum={"bgp", "ucsd-nt", "ping-slash24"},
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     in="query",
     *     type="integer",
     *     description="Maximum number of alerts this query returns",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="page",
     *     in="query",
     *     type="integer",
     *     description="Page number of the alerts",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="relatedTo",
     *     in="query",
     *     type="string",
     *     description="Find data related to another entity. Format: entityType[/entityCode]",
     *     required=false,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Return an array of all data sources used by IODA",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"outages.alerts"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     type="array",
     *                     @SWG\Items(
     *                          @SWG\Property(
     *                              property="datasource",
     *                              type="string"
     *                          ),
     *                          @SWG\Property(
     *                              property="entity",
     *                              ref=@Model(type=\App\Entity\MetadataEntity::class, groups={"public"})
     *                          ),
     *                          @SWG\Property(
     *                              property="time",
     *                              type="integer",
     *                          ),
     *                          @SWG\Property(
     *                              property="level",
     *                              type="string",
     *                          ),
     *                          @SWG\Property(
     *                              property="condition",
     *                              type="string",
     *                          ),
     *                          @SWG\Property(
     *                              property="value",
     *                              type="integer",
     *                          ),
     *                          @SWG\Property(
     *                              property="historicValue",
     *                              type="integer",
     *                          )
     *                     )
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     * @var string|null $entityType
     * @var string|null $entityCode
     * @var Request $request
     * @var SerializerInterface $serializer
     * @return JsonResponse
     */
    public function alerts(
        ?string $entityType, ?string $entityCode,
        Request $request,
        SerializerInterface $serializer,
        OutagesAlertsService $alertService
    ){
        $env = new Envelope('outages.alerts',
            'query',
            [
                new RequestParameter('from', RequestParameter::STRING, null, true),
                new RequestParameter('until', RequestParameter::STRING, null, true),
                new RequestParameter('datasource', RequestParameter::STRING, null, false),
                new RequestParameter('limit', RequestParameter::INTEGER, null, false),
                new RequestParameter('page', RequestParameter::INTEGER, null, false),
                new RequestParameter('relatedTo', RequestParameter::STRING, null, false),
            ],
            $request
        );

        /* LOCAL PARAM PARSING */
        $from = $this->parseTimestampParameter($env->getParam('from'));
        $until = $this->parseTimestampParameter($env->getParam('until'));
        $datasource = $env->getParam('datasource');
        $limit = $env->getParam('limit');
        $page = $env->getParam('page');
        $relatedTo = $this->parseRelatedTo($env->getParam('relatedTo'));

        /* SANTIY CHECKS */
        try {
            $this->sanitizeInputs($from, $until, $datasource, null, $limit, $page);
        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }

        $alerts = $alertService->findAlerts($from, $until, $entityType, $entityCode, $datasource, $limit, $page, $relatedTo[0], $relatedTo[1]);

        $env->setData($alerts);
        return $this->json($env);
    }

    /**
     * Retrieve IODA outage events.
     *
     * <p>
     * IODA's Event API allows users to query for outage events detected by IODA.
     * An outage event is an instance of a macroscopic Internet outage affecting
     * the edge of the network. Each event has well-defined fields, such as the
     * outage's start-time, duration and other fields, as defined in the
     * <a href="https://www.isi.edu/publications/trpublic/pdfs/isi-tr-729.pdf"> Common Outage Data Format </a>.
     * </p>
     *
     * <br/>
     *
     * <p>
     * IODA’s Event API has been designed to allow users to query for subsets of
     * outage events that users are interested in. Instead of retrieving all
     * outage events that IODA has ever detected, users will typically be
     * interested in specific subsets of events. For instance, users may seek
     * only the events that affect a specific country or Autonomous System, or
     * events that were detected during specific periods. In fact, we highly
     * recommend that users use the available filter parameters to limit the
     * number of results that are returned by the API.
     * </p>
     *
     * <h2> Usage Examples </h2>
     *
     * <h3>  Finding Events By Time  </h3>
     *
     * <p>
     * The Event API allows a user to specify the time range in which they are
     * interested, using the <b>from</b> and <b>until</b> parameters. The response will
     * include events that were detected during the user-specified time range
     * (we discuss the set of returned events in further detail, along with edge
     * cases, below). The <b>from</b> and <b>until</b> parameters should both be provided in
     * Unix Epoch seconds.
     * </p>
     *
     * <p>
     * For example, the following GET request will return all events detected by
     * IODA between 20:00:00 to 23:00:00 on June 26, 2019:
     * <pre>/outages/events?from=1561579200&until=1561590000</pre>
     * </p>
     *
     * <h3> Finding Events By Region </h3>
     *
     * <p>
     * The following GET request will return events detected in the U.S.
     * between 10:00:00 to 11:00:00 on Jan 10, 2019:
     * <pre>/outages/events/country/US?from=1561579200&until=1561590000</pre>
     * </p>
     *
     * <h3> Finding Events By ASN </h3>
     *
     * <p>
     * The following GET request will return events detected in AS 3307.
     * between 10:00:00 to 11:00:00 on Jan 10, 2019:
     * <pre>/outages/events/asn/3307?from=1561579200&until=1561590000</pre>
     * </p>
     *
     * @Route("/events/{entityType}/{entityCode}", methods={"GET"}, name="events", defaults={"entityType"=null,"entityCode"=null})
     * @SWG\Tag(name="Outages")
     * @SWG\Parameter(
     *     name="entityType",
     *     in="path",
     *     type="string",
     *     description="Type of the entity, e.g. country, region, asn",
     *     enum={"continent", "country", "region", "county", "asn"},
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="entityCode",
     *     in="path",
     *     type="string",
     *     description="Code of the entity, e.g. for United States the code is 'US'; use comma ',' to separate multiple codes",
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="from",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp from when the alerts should begin after",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="until",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp until when the alerts should end before",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="includeAlerts",
     *     in="query",
     *     type="boolean",
     *     description="Whether to include alerts in the returned events",
     *     default=false,
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="format",
     *     in="path",
     *     type="string",
     *     description="Returned event object format",
     *     enum={"codf", "ioda"},
     *     required=false,
     *     default="codf"
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     in="query",
     *     type="integer",
     *     description="Maximum number of events this query returns",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="page",
     *     in="query",
     *     type="integer",
     *     description="Page number of the events",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="relatedTo",
     *     in="query",
     *     type="string",
     *     description="Find data related to another entity. Format: entityType[/entityCode]",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="orderBy",
     *     in="query",
     *     type="string",
     *     description="Ordering summary by field. Format: attr[/[asc|desc]]",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="overall",
     *     in="query",
     *     type="boolean",
     *     description="Merge events across different data sources",
     *     default=false,
     *     required=false
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Return an array of outage events generated by IODA",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"outages.alerts"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     type="array",
     *                     @SWG\Items(
     *                          @SWG\Property(
     *                              property="location",
     *                              type="string"
     *                          ),
     *                          @SWG\Property(
     *                              property="location_name",
     *                              type="string"
     *                          ),
     *                          @SWG\Property(
     *                              property="start",
     *                              type="integer",
     *                          ),
     *                          @SWG\Property(
     *                              property="duration",
     *                              type="integer",
     *                          ),
     *                          @SWG\Property(
     *                              property="uncertainty",
     *                              type="string",
     *                          ),
     *                          @SWG\Property(
     *                              property="status",
     *                              type="integer",
     *                          ),
     *                          @SWG\Property(
     *                              property="fraction",
     *                              type="string",
     *                          ),
     *                          @SWG\Property(
     *                              property="score",
     *                              type="float",
     *                          ),
     *                          @SWG\Property(
     *                              property="overlaps_window",
     *                              type="boolean",
     *                          )
     *                     )
     *                 )
     *             )
     *         }
     *     )
     * )
     * TODO: figure out how to document ioda format here as well
     *
     * @var string|null $entityType
     * @var string|null $entityCode
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var OutagesEventsService
     * @return JsonResponse
     */
    public function events(
        ?string $entityType, ?string $entityCode,
        Request $request,
        SerializerInterface $serializer,
        OutagesEventsService $eventsService,
        OutagesAlertsService $alertsService
    ){
        $env = new Envelope('outages.events',
            'query',
            [
                new RequestParameter('from', RequestParameter::STRING, null, true),
                new RequestParameter('until', RequestParameter::STRING, null, true),
                new RequestParameter('datasource', RequestParameter::STRING, null, false),
                new RequestParameter('includeAlerts', RequestParameter::BOOL, false, false),
                new RequestParameter('format', RequestParameter::STRING, "codf", false),
                new RequestParameter('limit', RequestParameter::INTEGER, null, false),
                new RequestParameter('page', RequestParameter::INTEGER, null, false),
                new RequestParameter('relatedTo', RequestParameter::STRING, null, false),
                new RequestParameter('orderBy', RequestParameter::STRING, null, false),
                new RequestParameter('overall', RequestParameter::BOOL, false, false),
            ],
            $request
        );

        /* LOCAL PARAM PARSING */
        $from = $this->parseTimestampParameter($env->getParam('from'));
        $until = $this->parseTimestampParameter($env->getParam('until'));
        $datasource = $env->getParam('datasource');
        $includeAlerts = $env->getParam('includeAlerts');
        $format = $env->getParam('format');
        $limit = $env->getParam('limit');
        $page = $env->getParam('page');
        $orderBy = $this->parseOrderBy($env->getParam('orderBy'), "events");
        $relatedTo = $this->parseRelatedTo($env->getParam('relatedTo'));
        $overall = $env->getParam('overall');

        // sanitize user inputs
        try {
            $this->sanitizeInputs($from, $until, $datasource, $format, $limit, $page);
        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }

        // build events
        $alerts = $alertsService->findAlerts($from, $until, $entityType, $entityCode, $datasource, false, null, $relatedTo[0], $relatedTo[1]);
        $events = $eventsService->buildEventsObjects($alerts, $includeAlerts, $format, $from, $until, $limit, $overall, $page, $orderBy[0], $orderBy[1]);

        $env->setData($events);
        return $this->json($env);
    }

    /**
     * Retrieve IODA outage summaries.
     *
     * <p>
     * IODA's summary API returns summarized outages report over a given time range.
     * </p>
     *
     * <br/>
     *
     * <p>
     * For a given time range, the summary API aggregates all detected events by the entity (e.g. an asn or a country)
     * and calculate the overall score and the sum of the individual scores from different data sources during the time.
     * </p>
     *
     * <br/>
     *
     * <p>
     * To calculate the overall score, we first merge overlapping events from different sources. The score of an merged
     * event is the multiplication of all the socres of the overlapping events to be merged. The overall score reported
     * in summary is the sum of the scores for all merged events.
     * <p>
     *
     * @Route("/summary/{entityType}/{entityCode}", methods={"GET"}, name="summary", defaults={"entityType"=null,"entityCode"=null})
     * @SWG\Tag(name="Outages")
     * @SWG\Parameter(
     *     name="entityType",
     *     in="path",
     *     type="string",
     *     description="Type of the entity, e.g. country, region, asn",
     *     enum={"continent", "country", "region", "county", "asn"},
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="entityCode",
     *     in="path",
     *     type="string",
     *     description="Code of the entity, e.g. for United States the code is 'US'; use comma ',' to separate multiple codes",
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="from",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp from when the alerts should begin after",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="until",
     *     in="query",
     *     type="string",
     *     description="Unix timestamp until when the alerts should end before",
     *     required=true
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     in="query",
     *     type="integer",
     *     description="Maximum number of entity summaries this query returns",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="page",
     *     in="query",
     *     type="integer",
     *     description="Page number of the summaries",
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="relatedTo",
     *     in="query",
     *     type="string",
     *     description="Find data related to another entity. Format: entityType[/entityCode]",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="orderBy",
     *     in="query",
     *     type="string",
     *     description="Ordering summary by field. Format: attr[/[asc|desc]]",
     *     required=false,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Return an array of outage summaries for entities generated by IODA",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"outages.alerts"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     type="array",
     *                     @SWG\Items(
     *                          @SWG\Property(
     *                              property="scores",
     *                              type="object",
     *                              @SWG\Property(
     *                                  property="overall",
     *                                  type="number"
     *                              ),
     *                              @SWG\Property(
     *                                  property="bgp",
     *                                  type="number"
     *                              ),
     *                              @SWG\Property(
     *                                  property="ucsd-nt",
     *                                  type="number"
     *                              ),
     *                              @SWG\Property(
     *                                  property="ping-slash24",
     *                                  type="number"
     *                              ),
     *                          ),
     *                          @SWG\Property(
     *                              property="event_cnt",
     *                              type="number"
     *                          ),
     *                          @SWG\Property(
     *                              property="entity",
     *                              ref=@Model(type=\App\Entity\MetadataEntity::class, groups={"public"})
     *                          )
     *                     )
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     * @var string|null $entityType
     * @var string|null $entityCode
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var OutagesEventsService
     * @return JsonResponse
     */
    public function summary(
        ?string $entityType, ?string $entityCode,
        Request $request,
        SerializerInterface $serializer,
        OutagesEventsService $eventsService,
        OutagesAlertsService $alertsService
    ){
        $env = new Envelope('outages.summary',
            'query',
            [
                new RequestParameter('from', RequestParameter::STRING, null, true),
                new RequestParameter('until', RequestParameter::STRING, null, true),
                new RequestParameter('limit', RequestParameter::INTEGER, null, false),
                new RequestParameter('page', RequestParameter::INTEGER, null, false),
                new RequestParameter('relatedTo', RequestParameter::STRING, null, false),
                new RequestParameter('orderBy', RequestParameter::STRING, null, false),
            ],
            $request
        );

        /* LOCAL PARAM PARSING */
        $from = $this->parseTimestampParameter($env->getParam('from'));
        $until = $this->parseTimestampParameter($env->getParam('until'));
        $limit = $env->getParam('limit');
        $page = $env->getParam('page');
        $orderBy = $this->parseOrderBy($env->getParam('orderBy'), "summary");
        $relatedTo = $this->parseRelatedTo($env->getParam('relatedTo'));

        try {
            $this->sanitizeInputs($from, $until, null, null, $limit, $page);
        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }

        $alerts = $alertsService->findAlerts($from, $until, $entityType, $entityCode, null, null, null, $relatedTo[0], $relatedTo[1]);
        $summaries = $eventsService->buildEventsSummary($alerts, $from, $until, $limit, $page, $orderBy[0], $orderBy[1]);

        $env->setData($summaries);
        return $this->json($env);
    }
}
