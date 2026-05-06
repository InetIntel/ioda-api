<?php
/*
# This source code is Copyright (c) 2026 Georgia Tech Research Corporation. All
# Rights Reserved. Permission to copy, modify, and distribute this software and
# its documentation for academic research and education purposes, without fee,
# and without a written agreement is hereby granted, provided that the above
# copyright notice, this paragraph and the following three paragraphs appear in
# all copies. Permission to make use of this software for other than academic
# research and education purposes may be obtained by contacting:
#
#  Office of Technology Licensing
#  Georgia Institute of Technology
#  926 Dalney Street, NW
#  Atlanta, GA 30318
#  404.385.8066
#  techlicensing@gtrc.gatech.edu
#
# This software program and documentation are copyrighted by Georgia Tech
# Research Corporation (GTRC). The software program and documentation are
# supplied "as is", without any accompanying services from GTRC. GTRC does
# not warrant that the operation of the program will be uninterrupted or
# error-free. The end-user understands that the program was developed for
# research purposes and is advised not to rely exclusively on the program for
# any reason.
#
# IN NO EVENT SHALL GEORGIA TECH RESEARCH CORPORATION BE LIABLE TO ANY PARTY FOR
# DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES, INCLUDING
# LOST PROFITS, ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION,
# EVEN IF GEORGIA TECH RESEARCH CORPORATION HAS BEEN ADVISED OF THE POSSIBILITY
# OF SUCH DAMAGE. GEORGIA TECH RESEARCH CORPORATION SPECIFICALLY DISCLAIMS ANY
# WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
# MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE SOFTWARE PROVIDED
# HEREUNDER IS ON AN "AS IS" BASIS, AND  GEORGIA TECH RESEARCH CORPORATION HAS
# NO OBLIGATIONS TO PROVIDE MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR
# MODIFICATIONS.
#
# This source code is part of the IODA software. The original IODA software is
# Copyright (c) 2013 The Regents of the University of California. All rights
# reserved. Permission to copy, modify, and distribute this software for
# academic research and education purposes is subject to the conditions and
# copyright notices in the source code files and in the included LICENSE file.
*/

namespace App\OpenApi;

use App\Service\DatasourceService;
use Nelmio\ApiDocBundle\Describer\DescriberInterface;
use OpenApi\Annotations\OpenApi;

class DatasourceEnumDescriber implements DescriberInterface
{
    private $datasourceService;

    public function __construct(DatasourceService $datasourceService)
    {
        $this->datasourceService = $datasourceService;
    }

    public function describe(OpenApi $api): void
    {
        if (is_string($api->paths) || !is_iterable($api->paths)) {
            return;
        }

        $path = null;
        foreach ($api->paths as $pathItem) {
            if ($pathItem->path === '/signals/raw/{entityType}/{entityCode}') {
                $path = $pathItem;
                break;
            }
        }

        if (!$path) {
            return;
        }

        // Fetch the dynamic list of currently active datasource names
        $activeDatasources = $this->datasourceService->getDatasourceNames();

        // Find the 'datasource' parameter and dynamically update its enum
        // schema
        $getOperation = $path->get;
        if ($getOperation && !is_string($getOperation) && isset($getOperation->parameters) && is_array($getOperation->parameters)) {
            foreach ($getOperation->parameters as $parameter) {
                if ($parameter->name === 'datasource') {
                    // Update schema enum with dynamic list
                    $parameter->schema->enum = $activeDatasources;
                    break;
                }
            }
        }
    }
}

