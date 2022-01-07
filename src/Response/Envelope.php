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

namespace App\Response;

use App\Utils\QueryTime;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Annotation\Groups;

class Envelope
{
    const COPYRIGHT = "This data is Copyright (c) 2013 The Regents of the University of California. All Rights Reserved.";

    /**
     * @Groups({"all", "public"})
     * @SWG\Property(type="string")
     */
    private $type;

    /**
     * @Groups({"all", "public"})
     * @SWG\Property(ref=@Model(type=EnvelopeMetadata::class, groups={"public"}))
     */
    private $metadata;

    /**
     * @Groups({"all", "public"})
     * @SWG\Property(
     *     type="object",
     *     additionalProperties={
     *         "type": "string"
     *     }
     * )
     */
    private $requestParameters;

    /**
     * @Groups({"all", "public"})
     */
    private $error;

    /**
     * @Groups({"all", "public"})
     * @SWG\Property(type="object")
     */
    private $perf;

    /**
     * @Groups({"all", "public"})
     * @SWG\Property(type="object")
     */
    private $data;

    /**
     * @SWG\Property(type="string")
     * @Groups({"public"})
     */
    private $copyright;

    /**
     * @return mixed
     */
    public function getCopyright()
    {
        return self::COPYRIGHT;
    }

    /**
     * @param mixed $copyright
     * @return void
     */
    public function setCopyright($copyright)
    {
        return;
    }


    /**
     * Envelope constructor.
     * @param string $type
     * @param string $paramsIn
     * @param RequestParameter[] $requestParameters
     * @param Request $request
     */
    public function __construct(string $type,
                                string $paramsIn,
                                array $requestParameters,
                                Request $request)
    {
        $this->setType($type);
        $this->requestParameters = [];
        try {
            $this->processRequestParameters($paramsIn, $requestParameters, $request);
        } catch (\InvalidArgumentException $ex) {
            $this->setError($ex->getMessage());
        }
        $this->setMetadata(
            new EnvelopeMetadata($request->server->get('REQUEST_TIME')));
    }

    public function getType(): string
    {
        return $this->type;
    }

    private function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getMetadata(): EnvelopeMetadata
    {
        return $this->metadata;
    }

    private function setMetadata(EnvelopeMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @param string $paramsIn
     * @param RequestParameter[] $requestParameters
     * @param Request $request
     */
    private function processRequestParameters(string $paramsIn,
                                              array $requestParameters,
                                              Request $request)
    {
        if ($paramsIn == 'body') {
            if ($request->getContentType() != 'json') {
                $this->setError("Request type must be json, not '".$request->getContentType()."'");
                return;
            }
            $content = $request->getContent();
            if (!$content) {
                $this->setError("Request body was empty");
                return;
            }
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->setError('Invalid JSON in request: ' . json_last_error_msg());
                return;
            }
            $bag = new ParameterBag($data);
        } else {
            $bag = $request->query;
        }
        foreach ($requestParameters as $p) {
            // if parameter wasn't given, either error out or return the default
            if (!$bag->has($p->name)) {
                if ($p->required) {
                    $this->setError("Missing required request parameter '" . $p->name . "'");
                    $this->setRequestParameter($p->name, null);
                } else {
                    $this->setRequestParameter($p->name, $p->default);
                }
                continue;
            }

            // we are sure the parameter was given, so just parse the
            // value according to the type
            if ($p->type == RequestParameter::STRING ||
                $p->type == RequestParameter::MIXED) {
                $parsedVal = $bag->get($p->name);
            } elseif ($p->type == RequestParameter::BOOL) {
                $parsedVal = $bag->get($p->name);
                // allow "&param" to be treated as true
                if ($parsedVal === "" || $parsedVal === null) {
                    $parsedVal = true;
                } else {
                    $parsedVal = $bag->getBoolean($p->name);
                }
            } elseif ($p->type == RequestParameter::INTEGER) {
                $parsedVal = $bag->getInt($p->name);
            } elseif ($p->type == RequestParameter::ARRAY) {
                $parsedVal = $bag->get($p->name);
                if (!is_array($parsedVal)) {
                    $parsedVal = [$parsedVal];
                }
            } elseif ($p->type == RequestParameter::DATETIME) {
                $dateStr = $bag->get($p->name);
                $parsedVal = new QueryTime($dateStr);
            } else {
                throw new \InvalidArgumentException("Unexpected parameter type $p->type");
            }

            $this->setRequestParameter($p->name, $parsedVal);
        }
    }

    public function getRequestParameters(): ?array
    {
        return $this->requestParameters;
    }

    private function setRequestParameter(string $name, $value): void
    {
        $this->requestParameters[$name] = $value;
    }

    public function getParam(string $param)
    {
        return $this->requestParameters[$param];
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setPerf($data){
        $this->perf = $data;
    }

    public function getPerf()
    {
        return $this->perf;
    }

    public function setError(string $error): void
    {
        $this->error = $error;
        $this->data = null;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): void
    {
        $this->data = $data;
        $this->error = null;
    }

}

/** Represents a parameter that can be provided in the request */
class RequestParameter
{
    const STRING = 'string';
    const BOOL = 'bool';
    const INTEGER = 'integer';
    const ARRAY = 'array';
    const MIXED = 'mixed';
    const DATETIME = 'date-time';
    // TODO: add 'expression' type (will need expression factory)

    const TYPES = [
        RequestParameter::STRING,
        RequestParameter::BOOL,
        RequestParameter::INTEGER,
        RequestParameter::ARRAY,
        RequestParameter::MIXED,
        RequestParameter::DATETIME,
    ];

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $type;

    /**
     * @var mixed
     */
    public $default;

    /**
     * @var boolean
     */
    public $required;

    public function __construct(string $name, string $type, $default, bool $required=false)
    {
        $this->name = $name;

        if (!in_array($type, RequestParameter::TYPES)) {
            throw new \InvalidArgumentException("Invalid parameter type");
        }
        $this->type = $type;
        $this->default = $default;
        $this->required = $required;
    }
}

class EnvelopeMetadata
{
    /**
     * @SWG\Property(type="string", format="date-time")
     * @Groups({"public"})
     */
    private $requestTime;

    public function __construct($requestTimeEpoch)
    {
        $this->setRequestTime($requestTimeEpoch);
    }

    public function getRequestTime(): string
    {
        return $this->requestTime;
    }

    public function setRequestTime(int $requestTime): void
    {
        $this->requestTime = gmdate('c', $requestTime);
    }

    /**
     * @SWG\Property(type="string", format="date-time")
     * @Groups({"public"})
     */
    public function getResponseTime(): string
    {
        return gmdate('c', time());
    }
}