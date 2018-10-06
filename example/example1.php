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
$olapfile="../OLAPEngine.inc.php";
include 'examplearray.php';
$facts=$data;
$dimensions=[
	'city'=>['type'=>'string','hierarchy'=>['default' => ['country','region']], 'datatype'=>'dimension' ],
	'agent'=>['type'=>'string', 'datatype'=>'dimension'],
	'code'=>['type'=>'string', 'datatype'=>'dimension',
				'bundlefield'=>['item','sku'], 
				'hierarchy'=>[
						'default'=>[
							'category'=>['type'=>'string', 'datatype'=>'dimension']
						]] ],
	'date'=>['type'=>'date',]
	]; 

$othersfield=[
	'sku'=>['type'=>'string','datatype'=>'others', 'basecolumn'=>'code'],
	'item'=>['type'=>'string','datatype'=>'others', 'basecolumn'=>'code'],
	'sales'=>['type'=>'double','datatype'=>'measure'],
	'cost'=>['type'=>'double','datatype'=>'measure'],
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
	
		$dimension='country';
		$arrdimension=['code','city'];
		$measures=[
			'sales',//sum sales, default is sum, return as sales
			['field'=>'cost','agg'=>'sum'], //sum sales, return sales_sum			
			['field'=>'profit','querystr'=>'SUM(CASE WHEN `sales` >=131  THEN 1 ELSE 0 END) '], //custom,  'profit' not exists, it will run callback to get value
		];
			// 'date'=>[['from'=>'2010-01-01','to'=>'2020-12-31']],
		$filtercomponent=[
			
		];
		$sorts=[['code'=>'DESC'],['profit'=>'ASC']];
		$summary=$cube->aggregateByMultiDimension($arrdimension,$measures,$filtercomponent,$sorts); //summary of country
		writedebug($summary,'aggregate');

		

	}
}
else
{
	echo $olapfile .' not exits!';
}

