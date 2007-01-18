<?php
/**
 * Wsdl file.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the BSD License.
 *
 * Copyright(c) 2005 by Marcus Nyeholt. All rights reserved.
 *
 * To contact the author write to {@link mailto:tanus@users.sourceforge.net Marcus Nyeholt}
 * This file is part of the PRADO framework from {@link http://www.xisc.com}
 *
 * @author Marcus Nyeholt		<tanus@users.sourceforge.net>
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @version $Id$
 * @package System.Web.Services.SOAP
 */

/**
 * Contains the dom object used to build up the wsdl. The
 * operations generated by the generator are stored in here until the getWsdl()
 * method is called which builds and returns the generated XML string.
 * @author 		Marcus Nyeholt		<tanus@users.sourceforge.net>
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @version 	$Revision$
 */
class Wsdl
{
	/**
	 * The name of the service (usually the classname)
	 * @var 	string
	 */
	private $serviceName;

	/**
	 * The URI to find the service at. If empty, the current
	 * uri will be used (minus any query string)
	 */
	private $serviceUri;

	/**
	 * The complex types declarations
	 * @var 	ArrayObject
	 */
	private $types;


	/**
	 * A collection of SOAP operations
	 * @var 	array
	 */
	private $operations=array();

	/**
	 * Wsdl DOMDocument that's generated.
	 */
	private $wsdl = null;

	/**
	 * The definitions created for the WSDL
	 */
	private $definitions = null;

	/**
	 * The target namespace variable?
	 */
	private $targetNamespace ='';

	/**
	 * The binding style (default at the moment)
	 */
	private $bindingStyle = 'rpc';

	/**
	 * The binding uri
	 */
	private $bindingTransport = 'http://schemas.xmlsoap.org/soap/http';

	/**
	 * Creates a new Wsdl thing
	 * @param 	string		$name the name of the service.
	 * @param 	string		$serviceUri		The URI of the service that handles this WSDL
	 */
	public function __construct($name, $serviceUri='')
	{
		$this->serviceName = $name;
		if ($serviceUri == '') $serviceUri = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
		$this->serviceUri = str_replace('&amp;', '&', $serviceUri);
		$this->types = new ArrayObject();
		$this->targetNamespace = 'urn:'.$name.'wsdl';
	}

	public function getWsdl()
	{
		$this->buildWsdl();
		return $this->wsdl;
	}

	/**
	 * Generates the WSDL file into the $this->wsdl variable
	 */
	protected function buildWsdl()
	{
		$xml = '<?xml version="1.0" ?>
                 <definitions name="'.$this->serviceName.'" targetNamespace="'.$this->targetNamespace.'"
                     xmlns="http://schemas.xmlsoap.org/wsdl/"
                     xmlns:tns="'.$this->targetNamespace.'"
                     xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
                     xmlns:xsd="http://www.w3.org/2001/XMLSchema"
					 xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
                     xmlns:soap-enc="http://schemas.xmlsoap.org/soap/encoding/"></definitions>';

		$dom = new DOMDocument();
		$dom->loadXml($xml);
		$this->definitions = $dom->documentElement;

		$this->addTypes($dom);

		$this->addMessages($dom);
		$this->addPortTypes($dom);
		$this->addBindings($dom);
		$this->addService($dom);

		$this->wsdl = $dom->saveXML();
	}

	/**
	 * Adds complexType definitions to the document
	 * @param		DomDocument 		$dom		The document to add to
	 */
	public function addTypes(DomDocument $dom)
	{
		if (!count($this->types)) return;
		$types = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:types');
		$schema = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:schema');
		$schema->setAttribute('targetNamespace', $this->targetNamespace);
		foreach($this->types as $type => $elements)
		{
			$complexType = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:complexType');
			$complexType->setAttribute('name', $type);
			if(substr($type, strlen($type) - 5, 5) == 'Array')  // if it's an array
			{
				$complexContent = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:complexContent');
				$restriction = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:restriction');
				$restriction->setAttribute('base', 'soap-enc:Array');
				$attribute = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:attribute');
				$attribute->setAttribute('ref', "soap-enc:arrayType");
				$attribute->setAttribute('wsdl:arrayType', 'tns:' . substr($type, 0, strlen($type) - 5) . '[]');
				$restriction->appendChild($attribute);
				$complexContent->appendChild($restriction);
				$complexType->appendChild($complexContent);
			}
			else
			{
				$all = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:all');
				foreach($elements as $elem)
				{
					$e = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'xsd:element');
					$e->setAttribute('name', $elem['name']);
					$e->setAttribute('type', $elem['type']);
					$all->appendChild($e);
				}
				$complexType->appendChild($all);
			}
			$schema->appendChild($complexType);
			$types->appendChild($schema);
		}

		$this->definitions->appendChild($types);
	}

	/**
	 * Add messages for the service
	 * @param		DomDocument 		$dom		The document to add to
	 */
	protected function addMessages(DomDocument $dom)
	{
		foreach ($this->operations as $operation) {
			$operation->setMessageElements($this->definitions, $dom);
		}
	}

	/**
	 * Add the port types for the service
	 * @param		DomDocument 		$dom		The document to add to
	 */
	protected function addPortTypes(DOMDocument $dom)
	{
		$portType = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:portType');
		$portType->setAttribute('name', $this->serviceName.'PortType');

		$this->definitions->appendChild($portType);
		foreach ($this->operations as $operation) {
			$portOperation = $operation->getPortOperation($dom);
			$portType->appendChild($portOperation);
		}
	}

	/**
	 * Add the bindings for the service
	 * @param		DomDocument 		$dom		The document to add to
	 */
	protected function addBindings(DOMDocument $dom)
	{
		$binding = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:binding');
		$binding->setAttribute('name', $this->serviceName.'Binding');
		$binding->setAttribute('type', 'tns:'.$this->serviceName.'PortType');

		$soapBinding = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/soap/', 'soap:binding');
		$soapBinding->setAttribute('style', $this->bindingStyle);
		$soapBinding->setAttribute('transport', $this->bindingTransport);
		$binding->appendChild($soapBinding);

		$this->definitions->appendChild($binding);

		foreach ($this->operations as $operation) {
			$bindingOperation = $operation->getBindingOperation($dom, $this->targetNamespace, $this->bindingStyle);
			$binding->appendChild($bindingOperation);
		}
	}

	/**
	 * Add the service definition
	 * @param		DomDocument 		$dom		The document to add to
	 */
	protected function addService(DomDocument $dom)
	{
		$service = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:service');
		$service->setAttribute('name', $this->serviceName.'Service');

		$port = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/', 'wsdl:port');
		$port->setAttribute('name', $this->serviceName.'Port');
		$port->setAttribute('binding', 'tns:'.$this->serviceName.'Binding');

		$soapAddress = $dom->createElementNS('http://schemas.xmlsoap.org/wsdl/soap/', 'soap:address');
		$soapAddress->setAttribute('location', $this->serviceUri);
		$port->appendChild($soapAddress);

		$service->appendChild($port);

		$this->definitions->appendChild($service);
	}

	/**
	 * Adds an operation to have port types and bindings output
	 * @param 		WsdlOperation		$operation 		The operation to add
	 */
	public function addOperation(WsdlOperation $operation)
	{
		$this->operations[] = $operation;
	}

	/**
	 * Adds complexTypes to the wsdl
	 * @param string 	$type 	Name of the type
	 * @param Array		$elements	Elements of the type (each one is an associative array('name','type'))
	 */
	public function addComplexType($type, $elements)
	{
		$this->types[$type] = $elements;
	}
}
?>