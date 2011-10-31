<?php

/**
 * Copyright 2011 Felix Ostrowski, hbz
 *
 * This file is part of Phresnel.
 *
 * Phresnel is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * Phresnel is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Phresnel.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once("SPARQLEndpoint.php");

/**
 * Query a local model.
 */
class localSPARQLEndpoint extends SPARQLEndpoint {

    /**
     * Query the endpoint
     *
     * @param  string  $query
     * @return LibRDF_Model
     */
    protected function _query($query) {
        $queryObj = new LibRDF_Query($query, null, 'sparql');
        $statements = $queryObj->execute($this->_graph);
        $result = new LibRDF_Model(new LibRDF_Storage());
        foreach ($statements as $statement) {
            $result->addStatement($statement);
        }
        try {
            $dirty = $result->serializeStatements(new LibRDF_Serializer("ntriples"));
        } catch (LibRDF_Error $e) {
            // Empty query result?
            $this->_logger->logError("Trouble serializing\n$result");
            $dirty = "";
        }
        $clean = $this->__clean($dirty);
        return $clean;
    }

    /**
     * Fix for bnode syntax bug _:r8_r1308600998r26010r2297
     *
     * @param  string  $ntriples
     * @return string
     */
    private function __clean($ntriples) {
        return preg_replace('/_:r[0-9]+_/', '_:', $ntriples);
    }
}
