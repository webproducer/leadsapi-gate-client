<?php
namespace Leadsapi\Gate;

class Result
{
	public $id = 0;
	
	public function __construct(int $id)
	{
		$this->id = $id;
	}
}

