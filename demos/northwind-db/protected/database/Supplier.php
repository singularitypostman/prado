<?php
/**
 * Auto generated by prado-cli.php on 2007-05-01 05:33:08.
 */
class Supplier extends TActiveRecord
{
	const TABLE='Suppliers';

	public $SupplierID;
	public $CompanyName;
	public $ContactName;
	public $ContactTitle;
	public $Address;
	public $City;
	public $Region;
	public $PostalCode;
	public $Country;
	public $Phone;
	public $Fax;
	public $HomePage;

	public $Products=array();

	public static $RELATIONS=array
	(
		'Products' => array(self::HAS_MANY, 'Product')
	);

	public static function finder($className=__CLASS__)
	{
		return parent::finder($className);
	}
}