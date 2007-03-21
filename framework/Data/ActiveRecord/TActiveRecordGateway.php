<?php
/**
 * TActiveRecordGateway, TActiveRecordStatementType, TActiveRecordGatewayEventParameter classes file.
 *
 * @author Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @link http://www.pradosoft.com/
 * @copyright Copyright &copy; 2005-2007 PradoSoft
 * @license http://www.pradosoft.com/license/
 * @version $Id$
 * @package System.Data.ActiveRecord
 */

/**
 * TActiveRecordGateway excutes the SQL command queries and returns the data
 * record as arrays (for most finder methods).
 *
 * @author Wei Zhuo <weizho[at]gmail[dot]com>
 * @version $Id$
 * @package System.Data.ActiveRecord
 * @since 3.1
 */
class TActiveRecordGateway extends TComponent
{
	private $_manager;
	private $_tables=array(); //meta data cache.

	/**
	 * Constant name for specifying optional table name in TActiveRecord.
	 */
	const TABLE_CONST='TABLE';

	/**
	 * Record gateway constructor.
	 * @param TActiveRecordManager $manager
	 */
	public function __construct(TActiveRecordManager $manager)
	{
		$this->_manager=$manager;
	}

	/**
	 * @return TActiveRecordManager record manager.
	 */
	protected function getManager()
	{
		return $this->_manager;
	}

	/**
	 * Gets the table name from the 'TABLE' constant of the active record
	 * class if defined, otherwise use the class name as table name.
	 * @param TActiveRecord active record instance
	 * @return string table name for the given record class.
	 */
	public function getTableName(TActiveRecord $record)
	{
		$class = new ReflectionClass($record);
		if($class->hasConstant(self::TABLE_CONST))
		{
			$value = $class->getConstant(self::TABLE_CONST);
			if(empty($value))
				throw new TActiveRecordException('ar_invalid_tablename_property',
					get_class($record),self::TABLE_CONST);
			return $value;
		}
		else
			return strtolower(get_class($record));
	}

	/**
	 * @param TActiveRecord active record.
	 * @return TDbMetaData table meta data, null if not found.
	 */
	protected function getCachedMetaData($record)
	{
		$type=get_class($record);
		if(isset($this->_tables[$type]))
			return $this->_tables[$type];
		if(($cache=$this->getManager()->getCache())!==null)
		{
			//force loading of the table inspector to load the required classes
			// before unserializing cached meta data.
			$this->getManager()->getTableInspector($record->getDbConnection());
			$data = $cache->get($this->getMetaDataCacheKey($record));
			if($data !== false && $data !== null)
			{
				$this->_tables[$type] = $data;
				return $data;
			}
		}
	}

	/**
	 * @param TActiveRecord active record.
	 * @return string cache key, using connection string + record class name
	 */
	protected function getMetaDataCacheKey($record)
	{
		$conn = $record->getDbConnection()->getConnectionString();
		return $conn.':'.get_class($record);
	}

	/**
	 * Cache the meta data, tries the application cache if applicable.
	 * @param TActiveRecord active record.
	 * @param TDbMetaData table meta data
	 * @return TDbMetaData table meta data.
	 */
	protected function cacheMetaData($record,$data)
	{
		$type = get_class($record);
		if(($cache=$this->getManager()->getCache())!==null)
			$cache->set($this->getMetaDataCacheKey($record), $data);
		$this->_tables[$type] = $data;
		return $data;
	}

	/**
	 * Gets the meta data for given database and table.
	 */
	public function getMetaData(TActiveRecord $record)
	{
		$type=get_class($record);
		if(!($data = $this->getCachedMetaData($record)))
		{
			$conn = $record->getDbConnection();
			$inspector = $this->getManager()->getTableInspector($conn);
			$table = $this->getTableName($record);
			$meta = $inspector->getTableMetaData($table);
			if(count($meta->getColumns()) == 0)
				throw new TActiveRecordException('ar_invalid_table', $table);
			$data = $this->cacheMetaData($record,$meta);
		}
		return $data;
	}

