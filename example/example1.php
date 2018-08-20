<?php

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
	['field'=>'sales','type'=>'number','decimal'=>2,'prefix'=>'MYR'],
	['field'=>'cost','type'=>'number','decimal'=>2,'prefix'=>'MYR']
];

$facts = $data;

$olap = new OLAPEngine();

//get region list without filter
// $column=['item']; 
// $filter=[];


// $column=['region']; 
// $filter=['region'=>['SEA']];

//get country list under region SEA
// $column=['region','country']; 
// $filter=['region'=>['SEA']];
// $column=['item']; 
// $filter=[];


$column=['region','country','city']; 
$filter=['country'=>['*']];

//get all country list
// $column=['region','country']; 
// $filter=['region'=>['*']];

$cube = $olap->createCube($facts,$dimensions,$measures);
$data1=$olap->getDimensionList($cube,$column,$filter);
$data2=$olap->getDimensionFactsIndex($cube,$column,$filter);
$data3=$olap->getFactsFromIndex($facts,$data2);

// $data=$olap->getDimensionValues($cube,$column,$filter,'dimension');

//$subfacts=$olap->getFacts($cube,$facts,$filter);

echo '<pre>'.print_r($data1,true).'</pre>';
echo '<pre>'.print_r($data2,true).'</pre>';
echo '<pre>'.print_r($data3,true).'</pre>';
