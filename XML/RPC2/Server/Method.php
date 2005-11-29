<?php
/* LICENSE AGREEMENT. If folded, press za here to unfold and read license {{{ 
   vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4 foldmethod=marker:    
   +-----------------------------------------------------------------------------+
   | Copyright (c) 2004 S�rgio Gon�alves Carvalho                                |
   +-----------------------------------------------------------------------------+
   | This file is part of XML_RPC2.                                              |
   |                                                                             |
   | XML_RPC is free software; you can redistribute it and/or modify             |
   | it under the terms of the GNU Lesser General Public License as published by |
   | the Free Software Foundation; either version 2.1 of the License, or         |
   | (at your option) any later version.                                         |
   |                                                                             |
   | XML_RPC2 is distributed in the hope that it will be useful,         |
   | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
   | GNU Lesser General Public License for more details.                         |
   |                                                                             |
   | You should have received a copy of the GNU Lesser General Public License    |
   | along with XML_RPC2; if not, write to the Free Software             |
   | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA                    |
   | 02111-1307 USA                                                              |
   +-----------------------------------------------------------------------------+
   | Author: S�rgio Carvalho <sergio.carvalho@portugalmail.com>                  |
   +-----------------------------------------------------------------------------+
}}} */      
/**
 * XML_RPC2_Server_Method
 *
 * @package XML_RPC2
 * @author Sergio Carvalho <sergio.carvalho@portugalmail.com>
**/
/** 
 */
