<?php


class Cube
{
	public $version;
	private $dimensionsetting;
	public $status;	
	private $dimensionlist;
	private $sorts;
	private $cells = null;
	private $errormsg;
	private $othersField;
	private $originaldimensionsetting;
	private $aggdivider='__';
	private $storageengine='sqlite';
	private $connectioninfo;
	private $supportedstoragengine=array('sqlite');
	private $db;
	private $lastinsert;
	private $lastread;
	private $expiedduration=60*15; //15 minute consider expired
	public function __construct()
	{

	}



	/**
     * 
     * return error msg 
     *
     * @return string errormsg
     */
	public function getError()
	{
		return $this->errormsg;
	}

	/**
	  * set version number of olapengine
	  * @param string $version
	  * @return string $version
	  */
	public function setVersion($version)
	{
		$this->version=$version;
	}
	
	/**
	  * get version number of olapengine
	  * @return string $version
	  */
	public function getVersion()
	{
		return $this->version;	
	}


	/**
	 * detach PDO db connection
	 */
	public function detachPDO()
	{
		unset($this->db);
	}
	/**
	  * set storage engine of cells
	  * @param string $type (array, sqlite, clickhouse, mysql)
	  * @return bool
	  */
	public function setStorageEngine($type)
	{
		if(in_array($type,$this->supportedstoragengine))
		{
			$this->storageengine=$type;
			return true;
		}
		else
		{
			$this->msg='"'.$type .'" is not supported storage engine';
			return false;
		}
	}
	/**
	  * get storage engine of cells
	  * @return string storageengine
	  */
	public function getStorageEngine()
	{			
			return $this->storageengine;
		
	}
	/**
	  * define distinct dimension data into this cube	  
	  * @param array dimension data
	  *
	  */
	public function setDimensionList($dimensionlist=[])
	{						
		$this->dimensionlist=$dimensionlist;
	}


	/**
	  * get distinct dimension data from this cube	  
	  * @param string get distinct value from dimension name, leave empty to get all dimension
	  * @return array dimension data
	  */
	public function getDimensionList($dimensionname='')
	{
		if($dimensionname=='')
		{
			return $this->dimensionlist;	
		}
		else
		{
			if(isset($this->dimensionlist[$dimensionname]))
			{
				return $this->dimensionlist[$dimensionname];	
			}
			else
			{
				return false;
			}
			
		}
		
	}

   /**
  	* get all fields from cube, included dimension, measures and others field 
  	* @return array $fieldlist
  	*/
  public function getAllFields()
  {
  	$fields=$this->dimensionsetting;
  	foreach($this->othersField as $f=>$fobj)
  	{
  		if(!isset($fields[$f]))
  		{

  			if(isset($fobj['basecolumn']) || $fobj['datatype']=='measure')
  			{

  				$fields[$f]=$fobj;		
  			}
  			
  		}
  		
  	}  	
  	return $fields;
  }
 


	/**
	  * define dimension defination into this cube	  
	  * @param array dimension setting
	  *
	  */
	public function setDimensionSetting($dimensionsetting)
	{		
		//keep original dimension setting 
		$this->originaldimensionsetting=$dimensionsetting;		
		//flatten hierarchy dimension setting, mean parent/child all consider as individual dimension
		$this->dimensionsetting=[];	
		foreach($dimensionsetting as $d => $dobj)
		{

			$this->dimensionsetting[$d]=$dobj;
			//create date hierarchy when 'datehierarchy' defined
			//"Document Date":{"type":"date","datatype":"dimension","datehierarchy":["period","quarter","year"]},
			/*

	"Item Code":{"type":"string","datatype":"dimension", 
                    "bundlefield": ["Item Name"],
                     "hierarchy": {
                              "default": {
                                           "Category Code":{"type":"string","datatype":"dimension", "bundlefield": ["Category Name"]}                                                  
                                         }
                        }
		},
			*/
			if($dobj['type'] == 'date' && isset($dobj['datehierarchy']))
			{
				$tmphierarchy=[];
				foreach($dobj['datehierarchy'] as $date_no => $datetype)
				{
					$tmpfieldname=$d.'@'.$datetype;
					$tmpdimensionsetting=['datatype'=>'dimension','type'=>'string','dateformat'=>$datetype];
					$tmphierarchy[$tmpfieldname]=$tmpdimensionsetting;
				}
				$dobj['hierarchy']=['default'=>$tmphierarchy];
				$this->dimensionsetting[$d]=$dobj;
			}



			if(isset($dobj['hierarchy']) && count($dobj['hierarchy'])>0)
			{
				foreach($dobj['hierarchy'] as $hierarchtype => $hierarchyarr)
				{
					foreach($hierarchyarr as $hi => $ho)
					{

						if(gettype($ho)=='array')
						{
							if(!isset($this->dimensionsetting[$hi]))
							{
								$this->dimensionsetting[$hi]=$ho;
								$this->dimensionsetting[$hi]['basecolumn']=$d;
								$this->dimensionsetting[$hi]['datatype']='dimension';
							}
							
						}
						else if(gettype($ho)=='object')
						{
							if(!isset($this->dimensionsetting[$hi]))
							{
								$this->dimensionsetting[$hi]=(array)$ho;
								$this->dimensionsetting[$hi]['basecolumn']=$d;
								$this->dimensionsetting[$hi]['datatype']='dimension';
							}
						}
						else
						{	

							if(isset($this->dimensionsetting[$ho]))
							{
							}
							else
							{
								$this->dimensionsetting[$hi]=['type'=>$type,'basecolumn'=>$d,'field'=>$ho,'datatype'=>'dimension'];
							}							
						}						
					}
				}
			}
		}		
	}


