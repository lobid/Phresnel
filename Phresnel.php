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
require_once(dirname(__FILE__) . '/lib/KLogger/src/KLogger.php');
require_once(dirname(__FILE__) . '/Lens.php');
require_once(dirname(__FILE__) . '/AbstractBoxModel.php');
require_once(dirname(__FILE__) . '/AbstractFormBoxModel.php');
require_once(dirname(__FILE__) . '/HTMLTableBoxModel.php');
require_once(dirname(__FILE__) . '/HTMLTableFormBoxModel.php');
require_once(dirname(__FILE__) . '/RemoteSPARQLEndpoint.php');
require_once(dirname(__FILE__) . '/LocalSPARQLEndpoint.php');

/**
 * Phresnel lens factory
 *
 */
class Phresnel {

    /**
     * The graph containing lens definitions.
     *
     * @var LibRDF_Model  Defaults to null.
     */
    protected static $_lensGraph = null;

    /**
     * The uri containing lens definitions.
     *
     * @var LibRDF_Model  Defaults to null.
     */
    protected static $_lensGraphURI = null;

    /**
     * The remote SPARQL endpoint to connect to.
     *
     * @var SPARQLEndpoint  Defaults to null.
     */
    protected static $_endpoint = null;

    /**
     * The logger to write to.
     *
     * @var KLogger  Defaults to null.
     */
    protected static $_logger = null;

    public static function init($conf, KLogger $logger) {
        self::$_logger = $logger;
        $setup = array();
        if (!(is_array($conf))) {
            $rdfconf = new LibRDF_Model(new LibRDF_Storage());
            $rdfconf->loadStatementsFromURI(new LibRDF_Parser("turtle"), $conf);
            $epProp = new LibRDF_URINode("http://literarymachine.net/ontology/phresnel#endpoint");
            $epNode = $rdfconf->findStatements(null, $epProp, null)->current();
            if (null !== $epNode) {
                $epURL = $epNode->getObject();
                $epURL = substr($epURL, 1, strlen($epURL) - 2);
                $setup['endpoint'] = $epURL;
            }
            $lensProp = new LibRDF_URINode("http://literarymachine.net/ontology/phresnel#lenses");
            $lensDef = $rdfconf->findStatements(null, $lensProp, null)->current();
            $lensGraph = new LibRDF_Model(new LibRDF_Storage());
            $setup["lensDef"] = substr($lensDef->getObject(), 1, strlen($lensDef->getObject()) - 2);
        } else {
            $setup = $conf;
        }
        if (array_key_exists("endpoint", $setup)) {
            self::$_endpoint = new RemoteSPARQLEndpoint($setup["endpoint"], $logger);
        } else {
            $model = new LibRDF_Model(new LibRDF_Storage);
            self::$_endpoint = new LocalSPARQLEndpoint($model, $logger);
        }
        $lensGraph = new LibRDF_Model(new LibRDF_Storage());
        $lensGraph->loadStatementsFromURI(new LibRDF_Parser("turtle"), $setup["lensDef"]);
        self::$_lensGraph = $lensGraph;
        self::$_lensGraphURI = $setup["lensDef"];
    }

    /**
     * Get a Lens
     *
     * @param  string  $lensURI
     * @return Lens
     */
    public static function getLens($lensURI) {
        if (self::$_endpoint instanceof localSPARQLEndpoint) {
            $caching = false;
        } else {
            $caching = true;
        }

        if ($lensURI instanceof LibRDF_Node) {
            $lensURI = substr($lensURI, 1, strlen($lensURI) - 2);
        } else {
            $lensURI = self::$_lensGraphURI . "#$lensURI";
        }
        return new Lens(self::$_lensGraph, $lensURI,
                null, self::$_endpoint, self::$_logger, $caching);
    }

    public static function getEndpoint() {
        return self::$_endpoint;
    }

    public static function setEndpoint(SPARQLEndpoint $ep) {
        self::$_endpoint = $ep;
    }
}

