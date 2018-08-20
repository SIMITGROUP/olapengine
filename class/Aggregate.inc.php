<?php

class Aggregate extends OLAPClass
{
	public function __construct()
	{
		parent::__construct();	
	}

	public function aggregateSummary($facts,$c)
	{
		return $c;
	}
}
