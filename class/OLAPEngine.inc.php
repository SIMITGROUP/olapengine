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

	public function __construct()
	{

	}

	public function getDataType($result)
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
		$this->cube = new Cube();
		$this->agg= new Aggregate();
		parent::__construct();
		
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
		$c=$this->agg->aggregateSummary($facts,$c);
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
       * @return blankdimensionarray
       */
	public function getDimensionList(&$cube,&$k,&$filter)
	{

		return $this->cube->getDimensionValues($cube,$k,$filter,'dimension');
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
       * @return blankdimensionarray
       */
	public function getDimensionFactsIndex(&$cube,&$k,&$filter)
	{
		return $this->cube->getDimensionValues($cube,$k,$filter,'facts');
	}

	

	public function getFactsFromIndex(&$facts,&$indexes)
	{
		$data=[];
		foreach($indexes as $i => $rowno)
		{
			array_push($data,$facts[$rowno]);
		}
		return $data;
	}

}