	/**
	 * define others field beside dimension, include measures, bundle fields, hierarchy fields
	 * the info define here can be use for capture drill down info and etc.
	 *	 
	 */
	public function setOthersField($othersfield=[])
	{

		foreach($this->dimensionsetting as $d => $dobj)
		{							

			//idenfied is part of bundle field?
			if(isset($dobj['bundlefield']) && count($dobj['bundlefield'])>0)
			{				
				foreach($dobj['bundlefield'] as $bi => $fieldname)
				{
					if(!isset($othersField[$fieldname]))
					{
						$othersfield[$fieldname]=['type'=>'string','datatype'=>'other','basecolumn'=>$d];
					}
					else
					{
						$othersfield[$fieldname]['basecolumn']=$d;
					}	
				}
			}							
		}


			$this->othersField=$othersfield;
	}



    /**
	  * get distinct dimension setting from this cube	  
	  * @param string dimensionname, get setting from the dimension name, leave empty to get all dimension
	  * @return array dimension setting
	  */

	public function getOrigimalDimensionSetting()
	{
		return $this->originaldimensionsetting;
	}



	/**
	  * get distinct dimension setting from this cube	  
	  * @param string dimensionname, get setting from the dimension name, leave empty to get all dimension
	  * @return array dimension setting
	  */

	public function getDimensionSetting($dimensionname='')
	{
		if($dimensionname=='')
		{
			return $this->dimensionsetting;	
		}
		else
		{
			if(isset($this->dimensionsetting[$dimensionname]))
			{
				return $this->dimensionsetting[$dimensionname];
			}
			else
			{
				return false;		
			}
			
		}
	}


	public function generateCreateTableSQLite()
	{
		$sql="CREATE TABLE IF NOT EXISTS celltable ( fact_id INTEGER PRIMARY KEY";
		 //dimension all will convert become integer
		 foreach($this->dimensionsetting as $field => $fieldsetting)
		 {					
		 	$fieldtype=$this->getFieldType($field);
		 	if($fieldtype=='date')
		 	{
		 		$sql.=",`$field` TEXT ";
		 	}
		 	else
		 	{
		 		$sql.=",`$field` INTEGER ";
		 	}
			 
			 
		 }

		 //others field will remain as it is
		 foreach($this->othersField as $field => $fieldsetting)
		 {
		 	//skip bundle item
		 	if(isset($fieldsetting['basecolumn']))
		 	{
		 		continue;
		 	}
			 $dbfieldtype='';
			 switch($fieldsetting['type'])
			 {
				 case 'integer':
					 $dbfieldtype='INTEGER';
				 break;
				 case 'double':
					 $dbfieldtype='NUMERIC';
				 break;
				 case 'date':
				 case 'string':
				 default:
					 $dbfieldtype='TEXT';
				 break;
			 }
			 $sql.=",`$field` $dbfieldtype ";					
		 }
		 $sql.=');';	

		 return $sql;
	}

	/**
	 * use to connect several cell's storage engine with PDO
	 * return $pdo connection
	 */
	public function connectDB()
	{
		// echo "connect db\n";
		switch($this->storageengine)
		{
			case 'array':
				$this->msg="array is not database";
				return false;
			break;
			case 'sqlite':		

			if(!$this->db )
			{
				try {
				 	// $this->db = new PDO('sqlite:/tmp/cube.db');
				 	$this->db = new PDO('sqlite::memory:');
					$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
					 $sqlchecktableexists="SELECT name FROM sqlite_master WHERE type='table' AND name='celltable'";
					$tableexists=false;
					foreach($this->db->query($sqlchecktableexists) as $row)
					{
						$tableexists=true;
					}

					 if(!$tableexists)
					 {					 

						 $sql=$this->generateCreateTableSQLite();									
						 $this->db->exec($sql); 										
						 foreach($this->dimensionsetting as $field => $fieldsetting)
						 {					
							 $sqlindex="CREATE INDEX `$field` ON celltable($field)";
							 $this->db->exec($sqlindex);										
						 }	
	 
					 return true;			
				   }
				 }
				  catch(PDOException $e) {
					    // Print PDOException message
				    $this->msg=$e->getMessage();
				    return false;
				  }
			}
			break;
		}
	}

