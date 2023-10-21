<?php

declare(strict_types=1);
	class IPSDimmer extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			##### Target
			$this->RegisterPropertyInteger('TargetVariable', 0);
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
		}
	}