<?php

include "../class/OLAPEngine.inc.php";
include 'examplearray.php';


$dimensions=[
	['field'=>'agent','type'=>'string'],
	['field'=>'item','type'=>'string'],
	// ['field'=>'country','type'=>'string','parent'=>'region'],
	// ['field'=>'city','type'=>'string','parent'=>'country'],
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
// $column=['region','country','city'];
$column=['region'];
// $column=['region'];
$filter=['region'=>['SEA']];

$cube = $olap->createCube($facts,$dimensions,$measures);
$data=$olap->getDimensionValues($cube,$column,$filter,'facts');
$subfacts=$olap->getFacts($cube,$facts,$filter);

echo '<pre>'.print_r($data,true).'</pre>';
die;


//$olap->createCube($fact); // will auto detect

//$olap->aggregate($cube)



//data pattern as this:
/*
	$fact[$fieldname]=[$dimensionvalue:{...}..];
	$fact['country']=[MY:[{},{},{}],SG:[],US:[]...];

	$cube = [
		dimension:dimensions,
		measures:measures,
		facts:facts,
		dimensionlist:['county':[MY,SG,]]
		sufacts:[
			'country':{MY:[1,4],'SG':[2],'US':[3]},
			'agent':{A:[1,4],'B':[2],'C':[3]},
		],
		aggregate: 
			[
			'country':[
				subset: {MY:[1,4],'SG':[2],'US':[3]},
				'sum': {'MY':3455,'SG':123,'US':2},
				'AVG': {'MY':23,'SG':3,'US':1},
				],
			'country':[
				subset: {MY:[1,4],'SG':[2],'US':[3]},
				'sum': {'MY':3455,'SG':123,'US':2},
				'AVG': {'MY':23,'SG':3,'US':1},
				],

			]
	]
*/