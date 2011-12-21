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

/**
 * Render a resource as an HTML Form.
 */
class HTMLTableFormBoxModel extends AbstractBoxModel {

    /**
     * Wrap a form for a resource.
     *
     * @param  LibRDF_URINode $resource The resource to render a form for.
     * @return string
     */
    protected function _render() {
        $rs = "<form method=\"POST\" action=\"\">";
        $rs .= $this->_form($this->_lens, $this->_resourceURI);
        $rs .= '<input type="submit" name="format" value="RDFa" />';
        $rs .= '<input type="submit" name="format" value="Turtle" />';
        $rs .= '<input type="submit" name="format" value="RDF/XML" />';
        $rs .= '<input type="submit" name="format" value="Save" />';
        $rs .= "</form>";
        $doc = new DomDocument('1.0', 'UTF-8');
        $doc->loadXml($rs);
        $doc->formatOutput = true;
        foreach ($this->_namespaces as $url => $prefix) {
            $doc->documentElement->appendChild(
                    $doc->createAttribute("$prefix"))->appendChild(
                    $doc->createTextNode($url));
        }
        return $doc->saveXml($doc->documentElement);
    }

    /**
     * Render a form for a resource.
     *
     * @param  LibRDF_Node  $lensURI     The URI of the lens to use.
     * @param  LibRDF_Node  $resourceURI The URI of the resource.
     * @param  resource     $rem         HTML containing the remove button.
     * @return string
     */
    protected function _form(LibRDF_Node $lensURI, LibRDF_Node $resourceURI, $rem = "") {
        try {
            $domain = $this->_lensDef->getTarget($lensURI, new
                    LibRDF_URINode(FRESNEL."classLensDomain"));
            list ($dname, $dns) = $this->_nssplit($domain);
            $d = substr($domain, 1, strlen($domain) - 2);
        } catch (LibRDF_LookupError $e) {
            $d = null;
        }
        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        $rs = '';
        $rs .= "<table class=\"resource\">";
        $lst = $this->_lensDef->getTarget($lensURI, new
                LibRDF_URINode(FRESNEL."showProperties"));
        $props = $this->_unlist($lst);
        $rs .= "<tr><td class=\"rlabel\" colspan=\"2\">";
        $rs .= "<span>$resourceURI</span>";
        if ($domain) {
            $rs .= sprintf('<input type="hidden" name="content[%1$s][object][%2$s][]" value="%3$s" />', $r, RDF."type", $d);
        }
        $rs .= "$rem</td></tr>";
        foreach ($props as $prop) {
            if ($prop instanceof LibRDF_BlankNode) {
                $rs .= $this->_subform($resourceURI, $prop);
                // TODO: Alternatively render search field
            } else if ($prop instanceof LibRDF_URINode) {
                $rs .= $this->_input($resourceURI, $prop);
            }
        }
        $rs .= "</table>";
        return $rs;
    }

    /**
     * Render a text input for a property.
     *
     * @param  LibRDF_Node     $resourceURI The URI of the resource.
     * @param  LibRDF_URINode  $prop        The URI of the property.
     * @return string
     */
    protected function _input(LibRDF_Node $resourceURI, LibRDF_URINode $prop) {
        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        $values = $this->_data->findStatements($resourceURI, $prop, null);
        $rs = '';
        try {
            $format = $this->_lensDef->getSource(new
                    LibRDF_URINode(FRESNEL."propertyFormatDomain"),
                    $prop);
            $label = $this->_lensDef->getTarget($format, new
                    LibRDF_URINode(FRESNEL."label"));
        } catch(LibRDF_LookupError $e) {
            $label = "$prop";
        }
        if ($values->current() === null) {
            $rs .= "<tr>";
            $rs .= "<td class=\"plabel\">$label</td>";
            $rs .= sprintf('<td><input type="text" name="content[%1$s][data]%2$s[]" /></td>',
                    $r, $prop);
            $rs .= "</tr>";
        } else {
            foreach ($values as $val) {
                $rs .= "<tr>";
                $rs .= "<td class=\"plabel\">$label</td>";
                $escapedVal = htmlspecialchars($val->getObject());
                $rs .= sprintf( '<td><input type="text" name="content[%1$s][data]%2$s[]" value="%3$s" /></td>',
                        $r, $prop, $escapedVal);
                $rs .= "</tr>";
            }
        }
        return $rs;
    }