	/**
	* use to dump sqlite in-memory cell database into file
	* return bool
	*/
	public function exportSQLiteDB($filename='')
	{
		if($filename=='')
		{
			$this->msg="exportSQLDB required to define destinate database filename";
			return false;		 
		}
		//prepare physical database file
		$backupdb = new PDO('sqlite:'.$filename);

		$this->db->exec("ATTACH '$filename' as backupdb");
		$this->db->exec("CREATE TABLE backupdb.celltable AS SELECT * from celltable");
		$this->db->exec("DETACH backupdb");
		return true;
	}

	/**
	* use to restore sqlite in-memory cell database into file
	* return bool
	*/
	public function restoreSQLiteDB($filename)
	{
		if($filename=='')
		{
			$this->msg="restoreSQLiteDB required to define destinate database filename";
			return false;		 
		}

		if(!file_exists($filename))
		{
			$this->msg="'$filename' does not exists";
			return false;
		}

		//prepare memory database
		$this->db = new PDO('sqlite::memory:');
		$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->db->exec("ATTACH '$filename' as backupdb");
		$this->db->exec("CREATE TABLE celltable AS SELECT * from backupdb.celltable");
		$this->db->exec("DETACH backupdb");

		$sql="SELECT COUNT(*) as rowcount FROM celltable";
		
		foreach($this->db->query($sql) as $row)
		{
			// echo 'imported row count:'.$row['rowcount'];
		}
	}
   /**
	 * append cell into cube
	 * @param array of dimension matrix like: {'region'=>1,'item'=>1,'fact_id'=>3 }
	*/
	public function addCell($cell)
	{
		//if using database engine, but connector not yet initialize, build connector
		if($this->storageengine!='array' && !$this->db)
		{
			 if(!$this->connectDB())
			 {
			 	// echo "cannot connect db\n";
			 }
		}		
		switch($this->storageengine)
		{
			case 'sqlite':
				$newcellvalue=[];
				foreach(array_values($cell) as $cellvalue)
				{					
					// array_push($newcellvalue, str_replace("'", "\'", $cellvalue) );
					array_push($newcellvalue, SQLite3::escapeString($cellvalue) );
					
				}


				$columns = "`".implode('`,`', array_keys($cell))."`";
				// $values = "'".implode("','", array_values($cell))."'";
				$values = "'".implode("','" , $newcellvalue)."'";
				$sql = "INSERT INTO celltable($columns) values($values)";

				// echo $sql."\n\n";
				// $stmt = $this->db->prepare($sql);

				$this->db->exec($sql);
				return true;
			break;			
			default:
				$this->msg='Currently using storage engine "'.$this->storageengine.'" which is not allow to add cell';
				return false;
			break;
				
		}
		
	}


	public function optimizeMemory()
	{
		// foreach($this->originaldimensionsetting as $fieldname => $dim_obj)
		// {
		// 		$arrcount=count($this->dimensionlist[$fieldname]);
		// 		$tmparr=new SplFixedArray($arrcount);
		// 		foreach($this->dimensionlist[$fieldname] as $i => $o)
		// 		{
		// 			$tmparr[$i]=$o;
		// 		}
		// 		unset($this->dimensionlist[$fieldname]);
		// 		$this->dimensionlist[$fieldname]=$tmparr;
		// }
		

	}


