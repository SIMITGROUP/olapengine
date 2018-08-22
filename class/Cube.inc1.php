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
	private $dimensiondata;
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

		$dimensiondata=$this->generateDistinctDimensionSchema($dimensions);
		foreach($facts as $i => $f)
		{
			$dimensiondata=$this->prepareDimensionMasterList($dimensiondata,$i,$f,$dimensions,0);						
		}

		$levelname='top';
		$dimensiondata=$this->computeParentFactsArray($dimensiondata,$measures,$facts);
		$cube['dimensionmasterdata']=$dimensiondata;

		$this->fieldtypes=$this->generateFieldTypeList($dimensions);

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
				$d[$fieldname]['data'][$dimensionvalue]=$this->prepareDimensionMasterList($d[$fieldname]['data'][$dimensionvalue],$rowno,$row,$childfielddimension,$level);									

			}
			else
			{
				//append into array if not exists				
				$existingarr=$d[$fieldname]['data'];
				if(!isset($d[$fieldname]['data'][$dimensionvalue]))
				{					
					$d[$fieldname]['data'][$dimensionvalue]=[];
					$d[$fieldname]['data'][$dimensionvalue]['facts']=[$rowno];
					// =['facts'=>[$rowno]];
				}
				else
				{
					array_push($d[$fieldname]['data'][$dimensionvalue]['facts'],$rowno);	
				}
			}
			
		}
		return $d;
	}



	/**
       * 
       * This function will compute fact array to those parent hierarchy data
       *
       * @param array $dimensiondata
       * @return dimensiondata 
       */
	private function computeParentFactsArray($dimensiondata,$measures,&$facts)
	{
		// echo 'under computeParentFactsArray:'.count($facts).'<br/>';
		$agg = new Aggregate($facts);
		$tmpfact=[];
		foreach($dimensiondata as $fieldname => $obj)
		{						
			foreach($obj['data'] as $dimensionvalue => $dobj)
			{
				//mean this folder have facts array already, it is last level and we no need to do anything
				if(isset($dobj['facts'])) 
				{
					$tmpfact=array_merge($tmpfact,$dobj['facts']);	
					// $dimensiondata[$fieldname]['data'][$dimensionvalue]['aggregate']=$agg->aggregateSummary($dobj['facts'],$measures);
				}
				else //we need to recursive define facts here
				{						
					$r=$this->computeParentFactsArray($dobj,$measures,$facts);
					$tmpfact2=$r['facts'];
					//$r['aggregate']=$agg->aggregateSummary($tmpfact2,$measures);;
					$dimensiondata[$fieldname]['data'][$dimensionvalue]=$r;					
					$tmpfact=array_merge($tmpfact,$tmpfact2);
					$tmpfact2=[];
				}


			}

		}
		
		$dimensiondata['facts']=$tmpfact;
		// $dimensiondata['aggregate']=$agg->aggregateSummary($dimensiondata['facts'],$measures);
		return $dimensiondata;

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
       * Create blank aggregate hash result
       *
       * @param array $dimension array of dimension

       * @return aggregated array
       */
	 private function genDefaultAggregateValue()
	 {
	 	return ['sum'=>0,'count'=>0];
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
	   * @param string $valuetype either 'dimension' (show distinct value of dimension) or 'fact' (show index of fact under specific dimension)
	   * @param array $aggs desired aggregated column wish to get, example [ ['sales'=>'sum'],['cost'=>'max']..]
       * @return blankdimensionarray
       */
	public function getDimensionValues(&$cube,&$k,&$filter=[],$valuetype='dimension',$aggs=array())
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
					if($this->checkDimensionFilter($dindex,$filter[$value],$value))
					{						


						switch($valuetype)
						{
							case 'dimension':
								array_push($r, $dindex);	
							break;
							case 'facts':
								$r=array_merge($r,$dobj['facts']);
							break;
							case 'aggregate':
								$tmpvalue=[];
								$fieldname=$value;
								$tmpvalue[$fieldname]=$dindex;								

								foreach($dobj['aggregate'] as $fieldkey => $fieldobj)
								{

									$tmpvalue[$fieldkey]['sum']+=$fieldobj['sum'];
									$tmpvalue[$fieldkey]['count']+=$fieldobj['count'];
									if(!isset($tmpvalue[$fieldkey]['max']) || $tmpvalue[$fieldkey]['max'] < $fieldobj['max'])
									{
										$tmpvalue[$fieldkey]['max']=$fieldobj['max'];
									}
									if(!isset($tmpvalue[$fieldkey]['min']) || $tmpvalue[$fieldkey]['min'] < $fieldobj['min'])
									{
										$tmpvalue[$fieldkey]['min']=$fieldobj['min'];
									}									
									
								}

								
								$tmpsummary=[];
								$tmpsummary[$fieldname]=$dindex;
								//prepare every aggregated rows
								foreach($aggs as $measureindex => $mobj)
								{										
									foreach ($mobj as $aggfieldname => $aggmethod) 
									{$tmpsummary[$aggfieldname.'_'.$aggmethod]=$tmpvalue[$aggfieldname][$aggmethod];break;}

								}
								array_push($r,$tmpsummary);
							break;
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


				if($this->checkDimensionFilter($dindex,$filter[$value1],$value1))
				{					
					array_push($tmp, $dobj[$value2]['data']);
				}
				
			}		
			
			foreach($tmp as $i => $o)
			{
				foreach($o as $value=>$child)
				{					


					switch($valuetype)
					{
						case 'dimension':
							array_push($r, $value);	
						break;
						case 'facts':
							$r=array_merge($r,$child['facts']);
						break;
						case 'aggregate':
						break;
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
				if($this->checkDimensionFilter($dindex,$filter[$value1],$value1))
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

				if($this->checkDimensionFilter($dindex,$filter[$value2],$value2))
					{
						array_push($tmp2, $dobj[$value3]['data']);
					}

				}	
			}		

			
			foreach($tmp2 as $i => $o)
			{
				foreach($o as $a=>$b)
				{

					switch($valuetype)
					{
						case 'dimension':
							array_push($r, $a);	
						break;
						case 'facts':
							$r=array_merge($r,$b['facts']);
						break;
						case 'aggregate':
						break;
					}
					
				}
				
			}		
 
		}
		
		switch($valuetype)
		{
			case 'dimension':
				sort($r,SORT_STRING);
			break;
			case 'facts':
				sort($r);
			break;
			case 'aggregate':
				//do nothing yet
			break;
		}
		

		return $r;

	}
	
}
