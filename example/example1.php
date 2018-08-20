<?php

include "../class/OLAPEngine.inc.php";
include 'examplearray.php';


$dimensions=[
	['field'=>'agent','type'=>'string'],
	['field'=>'item','type'=>'string'],
	['field'=>'region','type'=>'string',
			'child'=>[
				['field'=>'country','type'=>'string',
					'child'=>[
						['field'=>'city','type'=>'string']
					]
				]
			]
	],

	
	['field'=>'date','type'=>'date'],
	]; 

$measures=[
	['field'=>'sales','type'=>'number','decimal'=>2,'prefix'=>'MYR'],
	['field'=>'cost','type'=>'number','decimal'=>2,'prefix'=>'MYR']
];

$facts = $data;

$olap = new OLAPEngine();

//get region list without filter
$column=['item']; 
$filter=[];

/*
$column=['region']; 
$filter=[];

//get country list under region SEA
$column=['region','country']; 
$filter=['region'=>['SEA']];


//get all country list
$column=['region','country']; 
$filter=['region'=>['*']];
*/
$cube = $olap->createCube($facts,$dimensions,$measures);
$data=$olap->getDimensionValues($cube,$column,$filter,'facts');
// $data=$olap->getDimensionValues($cube,$column,$filter,'dimension');

//$subfacts=$olap->getFacts($cube,$facts,$filter);

echo '<pre>'.print_r($cube,true).'</pre>';
