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

require_once("AbstractBoxModel.php");

/**
 * A simple HTML table view.
 *
 */
class HTMLTableBoxModel extends AbstractBoxModel {

    /**
     * TODO: short description.
     * 
     * @return TODO
     */
    protected function _render() {
        if ($this->_resourceURI instanceof LibRDF_BlankNode) {
            $instances = $this->_data->findStatements(
                    null,
                    new LibRDF_URINode(RDF."type"),
                    $this->_lensObj->getLensDomain());
            $rs = "<div>";
            foreach ($instances as $i) {
                $rs .= $this->_display($this->_lens, $i->getSubject());
            }
            $rs .= "</div>";
        } else {
            $rs = $this->_display($this->_lens, $this->_resourceURI);
        }
        $doc = new DomDocument('1.0', 'UTF-8');
        $doc->loadXml($rs);
        $doc->formatOutput = true;
        foreach ($this->_namespaces as $url => $prefix) {
            $doc->documentElement->appendChild(
                    $doc->createAttribute("xmlns:$prefix"))->appendChild(
                    $doc->createTextNode($url));
        }
        return $doc->saveXml($doc->documentElement);
    }

    /**
     * Render a display view of the resource.
     *
     * @param  LibRDF_Node  $lensURI
     * @param  LibRDF_Node  $resourceURI
     * @return string
     */
    public function _display(LibRDF_Node $lensURI, LibRDF_Node $resourceURI) {
        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        try {
            $domain = $this->_lensDef->getTarget($lensURI, new
                    LibRDF_URINode(FRESNEL."classLensDomain"));
            list ($dname, $dns) = $this->_nssplit($domain);
        } catch (LibRDF_LookupError $e) {
            $domain = false;
        }
        // sublens with no properties to show are
        // raw links
        try {
            $lst = $this->_lensDef->getTarget($lensURI, new
                    LibRDF_URINode(FRESNEL."showProperties"));
        } catch (LibRDF_LookupError $ex) {
            return "<a href=\"" . htmlspecialchars($r) . "\">" . htmlspecialchars($r) . "</a>";
        }
        $props = $this->_unlist($lst);
        try {
            $purpose = $this->_lensDef->getTarget($lensURI, new
                    LibRDF_URINode(FRESNEL."purpose"));
        } catch(LibRDF_LookupError $e) {
            $purpose = null;
        }
        if ($purpose and $purpose->isEqual(new LibRDF_URINode(LM."linkLens"))) {
            $labelVals = array();
            foreach ($props as $prop) {
                if ($prop instanceof LibRDF_URINode) {
                    try {
                        $labelVals[] = $this->_data->getTarget($resourceURI, $prop);
                    } catch (LibRDF_LookupError $e) {
                    }
                }
            }
            return "<a href=\"" . htmlspecialchars($r) ."\">" . htmlspecialchars(implode(', ', $labelVals)) . "</a>";
        }
        if ($resourceURI instanceof LibRDF_URINode and $domain) {
            $rs = "<table about=\"$r\" typeof=\"$dns:$dname\"><tr><td class=\"rlabel\" colspan=\"2\"><a href=\"$r\">$r</a></td></tr>";
        } else if ($resourceURI instanceof LibRDF_URINode) {
            $rs = "<table about=\"$r\"><tr><td class=\"rlabel\" colspan=\"2\"><a href=\"$r\">$r</a></td></tr>";
        } else if ($domain) {
            $rs = "<table typeof=\"$dns:$dname\"><tr><td class=\"rlabel\" colspan=\"2\"><span>$r</span></td></tr>";
        } else {
            $rs = "<table><tr><td class=\"rlabel\" colspan=\"2\"><span>$r</span></td></tr>";
        }
        foreach ($props as $prop) {
            if ($prop instanceof LibRDF_BlankNode) {
                $rs .= $this->_subdisplay($resourceURI, $prop);
            } else if ($prop instanceof LibRDF_URINode) {
                $rs .= $this->_output($resourceURI, $prop);
            }
        }
        $rs .= "</table>";
        return $rs;
    }

    /**
     * Render a resource embedded in another.
     *
     * @param  resource  $resourceURI
     * @param  mixed     $prop
     * @return string
     */
    protected function _subdisplay($resourceURI, $prop) {
        $sublens = $this->_lensDef->getTarget($prop, new
                LibRDF_URINode(FRESNEL."sublens"));
        $link = $this->_lensDef->getTarget($prop, new
                LibRDF_URINode(FRESNEL."property"));
        try {
            $format = $this->_lensDef->getSource(new LibRDF_URINode(FRESNEL."propertyFormatDomain"), $link);
            $label = $this->_lensDef->getTarget($format, new LibRDF_URINode(FRESNEL."label"));
        } catch(LibRDF_LookupError $e) {
            $label = "$link";
        }
        list ($pname, $pns) = $this->_nssplit($link);
        $values = $this->_data->findStatements($resourceURI, $link, null);
        $rs = '';
        foreach ($values as $val) {
            $l = substr($link, 1, strlen($link) - 2);
            $rs .= "<tr><td class=\"plabel\">";
            $rs .= "<a href=\"$l\">$label</a>";
            $rs .= "</td><td rel=\"$pns:$pname\">";
            $child = $val->getObject();
            $c = substr($child, 1, strlen($child) - 2);
            $rs .= $this->_display($sublens, $child);
            $rs .= "</td></tr>";
        }
        return $rs;
    }

    /**
     * Render a literal property.
     * 
     * @param  resource  $resourceURI
     * @param  mixed     $prop
     * @return string
     */
    protected function _output($resourceURI, $prop) {
        $values = $this->_data->findStatements($resourceURI, $prop, null);
        $rs = '';
        list ($pname, $pns) = $this->_nssplit($prop);
        try {
            $format = $this->_lensDef->getSource(new LibRDF_URINode(FRESNEL."propertyFormatDomain"), $prop);
            $label = $this->_lensDef->getTarget($format, new LibRDF_URINode(FRESNEL."label"));
        } catch(LibRDF_LookupError $e) {
            $format = null;
            $label = "$prop";
        }
        if ($format) {
            try {
                $format_value = $this->_lensDef->getTarget($format, new LibRDF_URINode(FRESNEL."value"));
            } catch(LibRDF_LookupError $e) {
                $format_value = null;
            }
        } else {
            $format_value = null;
        }
        foreach ($values as $val) {
            $l = substr($prop, 1, strlen($prop) - 2);
            $rs .= "<tr><td class=\"plabel\">";
            $rs .= "<a href=\"$l\">$label</a>";
            if ($format_value and $format_value->isEqual(new LibRDF_URINode(FRESNEL."image"))) {
                $rs .= "</td><td rel=\"$pns:$pname\">";
                $imgURI = $val->getObject();
                $imgURI = substr($imgURI, 1, strlen($imgURI) - 2);
                $rs .= "<img src=\"" . $imgURI . "\" />";
            } else {
                $rs .= "</td><td property=\"$pns:$pname\">";
                $rs .= htmlspecialchars($val->getObject());
            }
            $rs .= "</td></tr>";
        }
        return $rs;
    }

}