	/**
	 * add row to cube, it will auto add cell, create suitable dimension
	 * @param array &$row 
	 * @return book success or failed
	 *
	 */
	public function addRow($num,$row)
	{
		$this->lastinsert=time();
		$this->lastread=$this->lastinsert;
		$cell=['fact_id'=>$num];	
		$dim_id='';
		//only run at first row to create others field

		foreach($this->dimensionsetting as $fieldname => $dim_obj)
		{
			
			
			if(strpos($fieldname, '@') !== false)
			{
				   $arrfieldname=explode('@', $fieldname);
				   $tmpfieldname=$arrfieldname[0];
				   $valueformat=$arrfieldname[1];
				   $tmpvalue=$row[$tmpfieldname];
				   switch($valueformat)
				   {
				   		case 'year':
				   			$dimensionvalue=$this->getYearFromDate($tmpvalue);
				   		break;
				   		case 'period':
				   			$dimensionvalue=$this->getPeriodFromDate($tmpvalue);
				   		break;
				   		case 'month':
				   			$dimensionvalue=$this->getMonthFromDate($tmpvalue);
				   		break;
				   		case 'quarter':
				   			$dimensionvalue=$this->getQuarterFromDate($tmpvalue);
				   		break;
				   		case 'week':
				   			$dimensionvalue=$this->getWeekFromDate($tmpvalue);
				   		break;
				   		case 'weekday':
				   			$dimensionvalue=$this->getWeekDayFromDate($tmpvalue);
				   		break;
				   		default:

				   		break;
				   }
			}
			else
			{
				$dimensionvalue=$row[$fieldname];
			}
			
			
			if(!isset($this->dimensionlist[$fieldname]))
			{
				$this->dimensionlist[$fieldname]=[];
			}
			
			$dimid_res=$this->getDimensionID($dimensionvalue,$this->dimensionlist[$fieldname]);
			$dim_id=$dimid_res['dim_id'];
			if($dim_id==-1)
			{	
				//get new dimension key, date will use date as dim_id, others string will use integer
				$fieldtype=$this->getFieldType($fieldname);
				if($fieldtype!='' && $fieldtype!='date')
				{
					$dim_id=$dimid_res['max_no']+1; //count($this->dimensionlist[$fieldname]);					
				}
				else
				{
					$dim_id=$dimensionvalue;
				}

				
				$obj=['dim_id'=>$dim_id, 'dim_value'=>$dimensionvalue];

				if(isset($dim_obj['bundlefield']))
				{
					foreach($dim_obj['bundlefield'] as $bundlenum => $bundlefieldname)
					{
						$obj[$bundlefieldname] = $row[$bundlefieldname];
					}	
				}
				


				if($fieldtype!='' && $fieldtype!='date')
				{
					array_push($this->dimensionlist[$fieldname],$obj);
				}
				else
				{
					// $obj['year']=$this->getYearFromDate($dimensionvalue);
					// $obj['period']=$this->getPeriodFromDate($dimensionvalue);
					// $obj['month']=$this->getMonthFromDate($dimensionvalue);					
					// $obj['quarter']=$this->getQuarterFromDate($dimensionvalue);
					// $obj['week']=$this->getWeekFromDate($dimensionvalue);
					// $obj['day']=$this->getWeekDayFromDate($dimensionvalue);

					$this->dimensionlist[$fieldname][$dimensionvalue]=$obj;
				}
				
			}
			$cell[$fieldname]=$dim_id;
		}

		foreach($this->othersField as $fieldname=>$othersobj)
		{
			if(isset($this->othersField[$fieldname]['basecolumn']))
			{
				continue;	
			}


			$val=$row[$fieldname];
			if($this->othersField[$fieldname]['type'] == 'integer')
			{
				$val=(int)$val;
			}
			else if ($this->othersField[$fieldname]['type'] == 'double')
			{
				$val=(double)$val;
			}

			//got define base column mean bundle field, then no need to put the data into cell
			
			
			$cell[$fieldname]=$row[$fieldname];
		}

			$this->addCell($cell);

	}

	public function getMonthFromDate($date) 
	{
	 return  date('m', strtotime($date));
	}
	public function getPeriodFromDate($date) 
	{
	 return  date('Y-m', strtotime($date));
	}
	
	public function getYearFromDate($date) 
	{
	  return date('Y', strtotime($date));
	}

	public function getQuarterFromDate($date) {
		$monthnumber = (int)date('m', strtotime($date));
	  	return  $this->getYearFromDate($date).'-Q'.(floor(($monthnumber - 1) / 3) + 1 );
	}

	public function getWeekFromDate($date) 
	{
		return (int)date('W', strtotime($date));	  	
	}
	public function getWeekDayFromDate($date) 
	{	    

		return strtoupper(substr(date('l', strtotime($date)),0,3));
	}

	private function getDimensionIDFromBundle($columnname,$search,$array)
	{
		$a=0;
		$usercolumnname=false;
		foreach($array as $a => $o)
		{
			//detech see whether use bundle field or dim_value
			if($a==0)
			{
								
				foreach($o as $bundlefieldname => $bundlevalue)
				{
					if($columnname==$bundlefieldname)
					{
						//mean, will use column name to search
						$usercolumnname=true;
						break;
					}
				}
				

				if(!$usercolumnname)
				{
					$columnname='dim_value';
				}

			}

			if($search==$o[$columnname])
			{
				return array('dim_id'=>$o['dim_id']);
			}


		}
		return array('dim_id'=>-1,'max_no'=>$a);
	}


	private function getDimensionID($search,$array)
	{
		$lineno=-1;
		foreach($array as $a => $o)
		{

			if($search==$o['dim_value'])
			{
				return array('dim_id'=>$o['dim_id']);
			}

			$lineno++;

		}
		return array('dim_id'=>-1,'max_no'=>$lineno);
	}


	public function setDataset($dataset_id='')
	{
		$this->dataset_id=$dataset_id;
	}

	public function getDataset()
	{
			return $this->dataset_id;
	}