	/**
	 * Returns record data matching the given primary key(s). If the table uses
	 * composite key, specify the name value pairs as an array.
	 * @param TActiveRecord active record instance.
	 * @param array primary name value pairs
	 * @return array record data
	 */
	public function findRecordByPK(TActiveRecord $record,$keys)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getFindByPkCommand($record->getDbConnection(),$keys);
		$this->raiseCommandEvent(TActiveRecordStatementType::Select,$command,$record,$keys);
		Prado::trace(get_class($record).'::FindRecordByPk('.var_export($keys,true).')', 'System.Data.ActiveRecord');
		return $meta->postQueryRow($command->queryRow());
	}

	/**
	 * Returns records matching the list of given primary keys.
	 * @param TActiveRecord active record instance.
	 * @param array list of primary name value pairs
	 * @return array matching data.
	 */
	public function findRecordsByPks(TActiveRecord $record, $keys)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getFindInPksCommand($record->getDbConnection(), $keys);
		$this->raiseCommandEvent(TActiveRecordStatementType::Select,$command,$record,$keys);
		Prado::trace(get_class($record).'::FindRecordsByPks('.var_export($keys,true).')', 'System.Data.ActiveRecord');
		return $meta->postQuery($command->query());
	}


	/**
	 * Returns record data matching the given critera. If $iterator is true, it will
	 * return multiple rows as TDbDataReader otherwise it returns the <b>first</b> row data.
	 * @param TActiveRecord active record finder instance.
	 * @param TActiveRecordCriteria search criteria.
	 * @param boolean true to return multiple rows as iterator, false returns first row.
	 * @return mixed matching data.
	 */
	public function findRecordsByCriteria(TActiveRecord $record, $criteria, $iterator=false)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getFindByCriteriaCommand($record->getDbConnection(),$criteria);
		$this->raiseCommandEvent(TActiveRecordStatementType::Select,$command,$record,$criteria);
		Prado::trace(get_class($record).'::FindRecordsByCriteria('.((string)$criteria).')', 'System.Data.ActiveRecord');
		return $iterator ? $meta->postQuery($command->query()) : $meta->postQueryRow($command->queryRow());
	}

	/**
	 * Return record data from sql query.
	 * @param TActiveRecord active record finder instance.
	 * @param string SQL string
	 * @param array query parameters.
	 * @return TDbDataReader result iterator.
	 */
	public function findRecordsBySql(TActiveRecord $record, $sql,$parameters=array())
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getFindBySqlCommand($record->getDbConnection(),$sql,$parameters);
		$this->raiseCommandEvent(TActiveRecordStatementType::Select,$command,$record,$parameters);
		Prado::trace(get_class($record).'::FindRecordsBySql('.var_export($parameters,true).')', 'System.Data.ActiveRecord');
		return $meta->postQuery($command->query());
	}

	/**
	 * Returns the number of records that match the given criteria.
	 * @param TActiveRecord active record finder instance.
	 * @param TActiveRecordCriteria search criteria
	 * @return int number of records.
	 */
	public function countRecords(TActiveRecord $record, $criteria)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getCountRecordsCommand($record->getDbConnection(),$criteria);
		$this->raiseCommandEvent(TActiveRecordStatementType::Select,$command,$record,$criteria);
		Prado::trace(get_class($record).'::CountRecords('.((string)$criteria).')', 'System.Data.ActiveRecord');
		return intval($command->queryScalar());
	}

	/**
	 * Insert a new record.
	 * @param TActiveRecord new record.
	 * @return int number of rows affected.
	 */
	public function insert(TActiveRecord $record)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getInsertCommand($record->getDbConnection(),$record);
		$this->raiseCommandEvent(TActiveRecordStatementType::Insert,$command,$record);
		Prado::trace(get_class($record).'::Insert()', 'System.Data.ActiveRecord');
		$rowsAffected = $command->execute();
		if($rowsAffected===1)
			$meta->updatePostInsert($record->getDbConnection(),$record);
		return $rowsAffected;
	}

	/**
	 * Update the record.
	 * @param TActiveRecord dirty record.
	 * @return int number of rows affected.
	 */
	public function update(TActiveRecord $record)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getUpdateCommand($record->getDbConnection(),$record);
		$this->raiseCommandEvent(TActiveRecordStatementType::Update,$command,$record);
		Prado::trace(get_class($record).'::Update()', 'System.Data.ActiveRecord');
		return $command->execute();
	}

	/**
	 * Delete the record.
	 * @param TActiveRecord record to be deleted.
	 * @return int number of rows affected.
	 */
	public function delete(TActiveRecord $record)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getDeleteCommand($record->getDbConnection(),$record);
		$this->raiseCommandEvent(TActiveRecordStatementType::Delete,$command,$record);
		Prado::trace(get_class($record).'::Delete()', 'System.Data.ActiveRecord');
		return $command->execute();
	}

	/**
	 * Delete multiple records using primary keys.
	 * @param TActiveRecord finder instance.
	 * @return int number of rows deleted.
	 */
	public function deleteRecordsByPk(TActiveRecord $record, $keys)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getDeleteByPkCommand($record->getDBConnection(),$keys);
		$this->raiseCommandEvent(TActiveRecordStatementType::Delete,$command,$record,$keys);
		Prado::trace(get_class($record).'::DeleteRecordsByPk('.var_export($keys,true).')', 'System.Data.ActiveRecord');
		return $command->execute();
	}

	/**
	 * Delete multiple records by criteria.
	 * @param TActiveRecord active record finder instance.
	 * @param TActiveRecordCriteria search criteria
	 * @return int number of records.
	 */
	public function deleteRecordsByCriteria(TActiveRecord $record, $criteria)
	{
		$meta = $this->getMetaData($record);
		$command = $meta->getDeleteByCriteriaCommand($record->getDBConnection(),$criteria);
		$this->raiseCommandEvent(TActiveRecordStatementType::Delete,$command,$record,$criteria);
		Prado::trace(get_class($record).'::DeleteRecordsByCriteria('.((string)$criteria).')', 'System.Data.ActiveRecord');
		return $command->execute();
	}

	/**
	 * Raise the corresponding command event, insert, update, delete or select.
	 * @param string command type
	 * @param TDbCommand sql command to be executed.
	 * @param TActiveRecord active record
	 * @param mixed data for the command.
	 */
	protected function raiseCommandEvent($type,$command,$record=null,$data=null)
	{
		$param = new TActiveRecordGatewayEventParameter($type,$command,$record,$data);
		$manager = $record->getRecordManager();
		$event = 'on'.$type;
		$manager->{$event}($param);
	}
}

