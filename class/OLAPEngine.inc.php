<?php
/**
 * OLAP Engine is main entrance point of php developer, developer include this file to perform OLAP analysis
 *
 * @author     kstan <kstan@simitgroup.com>
 */
ini_set('opcache.enable', 0);
include __DIR__.'/Cube.inc.php';

/**
 * OLAPEngine class
 */

class OLAPEngine
{	
	private $version='0.1';
	/**
	 * OLAPEngine constructor
	 */
	public function __construct()
	{

	}


	/**
       * 
       * Create Olap Cube, through Cube.inc.php. Facts is 1 flat php hash table, $dimensions is array descript the dimension & hierarchy,
       * $measures declare all measures.
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]
       * @return object cube object
       */
	public function createCube(&$facts,&$dimensions)
	{
		$cube = new Cube();

		if(count($dimensions)==0 || count($facts)==0)
		{
			return false;
		}
		else
		{
			$this->dimensionsetting=$dimensions;

			
			$cube->setVersion($this->version);
			$cube->setFacts($facts);
			$cube->setDimensionSetting($this->dimensionsetting);
			
			$dimensiondata=$this->generateDistinctDimensionSchema($dimensions);			
			foreach($facts as $i => $row)
			{
				$res=$this->prepareDimensionMasterList($dimensiondata,$i,$row);
				$dimensiondata=$res['data'];
				$cube->addCell($res['cell']);
				
			}
			$cube->setDimensionList($dimensiondata);
			
		}		
		return $cube;
		
	}

	/**
       * 
       * Create blank hierarchy dimension database, for use later
       *
       * @param array $dimension array of dimension

       * @return blankdimensionarray
       */
	private function generateDistinctDimensionSchema($dim)
	{
		$d=[];		
		
		foreach($dim as $i => $do)
		{
			$dimensionname=$i;
			//default as string
			if(!isset($do['type']))
			{
				$do['type']='string';
			}

				$d[$dimensionname]=[];
				
		}

		return $d;
	}

	/**
       * 
       * Build distinct dimension value, and store index fact index of each dimension
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]


       * @return cube 
       */
	private function prepareDimensionMasterList($dimensiondata,$num,&$row)
	{			
		$cell=['fact_id'=>$num];	
		foreach($dimensiondata as $fieldname => $dim_obj)
		{
			$dimensionvalue=$row[$fieldname];
			$existingarr=$dimensiondata[$fieldname];

			$dim_id=$this->getDimensionID($dimensionvalue,$existingarr);
			//append into array if not exists										
			if($dim_id==-1)
			{					
				//id=array_index, start from 0
				$dim_id=count($existingarr);		
				
				$obj=['dim_id'=>$dim_id, 'dim_value'=>$dimensionvalue];

				if(isset($this->dimensionsetting[$fieldname]['bundlefield']))
				{


					$bundles=$this->dimensionsetting[$fieldname]['bundlefield'];
					foreach($bundles as $bi => $bfield)
					{						
						$obj[$bfield]=$row[$bfield];
					}
				}
				array_push($dimensiondata[$fieldname],$obj);
				// $dimensiondata[$fieldname]['data'][$dimensionvalue]['facts']=[$num];
			}			

			$cell[$fieldname]=$dim_id;
		}
		
		return array('data'=>$dimensiondata,'cell'=>$cell);
	}

	private function getDimensionID($search,$array)
	{
		foreach($array as $a => $o)
		{
			if($search==$o['dim_value'])
			{
				return $o['dim_id'];
			}

		}
		return -1;
	}

	public function sliceCube(&$cube,$dimensionname,$filters)
	{
		$cubecomponent=[];
		$cubecomponent[$dimensionname]=$filters;
		$subfacts=$cube->getSubFacts($cubecomponent);		
		return $this->createCube($subfacts,$cube->getDimensionSetting());
	}

	public function diceCube(&$cube,$cubecomponent)
	{
		$subfacts=$cube->getSubFacts($cubecomponent);
		return $this->createCube($subfacts,$cube->getDimensionSetting());
	}
}


function writedebug($a,$title='')
{	

	if($title!='')
	{
		echo $title.'<br/>';
	}



	if(gettype($a)=='array' || gettype($a)=='object')
	{
		echo '<pre style="border:solid 1px #aaa">'.print_r($a,true).'</pre><br/>';	
	}
	else
	{
		echo $a.'<br/>';
	}
	
}