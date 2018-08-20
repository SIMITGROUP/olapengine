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

	  /**
       * 
       * Constructor
       *       
       * @return OLAPEngine
       */
	public function __construct()
	{
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
	public function createCube(&$fact,$dimensions=[],$measures=[])
	{
		/*
			$samplecube=[
			    [dimensions] => $dimensions,
			    [measures] => $measures,
			    [dimensionmasterdata] => [
			            [agent] => [
			                    [data] => [A]
			                ],
			            [item] => [
			                    [data] => [Item 1,Item 2]
			                ],           
			            [date] => [
			                    [data] => [
			                            2018-01-01,
			                            2018-01-03,
			                            2018-01-08,
			                            2018-01-18,
			                        ]
			                ],
			        ]
			]
		*/
		$cube = new Cube();
		$agg= new Aggregate();
		$c= $cube->createCube($fact,$dimensions,$measures);

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
	public function getDimensionValues($c,$k,$filter=[],$isdimensionlist='dimension')
	{
		$cube = new Cube();
		return $cube->getDimensionValues($c,$k,$filter,$isdimensionlist);
	}

	public function getFacts(&$cube,&$facts,$filters)
	{
		return $cube;
	}

}