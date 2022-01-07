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


use App\Entity\SymUrl;
use App\Repository\SymUrlRepository;

class SymUrlService
{
    /**
     * @var SymUrlRepository
     */
    private $symRepo;

    public function __construct(SymUrlRepository $symRepo)
    {
        $this->symRepo = $symRepo;
    }

    /**
     * @param string $url
     * @param null|string $shortTag
     * @return SymUrl
     * @throws \Doctrine\ORM\ORMException
     */
    public function createOrGet(string $url, ?string $shortTag): SymUrl
    {
        // first, if short is not null, then we need to look in the db in case
        // it already exists
        if ($shortTag) {
            if (($sym = $this->symRepo->findOneByShortTag($shortTag))) {
                // we have a record with this name,
                // lets see if it is the one they wanted
                if ($sym->getLongUrl() == $url) {
                    return $sym;
                } else {
                    // there is already a url with this short tag and it points
                    // to somewhere else.
                    //  well, too bad, you're getting a short tag that i pick
                    $shortTag = null;
                }
            }
        }

        // lets see if we can find a matching long record
        if (!$shortTag && ($symurls = $this->symRepo->findByLongUrl($url))) {
            // we do not have an explicit short name, so happy to take any
            // result that we can find
            // there may be multiple matches for this long if someone
            // created with a manual short url. we simply return the first
            return $symurls[0];
        }

        // when we get here, we know that there is not an existing short
        // with the same name, so it is safe to create a new symurl
        return $this->symRepo->newSymUrl($url, $shortTag);
    }

    /**
     * @param string $shortTag
     * @param bool $updateStats
     * @return SymUrl|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getExisting(string $shortTag,
                                bool $updateStats = false): ?SymUrl
    {
        $sym = $this->symRepo->findOneByShortTag($shortTag);
        if ($updateStats && $sym) {
            $this->symRepo->updateStats($sym);
        }
        return $sym;
    }
}
