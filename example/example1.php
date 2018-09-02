<?php
// get data $data
ini_set('opcache.enable', 0);

/*

['region'=>'SEA','country'=>'MY','city'=>'JB','agent'=>'A','item'=>'Item 1','code'=>'I1','category'=>'CA','sku'=>'sku1','date'=>'2018-01-01','sales'=>150,'cost'=>80,],



{
"DocumentNo" : { "type" : "string", "datatype": "others"},
"DocumentType" : { "type" : "string", "datatype": "dimension"},
"TotalCost" : { "type" : "double", "datatype": "measures"},
}


*/
$olapfile=__DIR__."/../class/OLAPEngine.inc.php";
include 'examplearray.php';
$facts=$data;
$dimensions=[
	'city'=>['type'=>'string','hierarchy'=>['default' => ['country','region']] ],
	'agent'=>['type'=>'string',],
	'code'=>['type'=>'string','bundlefield'=>['item','sku'], 'hierarchy'=>['default'=>['category']] ],
	'date'=>['type'=>'date',]
	]; 

$othersfield=[
	'sku'=>['type'=>'string','datatype'=>'others'],
	'item'=>['type'=>'string','datatype'=>'others'],
	'sales'=>['type'=>'string','datatype'=>'measures'],
	'cost'=>['type'=>'string','datatype'=>'measures'],
];	
//'hierarchy'=>['year','month','day'] 

if(file_exists($olapfile))
{
	include $olapfile;	
	$olapengine= new OLAPEngine();
	$rowcount=count($facts);

	$cube=$olapengine->createCube($dimensions,$rowcount); //required to submit rowcount

	if($cube)
	{		

	$cube->setOthersField($othersfield);
	

	foreach($facts as $i=>$rs)
	{
		$cube->addRow($i,$rs);
		
	}
	$cube->optimizeMemory();

		$dimension='country';
		$arrdimension=['code','city'];
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'sales','agg'=>'sum'], //sum sales, return sales_sum			
			['field'=>'profit','callback'=>'callback1'], //custom,  'profit' not exists, it will run callback to get value
		];
		$filtercomponent=[
				'date'=>[['from'=>'2010-01-01','to'=>'2020-12-31']],
		];
		$sorts=[['code'=>'DESC'],['profit'=>'ASC']];
		$summary=$cube->aggregateByMultiDimension($arrdimension,$measures,$filtercomponent,$sorts); //summary of country
		writedebug($summary,'aggregate');

		$summarydrilldown=$cube->drillDown($dimension,$measures,$filtercomponent,$sorts);
		writedebug($summarydrilldown,'drilldown');
		
		$summaryrollup=$cube->rollUp($dimension,$measures,$filtercomponent,$sorts);
		writedebug($summaryrollup,'rollUp');
		


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