	private function generateWhereString($filters=[])
	{

		$rangefilter=[]; 
		$filterstrarr=[];
		$newfilters=[];
			foreach($filters as $field => $filterarr)
			{
				//loop filter to take out range filter (if exists)
				foreach($filterarr as $filternum => $filtervalue)
				{
					
					//if value is array, mean it is something like ['from'=>'1','to'=>2]
					if(is_array($filtervalue))
					{

						$rangefilter[$field]=[];
						if(isset($filtervalue['from']))
						{
							$rangefilter[$field]['from']=$filtervalue['from'];
						}
						if(isset($filtervalue['to']))
						{
							$rangefilter[$field]['to']=$filtervalue['to'];
						}

						unset($filters[$field][$filternum]);
					}
					else
					{
						//if it is dimension, use dimension list directly to filter (except date cause date will use native data)
						$dimensionlist=$this->getDimensionList($field);
						
						if($dimensionlist && $this->getFieldType($field)!='date')
						{
							$dimensionrecord=$this->getDimensionID($filtervalue,$dimensionlist);
							$newfilters[$field][$filternum]=$dimensionrecord['dim_id'];
						}
						//if it is not dimension, but it is bundle field of dimension, then filter with bundle value
						else if( isset($this->othersField[$field]) && isset($this->othersField[$field]['basecolumn']))
						{
							
							$basecolumn=$this->othersField[$field]['basecolumn'];
							
							$dimensionlist=$this->getDimensionList($basecolumn);
							
							
							$dimensionrecord=$this->getDimensionIDFromBundle($field,$filtervalue,$dimensionlist);

							
							$field=$basecolumn;
							if($dimensionrecord['dim_id']>-1)
							{
								if(!isset($newfilters[$field]))
								{
									$newfilters[$field]=[];
								}
								$newfilters[$field][$filternum]=$dimensionrecord['dim_id'];
							}
						}
						else
						{
							if(!isset($newfilters[$field]))
								{
									$newfilters[$field]=[];
								}
							$newfilters[$field][$filternum]=$filtervalue;
						}		
					}					
				}
				if(isset($newfilters[$field]) && count($newfilters[$field])>0)
				{
					$filterstrarr[$field] = '"'.implode('","', $newfilters[$field]).'"';	
				}			
			}

			

			$wherestr='';
			foreach($filterstrarr as $field => $filter)
			{
				if($wherestr=='')
				{
					$wherestr=sprintf('`%s` IN (%s)',$field,$filter);	
				}
				else
				{
					$wherestr .= ' AND '. sprintf('`%s` IN (%s)',$field,$filter);		
				}
				
			}

			foreach($rangefilter as $field =>$range)
			{
				if($wherestr=='')
				{
					$wherestr = sprintf('`%s` BETWEEN "%s" AND "%s" ',$field,$range['from'],$range['to']);		
				}
				else
				{
					$wherestr .= ' AND '. sprintf('`%s` BETWEEN "%s" AND "%s" ',$field,$range['from'],$range['to']);		
				}
			}

			if($wherestr!='')
			{
				$wherestr=' WHERE  ' .$wherestr;
			}
		return $wherestr;
	}

	private function getBaseColumn($col)
	{
		if(isset($this->othersField[$col]['basecolumn']))
		{
			return $this->othersField[$col]['basecolumn'];	
		}
		else if(isset($this->dimensionlist[$col]['basecolumn']))
		{
			return $this->dimensionlist[$col]['basecolumn'];
		}
		else
		{
			return false;
		}
	}

	private function convertToDimension($groupbycolumns)
	{
		$columns=[];
		foreach($groupbycolumns as $i => $col)
		{			
			//dimension is defined
			if(isset($this->dimensionlist[$col]))
			{				
				array_push($columns,$col);
			}
			else if($this->getBaseColumn($col)) //not dimension, but inside othersField
			{

				$basecolumn=$this->getBaseColumn($col);
				array_push($columns, $basecolumn);
			}
			else
			{
				echo "$col not found in dimension and others field\n";
				//not supported column, so not pull the record
			}
		}
		return $columns;
	}


	private function convertQueryAsSQL($groupbycolumns,$measures,$filters=[])
	{
		
		$fieldstr='';
		$groupbystr='';
		$dimensionstr='';
		$wherestr='';
		
		$arrdimension=$this->convertToDimension($groupbycolumns);
		//remove empty dimension if found
		foreach($arrdimension as $i => $d)
		{
			if($d=='')
			{
				unset($arrdimension[$i]);
			}
		}

		//prepare group by string
		if(count($arrdimension)>0)
		{			
			$groupbystr=' GROUP  BY `'.implode('`,`', $arrdimension).'`';
			$dimensionstr='`'.implode('`,`', $arrdimension).'`';
		}
		else
		{
			$groupbystr='';
		}


		//compute measurable fieldname
		$measurestr='';
		if(count($measures)>0)
		{
		
			foreach($measures as $i => $measure)
			{
				//not array, mean no define agg, make default as sum
				if(!is_array($measure))
				{
					$measure= ['field'=>$measure, 'agg'=>'sum'];
				}
				

				if(isset($measure['field']) && ( isset($measure['agg'])  || isset($measure['querystr'])) )
				{
					if(!isset($measure['agg']))
					{
						$measure['agg']='';
					}
					if(!in_array($measure['agg'],['sum','max','min','avg','count']))
					{
						
						//use custom aggregate 
						if($measure['agg']=='')
						{
							if(!isset($measure['querystr']) || $measure['querystr']=='') 
							{
								$this->msg = 'Parameter querystr is not defined for "'.$measure['field'].'", you required that if you not define "agg" parameter.';
								return false;
							}
							else
							{
								$swapquerystr=$this->replaceDimensionValueFromString($measure['querystr']);
								if($measurestr=='')
								{
									$measurestr = '('. $swapquerystr .') AS `' . $measure['field'].'`';
								}	
								else
								{
									$measurestr.= ','.'('. $swapquerystr  .') AS `' . $measure['field'].'`';
								}
							}
						}
						else  //wrongly define agg method
						{
							$this->msg = 'Aggregate "'.$measure['agg'].'" is not supported, standard aggregate only sum/max/min/avg/count is supported.';
							return false;
						}
						

						
					}
					else
					{
						if($measurestr=='')
						{
							$measurestr =  $measure['agg'].'(`'.$measure['field'] .'`) AS `' . $measure['field'].$this->aggdivider.$measure['agg'].'`';
						}	
						else
						{
							$measurestr.= ','.$measure['agg'].'(`'.$measure['field'] .'`) AS `' . $measure['field'].$this->aggdivider.$measure['agg'].'`';
						}
					}					
				}
			}
		}
		

		//generate all fields
		if($dimensionstr!='')
		{
			$fieldstr=$dimensionstr;
		}
		
		if($fieldstr =='' && $measurestr!='')
		{
			$fieldstr=$measurestr;
		}
		else if($fieldstr !='' && $measurestr!='')
		{
			$fieldstr.=','.$measurestr;
		}
		
		$wherestr = $this->generateWhereString($filters);

		$sortstr='';		
		//temporary not using having
		$havingstr='';

		//temporary not support limit
		$limitstr='';
		$sql = sprintf("SELECT %s,count(*) as recordcount  FROM celltable %s %s %s %s %s",$fieldstr,$wherestr, $groupbystr, $havingstr,$sortstr, $limitstr);

		// echo $sql ."\n";
		return $sql;
	}

