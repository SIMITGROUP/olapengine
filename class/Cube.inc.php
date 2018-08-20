<?php
  /**
   * Cube
   * 
   * 
   * @package    Cube
   * @subpackage Controller
   * @author     kstan <kstan@simitgroup.com>
   */

class Cube extends OLAPClass
{

	

	private $data;
	public function __construct()
	{
		parent::__construct();
	}


	/**
       * 
       * Create Olap Cube, through Cube.inc.php. Facts is 1 flat php hash table, $dimensions is array descript the dimension & hierarchy,
       * $measures declare all measures. Loop facts to create cubes
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]
       * @param array $measures [['field'=>'sales','type'=>'number','decimal'=>2,'prefix'=>'MYR'],...];

       * @return cube 
       */
	public function createCube(&$facts,$dimensions,$measures)
	{

		$cube=[
			'dimensions'=>$dimensions,
			'measures'=>$measures,
		];

		$d=$this->generateDistinctDimensionSchema($dimensions);
		foreach($facts as $i => $f)
		{
			$d=$this->prepareDimensionMasterList($d,$i,$f,$dimensions,0);
			
		}		
		// echo '<pre>'.print_r($d,true).'</pre>';
		$cube['dimensionmasterdata']=$d;

		return $cube;
	}


	/**
       * 
       * Break facts into multi-dimensional/hierarchy array, and store distinct value of each dimension
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]
       * @param array $measures [['field'=>'sales','type'=>'number','decimal'=>2,'prefix'=>'MYR'],...];

       * @return cube 
       */
	private function prepareDimensionMasterList($d,$rowno,$row,$dimensions,$level)
	{		

		
		$level++;
		$dimensionchildarr=[];
		foreach($dimensions as $dn => $dobj)
		{
			if(isset($dobj['child']) && count ($dobj['child'])>0)
			{
				$childfield=$dobj['child'][0]['field'];
				$dimensionchildarr[$dobj['field']]=['field'=>$childfield, 'dimension'=>$dobj['child']];
			}
			else
			{
				$dimensionchildarr[$dobj['field']]=['field'=>''];
			}

			
		}

		foreach($d as $fieldname => $dim_obj)
		{
			$dimensionvalue=$row[$fieldname];

			

			//have sub rows

			if($dimensionchildarr[$fieldname]['field'] !='')
			{
				$childfield=$dimensionchildarr[$fieldname]['field'];
				$childfielddimension=$dimensionchildarr[$fieldname]['dimension'];

				//if not exists this dimension
				
				if(!isset($d[$fieldname]['data'][$dimensionvalue]))
				{					
					$d[$fieldname]['data'][$dimensionvalue]=[];
					$d[$fieldname]['data'][$dimensionvalue][$childfield]=['data'=>[]];
				}


				$tmp=$this->prepareDimensionMasterList($d[$fieldname]['data'][$dimensionvalue],$rowno,$row,$childfielddimension,$level);
				$d[$fieldname]['data'][$dimensionvalue]=$tmp;

			}
			else
			{
				//append into array if not exists
				
				$existingarr=$d[$fieldname]['data'];

				if(!isset($d[$fieldname]['data'][$dimensionvalue]))
				{
					$d[$fieldname]['data'][$dimensionvalue]=[$rowno];
				}
				else
				{
					array_push($d[$fieldname]['data'][$dimensionvalue],$rowno);
				}
			}
			
		}
		return $d;
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
			$dimensionname=$do['field'];

			// if($do['type']!='date')
			// {
				$d[$dimensionname]=['data'=>[]];
			// }
			// else
			// {
				//generate date hierarchy
			// }
			
		}

		return $d;
	}

	/**
       * 
       * get array of dimension according field, currently maximum support 3 level
       *
       * @param object $cube variable created by createCube() 
	   * @param array $k get dimension's data, ['region'] for region, or ['region','country'] for country. we shall define array according 
	   *		hierarchy of dimension. Last element of array is the desire array to check. Maximum 3 element, as ['region','country','city']
	   * @param object $filter to define filter parameter:  ['region'=>['SEA']] or ['region'=>['*']] or ['region'=>['SEA'],'country'=>['MY']],
	   *		maximum support 3 element
       * @return blankdimensionarray
       */
	public function getDimensionValues($cube,$k,$filter=[],$isdimensionlist='dimension')
	{
		$r=[];
		$setcount=count($k);		

		if($setcount==0) // no dimension selected
		{
			$r=[];
		}
		else if($setcount==1) //get 1 dimension list
		{
			$value=$k[0];
			foreach($cube['dimensionmasterdata'][$value]['data'] as $dindex =>$dobj)
				{			
					if(
						$filter[$value][0]=='*' || 
						!isset($filter[$value]) ||
						(isset($filter[$value]) &&  in_array($dindex, $filter[$value]))
					)
					{
						
						if($isdimensionlist=='dimension')
						{
							array_push($r, $dindex);	
						}
						else
						{

							foreach($dobj as $dobjindex => $factrowno)
							{
								array_push($r,$factrowno);
							}
							
						}
					}
					
				}			
		}
		else if($setcount==2) //get 2nd dimension list,  with filter according $filter
		{
			$value1=$k[0];
			$value2=$k[1];
			// print_r($cube['dimensionmasterdata'][$value1]['data']);
			$tmp=[];

			foreach($cube['dimensionmasterdata'][$value1]['data'] as $dindex =>$dobj)
			{			


				if( 
					$filter[$value1][0]=='*' || 
					!isset($filter[$value1]) ||
					( isset($filter[$value1]) &&  in_array($dindex, $filter[$value1]) )

					)
				{
					
					array_push($tmp, $dobj[$value2]['data']);
				}
				
			}		
			
			foreach($tmp as $i => $o)
			{
				foreach($o as $a=>$b)
				{

					if($isdimensionlist=='dimension')
					{
						array_push($r, $a);	
					}
					else
					{
						foreach($dobj as $dobjindex => $factrowno)
						{
							array_push($r,$factrowno);
						}
					}
					
				}
				
			}			 
		}
		else if($setcount==3) //get 3rd dimension list, with filter according $filter
		{
			$value1=$k[0];
			$value2=$k[1];
			$value3=$k[2];
			// print_r($cube['dimensionmasterdata'][$value1]['data']);
			$tmp1=[];

			foreach($cube['dimensionmasterdata'][$value1]['data'] as $dindex =>$dobj)
			{			


				if(
					$filter[$value1][0]=='*' || 
					!isset($filter[$value1]) || 
					(isset($filter[$value1]) &&  in_array($dindex, $filter[$value1]))
				)
				{
					array_push($tmp1, $dobj[$value2]['data']);
				}
				
			}
			
			$tmp2=[];

			foreach($tmp1 as $di =>$dob)
			{	
				// echo print_r($dob,true).':';	
				foreach($dob as $dindex => $dobj)
				{					
					if(
						$filter[$value2][0]=='*' ||
						!isset($filter[$value2]) ||
						(isset($filter[$value2]) &&  in_array($dindex, $filter[$value2]))
					)
					{
						array_push($tmp2, $dobj[$value3]['data']);
					}

				}	
			}		

			
			foreach($tmp2 as $i => $o)
			{
				foreach($o as $a=>$b)
				{

					if($isdimensionlist=='dimension')
					{
						array_push($r, $a);	
					}
					else
					{

					}
					
				}
				
			}		
 
		}
		
		return $r;

	}

}
