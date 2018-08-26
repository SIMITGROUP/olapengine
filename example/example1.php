<?php
// get data $data
ini_set('opcache.enable', 0);

// $array1 = array(8, 3, 2);
// // $array2 = array(3, 9, 1);
// // $array1 = array_intersect($array1, $array2);
// // print_r($array1);
// $a=array_search(8, $array1);
// if($a>=0)
// 	{echo 'exists at '.$a;}
// else
// 	{echo 'not exists';}
// die;
// die;

$olapfile=__DIR__."/../class/OLAPEngine.inc.php";
include 'examplearray.php';
$facts=$data;
$dimensions=[
	'city'=>['type'=>'string','hierarchy'=>['default' => ['country','region']] ],
	'agent'=>['type'=>'string',],
	'code'=>['type'=>'string','bundlefield'=>['item','sku'], 'hierarchy'=>['default'=>['category']] ],
	'date'=>['type'=>'date',]
	]; 
//'hierarchy'=>['year','month','day'] 

if(file_exists($olapfile))
{
	include $olapfile;	
	$olapengine= new OLAPEngine();
	$cube=$olapengine->createCube($facts,$dimensions);

	if($cube)
	{		
		// $cube->drawCube();
	
		// writedebug('<h1>example 1: slicing</h1>');
		$filters= [['from'=>'2018-01-01', 'to'=>'2018-01-03']];
		$cube_3day=$olapengine->sliceCube($cube,'date',$filters);
		// writedebug('done');





		// writedebug('<h1>example 2: aggregate</h1>');
		$dimension='code';
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'count'], //sum sales, return sales_sum
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$filtercomponent=[
				'date'=>['2018-01-03',['from'=>'2018-01-01','to'=>'2018-01-02']],				
		];
		$summary=$cube->aggregate($dimension,$measures,$filtercomponent); //summary of country
		// writedebug($summary,'summary');

	
		
		// writedebug('<h1>example 3: dice and summarise</h1>');
		$dimension='city';
		$dicecomponent=[
				'date'=>[['from'=>'2017-01-01','to'=>'2018-12-31']],		
		];		
		
		$cubedatecity=$olapengine->diceCube($cube,$dicecomponent);
		$summarydatecity=$cubedatecity->aggregate($dimension,['sales']); //summary of country
		// writedebug($summarydatecity,'summarydatecity');
		

		// writedebug('<h1>example 4: get raw data from getsubfacts</h1>');
		$filtercomponent=[
				'date'=>['2018-01-03',['from'=>'2018-01-01','to'=>'2018-01-02']],				
				'city'=>['JB','KL','TAMPINESS'],

		];
		$getsubfacts=$cube->getSubFacts($filtercomponent);
		// writedebug($getsubfacts,'getsubfacts');


		// writedebug('<h1>example 5: aggregate record with multiple filter and group by </h1>');
		$arrdimension=['code','city'];
		$filtercomponent=[
				'date'=>[['from'=>'2017-01-01','to'=>'2019-12-31']],				

		];
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'avg'], //sum sales, return sales_sum
			['field'=>'cost','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		// $summary_agggroupby=$cube->aggregateByMultiDimension($arrdimension,$measures,$filtercomponent);
		// writedebug($summary_agggroupby,'$summary_agggroupby');
		
		$dimension='country';
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'avg'], //sum sales, return sales_sum
			['field'=>'cost','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$filtercomponent=[
				'date'=>[['from'=>'2017-01-01','to'=>'2019-12-31']],
		];
		// $summarydrilldown=$cube->drillDown($dimension,$measures,$filtercomponent);
		// writedebug($summarydrilldown,'$summarydrilldown');
		
		// $summaryrollup=$cube->rollUp($dimension,$measures,$filtercomponent);
		// writedebug($summaryrollup,'$summaryrollup');


	}
}
else
{
	echo $olapfile .' not exits!';
}



function callback1($row,$broughforward)
{
	return $row['sales']-$row['cost'];
}