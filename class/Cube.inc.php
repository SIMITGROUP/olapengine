<?php


class Cube
{
	public $version;
	private $dimensionsetting;
	private $othersField;
	private $dimensionlist;
	private $facts;
	private $cells=[];
	private $errormsg;
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
	* define facts into current cube
	* @param array long facts, use reference to reduce memory usage
	*/
	public function setFacts(&$facts)
	{
		$this->facts=$facts;	
	}

  /**
	* get facts from current cube
	* @return return facts in this cube
	*/
	public function getFacts()
	{
		return $this->facts;
	}


	/**
	  * define dimension defination into this cube	  
	  * @param array dimension setting
	  *
	  */
	public function setDimensionSetting($dimensionsetting)
	{		
		$this->originaldimensionsetting=$dimensionsetting;
		
			$this->dimensionsetting=[];	
			foreach($dimensionsetting as $d => $dobj)
			{

				$this->dimensionsetting[$d]=$dobj;
				// writedebug($dobj['hierarchy'],$d.'=$dobj["hierarchy"]');
				//there is hierarchy, then add hierarchy field into dimension too
				if(isset($dobj['hierarchy']) && count($dobj['hierarchy'])>0)
				{
					foreach($dobj['hierarchy'] as $hierarchtype => $hierarchyarr)
					{
						foreach($hierarchyarr as $hi => $ho)
						{
							if(!isset($this->dimensionsetting[$ho]))
							{
								if(gettype($ho)=='array')
								{
									echo 'isarray';
									$this->dimensionsetting[$hi]=$ho;
								}
								else
								{									
									$type=gettype($ho);
									$this->dimensionsetting[$ho]=['type'=>$type,'basecolumn'=>$d,'field'=>$ho];
								}
							}
						}
					}
				}
			}
			//$this->dimensionsetting;//=$dimensionsetting;
		// }
		// else
		// {
		// 	$this->dimensionsetting=$dimensionsetting;
		// }
		
		// $this->setOthersField();
	}



