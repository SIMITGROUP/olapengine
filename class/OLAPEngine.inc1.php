<?php

  /**
   * OLAPClass
   * 
   * 
   * @package    OLAPClass
   * @subpackage Controller
   * @author     kstan <kstan@simitgroup.com>
   */
class OLAPClass
{

	protected $facts;
	protected $dimensions;
	protected $measures;
	protected $fieldtypes;
	public function __construct()
	{

	}

	/**
       * 
       * use to keep dimension setting and generate array of fieldtype for future processing
       *
       * @param array dimensions	   
       * @return bool
       */

	protected function generateFieldTypeList(&$dimensions)
	{
		$fieldtypes=[];
		foreach($dimensions as $i => $d)
		{
			
			$fieldtypes[$d['field']]=$d['type'];
			
			if(isset($d['child']) && isset($d['child'][0]['field']))
			{
				$r=$this->generateFieldTypeList($d['child']);
				foreach($r as $k=>$v)
				{
					$fieldtypes[$k]=$v;	
				}				
			}
			
			
			

		}

		return $fieldtypes;
		
	}

	/**
       * 
       * function use for evaluate filter string 
       *
       * @param mix string,integer, date or etc value to check
	   * @param array of filter, can be string, number, date, or array with range record example: ['a',2008,'2008-01-01',[from=>2008,'to'=>2009]]
       * @return bool
       */
	protected function checkDimensionFilter($value,$filters,$fieldname)
	{

		
		$fieldtype=$this->fieldtypes[$fieldname];
		
		if(!isset($filters))
		{
			return true;
		}		
		else if($filters[0]=='*')
		{
			return true;		 
		}
		else 
		{
			foreach($filters as $i => $f)
			{


				// if($this->fieldtypes[])
				// {

				// }
				if($value==$f)
				{
					return true;
				}
				else if(gettype($f)=='array' && isset($f['from']) && isset($f['to']))
				{


					//date have special process
					if($fieldtype=='date')
					{
						
						if($value>=$f['from'] && $value <= $f['to'])
						{
							return true;
						}
					}					
					else //none date treatment
					{
						if($value>=$f['from'] && $value <= $f['to'])
						{
							return true;
						}	
					}
					
				}
			}
			return false;
		}

	}
	/**
       * 
       * get partial row of of fact's data from indexes
       *
       * @param array $facts long data in hash table
	   * @param array index, use to identify which row to return	   
       * @return array
       */
	public function getFactsFromIndex(&$facts,&$indexes)
	{
		$data=[];
		foreach($indexes as $i => $rowno)
		{
			array_push($data,$facts[$rowno]);
		}
		return $data;
	}


	/**
       * 
       * use to identified what is the data type, it will return string, date, number and etc
       *
       * @param mix $v 	
       * @return string
       */
	public function getDataType($type)
	{
		
			$type=gettype($v);;

			if(isDate($v))
			{

				$type='date';
			}
			

		return $type;
	}
}


include __DIR__."/Cube.inc.php";
include __DIR__."/Aggregate.inc.php";


  /**
   * OLAPEngine
   * 
   * 
   * @package    OLAPEngine
   * @subpackage Controller
   * @author     kstan <kstan@simitgroup.com>
   */


class OLAPEngine extends OLAPClass
{
	private $cube;
	private $agg;

	  /**
       * 
       * Constructor
       *       
       * @return OLAPEngine
       */
	public function __construct()
	{
		
		parent::__construct();
		$this->cube = new Cube();
		// $this->agg= new Aggregate();
	}




	/**
       * 
       * Create Olap Cube, through Cube.inc.php. Facts is 1 flat php hash table, $dimensions is array descript the dimension & hierarchy,
       * $measures declare all measures.
       *
       * @param array $fact
       * @param array $dimensions [['field'=>'agent','type'=>'string'],[...]]
       * @param array $measures [['field'=>'sales','type'=>'number','decimal'=>2,'prefix'=>'MYR'],...];

       * @return cube 
       */
	public function createCube(&$facts,$dimensions=[],$measures=[])
	{		
		$c= $this->cube->createCube($facts,$dimensions,$measures);		
		

		
		return $c;
	}



	/**
       * 
       * get array of dimension according field, currently maximum support 3 level, call getDimensionValues() at Cube.inc.php
       *
       * @param object $cube variable created by createCube() 
	   * @param array $k get dimension's data, ['region'] for region, or ['region','country'] for country. we shall define array according 
	   *		hierarchy of dimension. Last element of array is the desire array to check. Maximum 3 element, as ['region','country','city']
	   * @param object $filter to define filter parameter:  ['region'=>['SEA']] or ['region'=>['*']] or ['region'=>['SEA'],'country'=>['MY']],
	   *		maximum support 3 element
       * @return array of distinc dimension value
       */
	public function getDimensionList(&$cube,&$k,&$filter)
	{

		return $this->cube->getDimensionValues($cube,$k,$filter,'dimension',[]);
	}


	/**
       * 
       * get array of fact's index for specific dimension (according filter), currently maximum support 3 level, call getDimensionValues() at Cube.inc.php
       *
       * @param object $cube variable created by createCube() 
	   * @param array $k get dimension's data, ['region'] for region, or ['region','country'] for country. we shall define array according 
	   *		hierarchy of dimension. Last element of array is the desire array to check. Maximum 3 element, as ['region','country','city']
	   * @param object $filter to define filter parameter:  ['region'=>['SEA']] or ['region'=>['*']] or ['region'=>['SEA'],'country'=>['MY']],
	   *		maximum support 3 element
       * @return array of facts in that dimension value
       */
	public function getDimensionFactsIndex(&$cube,&$k,&$filter)
	{
		return $this->cube->getDimensionValues($cube,$k,$filter,'facts');
	}	

	/**
       * 
       * get array of aggregate result, currently maximum support 3 level, call getDimensionValues() at Cube.inc.php
       *
       * @param object $cube variable created by createCube() 
	   * @param array $k get dimension's data, ['region'] for region, or ['region','country'] for country. we shall define array according 
	   *		hierarchy of dimension. Last element of array is the desire array to check. Maximum 3 element, as ['region','country','city']
	   * @param object $filter to define filter parameter:  ['region'=>['SEA']] or ['region'=>['*']] or ['region'=>['SEA'],'country'=>['MY']],
	   *		maximum support 3 element
       * @return array of aggregated result (sum/avg/count/min/max) in that dimension value
       */
	public function getAggregateResult(&$cube,&$k,&$filter,$aggs=[])
	{

		return $this->cube->getDimensionValues($cube,$k,$filter,'aggregate',$aggs);
	}

}