    /**
     * Render a subform for a property of a resource.
     *
     * @param  LibRDF_Node     $resourceURI The URI of the resource.
     * @param  LibRDF_Node  $prop        The URI of the property.
     * @return string
     */
    protected function _subform(LibRDF_Node $resourceURI, LibRDF_Node $prop) {
        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        $sublens = $this->_lensDef->getTarget($prop, new
                LibRDF_URINode(FRESNEL."sublens"));
        $link = $this->_lensDef->getTarget($prop, new
                LibRDF_URINode(FRESNEL."property"));
        try {
            $format = $this->_lensDef->getSource(new
                    LibRDF_URINode(FRESNEL."propertyFormatDomain"), $link);
            $label = $this->_lensDef->getTarget($format, new LibRDF_URINode(FRESNEL."label"));
        } catch(LibRDF_LookupError $e) {
            $label = "$prop";
        }
        try {
            $purpose = $this->_lensDef->getTarget($sublens, new
                    LibRDF_URINode(FRESNEL."purpose"));
        } catch(LibRDF_LookupError $e) {
            $purpose = null;
        }
        $values = $this->_data->findStatements($resourceURI, $link, null);
        $rs = '';
        if ($purpose and $purpose->isEqual(new LibRDF_URINode(LM."linkLens"))) {
            // LINK
            $rs .= $this->_link($resourceURI, $link, $sublens, $values);
        } else if ($values->current() === null) {
            try {
                $this->_lensDef->getTarget($prop, new
                        LibRDF_URINode(LM."optional", "true"));
            } catch (LibRDF_LookupError $e) {
                $child = new LibRDF_BlankNode();
                $c = substr($child, 1, strlen($child) - 2);
                $rem = sprintf('<input type="submit" class="remove" name="remove[%1$s][object]%2$s[%3$s]" value="X" />', $r, $link, $c);
                // Recursively render subforms
                $rs .= "<tr><td class=\"plabel\">$label";
                $rs .= sprintf('<input type="hidden" name="content[%1$s][object]%2$s[]" value="%3$s" /></td>',
                        $r, $link, $c);
                $rs .= '<td>';
                $rs .= $this->_form($sublens, $child, $rem);
                $rs .= '</td></tr>';
            }
            $rs .= sprintf('<tr><td colspan="2"><input type="submit" class="add" name="add[%1$s][object]%2$s" value="%3$s hinzufügen" /></td></tr>', $r, $link, $label);
        } else {
            foreach ($values as $val) {
                $child = $val->getObject();
                $c = substr($child, 1, strlen($child) - 2);
                $rs .= "<tr><td class=\"plabel\">$label";
                $rs .= sprintf('<input type="hidden" name="content[%1$s][object]%2$s[]" value="%3$s" /></td>',
                        $r, $link, $c);
                $rs .= '<td>';
                $rem = sprintf('<input type="submit" class="remove" name="remove[%1$s][object]%2$s[%3$s]" value="X" />', $r, $link, $c);
                $rs .= $this->_form($sublens, $child, $rem);
                $rs .= '</td></tr>';
            }
            $rs .= sprintf('<tr><td colspan="2"><input type="submit" class="add" name="add[%1$s][object]%2$s" value="%3$s hinzufügen" /></td></tr>', $r, $link, $label);
        }
        return $rs;
    }