/* dependencies {{{ */
require_once 'XML/RPC2/Exception.php';
/* }}} */
/**
 * Class representing an XML-RPC exported method. 
 *
 * This class is used internally by XML_RPC2_Server. External users of the 
 * package should not need to ever instantiate XML_RPC2_Server_Method
 *
 * @package XML_RPC2
 * @author Sergio Carvalho <sergio.carvalho@portugalmail.com>
 * @see XML_RPC2_ServerMethodHandler
**/
class XML_RPC2_Server_Method
{
    /* fields {{{ */
    /** Method signature parameters */
    public $parameters;
    /** Method signature return type */
    public $returns ;
    /** Method help, for introspection */
    public $help;
    /* }}} */
    /* internalMethod field {{{ */
    /** Method name in PHP-land */
    protected $_internalMethod;
    /** internalMethod getter 
     * 
     * @return string internalMethod
     */
    public function getInternalMethod() 
    {
        return $this->_internalMethod;
    }
    /** internalMethod setter 
     * 
     * @param string internalMethod
     */
    public function setInternalMethod($value)
    {
        $this->_internalMethod = $value;
    }
    /* }}} */
    /* hidden field {{{ */
    /** True if the method is hidden */
    protected $_hidden;
    /** hidden getter
     * 
     * @return boolean hidden value
     */
    public function isHidden() 
    {
        return $this->_hidden;
    }
    /** hidden setter
     * 
     * @param boolean hidden value
     */
    public function setHidden($hidden) 
    {
        $this->_hidden = $hidden;
    }
    /* }}} */
    /* name Field {{{ */
    /** External method name */
    protected $_name;
    /**
     * _name getter
     *
     * @return string _name
     */
    public function getName() 
    {
        return $this->_name;
    }
    /**
     * _name setter
     *
     * @param string _name
     */
    public function setName($name) 
    {
        $this->_name = $name;
    }
    /* }}} */
    /* constructor {{{ */
    /**
     * Create a new XML-RPC method by introspecting a PHP method
     *
     * @param ReflectionMethod The PHP method to introspect
     */
    public function __construct(ReflectionMethod $method, $defaultPrefix)
    {
        $hidden = false;
        $docs = $method->getDocComment();
        if (!$docs) {
            $hidden = true;
        }
        $docs = explode("\n", $docs);

        $parameters = array();
        $methodname = null;
        $returns = 'mixed';
        $shortdesc = '';
        $paramcount = -1;
        $prefix = $defaultPrefix;

        // Extract info from Docblock
        $paramDocs = array();
        foreach ($docs as $i => $doc) {
            $doc = trim($doc, " \r\t/*");
            if (strlen($doc) && strpos($doc, '@') !== 0) {
                if ($shortdesc) {
                    $shortdesc .= "\n";
                }
                $shortdesc .= $doc;
                continue;
            }
            if (strpos($doc, '@xmlrpc.hidden') === 0) {
                $hidden = true;
            }
            if ((strpos($doc, '@xmlrpc.prefix') === 0) && preg_match('/@xmlrpc.prefix( )*(.*)/', $doc, $matches)) {
                $prefix = $matches[2];
            }
            if ((strpos($doc, '@xmlrpc.methodname') === 0) && preg_match('/@xmlrpc.prefix( )*(.*)/', $doc, $matches)) {
                $methodname = $matches[2];
            }
            if (strpos($doc, '@param') === 0) { // Save doctag for usage later when filling parameters
                $paramDocs[] = $doc;
            }

            if (strpos($doc, '@return') === 0) {
                $param = preg_split("/\s+/", $doc);
                if (isset($param[1])) {
                    $param = $param[1];
                    $returns = $param;
                }
            }
        }

        // Fill in info for each method parameter
        foreach ($method->getParameters() as $parameterIndex => $parameter) {
            // Parameter defaults
            $newParameter = array('optional' => false, 'type' => 'mixed');

            // Attempt to extract type and doc from docblock
            if (array_key_exists($parameterIndex, $paramDocs) &&
                preg_match('/@param\s+(\S+)(\s+(.+))/', $paramDocs[$parameterIndex], $matches)) {
                if (strpos($matches[1], '|')) {
                    $newParameter['type'] = explode('|', $matches[1]);
                } else {
                    $newParameter['type'] = $matches[1];
                }
                $newParameter['doc'] = $matches[2];
            }

            // Attempt to extract optional status from Reflection API
            if (method_exists($method, 'isOptional')) {
                $newParameter['optional'] = $parameter->isOptional();
            }

            // Attempt to extract type from Reflection API
            if ($parameter->getClass()) {
                $newParameter['type'] = $parameter->getClass();
            }

            $parameters[$parameter->getName()] = $newParameter;
        }

        if (is_null($methodname)) {
            $methodname = $prefix . $method->getName();
        }

        $this->setInternalMethod($method->getName());
        $this->parameters = $parameters;
        $this->returns  = $returns;
        $this->help = $shortdesc;
        $this->setName($methodname);
        $this->setHidden($hidden);
    }
    /* }}} */
    /* matchesSignature {{{ */
    /** 
     * Check if method matches provided call signature 
     * 
     * Compare the provided call signature with this methods' signature and
     * return true iff they match.
     *
     * @param  string Signature to compare method name
     * @param  array  Array of parameter values for method call.
     * @return boolean True if call matches signature, false otherwise
     */
    public function matchesSignature($methodName, $callParams)
    {
        if ($methodName != $this->getName()) return false;
        $paramIndex = 0;
        foreach($this->parameters as $param) {
            if (!($param['optional'] || array_key_exists($paramIndex, $callParams))) { // Missing non-optional param
                return false;
            }
            if ((array_key_exists($paramIndex, $callParams)) &&
                (!($param['type'] == 'mixed' || $param['type'] == gettype($callParams[$paramIndex])))) {
                return false;
            }
        }
        return true;
    }
    /* }}} */
    /* autoDocument {{{ */
    /**
     * Return HTML snippet documenting method, for XML-RPC server introspection.
     *
     * @return string HTML snippet documenting method
     */
    public function autoDocument()
    {
        $result = '<dl><dt>Method description</dt><dd>' . $this->help . '</dd>';
        $result .= '<dt>Method parameters</dt><dd><dl>';
        foreach ($this->parameters as $paramName => $param) {
            $result .= '<dt><i>' . $param['type'] . "</i>$paramName</dt><dd>";
            if ($param['optional']) $result .= '[optional]';
            $result .= $param['doc'];
            $result .= '</dd>';
        }
        $result .= '</dl></dd>';
        $result .= '<dt>Returns</dt><dd><i>' . $this->returns . '</i></dd>';
        $result .= '</dl>';

        return $result;
    }
    /* }}} */
}
?>