/**
 * Command statement types.
 *
 * @author Wei Zhuo <weizho[at]gmail[dot]com>
 * @version $Id$
 * @package System.Data.ActiveRecord
 * @since 3.1
 */
class TActiveRecordStatementType
{
	const Insert='Insert';
	const Update='Update';
	const Delete='Delete';
	const Select='Select';
}

/**
 * Active Record command event parameter.
 *
 * @author Wei Zhuo <weizho[at]gmail[dot]com>
 * @version $Id$
 * @package System.Data.ActiveRecord
 * @since 3.1
 */
class TActiveRecordGatewayEventParameter extends TActiveRecordEventParameter
{
	private $_type;
	private $_command;
	private $_record;
	private $_data;

	/**
	 * New gateway command event parameter.
	 */
	public function __construct($type,$command,$record=null,$data=null)
	{
		$this->_type=$type;
		$this->_command=$command;
		$this->_data=$data;
		$this->_record=$record;
	}

	/**
	 * @return string TActiveRecordStateType
	 */
	public function getType()
	{
		return $this->_type;
	}

	/**
	 * @return TDbCommand command to be executed.
	 */
	public function getCommand()
	{
		return $this->_command;
	}

	/**
	 * @return TActiveRecord active record.
	 */
	public function getRecord()
	{
		return $this->_record;
	}

	/**
	 * @return mixed command data.
	 */
	public function getData()
	{
		return $this->_data;
	}
}

?>