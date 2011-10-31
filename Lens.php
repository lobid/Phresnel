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

require_once(dirname(__FILE__) . '/lib/LibRDF/LibRDF.php');
require_once(dirname(__FILE__) . '/lib/KLogger/src/KLogger.php');
require_once("RemoteSPARQLEndpoint.php");
require_once("LocalSPARQLEndpoint.php");
require_once("Phresnel.php");

define("RDF", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
define("FRESNEL", "http://www.w3.org/2004/09/fresnel#");
define("LM", "http://literarymachine.net/ontology/lm#");


class Lens {

    /**
     * Logger
     *
     * @var KLogger
     */
    protected $_log;

    /**
     * The data viewed through the lens.
     *
     * @var LibRDF_Model
     */
    protected $_data;

    /**
     * The graph containing the lens definitions.
     *
     * @var LibRDF_Model
     */
    protected $_lensDef;

    /**
     * The URI of the lens definition to use.
     *
     * @var string
     */
    protected $_lens;

    /**
     * Namespace definitions.
     *
     * @var array
     */
    protected $_namespaces = array();

    /**
     * The SPARQL endpoint to query.
     *
     * @var SPARQLEndpoint  Defaults to null.
     */
    protected $_ep = null;

    /**
     * Whether to use the query cache.
     *
     * @var bool  Defaults to true.
     */
    protected $_useCache = true;

    /**
     * The URI of the resource in focus.
     *
     * @var LibRDF_Node  Defaults to null.
     */
    protected $_resourceURI = null;

    /**
     * Constructor.
     *
     * @param  LibRDF_Model    $lensGraph Graph containing lens definitions.
     * @param  string          $lensURI   URI of the lens to use.
     * @param  LibRDF_Model    $data      Optional, defaults to null.
     * @param  SPARQLEndpoint  $ep        The endpoint to query for instance data.
     * @param  KLogger         $log       The logger to log to.
     * @param  mixed           $useCache  Optional, defaults to true.
     */
    public function __construct($lensGraph, $lensURI,
            LibRDF_Model $data = null,
            SPARQLEndpoint $ep,
            KLogger $log,
            $useCache = true) {

        // Check if lens is defined
        $s = new LibRDF_URINode($lensURI);
        $p = new LibRDF_URINode(RDF."type");
        $o = new LibRDF_URINode(FRESNEL."Lens");
        if (null == $stmt = $lensGraph->findStatements($s, $p, $o)->current()) {
            throw new Exception("Undefined lens $lensURI.");
        }

        $this->_lensDef = $lensGraph;
        $this->_lens = new LibRDF_URINode($lensURI);
        if (null === $data) {
            $this->_data = new LibRDF_Model(new LibRDF_Storage());
        } else {
            $this->_data = $data;
        }
        $this->_ep = $ep;
        $this->_log = $log;
        $this->_useCache = $useCache;
    }

    /**
     * Load a resource into the lens.
     *
     * @param  LibRDF_URINode  $uri The URI of the resource
     * @return void
     */
    public function loadResource(LibRDF_Node $uri, LibRDF_Model $data = null) {
        $this->_resourceURI = $uri;
        //if ($this->_useCache and $cache = $this->_getCache($this->_lens, $uri)) {
        //    $this->_data->loadStatementsFromString(
        //            new LibRDF_Parser("ntriples"), $cache);
        //    // TODO: decide whether to check bounds for
        //    // cached resources.
        //    // $this->_checkBounds($uri, $this->_lens);
        //    return;
        //}
		if (null === $data) {
			$queries = $this->buildQuery($uri);
			$result = $this->_ep->query($queries);
			$this->_log->logInfo("Query result: \n".$result);
			$this->_data->loadStatementsFromString(
                new LibRDF_Parser("ntriples"), $result);
			$this->_checkBounds($uri, $this->_lens);
		} else {
			$this->_data = $data;
		}
        // $this->_cache($this->_lens, $uri,
        //        $this->_data->serializeStatements(new LibRDF_Serializer("ntriples")));
    }

    /**
     * Check if all properties required by the lens are
     * available and load linked data if not so.
     *
     * @return void
     */
    protected function _checkBounds(LibRDF_Node $resourceURI, LibRDF_Node $lensURI) {
        $lst = $this->_lensDef->getTarget($lensURI, new
                LibRDF_URINode(FRESNEL."showProperties"));
        $props = $this->_unlist($lst);
        foreach ($props as $prop) {
            if ($prop instanceof LibRDF_BlankNode) {
                $sublens = $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(FRESNEL."sublens"));
                $link = $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(FRESNEL."property"));
                $values = $this->_data->findStatements($resourceURI, $link, null);
                $v = $values->current();
                if ($values->current() === null and $resourceURI instanceof LibRDF_URINode) {
                    $this->_loadLinkedData($resourceURI);
                } else {
                    foreach ($values as $value) {
                        $this->_checkBounds($value->getObject(), $sublens);
                    }
                }
            } else if ($prop instanceof LibRDF_URINode) {
                $values = $this->_data->findStatements($resourceURI, $prop, null);
                if ($values->current() === null and $resourceURI instanceof LibRDF_URINode) {
                    $this->_loadLinkedData($resourceURI);
                }
            }
        }
    }

    /**
     * Load linked data via HTTP.
     * FIXME: check retrieved data.
     *
     * @param  LibRDF_URINode  $resourceURI The URI of the resource.
     * @return void
     */
    protected function _loadLinkedData(LibRDF_URINode $resourceURI) {
        // FIXME: disabled to reduce load
        return;
        $this->_log->logInfo("Loading linked data from $resourceURI");
        $curl_handle = curl_init();
        curl_setopt($curl_handle,
                CURLOPT_HTTPHEADER,
                array('Accept: application/rdf+xml'));
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,true);

        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        curl_setopt($curl_handle,CURLOPT_URL, $r);
        $start = microtime(true);
        $data = curl_exec($curl_handle);
        $end = microtime(true);
        $duration = $end - $start;
        $this->_log->logInfo("Received data:" . $data);
        try {
            $this->_data->loadStatementsFromString(
                    new LibRDF_Parser("rdfxml"), $data);
        } catch (LibRDF_Error $e) {
                $this->_log->logError("Could not parse linked data from $resourceURI");
        }
        switch (curl_errno($curl_handle)) {
            case 28:
                $this->_log->logError("Loading linked data: timeout");
                break;
            default:
                $this->_log->logInfo("Linked data loaded, duration: " . $duration);
        }
        curl_close($curl_handle);
    }

    /**
     * Write data to the cache.
     *
     * @param  LibRDF_URINode $lensURI     The URI of the lens used on the data.
     * @param  LibRDF_URINode $resourceURI The URI of the resource viewed.
     * @param  string         $content     The content to cache.
     * @return void
     */
    protected function _cache(LibRDF_URINode $lensURI, LibRDF_URINode $resourceURI, $content) {
        $dir = dirname(__FILE__) . "/cache";
        $hash = md5($lensURI . $resourceURI);
        $filename = "$dir/$hash.nt";
        $this->_log->logInfo("Caching results for $resourceURI using $lensURI. Cache file is $filename.");
        file_put_contents($filename, $content);
    }

    /**
     * Retrive data from the cache.
     *
     * @param  LibRDF_URINode $lensURI     The URI of the lens used on the data.
     * @param  LibRDF_URINode $resourceURI The URI of the resource viewed.
     * @return string
     */
    protected function _getCache(LibRDF_URINode $lensURI, LibRDF_URINode $resourceURI) {
        $dir = dirname(__FILE__) . "/cache";
        $hash = md5($lensURI . $resourceURI);
        $filename = "$dir/$hash.nt";
        $this->_log->logInfo("Reading results for $resourceURI using $lensURI from cache. Cache file is $filename.");
        if (!file_exists($filename)) {
            return false;
        }
        return file_get_contents($filename);
    }


    /**
     * Turns an RDF list into an ordered PHP array.
     *
     * @param  mixed  $listURI The URI of the head of the list.
     * @return array  The list as an array.
     */
    protected function _unlist($listURI) {
        $lst = array();
        try {
            $lst[] = $this->_lensDef->getTarget($listURI, new
                    LibRDF_URINode(RDF."first"));
        } catch (LibRDF_LookupError $e) {
            return $lst;
        }
        $tail = $this->_lensDef->getTarget($listURI, new
                LibRDF_URINode(RDF."rest"));
        return array_merge($lst, $this->_unlist($tail));
    }

    /**
     * Get the data of the resource currently focussed.
     *
     * @return LibRDF_Model
     */
    public function getData() {
        return $this->_data;
    }

    /**
     * Build all neccessary queries to retrieve the data of a resource.
     *
     * @param  LibRDF_URINode  $resourceURI
     * @return array An array of query strings.
     */
    public function buildQuery(LibRDF_Node $resourceURI) {
        $rdftype = RDF."type";
        $this->_constructQuery($resourceURI, $this->_lens);
        $queries = array();
        foreach ($this->tmp as $var => $query) {
            $domain = $this->_nodeToString($this->types[$var]);
            $queries[] = "CONSTRUCT { $var ?p ?o }\nWHERE {\n$query\n$var <$rdftype> $domain}";
        }
        $this->tmp = array();
        $this->types = array();
        return $queries;
    }

    /**
     * Build a query for the properties of a single resource.
     *
     * @param  resource  $resourceURI
     * @param  mixed     $lensURI
     * @param  mixed     $parentQuery String containing the path to
     *                                the current resource.
     * @return string
     */
    protected function _constructQuery($resourceURI, $lensURI, $parentQuery = "") {
        $lst = $this->_lensDef->getTarget($lensURI, new
                LibRDF_URINode(FRESNEL."showProperties"));
        $props = $this->_unlist($lst);
        $ret = $parentQuery;
        $ret .= $this->_nodeToString($resourceURI) . " ?p ?o .\n";
        $domain = $this->_lensDef->getTarget($lensURI, new
                LibRDF_URINode(FRESNEL."classLensDomain"));
        $this->types[$this->_nodeToString($resourceURI)] = $domain;
        $filter = array("<" . RDF . "type" . ">");
        foreach ($props as $prop) {
            if (!($prop instanceof LibRDF_BlankNode)) {
                $filter[] = $this->_nodeToString($prop);
            } else if ($prop instanceof LibRDF_BlankNode) {
                $link = $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(FRESNEL."property"));
                $filter[] = $this->_nodeToString($link);
            }
        }
        $ret .= "FILTER (?p=" . implode(" || ?p=", $filter) . ")\n";
        if (!empty($filter)) {
            $this->tmp[$this->_nodeToString($resourceURI)] = $ret;
        }
        foreach ($props as $prop) {
            $rs = "";
            $child = new LibRDF_BlankNode();
            if ($prop instanceof LibRDF_BlankNode) {
                $sublens = $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(FRESNEL."sublens"));
                $link = $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(FRESNEL."property"));
                $rs .= $this->_nodeToString($resourceURI) . " ";
                $rs .= $this->_nodeToString($link) . " ";
                $rs .= $this->_nodeToString($child) . " .\n";
                $this->_constructQuery($child, $sublens, $parentQuery . $rs);
            }
        }
    }

    /**
     * A string representation of a node that
     * is suitable to use in SPARQL queries.
     *
     * @param  LibRDF_Node  $node
     * @return string
     */
    protected function _nodeToString(LibRDF_Node $node) {
        $r = substr($node, 1, strlen($node) - 2);
        if ($node instanceof LibRDF_URINode) {
            return "<$r>";
        } else if ($node instanceof LibRDF_BlankNode) {
            return "?$r";
        }
    }

    /**
     * TODO: short description.
     * 
     * @return TODO
     */
    public function getLensURI() {
        return $this->_lens;
    }

    /**
     * TODO: short description.
     * 
     * @return TODO
     */
    public function getLensGraph() {
        return $this->_lensDef;
    }

    /**
     * TODO: short description.
     * 
     * @return TODO
     */
    public function getResourceURI() {
        return $this->_resourceURI;
    }

    /**
     * TODO: short description.
     * 
     * @return TODO
     */
    public function getLensDomain() {
        $domain = $this->_lensDef->getTarget($this->_lens, new
                LibRDF_URINode(FRESNEL."classLensDomain"));
        return $domain;
    }

}