	public function getBundleValueFromDimensionId($arrdimension,$row)
	{
		//find out all dimension, and get all dimension and bundle value
		$dimensionvalue=[];
		foreach($row as $field => $value)
		{		
			//field is dimension	
			if(isset($this->dimensionlist[$field]))
			{

				//prepare all suitable column
				$dimensionvalue[$field]=$this->dimensionlist[$field][$value]['dim_value'];
				foreach($this->dimensionlist[$field][$value] as $col => $colvalue)
				{
					if(!in_array($col, ['dim_id','dim_value']))
					{
						$dimensionvalue[$col]=$colvalue;
					}
				} 

				//if the dimension is not desire column, remove it
				if(!in_array($field,$arrdimension))
				{
					unset($row[$field]);
				}
				else
				{
					$row[$field]=$this->dimensionlist[$field][$value]['dim_value'];
				}
			}			
		}

		//append required dimension value (normally bundle item) into row
		foreach($arrdimension as $i => $col)
		{
			// echo "append column $col into row\n";
			if($col=='')
			{
				continue;
			}
			else
			{
				$row[$col]=
					$dimensionvalue[$col];
			}
		}
		
			return $row;
	}

	
	public function isExpired()
	{
		$now=time();
		$expiredtime=$this->lastread + $this->expiedduration;
		if($now > $expiredtime)
		{
			return true;
		}
		else
		{
			return false;		
		}
	}
	public function getLastRead()
	{
		return $this->lastread;
	}

	public function aggregateByMultiDimension($arrdimension,$measures,$filters=[],$sorts=[],$addbundle=false)
	{
		$this->lastread=time();
		$sql = $this->convertQueryAsSQL($arrdimension,$measures,$filters);
		$res=[];
		$q=$this->db->query($sql,PDO::FETCH_ASSOC);
		// print_r($q);	
		foreach ($q as $rownum=> $row )
		{		

			$tmp=$this->getBundleValueFromDimensionId($arrdimension,$row);

			array_push($res,$tmp);
		}
		

		$this->sorts=$sorts;

		if(count($sorts)>0)
		{
			
			usort($res,array($this,'compareObjectValue'));	
		}
		return $res;
	}



	/**
       * 
       * this function will return facts filter by dimension, it use for slice and dice a cube
       *
       * @param array $cubecomponent ['date'=>['2018-01-01',..], 'city'=>['KL',..],.. ]
       * @return array cells 
       */
	public function getSubFacts($cubecomponent=[])
	{			
		$wherestr = $this->generateWhereString($cubecomponent);
		$sql= sprintf("SELECT * FROM celltable %s", $wherestr);
		$q=$this->db->query($sql,PDO::FETCH_ASSOC);
		$res=[];
		foreach ($q as $rownum=> $row )
		{			
			$tmp=[];
			foreach($row as $col =>$value )
			{
				$dimensionlist=$this->getDimensionList($col);
				if($dimensionlist)
				{
					$tmp[$col]=$dimensionlist[$value]['dim_value'];
				}
				else
				{
					$tmp[$col]=$value;
				}
			}
			
			array_push($res,$tmp);
		}
		return $res;
	}


	/**
	* this function use to identify the hierarchy base dimension, example city is base dimension of country and region
	* @param $column as column name to identify the base column
	* @return $string basedimension
	*/
	public function getBaseField($column)
	{
		$basedimension='';
		//in others field? If yes get the base dimension and get the hierarchy
		if(isset($this->dimensionsetting[$column]) && isset($this->dimensionsetting[$column]['basecolumn']))
		{
			$basedimension=$this->dimensionsetting[$column]['basecolumn'];			
		} //it is basedimension
		else if(isset($this->dimensionsetting[$column]) && count($this->dimensionsetting[$column]['hierarchy'])>0)
		{			
				$basedimension=$column;				
		}
		else //supplied $column not belong to any hierarchy, it dont have next level and no support drill up/down
		{			
			$this->errormsg=sprintf("%s not found in any dimension and hierarchy. ",$column);
			return false;
		}

		return $basedimension;
	}
	


