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

namespace App\Utils;


use Symfony\Component\Serializer\Annotation\Groups;

class QueryTime
{
    /**
     * @var bool
     * @Groups({"public"})
     */
    protected $relative;

    /**
     * @var string
     * @Groups({"public"})
     */
    protected $relativeTime;

    /**
     * @var \DateTime
     * @Groups({"public"})
     */
    protected $absoluteTime;

    /**
     * @var string
     * @Groups({"public"})
     */
    protected $rawTime;

    /**
     * QueryTime constructor.
     * @param null|string $time
     */
    public function __construct(?string $time)
    {
        $this->rawTime = $time;

        if (!$time) {
            // treat as 'now'
            $this->setRelativeTime('now');
            return;
        }

        if (ctype_digit($time)) {
            // assume epoch time
            $dt = new \DateTime();
            $dt->setTimestamp((int)$time);
            $this->setAbsoluteTime($dt);
            return;
        }

        // is it a relative time?
        if (strtolower($time) == 'now') {
            $this->setRelativeTime('now');
            return;
        }
        if ($time[0] == '-' || $time[0] == '+') {
            $this->setRelativeTime($time);
            return;
        }

        // otherwise, assume an RFC3339 string (e.g., 2018-09-14T00:00:00Z)
        $dt = \DateTime::createFromFormat(\DateTime::RFC3339, $time);
        if ($dt === false) {
            throw new \InvalidArgumentException("Could not parse time: '$time'");
        }
        $this->setAbsoluteTime($dt);
        return;
    }

    private function setRelativeTime(string $relTime)
    {
        $this->relative = true;
        $this->relativeTime = $relTime;
    }

    private function setAbsoluteTime(\DateTime $absTime)
    {
        $this->relative = false;
        $this->absoluteTime = $absTime;
    }

    public function __toString()
    {
        return $this->relative ? $this->relativeTime : $this->absoluteTime->format(DATE_ATOM);
    }

    public function isRelative(): bool
    {
        return $this->relative;
    }

    public function getRelativeTime(): ?string
    {
        return $this->relativeTime;
    }

    public function getAbsoluteTime(): ?\DateTime
    {
        return $this->absoluteTime;
    }

    /**
     * @Groups({"public"})
     */
    public function getEpochTime(): ?int
    {
        return $this->relative ? null : $this->absoluteTime->getTimestamp();
    }

    public function getRawTime(): string
    {
        return $this->rawTime;
    }

    public function getGraphiteTime(): string
    {
        return $this->relative ? $this->relativeTime : $this->absoluteTime->getTimestamp();
    }

    public function getInfluxTime(): string
    {
        return $this->relative ? $this->relativeTime : $this->absoluteTime->getTimestamp();
    }

}
