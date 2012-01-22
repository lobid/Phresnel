<?php

/**
 * Copyright 2012 Felix Ostrowski
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

abstract class AbstractFormBoxModel extends AbstractBoxModel {

    /**
     * Convert data submitted by a form to a model.
     *
     * @param  array  $data
     * @return LibRDF_Model
     */
    public static function handlePostData($data) {
        $store = new LibRDF_Storage();
        $model = new LibRDF_Model($store);
        foreach($data['content'] as $resource => $statements) {
            if (substr($resource, 0, 1) === 'r') {
                $subject = new LibRDF_BlankNode($resource);
            } else {
                $subject = new LibRDF_URINode($resource);
            }
            if (array_key_exists('data', $statements)) {
                foreach($statements['data'] as $predicate => $objects) {
                    foreach($objects as $object) {
                        if ("" == $object) continue;
                        $model->addStatement(new LibRDF_Statement(
                                    $subject,
                                    new LibRDF_URINode($predicate),
                                    new LibRDF_LiteralNode($object)
                                    ));
                    }
                }
            }
            if (array_key_exists('object', $statements)) {
                foreach($statements['object'] as $predicate => $objects) {
                    foreach($objects as $object) {
                        if ("" == $object) continue;
                        if (substr($object, 0, 1) === 'r') {
                            $object = new LibRDF_BlankNode($object);
                        } else {
                            $object = new LibRDF_URINode($object);
                        }
                        $model->addStatement(new LibRDF_Statement(
                                    $subject,
                                    new LibRDF_URINode($predicate),
                                    $object
                                    ));
                    }
                }
            }
        }

        if (isset($data['remove'])) {
            $s = key($data['remove']);
            $cls = key($data['remove'][$s]);
            $p = key($data['remove'][$s][$cls]);
            $o = key($data['remove'][$s][$cls][$p]);

            if (substr($s, 0, 1) !== 'r') {
                $s = new LibRDF_URINode($s);
            } else {
                $s = new LibRDF_BlankNode($s);
            }

            if ('object' === $cls and substr($o, 0, 1) !== 'r') {
                $o = new LibRDF_URINode($o);
            } else if ('object' === $cls) {
                $o = new LibRDF_BlankNode($o);
            } else {
                $o = new LibRDF_LiteralNode($o);
            }

            $p = new LibRDF_URINode($p);

            foreach ($model->findStatements($s, $p, $o) as $statement) {
                $model->removeStatement($statement);
            }
        }

        if (isset($data['add'])) {
            $s = key($data['add']);
            $cls = key($data['add'][$s]);
            $p = key($data['add'][$s][$cls]);

            if (isset($data['link'][$s][$cls][$p])) {
                $o = new LibRDF_URINode($data['link'][$s][$cls][$p]);
            } else if ('object' === $cls) {
                $o = new LibRDF_BlankNode();
            } else {
                $o = new LibRDF_LiteralNode();
            }
            if (substr($s, 0, 1) != 'r') {
                $s = new LibRDF_URINode($s);
            } else {
                $s = new LibRDF_BlankNode($s);
            }
            $p = new LibRDF_URINode($p);
            $model->addStatement(new LibRDF_Statement($s, $p, $o));
        }
        return $model;
    }
}