	/**
	 * this function convert dimension dim_id become value
	 * @param string $dimensionname 
	 * @param mix $dimensionvalue
	 * @param mix value of that id
	*/

	private function getDimensionValue($dimensionname,$dim_id=-1)
	{
		$dimarr=$this->getDimensionList($dimensionname);
		if($dim_id==-1 || !isset( $dimarr[$dim_id]))
		{
			$this->errormsg="Invalid value for ".$dim_id;
			return false;
		}
		else
		{
			$val=$dimarr[$dim_id]['dim_value'];	
			return $val;
		}
		
	}



	
	 /**
       * 
       * this function will summarise single dimension data according measures
       * @param $dimensionname dimension or hierarchy fieldname
       * @param array $measures ['sales', ['field'=>'cost','agg'=>'sum'], ['field'=>'profit','callback'=>'callprofit']]
       *        call back have will return 2 variable, 1st variable is currentrow and 2nd is broughforward aggregate result
       *		you can create a function similar like:
       *			function callback1($row,$broughforward)
	   *			{
	   *					return $broughforward['sales']['sum']-$broughforward['cost']['sum'] ;
	   *				}
       *      return result will assign to field 'profit'.
       * @return mix
       */
	public function aggregate($dimensionname,$measures,$filters=[],$sorts=[])
	{
		$arrdimension=[];
		array_push($arrdimension,$dimensionname);
		$res =$this->aggregateByMultiDimension($arrdimension,$measures,$filters,$sorts);

		// writedebug($res,'res');
		return $res;			
	}


	/**
	 *
	 * this function will drilldown 1 or more level, perform aggregate on $measure, group by next level of hierarchy field
	 * @param string from dimensionname or hierarchy field, roll up from this field
	 * @param array $measures
	 * @return array $aggregatedresult
	 */

	public function drillDown($dimensionname,$measures,$filters,$sorts=[],$level=1)
	{		
		$nextdimensionname=$this->getNextLevelName($dimensionname,'drilldown','',$level);
		return $this->aggregate($nextdimensionname,$measures,$filters);
	}



	 /**
        * 
        * this is function will roll up 1 or more level,  perform aggregate on $measures, group by next level of hierarchy field.
        * @param string from dimensionname or hierarchy field, roll up from this field
        * @param array $measures ['sales', ['field'=>'cost','agg'=>'sum'], ['field'=>'profit','callback'=>'callprofit']]
    	* @return array $aggregatedresult
        */
	public function rollUp($dimensionname,$measures,$filters,$sorts=[],$level=1)
	{
		
		$nextdimensionname=$this->getNextLevelName($dimensionname,'rollup','',$level);		
		return $this->aggregate($nextdimensionname,$measures,$filters);
	} 

/**
	  * this function use to identify drill up/drill down to which field name
	  * @param string $columnname column name in part of hierarchy
	  * @param string $hierarchyname=''  empty will use first hierarchy
	  * @param int $offsetlevel, switch how many level, 1 = roll up to upper level (state => country), -1 drill down to lower level (state => city)
	  * @return string $basedimensionname
	  */
	public function getNextLevelName($column,$type='',$hierarchyname='',$level=1)
	{
		
		$basedimension=$this->getBaseField($column);		
		$hierarchy=[];
		//get suitable hierarchy
		if($hierarchyname!='')
		{
			$hierarchy=$this->dimensionsetting[$basedimension]['hierarchy'];
		}
		else
		{

			foreach($this->dimensionsetting[$basedimension]['hierarchy'] as $h =>$hierarchy)
			{			
				break;
			}
		}

		$hierarchycount=count($hierarchy);
		$position=array_search($column, $hierarchy);



		if($type=='drilldown')
		{
			//if it is at lowest hierarchy, and it is drill down, then return same column	
			if($column==$basedimension)
			{
				return $column;
			}			
			//return base level when it found in first level
			else if($position==0)
			{
				return $basedimension;				
			}
			else
			{
				$newlevel=$position-$level;
				return $hierarchy[$newlevel];
			}



		}
		else
		{

			if($position==($hierarchycount-1) && $type=='rollup')
			{
				return $column;
			}
			//selected column is base dimension, roll up to 1st level
			else if($column==$basedimension)
			{
				$newlevel=($level-1);
				return $hierarchy[$newlevel];
			}
			else
			{				
				$newlevel=$position+$level;
				return $hierarchy[$newlevel];
				
			}
		}
			
	}

