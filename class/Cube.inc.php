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
	public function getDimensionList()
	{
		return $this->dimensionlist=$dimensionlist;	
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
	public function getDimensionSetting()
	{
		return $this->dimensionsetting;
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

	public function rollUp($dimensionname,$measures,$callback='')
	{

		if($callback!='')
		{			
			call_user_func($callback,'sample data');
		}
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