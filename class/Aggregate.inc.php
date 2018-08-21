<?php

class Aggregate extends OLAPClass
{
		
	public function __construct(&$facts)
	{
		parent::__construct();	
		$this->facts = &$facts;
		// echo '<pre>'.print_r($this->facts,true).'</pre><hr/>';
	}

	public function aggregateSummary(&$indexes,&$measures)
	{
		// echo '<pre>'.print_r($measures,true).'</pre><hr/>';
		$data=[];
		$rows=$this->getFactsFromIndex($this->facts,$indexes);

		foreach($rows as $i => $r)
		{
	//		echo '<hr/><pre>'.$i.'/'.count($rows).' , count facts='.count($this->facts).','.$indication.','.print_r($r,true).'</pre>';


			foreach($measures as $mi => $mo)
			{
				// if(!isset($r[$fieldname]))
				// {
				// 	continue;
				// }
				$fieldname=$mo['field'];
				// echo $fieldname.' = '.$r[$fieldname].' <br/>';
				if(!isset($data[$fieldname]))
				{
					$data[$fieldname]=array(
						'sum'=>$r[$fieldname],
						'count'=>1,
						'avg'=>0,
						'max'=>$r[$fieldname],
						'min'=>$r[$fieldname],
					);	
				}
				else
				{
					$data[$fieldname]['sum']+=$r[$fieldname];
					$data[$fieldname]['count']++;
					if($data[$fieldname]['max']<$r[$fieldname])
					{
						$data[$fieldname]['max']=$r[$fieldname];
					}
					if($data[$fieldname]['min']>$r[$fieldname])
					{
						$data[$fieldname]['min']=$r[$fieldname];
					}

				}


				
			}
		}

		//calculate average by using sum/count
		foreach($data as $fieldname => $aggobj)
		{
			$data[$fieldname]['avg']=$data[$fieldname]['sum'] / $data[$fieldname]['count'];
		}

		return $data;

	}
}