	/**
	 * define others field beside dimension, include measures, bundle fields, hierarchy fields
	 * the info define here can be use for capture drill down info and etc.
	 *	 
	 */
	private function setOthersField()
	{
			$othersfield=[];
			foreach($this->facts[0] as $field => $value)
			{
				$tmp=[];		

				//predefine dimension				
				if( isset($this->dimensionsetting[$field])  &&  count($this->dimensionsetting[$field]) >0)
				{				
					;
				}
				else //others fields include bundles, hierarchy field, measures and etc
				{
					$type=gettype($value);				
					if($type=='string' && $this->isDate($value))
					{
						$type='date';
					}
						$tmp['type']=$type;

						//loop and check was defined as hierarchy field? then define hierarchy base column name, that for drill purpose only
						foreach($this->dimensionsetting as $d => $dobj)
						{							

							//idenfied is part of bundle field?
							if(isset($dobj['bundlefield']) && count($dobj['bundlefield'])>0)
							{
								//loop bundle column, tag it link to which base column
								foreach($dobj['bundlefield'] as $bi => $bundlefield)
								{
									if($bundlefield==$field)
									{
										$tmp['basecolumn']=$d;	
									}									
								}	
							}							
						}
				$othersfield[$field]=$tmp;
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


   /**
	 * append cell into cube
	 * @param array of dimension matrix like: {'region'=>1,'item'=>1,'fact_id'=>3 }
	*/
	public function addCell($cell)
	{
		$this->cells[]=$cell;
	}




	/**
       * 
       * this function will filter cells and return suitable cell index, the cells index array will later generate sub facts
       *
       * @param array $cubecomponent ['date'=>['2018-01-01',..], 'city'=>['KL',..],.. ]
       * @return array cells 
       */
	public function filterCells($cubecomponent=[])
	{		
		// writedebug($cubecomponent,'cubecomponent');
		// writedebug($this->cells,'all cells');
		$subfactscell=[];
		$filteredresult=[];
	
		
		//no filter will return all, skip looping. is string consider error
		if(!isset($cubecomponent) || $cubecomponent==''  || gettype($cubecomponent)=='string')
		{


			$this->errormsg='You shall filter Cells with array.';			

			return false;
		}

		if(count($cubecomponent)==0)
		{	
			$tmpcell=[];
			foreach($this->cells as $index => $cell)
			{
				
					// writedebug('<b style="color:green">inserted</b>');
					array_push($tmpcell,$cell['fact_id']);
				
			}


			return $tmpcell;
		}

		//filter each dimension
		$dimensioncount=0;
		// writedebug($cubecomponent,'cubecomponent');
		foreach($cubecomponent as $dimensionname => $filters)
		{		

			$tmpcell=[];
			$filteredresult=[];
			// writedebug($dimensionname,'begin loop');
			//make variable shorter
			// writedebug($this->getDimensionList(),'getDimensionList');
			$dimlist=$this->getDimensionList($dimensionname);
			// writedebug($dimlist,'dimlist');
			foreach($dimlist as $i => $v)
			{
				$id=$v['dim_id'];
				$value=$v['dim_value'];
				//filter specific dimension with the parameter

				
				$res=$this->evaluateFilter($dimensionname,$value,$filters);
				// writedebug($v,'v');

				if($res)
				{					
					array_push($filteredresult,$v['dim_id']);				
				}
			}
			// writedebug($filteredresult,'filteredresult');
			// writedebug($filteredresult,'filteredresult');
			//find out which cell is suitable from filtered result
			// writedebug($this->cells);
			foreach($this->cells as $index => $cell)
			{
				// writedebug($cell,'cell');
				// writedebug($filteredresult,'filteredresult');
				if(in_array($cell[$dimensionname],$filteredresult))
				{
					// writedebug('<b style="color:green">inserted</b>');
					array_push($tmpcell,$cell['fact_id']);
				}
			}
			
			
			// writedebug($tmpcell,'<br/>end tmpcell');
			if($dimensioncount==0)
			{
				$subfactscell=$tmpcell;
			}
			else
			{
				$subfactscell=array_intersect($subfactscell,$tmpcell);
			}
			
			// writedebug($tmpcell,'tmpcell');

			$dimensioncount++;
		}		
		

		return $subfactscell;

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
		
		if(count($cubecomponent)==0 )
		{
			return $this->facts;
		}

		$cells=$this->filterCells($cubecomponent);

		$subfacts=[];
		
		foreach($cells as $i =>$fact_id)
		{
			array_push($subfacts,$this->facts[$fact_id]);
		}
		return $subfacts;	
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
	


	private function dimensionsExistsInWhichRow($rowarr=[],$dimensionarr=[])
	{
		// writedebug($rowarr,'rowarr');
		// writedebug($dimensionarr,'dimensionarr');
		foreach($rowarr as $rownum=>$row)
		{
			$isexists=true;
			foreach($dimensionarr as $dimensionname => $dimensionvalue)
			{
				// writedebug('$row[$dimensionname] => $dimensionvalue==='.$row[$dimensionname].' => '.$dimensionvalue,'compare');
				if($row[$dimensionname] != $dimensionvalue)
				{
					// writedebug('not match');
					$isexists=false;
					//not found similar, continue next row
					continue;
				}
				else
				{
					// writedebug('matched');
				}

			}//finish compare all dimension, if all match then isexists maintain true, it mean found same record
			// writedebug("'".$exists."'",'exists');
			if($isexists==true)
			{
				// writedebug($exists,'exists=true');
				return $rownum;	
			}
			else
			{
				// writedebug($exists,'not exists, continue');
				continue;
			}
			
			
		}

		//finish loop, not similar found
		return -1;

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


	public function aggregateByMultiDimension($arrdimension,$measures,$filters=[],$addbundle=false)
	{
		$cells= $this->filterCells($filters);
		// writedebug($cells,'$cells---...');
		$res=[];

		foreach($cells as $i => $r)
		{
			// writedebug($r,'$r');
			$cell=$this->cells[$r];

			$tmprow=[];
			$dimensionvalueset=[];
			//put extract group by column from cell value
			foreach($arrdimension as $di=>$dimensionname)
			{
				$dimensionvalueset[$dimensionname]=$cell[$dimensionname];
				$tmprow[$dimensionname]=$this->getDimensionValue($dimensionname,$cell[$dimensionname]);
			}


			
			$existsInRowNum=$this->dimensionsExistsInWhichRow($res,$tmprow);
			$factrow=$this->facts[$cell['fact_id']];

			//exists, update existing row
			if($existsInRowNum>=0)
			{
				$lastrecord=$res[$existsInRowNum];

				foreach($measures as $mi => $mobj)
				{

					if(gettype($mobj)!='array') //no submit agg type, default as sum
					{						
						$mobj=['field'=>$mobj,'agg'=>'sum'];
					}
					$field=$mobj['field'];
					
					if(isset($mobj['agg'])) //run build in aggregation
					{
						$val='';

						switch($mobj['agg'])
						{
							case 'sum':
								$val=$factrow[$field]+$lastrecord[$field.'_sum'];
								break;
							case 'max':
								if($factrow[$field]<=$lastrecord[$field.'_max'])
								{
									$val=$lastrecord[$field.'_max'];
								}
								else
								{
									$val=$factrow[$field];
								}								
								break;
							case 'min':
								if($factrow[$field]>=$lastrecord[$field.'_min'])
								{
									$val=$lastrecord[$field.'_min'];
								}
								else
								{
									$val=$factrow[$field];
								}	
								break;
							case 'avg':
								//$tmprow['aggregate_rowcount']=$lastrecord['aggregate_rowcount']+1;
								$val=$factrow[$field] / $tmprow['aggregate_rowcount'] ;
								break;
							break;													
							case 'count':
								$lastrecord[$field.'_count']++;
							break;							
						}
						$tmprow[$mobj['field'].'_'.$mobj['agg']]=$val;
					}
					else
					{						
						$callback=$mobj['callback'];
						$tmprow[$mobj['field']]=call_user_func($callback,$factrow,$tmprow[$mobj['field']]);	
					}
				}

				$res[$existsInRowNum]=$tmprow;
			}
			else //not exists, assign new row
			{				

				foreach($measures as $mi => $mobj)
				{
					if(gettype($mobj)!='array') //no submit agg type, default as sum
					{						
						$mobj=['field'=>$mobj,'agg'=>'sum'];
					}

					
					if(isset($mobj['agg'])) //run build in aggregation
					{
						$val='';
						switch($mobj['agg'])
						{
							case 'sum':
							case 'max':
							case 'min':							
								$val=$factrow[$mobj['field']];
							break;													
							case 'avg':
								$val=$factrow[$mobj['field']];
								// $tmprow['aggregate_rowcount']=1;
							break;
							case 'count':
								$val=1;
							break;							
						}
						$tmprow[$mobj['field'].'_'.$mobj['agg']]=$val;

						
					}
					else
					{						
						$callback=$mobj['callback'];
						$tmprow[$mobj['field']]=call_user_func($callback,$factrow,$tmprow[$mobj['field']]);	
					}

				}
				
				// writedebug($tmprow,'$tmprow');
				array_push($res,$tmprow);
			}			
		}						


		//convert dimension dim_id become value
		// writedebug($res,'res');
		return $res;
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
	public function aggregate($dimensionname,$measures,$filters=[])
	{
		$arrdimension=[];
		array_push($arrdimension,$dimensionname);
		$res =$this->aggregateByMultiDimension($arrdimension,$measures,$filters);

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

	public function drillDown($dimensionname,$measures,$filters,$level=1)
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
	public function rollUp($dimensionname,$measures,$filters,$level=1)
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
		$fieldtype=$this->dimensionsetting[$dimensionname]['type'];
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


}