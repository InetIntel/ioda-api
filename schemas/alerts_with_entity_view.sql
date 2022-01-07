/*
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

-- View: public.alerts_with_entity_view

-- DROP VIEW public.alerts_with_entity_view;

CREATE OR REPLACE VIEW public.alerts_with_entity_view
AS
SELECT a.id,
       a.fqid,
       a.name,
       a.query_time,
       a.level,
       a.method,
       a.query_expression,
       a.history_query_expression,
       a."time",
       a.expression,
       a.condition,
       a.value,
       a.history_value,
       a.meta_type,
       a.meta_code,
       m.id AS meta_id,
       omt.type AS related_type,
       om.code AS related_code
FROM (((((watchtower_alert a
    JOIN mddb_entity m ON (((a.meta_code)::text = (m.code)::text)))
    JOIN mddb_entity_type mt ON ((m.type_id = mt.id)))
    JOIN mddb_entity_relationship r ON ((m.id = r.from_id)))
    JOIN mddb_entity om ON ((om.id = r.to_id)))
         JOIN mddb_entity_type omt ON ((om.type_id = omt.id)))
WHERE ((mt.type)::text = (a.meta_type)::text);

ALTER TABLE public.alerts_with_entity_view
    OWNER TO charthouse;


