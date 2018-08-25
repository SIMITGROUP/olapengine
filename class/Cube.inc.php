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
	public function setDimensionSetting($dimensionsetting,$compute=true)
	{		
		
		if($compute)
		{
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
		}
		else
		{
			$this->dimensionsetting=$dimensionsetting;
		}
		
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
			return $this->cells;
		}

		//filter each dimension
		$dimensioncount=0;
		// writedebug($cubecomponent,'cubecomponent');
		foreach($cubecomponent as $dimensionname => $filters)
		{		
			$tmpcell=[];
			$filteredresult=[];
			// writedebug($tmpcell,'begin tmpcell of '.$dimensionname);
			//make variable shorter
			$dimlist=&$this->dimensionlist[$dimensionname];

			foreach($dimlist as $i => $v)
			{
				$id=$v['dim_id'];
				$value=$v['dim_value'];
				//filter specific dimension with the parameter

				
				$res=$this->evaluateFilter($dimensionname,$value,$filters);

				// writedebug($dimensionname. ',dim_id='.$id .'. value='.$value.', filter='.print_r($filters,true) .'"'.$res.'"');
				if($res)
				{
					// writedebug('append dim_id='.$v['dim_id']);
					array_push($filteredresult,$v['dim_id']);
				}			

			}

			// writedebug($filteredresult,'filteredresult');
			//find out which cell is suitable from filtered result
			// writedebug($this->cells);
			foreach($this->cells as $index => $cell)
			{
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
			
			// writedebug($subfactscell,'subfactscell');

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
	public function getSubFacts($cubecomponent)
	{		
		// writedebug('','getSubFacts');
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
		if(isset($this->othersField[$column]))
		{

			$basedimension=$this->othersField[$column]['basedimension'];			
		} //it is basedimension
		else if(isset($this->dimensionsetting[$column]) && count($this->dimensionsetting[$basedimension]['hierarchy'])>0)
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
	  * this function use to identify drill up/drill down to which field name
	  * @param string $columnname column name in part of hierarchy
	  * @param string $hierarchyname=''  empty will use first hierarchy
	  * @param int $offsetlevel=1, off set how many level, 1 = drill down upper level (state => country), -1 to lower level (state => city)
	  * @return string $basedimensionname
	  */
	public function getNextLevelName($column,$hierarchyname='',$offsetlevel=-1)
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

		
				
		
		foreach($hierarchy as $hindex=>$hfield)
		{
		
			if($column==$hfield)
			{
			
				$currentlevel=$hindex;				
				// writedebug('currentlevel:'.$currentlevel,'currentlevel');
				$newlevel=$currentlevel+$offsetlevel;
				// writedebug('newlevel:'.$newlevel,'newlevel');

				if($newlevel==-1)
				{
					// writedebug($basecolumn);
					return $basecolumn;
				}
				if($newlevel<-1)
				{
					$this->errormsg=sprintf('"%s" found unexpected "%s" in hierarchy:',$column,$hfield);
					return false;
				}
				else
				{
					return $hierarchy[$newlevel];
				}
				


			}
		}
					$this->errormsg=sprintf("%s not found in any dimension hierarchy. ",$column);
					return false;			 	
	}


	

	/**
	 *
	 * this function will drilldown 1 or more level, perform aggregate on $measure, group by next level of hierarchy field
	 * @param string from dimensionname or hierarchy field, roll up from this field
	 * @param array $measures
	 * @return array $aggregatedresult
	 */

	public function drillDown($dimensionname,$filters,$measures,$level=1)
	{
		// $nextlevel=$level

		// $nextdimension=$this->getNextLevelName($column,$hierarchyname='',$offsetlevel=-1)

		return $this->aggregate($dimensionname,$measures,false);
	}



	 /**
        * 
        * this is function will roll up 1 or more level,  perform aggregate on $measures, group by next level of hierarchy field.
        * @param string from dimensionname or hierarchy field, roll up from this field
        * @param array $measures ['sales', ['field'=>'cost','agg'=>'sum'], ['field'=>'profit','callback'=>'callprofit']]
    	* @return array $aggregatedresult
        */
	public function rollUp($dimensionname,$measures,$level=1)
	{
		return $this->aggregate($dimensionname,$measures,false);
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
	public function aggregate($dimensionname,$measures,$filters=[],$addbundle=false)
	{

		// writedebug('','aggregate');
		$tmp_arr=[];		
		
		if($this->getDimensionSetting($dimensionname))
		{
			$tmp_arr=$this->getDimensionList($dimensionname);
		}
		$cells= $this->filterCells($filters);
	
		if(!$cells)
		{
			$this->errormsg='Error detected during filter cells, no cell return.';
			return false;
		}

		//looping cell, transfer value into $res
		foreach($cells as $ci =>$cobj)
		{
			$dim_id=$cobj[$dimensionname];
			$fact_id=$cobj['fact_id'];
			$row=$this->facts[$fact_id]; //fact record


			//summarise data, before draw output
			foreach($measures as $mi => $mobj)
			{

				if(gettype($mobj)!='array')
				{
					$mobj=['field'=>$mobj,'agg'=>'sum'];
				}

				if(isset($mobj['agg']))
				{						
					$field=$mobj['field'];
					
					if(!isset($tmp_arr[$dim_id][$field]['sum']))
					{
						$tmp_arr[$dim_id][$field]['sum']=0;
					}
					if(!isset($tmp_arr[$dim_id][$field]['count']))
					{
						$tmp_arr[$dim_id][$field]['count']=0;
					}

					$tmp_arr[$dim_id][$field]['sum']+=$row[$field];
					$tmp_arr[$dim_id][$field]['count']++;

					if(!isset($tmp_arr[$dim_id][$field]['max']) || $tmp_arr[$dim_id][$field]['max'] < $row[$field]  )
					{
						$tmp_arr[$dim_id][$field]['max']=$row[$field];	
					}
					if(!isset($tmp_arr[$dim_id][$field]['min']) || $tmp_arr[$dim_id][$field]['min'] > $row[$field]  )
					{
						$tmp_arr[$dim_id][$field]['min']=$row[$field];	
					}
					
					$tmp_arr[$dim_id][$field]['avg']=$tmp_arr[$dim_id][$field]['sum']/$tmp_arr[$dim_id][$field]['count'];
				}
				else if(isset($mobj['callback']) && $mobj['callback']!='') // use custom callback function
				{

					$field=$mobj['field'];
					$callback=$mobj['callback'];

					$tmp_arr[$dim_id][$field] = call_user_func($callback,$row,$tmp_arr[$dim_id]);
				}


				//add bundle data when needed
				if($addbundle)
				{
					$dimensionsseting=$this->getDimensionSetting($dimensionname);
					if(isset($dimensionsseting['bundlefield']))
					{
						foreach($dimensionsseting['bundlefield'] as $bi => $bundlefield)
						{
							$tmp_arr[$dim_id][$bundlefield] = $row[$bundlefield];
						}
					}					
				}
			}
		}
		
		if(!$tmp_arr)
		{
			return false;
		}
		
		//draw proper output
		$res=[];
		foreach($tmp_arr as $ti => $tobj)
		{
			$tmp=[];
			$tmp[$dimensionname]=$tobj['dim_value'];
			if(isset($dimensionsseting['bundlefield']))
			{
				foreach($dimensionsseting['bundlefield'] as $bi => $bundlefield)
				{
					$tmp[$bundlefield]=  $tobj[$bundlefield];
				}
			}


			foreach($measures as $mi => $mobj)
			{
				// if(!isset($tmp_arr[$ti]))
				// {
				// 	$tmp_arr[$ti]=[];
				// }

				// if(!isset($tmp_arr[$ti][$mobj]))
				// {
				// 	$tmp_arr[$ti][$mobj]=[];
				// }				

				// if(!isset($tmp_arr[$ti][$field]))
				// {
				// 	$tmp_arr[$ti][$field]=[];	
				// }

				if(gettype($mobj)!='array')
				{
					$tmp[$mobj]=$tmp_arr[$ti][$mobj]['sum'];
				}
				else if(isset($mobj['agg']))
				{
					$field=$mobj['field'];
					$agg=$mobj['agg'];
					$newfieldname=$field.'_'.$agg;
					$tmp[$newfieldname]=$tmp_arr[$ti][$field][$agg];
				}
				else if(isset($mobj['callback']) && $mobj['callback']!='') // use custom callback function
				{
					$field=$mobj['field'];
					$tmp[$field]=$tmp_arr[$ti][$field];
				}

			}
			array_push($res,$tmp);
		}		
		return $res;		
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