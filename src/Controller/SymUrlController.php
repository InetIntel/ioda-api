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
use App\Response\RequestParameter;
use App\Service\SymUrlService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class SymUrlController
 * @package App\Controller
 * @Route("/sym", name="sym_")
 */
class SymUrlController extends ApiController
{
    /**
     * Expand an existing short URL
     *
     * Returns a JSON object with metadata about the given short URL. Note that
     * this also updates last-used times and counters unless the "no_stats"
     * parameter is provided.
     *
     * @Route("/{short}/", methods={"GET"}, name="get")
     * @SWG\Tag(name="URL Shortener")
     * @SWG\Parameter(
     *     name="noStats",
     *     in="query",
     *     type="boolean",
     *     description="Do not update usage stats (counter, last-used time, etc.)",
     *     required=false,
     *     default=false,
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns information for the given short URL tag",
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
     *                     ref=@Model(type=\App\Entity\SymUrl::class, groups={"public"})
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     * @var string $short
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var SymUrlService
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getShortUrl(string $short, Request $request,
                            SerializerInterface $serializer,
                            SymUrlService $symUrlService)
    {
        $env = new Envelope('sym.get',
                            'query',
                            [
                                new RequestParameter('noStats', RequestParameter::BOOL, false, false),
                            ],
                            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }
        $env->setData($symUrlService->getExisting($short,
                                                  !$env->getParam('noStats')));
        return $this->json($env);
    }

    /**
     * Create a new short URL
     *
     * Creates a new short URL that maps to the given long URL. If there is
     * already a short URL for the given long URL, the existing short URL is
     * returned, otherwise a new short URL is created. If the "short_url"
     * parameter is provided, then it will be used instead of an automatically
     * generated URL unless it already exists, in which case an error will be
     * returned.
     *
     * @Route("/", methods={"POST"}, name="new")
     * @SWG\Tag(name="URL Shortener")
     * @SWG\Parameter(
     *     name="query",
     *     in="body",
     *     type="object",
     *     description="Object describing the URL to be shortened",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(
     *                     property="longUrl",
     *                     type="string",
     *                     example="https://hicube.caida.org",
     *                     description="Long URL to be shortened"
     *         ),
     *         @SWG\Property(
     *                     property="shortTag",
     *                     type="string",
     *                     example="myurl",
     *                     description="Short tag to use instead of auto-generated tag [optional]"
     *         )
     *     )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns information about the newly created short URL",
     *     @SWG\Schema(
     *         allOf={
     *             @SWG\Schema(ref=@Model(type=Envelope::class, groups={"public"})),
     *             @SWG\Schema(
     *                 @SWG\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"sym.post"}
     *                 ),
     *                 @SWG\Property(
     *                     property="error",
     *                     type="string",
     *                     enum={}
     *                 ),
     *                 @SWG\Property(
     *                     property="data",
     *                     ref=@Model(type=\App\Entity\SymUrl::class, groups={"public"})
     *                 )
     *             )
     *         }
     *     )
     * )
     *
     * @var Request $request
     * @var SerializerInterface $serializer
     * @var SymUrlService
     * @return JsonResponse
     * @throws ORMException
     */
    public function post(Request $request,
                        SerializerInterface $serializer,
                        SymUrlService $symUrlService)
    {
        $env = new Envelope('sym.post',
                            'body',
                            [
                                new RequestParameter('longUrl', RequestParameter::STRING, null, true),
                                new RequestParameter('shortTag', RequestParameter::STRING, null, false),
                            ],
                            $request
        );
        if ($env->getError()) {
            return $this->json($env, 400);
        }
        $env->setData($symUrlService->createOrGet($env->getParam('longUrl'),
                                                  $env->getParam('shortTag')));
        return $this->json($env);
    }
}
