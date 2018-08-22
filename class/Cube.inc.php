<?php


class Cube
{
	public $version;
	private $dimensionsetting;
	private $dimensionlist;
	private $facts;
	private $cells=[];
	public function __construct()
	{

	}

	public function setVersion($version)
	{
		$this->version=$version;
	}
	public function getVersion()
	{
		return $this->version;	
	}
	public function setDimensionList($dimensionlist)
	{		
		$this->dimensionlist=$dimensionlist;
	}
	public function getDimensionList($dimensionname='')
	{
		if($dimensionname=='')
		{
			return $this->dimensionlist;	
		}
		else
		{
			return $this->dimensionlist[$dimensionname];
		}
		
	}

	public function setFacts(&$facts)
	{
		return $this->facts=$facts;	
	}

	public function getFacts()
	{
		return $this->facts;
	}

	public function setDimensionSetting($dimensionsetting)
	{
		$this->dimensionsetting=$dimensionsetting;
	}

	public function getDimensionSetting($dimensionname='')
	{
		if($dimensionname=='')
		{
			return $this->dimensionsetting;	
		}
		else
		{
			return $this->dimensionsetting[$dimensionname];
		}
	}

	public function drawCube()
	{
		writedebug($this);
	}

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
	public function getCells($cubecomponent)
	{		

		// writedebug($this->cells,'all cells');
		$subfactscell=[];
		$filteredresult=[];

		// writedebug($this->cells);
		
		//no filter will return all, skip looping. count 1 = slice, count multiple as dice
		if(!isset($cubecomponent) || count($cubecomponent)==0 || $cubecomponent=='' )
		{
			return [];
		}


		//filter each dimension
		$dimensioncount=0;
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
			foreach($this->cells as $index => $cell)
			{
				// writedebug($index.'.'.$cell[$dimensionname],'loop this->cells, till '.$cell[$dimensionname]);
				if(in_array($cell[$dimensionname],$filteredresult))
				{
					// writedebug('<b style="color:green">inserted</b>');
					array_push($tmpcell,$cell['fact_id']);
				}
			}
			
			
			// writedebug($tmpcell,'end tmpcell');
			if($dimensioncount==0)
			{
				$subfactscell=$tmpcell;
			}
			else
			{
				$subfactscell=array_intersect($subfactscell,$tmpcell);
			}
			

			$dimensioncount++;
		}		
		

		// writedebug($subfactscell,'subfactscell');
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
		$cells=$this->getCells($cubecomponent);
		$subfacts=[];
		
		foreach($cells as $i =>$fact_id)
		{
			array_push($subfacts,$this->facts[$fact_id]);
		}

		return $subfacts;
		

	}


	/**
       * 
       * this function will summarise single dimension data according measures
       *
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
	public function rollUp($dimensionname,$measures,$addbundle=false)
	{

		$tmp_arr=$this->getDimensionList($dimensionname);

		//looping cell, transfer value into $res
		foreach($this->cells as $ci =>$cobj)
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


	

}