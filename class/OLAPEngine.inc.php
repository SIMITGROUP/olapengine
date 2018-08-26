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
	private $errormsg='';
	/**
	 * OLAPEngine constructor
	 */
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
       * 
       * Create Olap Cube, through Cube.inc.php. Facts is 1 flat php hash table, $dimensions is array descript the dimension & hierarchy,
       * $measures declare all measures.
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]
       * @return object cube object
       */
	public function createCube(&$facts,$dimensions,$computedimension=true)
	{
		$cube = new Cube();

		if(count($dimensions)==0 || count($facts)==0)
		{
			return false;
		}
		else
		{			
			$cube->setVersion($this->version);
			$cube->setFacts($facts);
			$cube->setDimensionSetting($dimensions,$computedimension);
			
			
			
			$dimensiondata=$this->generateDistinctDimensionSchema($cube);			
			
			foreach($facts as $i => $row)
			{				
				$res=$this->prepareDimensionMasterList($dimensiondata,$i,$row);
				if(!$res)
				{
					return false;
				}
			
				$dimensiondata=$res['data'];
				$cube->addCell($res['cell']);
				
			}
			$cube->setDimensionList($dimensiondata);
				// writedebug($dimensiondata,'setDimensionList');
				// writedebug($cube->getDimensionList(),'getDimensionList at createcube');
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
	private function generateDistinctDimensionSchema($cube)
	{
		$d=[];		
		$dimlist=$cube->getDimensionSetting();

		if($dimlist)
		{		
			foreach($dimlist as $i => $do)
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
		else
		{
			return false;
		}
		

		
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

				if(isset($this->dimensionsetting[$fieldname]['hierarchy']) && count($this->dimensionsetting[$fieldname]['hierarchy'])>0)
				{

					//hierarchy support more then 1 tree
					$hierarchy=$this->dimensionsetting[$fieldname]['hierarchy'];
					foreach($hierarchy as $hi => $hobj)
					{	
					
					//each hierarchy support more then 1 level
						foreach($hobj as $hierarchyfieldname)
						{	
							if($fieldname==$hierarchyfieldname)
							{
								$this->errormsg='You shall not assign "'.$hierarchyfieldname.'" into hierarchy of field "'.$fieldname.'"';
								return false;

							}
							$obj[$hierarchyfieldname]=$row[$hierarchyfieldname];
							// $obj[$bfield];
						}
					}



				}
				
				array_push($dimensiondata[$fieldname],$obj);
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
		return $this->createCube($subfacts,$cube->getDimensionSetting(),false);
	}

	public function diceCube(&$cube,$cubecomponent)
	{
		$subfacts=$cube->getSubFacts($cubecomponent);
		return $this->createCube($subfacts,$cube->getDimensionSetting(),false);
	}
}


function writedebug($a,$title='')
{	
	     $bt = debug_backtrace();
	     $caller = array_shift($bt);
	      $callerline =$caller['line'];
	      




	if($title!='')
	{
		echo '<u>line:'.$callerline.','.$title.'</u><br/>';
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