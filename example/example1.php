<?php


// $a=['2018-03-01',['from'=>'2018-01-03','to'=>'2019-01-31'],2018,3434.3,'2018'];


// foreach($a as $b=>$c)
// {
// 	echo  $b.'. ' .gettype($c).'<br/>';
// }
// die;




include "../class/OLAPEngine.inc.php";
include 'examplearray.php';


$dimensions=[
	['field'=>'region','type'=>'string',
			'child'=>[
				['field'=>'country','type'=>'string',
					'child'=>[
						['field'=>'city','type'=>'string']
					]
				]
			]
	],
	['field'=>'agent','type'=>'string'],
	['field'=>'item','type'=>'string'],	
	['field'=>'date','type'=>'date'],
	]; 

$measures=[
	['field'=>'sales'],
	['field'=>'cost']
];


/*
1. document_date  (year)
	data
		2018
			2. month
				data
					1
						3. day
							data
								1.
								2.
								...
								31.
			3. quarter
				convert as array range (1-3),(4-6)...

*/
$facts = $data;
$olap = new OLAPEngine();
$column=['date']; 
$filter=['date'=>[['from'=>'2018-01-01','to'=>'2018-12-31']]];

$aggs=[['sales'=>'sum'],['sales'=>'count'],['sales'=>'avg'],['cost'=>'sum'],['cost'=>'count'],['cost'=>'avg']];
$cube = $olap->createCube($facts,$dimensions,$measures);
$data1=$olap->getDimensionList($cube,$column,$filter);
$data2=$olap->getDimensionFactsIndex($cube,$column,$filter);
$data3=$olap->getFactsFromIndex($facts,$data2);
$data4=$olap->getAggregateResult($cube,$column,$filter,$aggs);
echo '<pre>cube:'.print_r($cube,true).'</pre>';


echo '<pre>data1:'.print_r($data1,true).'</pre>';
echo '<pre>data2:'.print_r($data2,true).'</pre>';
echo '<pre>data3:'.print_r($data3,true).'</pre>';
echo '<pre>data4:'.print_r($data4,true).'</pre>';