	/**
	* this function will loop through dimension and others field to identify field type
	* @param string fieldname
	* @return string fieldtype
	*/
	private function getFieldType($fieldname)
	{
		$fieldtype='';
		if(isset($this->dimensionsetting[$fieldname]) )
		{
			$fieldtype=$this->dimensionsetting[$fieldname]['type'];	
		}
		else if(isset($this->dimensionsetting[$fieldname]) )
		{
			$fieldtype=$this->othersField[$fieldname]['type'];		
		}		
		return $fieldtype;

	}
     /**
       * 
       * this is private function use to evaluate user's filter
       *
       * @param string $dimensionname, a column name
       * @param mixed $value use to compare the dimension
       * @param array of filters with different condition      
       * @return bool
       */
	private function evaluateFilter($dimensionname,$value,$filters)
	{
		if(isset($this->dimensionsetting[$dimensionname]['type']))
		{
			$fieldtype=$this->dimensionsetting[$dimensionname]['type'];	
		}
		else
		{
			$fieldtype=$this->othersField[$dimensionname]['type'];		
		}
		
		foreach($filters as $i => $f)
		{			
			//select all, or match
			if($f=='*' || $f==$value)
			{				
				return true;
			}
			//range filter
			else if(gettype($f)=='array' && isset($f['from']) && isset($f['to']))
			{
				//date have special process
				if($fieldtype=='date')
				{
					//assume it is year
					if(strlen($f['from'])==4)
					{
						$f['from'].='-01-01';
					}
					if(strlen($f['to'])==4)
					{
						$f['to'].='-12-31';	
					}

					if(strlen($f['from'])==7)
					{
						$f['from'].='-01';
					}
					if(strlen($f['to'])==7)
					{
						$f['to'].='-31';	
					}

					if($value>=$f['from'] && $value <= $f['to'])
					{
						return true;
					}		
				}
				else
				{
					if($value>=$f['from'] && $value <= $f['to'])
					{
						return true;
					}
				}

			}
		}
		//no condition match
		return false;
	}

	
	public function drawCube()
	{
		writedebug($this);
	}


	private function isDate($date)
	{
		$datetimeformat='Y-m-d H:i:s';
		$dateformat='Y-m-d';
	    $d1 = DateTime::createFromFormat($datetimeformat, $date);
	    $d2 = DateTime::createFromFormat($dateformat, $date);
	    // echo $d1->format($datetimeformat);
	    if( $d1 && $d1->format($datetimeformat) == $date)
	    {
	    	return true;
	    }
	    else if( $d2 && $d2->format($dateformat) == $date)
	    {
	    	return true;
	    }
	    else
	    {
	    	return false;
	    }
	}


	private function compareObjectValue($a, $b)
	{
		$r=0;
		// print_r($this->sorts);
		foreach($this->sorts as $i => $sorts)
		{
			foreach($sorts as $field => $sortmethod)
			{



				$fieldname=str_replace(':', '__', $field);

				if(strpos($field, ':') !== false) 
				{
					
					
						if($a[$fieldname] == $b[$fieldname])
						{
							$r=0;
						}
						else
						{
							if(strtolower($sortmethod)=='desc')
							{
								$r=-1* ($a[$fieldname] - $b[$fieldname]);
							}
							else
							{
								$r =$a[$fieldname] - $b[$fieldname];;
							}
							
						}

				}
				else
				{
					
					if(strtolower($sortmethod)=='desc')
					{
						$r= -1 * strcmp( $a[$fieldname] , $b[$fieldname]);
					}
					else
					{
						$r=  strcmp($a[$fieldname] ,$b[$fieldname]);
					}

				}
				

				if($r !=0 || $r!='0')
				{
					return $r;
				}
			}
		}		
		return 0;	    
	}


	private function replaceDimensionValueFromString($longstring)
	{

			

			$matches = array();
			preg_match_all('/\{([^}]+)\}/', $longstring, $matches);
			$arrdimension=$matches[1];
			$newmatch=[];
			$str=$longstring;
			foreach($arrdimension as $num => $d)
			{
				$dv = explode('|', $d);
				$dimensionname=$dv[0];
				$dimensionvalue=$dv[1];

				
				$dim_id=$this->getDimensionID($dimensionvalue,$this->dimensionlist[$dimensionname])['dim_id'];
				if($dim_id>-1)
				{
					$str=str_replace($matches[0][$num], $dim_id, $str);	
				}
				else //try explore is it a bundle field
				{
					if(isset($this->othersField[$dimensionname]['basecolumn']))
					{
						$bundlefieldname=$dimensionname;
						$dimensionname=$this->othersField[$dimensionname]['basecolumn'];
						$filtervalue=$dimensionvalue;

						// echo "<pre>".print_r($this->dimensionlist[$dimensionname],true)."</pre>";
						$dim_id=$this->getDimensionIDFromBundle($bundlefieldname,$filtervalue,$this->dimensionlist[$dimensionname])['dim_id'];
						if($dim_id>-1)
						{
							$str=str_replace($matches[0][$num], $dim_id, $str);	
						}
						
					}
					else //it is not dimension and bundle field, direct replace regular expression become value
					{
						$str=str_replace($matches[0][$num], $dimensionvalue, $str);	
					}
				}
				

				//
			}



			
			return $str;

	}
}