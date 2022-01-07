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

namespace App\Repository;

use App\Entity\SymUrl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method SymUrl|null find($id, $lockMode = null, $lockVersion = null)
 * @method SymUrl|null findOneBy(array $criteria, array $orderBy = null)
 * @method SymUrl[]    findAll()
 * @method SymUrl[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SymUrlRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, SymUrl::class);
    }

    /**
     * @param string $shortTag
     * @return SymUrl|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByShortTag(string $shortTag): ?SymUrl
    {
        return $this->createQueryBuilder('s')
                    ->andWhere('s.shortTag = :val')
                    ->setParameter('val', $shortTag)
                    ->getQuery()
                    ->getOneOrNullResult();
    }

    /**
     * @param string $longUrl
     * @return SymUrl[]|null
     */
    public function findByLongUrl(string $longUrl): ?array
    {
        return $this->createQueryBuilder('s')
                    ->andWhere('s.longUrl = :val')
                    ->setParameter('val', $longUrl)
                    ->getQuery()
                    ->getResult();
    }

    public function updateStats(SymUrl $symUrl)
    {
        $symUrl->setDateLastUsed(new \DateTime());
        $symUrl->setUseCount($symUrl->getUseCount() + 1);
        $em = $this->getEntityManager();
        $em->persist($symUrl);
        $em->flush();
    }

    /**
     * @param string $longUrl
     * @param null|string $shortTag
     * @return SymUrl
     * @throws \Doctrine\ORM\ORMException
     */
    public function newSymUrl(string $longUrl, ?string $shortTag): SymUrl
    {
        $symurl = new SymUrl();
        $symurl->setLongUrl($longUrl);
        if (!$shortTag) {
            /* create new short url */
            $shortTag = $this->shorten($longUrl);
        }
        $symurl->setShortTag($shortTag);
        $em = $this->getEntityManager();
        $em->persist($symurl);
        $em->flush();
        return $symurl;
    }

    /**
     * @param string $longUrl
     * @return string
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function shorten(string $longUrl): string
    {
        $length = 1;
        $shortTag = null;
        $hash = $this->hashLong($longUrl);
        $hashLen = strlen($hash);
        while (true) {
            if ($length > $hashLen) {
                $hash .= $hash;
                $hashLen = strlen($hash);
            }
            $shortTag = substr($hash, 0, $length);
            if ($this->findOneByShortTag($shortTag) == null) {
                // this is a new short, we can keep it
                break;
            }
            $length++;
        }
        return $shortTag;
    }

    private function hashLong(string $longUrl): string
    {
        $md5 = md5($longUrl);
        $hash = pack('H*', $md5);
        $hash = base64_encode($hash);
        return str_replace(array('+', '/', '='), array('', '', ''), $hash);
    }
}
