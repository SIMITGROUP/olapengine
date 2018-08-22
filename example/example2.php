<?php
// get data $data
// ini_set('opcache.enable', 0);

// $array1 = array(8, 3, 2);
// $array2 = array(3, 9, 1);
// $array1 = array_intersect($array1, $array2);
// print_r($array1);

// die;


include 'examplearray.php';
$facts=$data;
$dimensions=['city','country','region', 'agent','item','date']; //simple, it will identify data type base on first row of facts, flat
$dimensions=[
	'city'=>['type'=>'string','parent'=>'country'],
	// 'country'=>['type'=>'string','parent'=>'country'],
	// 'region'=>['type'=>'string'], 
	'agent'=>['type'=>'string',],
	'code'=>['type'=>'string','parent'=>'category','bundlefield'=>['item','sku']],
	'date'=>['type'=>'date','datehierarchy'=>['year','month','day','week','quarter','']]
	]; //more complete defination

$olapfile=__DIR__."/../class/OLAPEngine.inc.php";
if(file_exists($olapfile))
{
	include $olapfile;	
	$olapengine= new OLAPEngine();
	$cube=$olapengine->createCube($facts,$dimensions);
	// writedebug($facts,'facts');
	if($cube)
	{		
		// $cube->drawCube();
	
		//slice cube within date range
		$filters= ['from'=>'2018-01-01', 'to'=>'2018-01-31'];		
		$cube_3day=$olapengine->sliceCube($cube,'date',$filters);
		// writedebug($cube_3day);

		
		//create dice cube within multiple dimension filter
		$dicecomponent=[
				'date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']],		
				'city'=>['KL','JB'],
		];
		$cube_item_city=$olapengine->diceCube($cube,$dicecomponent);
		// writedebug($cube_item_city);
		


		//get brz table with filter within specific original cube, record return
		$filtercomponent=[
				'date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']],				
				'city'=>['BRZ'],
		];
		$getfilterfact=$cube->getSubFacts($filtercomponent);
		// writedebug($getfilterfact,'getfilterfact');


		//get brz table with filter within KL/JB cube, no data return
		$filtercomponent=[
				'date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']],				
				'city'=>['BRZ'],
		];
		$getfilterfact=$cube_item_city->getSubFacts($filtercomponent);
//		writedebug($getfilterfact,'getfilterfact');


		$dimension='date';
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'count'], //count sales, return sales_count
			['field'=>'sales','agg'=>'max'], //get max sales, return sales_max
			['field'=>'sales','agg'=>'min'], //get min sales, return sales_min
			['field'=>'sales','agg'=>'avg'], //get avg sales, return sales_avg
			['field'=>'profit','callback'='callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$cube->rollUp('date',$measures);

		
		// $cube->rollUp('date',$measures);
		
		
	}
}
else
{
	echo $olapfile .' not exits!';
}



function callback1($data='')
{
	echo 'call back data: '.$data;
}