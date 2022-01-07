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
use App\Service\TopoService;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class TopoController
 * @package App\Controller
 * @Route("/topo", name="topo_")
 */
class TopoController extends ApiController
{
    /**
     * Get topographic database information
     *
     * Returns topographic data based on the specified entity type. The entity type must be one of the following:
     * continent, country, region, county. The topographic data returned can be used for plotting geo-location maps
     * for the frontend application. For example, the outages world map in the dashboard uses the country-level
     * topographic data.
     *
     * @Route("/{entityType}", methods={"GET"}, name="get")
     * @SWG\Tag(name="Topographic")
     * @SWG\Parameter(
     *     name="entityType",
     *     in="path",
     *     type="string",
     *     description="Type of the entity: continent, country, region, county",
     *     default=null
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns information about the given topographic database",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"topo.get"}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     type="object",
     *                     description="The corresponding topographic data",
     *                     @SWG\Property(
     *                         property="entityType",
     *                         type="string"
     *                     ),
     *                     @SWG\Property(
     *                         property="idField",
     *                         type="string"
     *                     ),
     *                     @SWG\Property(
     *                         property="topology",
     *                         type="object"
     *                     ),
     *                 )
     *             )
     *         }
     *     )
     * )
     * @var string $entityType
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var TopoService $topoService
     * @return JsonResponse
     */
    public function topoLookup(string $entityType, Request $request,
                          SerializerInterface $serializer,
                          TopoService $topoService)
    {
        $env = new Envelope('topo.get',
            'query',
            [],
            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }
        try {
            $env->setData([
                "entityType" => $entityType,
                "idField" => $topoService->getIdField($entityType),
                "topology" => $topoService->getTopoJson($entityType)
            ]);
        } catch (\InvalidArgumentException $ex) {
            $env->setError($ex->getMessage());
            return $this->json($env, 400);
        }
        return $this->json($env);
    }
}