    /**
     * Render a dropdown menu to link to another resource.
     *
     * @param  LibRDF_Node     $resourceURI The source resource.
     * @param  LibRDF_URINode  $link        The property, i.e. link.
     * @param  LibRDF_Node     $sublens     The sublens to use to display the options.
     * @param  mixed           $value       Optional, defaults to null.
     * @return string
     */
    protected function _link(
            LibRDF_Node $resourceURI,
            LibRDF_URINode $link,
            LibRDF_Node $sublens,
            $values) {
        try {
            $format = $this->_lensDef->getSource(new
                    LibRDF_URINode(FRESNEL."propertyFormatDomain"), $link);
            $label = $this->_lensDef->getTarget($format, new LibRDF_URINode(FRESNEL."label"));
        } catch(LibRDF_LookupError $e) {
            $label = "$link";
        }
        $rs = "";
        $r = substr($resourceURI, 1, strlen($resourceURI) - 2);
        try {
            $domain = $this->_lensDef->getTarget($sublens, new
                    LibRDF_URINode(FRESNEL."classLensDomain"));
            list ($dname, $dns) = $this->_nssplit($domain);
        } catch (LibRDF_LookupError $e) {
            $domain = null;
        }
        try {
            // Query for options
            $type = new LibRDF_URINode(RDF."type");
            $l = Phresnel::getLens($sublens);
            $l->loadResource(new LibRDF_BlankNode());
            $optModel = $l->getData();
            $options = $optModel->findStatements(null, $type, $domain);
            $lst = $this->_lensDef->getTarget($sublens, new
                    LibRDF_URINode(FRESNEL."showProperties"));
            $props = $this->_unlist($lst);
            // List selected options
            $selected = array();
            foreach ($values as $value) {
                $val = $value->getObject();
                $labelVals = array();
                foreach ($props as $prop) {
                    if ($prop instanceof LibRDF_URINode) {
                        try {
                            $labelVals[] = $optModel->getTarget($val, $prop);
                        } catch (LibRDF_LookupError $e) {
                        }
                    }
                }
                $val = substr($val, 1, strlen($val) - 2);
                if (empty($labelVals)) {
                    $labelVals[] = $val;
                }
                $rs .= '<tr><td>';
                $rs .= $label;
                $rs .= '</td><td>';
                $rs .= implode($labelVals, ", ");
                $rs .= sprintf('<input type="hidden" name="content[%1$s][object]%2$s[]" value="%3$s" />',
                        $r, $link, $val);
                $rs .= sprintf('<input type="submit" class="remove" name="remove[%1$s][object]%2$s[%3$s]" value="X" />', $r, $link, $val);
                $rs .= '</td>';
                $rs .= '</tr>';
                $selected[] = $val;
            }
            $available = array();
            foreach ($options as $option) {
                $uri = $option->getSubject();
                $u = substr($uri, 1, strlen($uri) - 2);
                if (in_array($u, $selected)) {
                    continue;
                }
                $labelVals = array();
                foreach ($props as $prop) {
                    if ($prop instanceof LibRDF_URINode) {
                        try {
                            $labelVals[] = $optModel->getTarget($option->getSubject(), $prop);
                        } catch (LibRDF_LookupError $e) {
                        }
                    }
                }
                if (empty($labelVals)) {
                    $labelVals[] = $u;
                }
                $available[$u] = implode($labelVals, ", ");
            }
        } catch(LibRDF_LookupError $e) {
            // Handle empty options, only list existing ones!
            // $this->_logger->logError($e);
        }
        if (!empty($available)) {
            $rs .= '<tr><td colspan="2">';
            $rs .= sprintf('<select name="link[%1$s][object]%2$s">', $r, $link);
            foreach ($available as $u => $l) {
                $rs .= "<option value=\"$u\">";
                $rs .= $l;
                $rs .= "</option>"; 
            }
            $rs .= '</select>';
            $rs .= '</td></tr>';
            $rs .= sprintf('<tr><td colspan="2"><input type="submit" class="add" name="add[%1$s][object]%2$s" value="%3$s hinzufügen" /></td></tr>', $r, $link, $label);
        }
        return $rs;
    }

}
