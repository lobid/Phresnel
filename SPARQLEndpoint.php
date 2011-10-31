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

require_once(dirname(__FILE__) . '/lib/LibRDF/LibRDF/LibRDF.php');

/**
 * The interface for a SPARQL endpoint
 */
abstract class SPARQLEndpoint {

    /**
     * The graph queried by the endpoint.
     *
     * @var string
     */
    protected $_graph = "";

    /**
     * The logger to write to.
     *
     * @var KLogger  Defaults to null.
     */
    protected $_logger = null;

    /**
     * Constructor.
     *
     * @param  mixed    $graph
     * @param  KLogger  $logger
     */
    public function __construct($graph, KLogger $logger) {
        $this->_graph = $graph;
        $this->_logger = $logger;
    }

    /**
     * Query the endpoint
     *
     * @param  mixed  $query
     * @return LibRDF_Model
     */
    public function query($query) {
        $this->_logger->logInfo("Endpoint is $this->_graph.");
        $result = "";
        if (is_array($query)) {
            foreach($query as $q) {
                $result .= $this->_query($q);
            }
        } else if (is_string($query)) {
            $result .=$this->_query($query);
        }
        //$graph = new LibRDF_Model(new LibRDF_Storage());
        //$graph->loadStatementsFromString(new LibRDF_Parser("ntriples"), $result);
        //return $graph;
        //TODO return model
        return $result;
    }

    /**
     * Perform a single query.
     *
     * @param  string  $query
     * @return string  The ntriples encoded query result.
     */
    protected abstract function _query($query);
}
