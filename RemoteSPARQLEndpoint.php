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
 * A remote SPARQL endpoint
 */
class RemoteSPARQLEndpoint extends SPARQLEndpoint {

    /**
     * Query the endpoint
     *
     * @param  string  $query
     * @return LibRDF_Model
     */
    protected function _query($query) {
        $curl_handle = curl_init();
        curl_setopt($curl_handle,
                CURLOPT_HTTPHEADER,
                array('Accept: text/plain'));
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_URL, $this->_graph);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "query=$query");

        $this->_logger->logInfo("Start query\n" . $query);
        $start = microtime(true);
        $result = curl_exec($curl_handle);
        $end = microtime(true);
        $duration = $end - $start;
        //$this->_logger->logInfo("Query result: \n".$result);
        switch (curl_errno($curl_handle)) {
            case 28:
                $this->_logger->logError("Query timeout");
                break;
            default:
                $this->_logger->logInfo("End query, duration: " . $duration);
        }
        curl_close($curl_handle);
        return $result;
    }
}
