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

namespace App\Controller;

use App\Service\MetadataEntitiesService;
use App\Response\Envelope;
use App\Response\RequestParameter;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\MetadataEntity;
use App\Entity\MetadataEntityType;


/**
 * Class EntitiesController
 * @package App\Controller
 * @Route("/entities", name="entities_")
 */
class EntitiesController extends ApiController
{
    /**
     * Lookup metadata entities
     *
     * Returns a JSON object with metadata for the searched entities.
     *
     * <h2> Usage examples </h2>
     *
     * To get information about United States, use the following query:
     * <pre>/entities/?entityType=country&entityCode=US</pre>
     *
     * </br>
     *
     * To search for entities whose name contains the word "united", use the following query
     * <pre>/entities?search=united</pre>
     * To narrow down the previous search for only the countries, use the following query
     * <pre>/entities/?entityType=country&search=united</pre>
     *
     * </br>
     *
     * For more advanced search, you can also use the <b>relatedTo</b> parameter. It takes a entity type and code
     * separated by <b>/</b>.
     * For example, to search for all ASes operate in New Zealand, use the following query:
     * <pre><code>/entities/?entityType=asn&relatedTo=country/NZ</code></pre>
     *
     * @Route("/", methods={"GET"}, name="get")
     * @SWG\Tag(name="Metadata Entities")
     * @SWG\Parameter(
     *     name="entityType",
     *     in="query",
     *     type="string",
     *     description="Type of the entity, e.g. country, region, asn",
     *     default=null,
     *     required=false
     * )
     * @SWG\Parameter(
     *     name="entityCode",
     *     in="query",
     *     type="string",
     *     description="Code of the entity, e.g. for United States the code is 'US'; use comma ',' to separate multiple codes",
     *     required=false,
     *     default=null
     * )
     * @SWG\Parameter(
     *     name="relatedTo",
     *     in="query",
     *     type="string",
     *     description="Find entities related to another entity. Format: entityType[/entityCode]",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="search",
     *     in="query",
     *     type="string",
     *     description="Search entities with name that matches the search term",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="limit",
     *     in="query",
     *     type="integer",
     *     description="maximum number of entities to return",
     *     required=false,
     * )
     * @SWG\Parameter(
     *     name="page",
     *     in="query",
     *     type="integer",
     *     description="specify the page number of the returned entities",
     *     required=false,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Return an array of metadata entities",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"sym.get"}
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
     *                          ref=@Model(type=\App\Entity\MetadataEntity::class, groups={"public"})
     *                     )
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var MetadataEntitiesService
     * @return JsonResponse
     */
    public function lookup(
        Request $request,
        SerializerInterface $serializer,
        MetadataEntitiesService $service
    ){
        $env = new Envelope('entities.lookup',
            'query',
            [
                new RequestParameter('relatedTo', RequestParameter::STRING, null, false),
                new RequestParameter('search', RequestParameter::STRING, null, false),
                new RequestParameter('limit', RequestParameter::INTEGER, null, false),
                new RequestParameter('page', RequestParameter::INTEGER, null, false),
                new RequestParameter('entityType', RequestParameter::STRING, null, false),
                new RequestParameter('entityCode', RequestParameter::STRING, null, false),
            ],
            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }

        /* LOCAL PARAM PARSING */
        $search = $env->getParam('search');
        $relatedTo = $env->getParam('relatedTo');
        $limit = $env->getParam('limit');
        $page = $env->getParam('page');
	$entityType = $env->getParam('entityType');
	$entityCode = $env->getParam('entityCode');
	/*
        if($search){
            $entity = $service->search($entityType, null, $search, $limit, true);
            $env->setData($entity);
            return $this->json($env);
        }
        */
        try {
            if(!empty($entityCode) && (!empty($search)||!empty($relatedTo))){
                // both entity type and code are provided, there is no search available
                throw new \InvalidArgumentException(
                    "entity type and code provided, no search or relatedTo can be used"
                );
            }

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

        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }
        $entity = $service->search($entityType, $entityCode, $search, $limit, $page, true, $relatedTo[0], $relatedTo[1]);
        // $entity = $service->lookup($entityType, $entityCode, $relatedTo[0], $relatedTo[1], $limit);

        /* Mildly dirty hack added by Shane to provide a "default"
         * name for ASNs which don't have entries in the AS2org
         * dataset. This is a much better alternative than just erroring
         * when we don't have usable metadata.
         *
         * Note: the ID numbers here are irrelevant and not used at all.
         * They're normally sequenced IDs from the metadata rows in the
         * postgres database, but I'm just making sure they are set to
         * something in case some other code requires them to be set.
         */
        if ($entity == null && $entityType == "asn") {
            $defaultAsn = new MetadataEntity();
            $defaultAsnMetatype = new MetadataEntityType();

            $defaultAsnMetatype->setId(8);
            $defaultAsnMetatype->setType("asn");
            $defaultAsn->setCode($entityCode);
            $defaultAsn->setName(sprintf("AS%d", $entityCode));
            $defaultAsn->setId(1001);
            $defaultAsn->setType($defaultAsnMetatype);
            $defaultAsn->setFQID(sprintf("asn.%d", $entityCode));
            $defaultAsn->setIpCount(0);

            $entity = [$defaultAsn];
        }

        $env->setData($entity);
        return $this->json($env);
    }
}
