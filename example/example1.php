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
	
		//slice cube within date range
		writedebug('<h1>example 1: slicing</h1>');
		$filters= [['from'=>'2018-01-01', 'to'=>'2018-01-03']];
		$cube_3day=$olapengine->sliceCube($cube,'date',$filters);

		writedebug('<h1>example 2: aggregate</h1>');
		$dimension='s';
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'count'], //sum sales, return sales_sum
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$summary=$cube_3day->aggregate($dimension,$measures); //summary of country
		writedebug($summary,'summary');

		/* ---------------------------------*/
		
		//create dice cube within multiple dimension filter
		$dicecomponent=[
				'date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']],		
				'city'=>['KL','JB'],
		];
		$cube_item_city=$olapengine->diceCube($cube,$dicecomponent);
		// writedebug($cube_item_city);
		


		//get brz table with filter within specific original cube, record return
		$filtercomponent=[
				'date'=>['2018-01-03',['from'=>'2018-01-01','to'=>'2018-01-02']],				
				'city'=>['JB','KL','TAMPINESS'],

		];
		$getfilterfact=$cube->getSubFacts($filtercomponent);
		// writedebug($getfilterfact,'getfilterfact');


		//get brz table with filter within KL/JB cube, no data return
		// $filtercomponent=[
		// 		'date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']],				
		// 		'city'=>['BRZ'],
		// ];
		// $getfilterfact=$cube_item_city->getSubFacts($filtercomponent);
//		writedebug($getfilterfact,'getfilterfact');


		$dimension='country';
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum
			['field'=>'sales','agg'=>'count'], //sum sales, return sales_sum
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$summary=$cube->aggregate($dimension,$measures); //summary of country
		// writedebug($summary);
		// $summaryofcity=$cube->drillDown($dimension,[],$measures); //drill down 1 level, group data by city
		// $summaryofcountry=$cube->rollUp($dimension,[],$measures); //rollup down 1 level, present same data as region

		


//////*********************
		//$summary=$cube->rollUp('city',['sales','cost']);
		

		// $smallercube=$cube->slice('country',['MY','SG']);
		// $smallercube->rollUp('region',['sales','cost']);


		// $countryresult=$cube->drillDown('country',['MY','SG'],['sales','cost']); //drill down to city 
		

//////*********************




		// $filters= ['from'=>'2018-01-01', 'to'=>'2018-01-31'];		
		// $drilldowncube=$olapengine->sliceCube($cube,'date',$filters);
		


		// $summarydrilltocountry=$cube->drillDown($dimension,$filter,$measures);

		// $dimension='country';					
		// // $drilldowncube=$cube->drillDown($dimension,$filter);
		// $debugger=1;
		// $rollresult=$cube->rollUp($dimension,$measures);  

		// if(!$rollresult)
		// {
		// 	echo $cube->getError();
		// }

		//it will auto go to next level which is country
		// $drilldowncube=$cube->drillDown(,);  //it will auto go to next level which is country



//		let markMembers = cube.drillUpMembers('product', productMembers, 'mark')

		
		
		
	}
}
else
{
	echo $olapfile .' not exits!';
}



function callback1($row,$broughforward)
{
	return $broughforward['sales']['sum']-$broughforward['cost']['sum'] ;
}