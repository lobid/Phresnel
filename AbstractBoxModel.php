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

abstract class AbstractBoxModel {

    /**
     * TODO: description.
     * 
     * @var mixed  Defaults to array(). 
     */
    protected $_namespaces = array();

    public abstract function render();

    /**
     * TODO: short description.
     * 
     * @param  Lens  $lens 
     */
    public function __construct(Lens $lens) {
        $this->_lensObj = $lens;
        $this->_lens = $lens->getLensURI();
        $this->_lensDef = $lens->getLensGraph();
        $this->_resourceURI = $lens->getResourceURI();
        $this->_data = $lens->getData();
        $this->_namespaces = Phresnel::$_namespaces;
    }

    /**
     * Maps prefixes to namespaces.
     *
     * @param  LibRDF_URINode  $uri The URI to split.
     * @return array The local part of the name and the prefix.
     */
    protected function _nssplit(LibRDF_URINode $uri) {
        if (false !== strpos($uri, '#')) {
            $name = substr($uri, strrpos($uri, '#') + 1, -1);
            $ns = substr($uri, 1, strrpos($uri, '#'));
        } else {
            $name = substr($uri, strrpos($uri, '/') + 1, -1);
            $ns = substr($uri, 1, strrpos($uri, '/'));
        }
        if (!array_key_exists($ns, $this->_namespaces)) {
            $this->_namespaces[$ns] = "ns" . count($this->_namespaces);
        }
        return array($name, $this->_namespaces[$ns]);
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
}
