<?php

declare(strict_types=1);
	class IPSDimmer extends IPSModule
	{
	    private const MODULE_PREFIX = 'DIM';
	    
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			##### Target
			$this->RegisterPropertyInteger('TargetVariable', 0);
			
			$this->RegisterPropertyInteger('TargetBrightness', 0);
			
			$this->RegisterPropertyInteger('TargetColor', 0);
			
			##### Input Switch variable
			$id = @$this->GetIDForIdent('DIMStatus');
            $id = $this->RegisterVariableBoolean('DIMStatus', 'Status', '~Switch', 10);
			$this->EnableAction('DIMStatus');
			if (!$id) {
                $this->SetValue('DIMStatus', true);
            }
			
			##### Einstellungen
			
			$this->RegisterPropertyInteger('DimSpeed', 10);
			
			$this->RegisterPropertyInteger('EndColor', 500);
			
			$this->RegisterPropertyInteger('EndIntensity', 255);
			
			$script = self::MODULE_PREFIX .'_' . 'Timer(' . $this->InstanceID . ');
		    $this->RegisterTimer("DimTimer",0,$script);


		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
		    //Wait until IP-Symcon is started
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        
			//Never delete this line!
			parent::ApplyChanges();
			
			//Check runlevel
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
    
            //Delete all references
            foreach ($this->GetReferenceList() as $referenceID) {
                $this->UnregisterReference($referenceID);
            }
    
            //Delete all update messages
            foreach ($this->GetMessageList() as $senderID => $messages) {
                foreach ($messages as $message) {
                    if ($message == VM_UPDATE) {
                        $this->UnregisterMessage($senderID, VM_UPDATE);
                    }
                }
            }
            
            $id = @$this->GetIDForIdent("DIMStatus");
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            
            //Reset buffer
            $this->SetBuffer("LastMessage", json_encode([]));

		}
		
		public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
    
                case "DIMStatus":
                    $this->SetValue($Ident, $Value);
                    break;
    
                
            }
        }
		
		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
            if (!empty($Data)) {
                foreach ($Data as $key => $value) {
                    $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
                }
            }
    
            if (json_decode($this->GetBuffer('LastMessage'), true) === [$SenderID, $Message, $Data]) {
                $this->SendDebug(__FUNCTION__, sprintf(
                    'Doppelte Nachricht: Timestamp: %s, SenderID: %s, Message: %s, Data: %s',
                    $TimeStamp,
                    $SenderID,
                    $Message,
                    json_encode($Data)
                ), 0);
                return;
            }
    
            $this->SetBuffer('LastMessage', json_encode([$SenderID, $Message, $Data]));
    
            switch ($Message) {
                case IPS_KERNELSTARTED:
                    $this->KernelReady();
                    break;
    
                case EM_UPDATE:
                   
                    break;
    
                case VM_UPDATE:
    
                    if ($SenderID == @$this->GetIDForIdent('DIMStatus') && $Data[1]) { // only on change

    					$this->RunDimmer($Data[0]);
                    }   
                    
                   
                    break;
    
            }
        }
        private function RunDimmer($targetState)
        {
            
            $timeslice = 300; //300mm Timer
            
            $targetVariable = $this->ReadPropertyInteger('TargetVariable');
            if ($targetVariable != 0 && @IPS_ObjectExists($targetVariable)) 
            {
                $current = GetValueBoolean($targetVariable);
                
                if ($current != $targetState)
                {
                    //es findet eine Änderung des Zustands statt
                    $this->SendDebug("Dimmer", "Dimme nach " . $targetState ? "an":"aus", 0);


                    if ($targetState == TRUE)
                    {
                        $targetBrightness = $this->ReadPropertyInteger('TargetBrightness');
                        if ($targetBrightness != 0 && @IPS_ObjectExists($targetBrightness)) 
                        {
                            @RequestAction($targetBrightness, 0);
                        }
                        $start = 0;
                        $targetColor = $this->ReadPropertyInteger('TargetColor');
                        if ($targetColor != 0 && @IPS_ObjectExists($targetColor)) 
                        {
                            @RequestAction($targetColor, 555); //ganz warm
                        }
                        
                        @RequestAction($targetVariable, $targetState);
                        
                        $end = $this->ReadPropertyInteger('EndIntensity');
                        $duration = $this->ReadPropertyInteger('DimSpeed') * 1000.0;
                        
                        $steps = $duration / $timeslice;
                        $step = ($end-$start)/$steps;
                        
                        
				        $this->SendDebug('Dimmer', 'Dimme ' . $start . ' ' . $step . ' ->' . $end, 0);
				        
				        $this->SetTimerInterval("DimTimer", $timeslice);
                    }
                    else
                    {
                        @RequestAction($targetVariable, $targetState);
                        $this->SetTimerInterval("DimTimer", 0);
                    }
                    
                    
                }
            }
            
        }
        public function Timer():void
        {
            $current = $this->ReadAttributeFloat('DimmingCurrent');
            $step = $this->ReadAttributeFloat('DimmingStep');
            $end = $this->ReadAttributeFloat('DimmingEnd');
            
            $current = $current + $step;
            $this->SendDebug('Dimmer', 'Dimme ' . $current . ' ->' . $finish, 0);
            
            if (($step > 0) && ($current > $end))
            {
                $this->SetTimerInterval("DimTimer", 0); //timer off
                $current = $end;
            }
            
            if (($step < 0) && ($current < $end))
            {
                $this->SetTimerInterval("DimTimer", 0); //timer off
                $current = $end;
            }
            
            $targetBrightness = $this->ReadPropertyInteger('TargetBrightness');
            if ($targetBrightness != 0 && @IPS_ObjectExists($targetBrightness)) 
            {
                @RequestAction($targetBrightness, $current);
            }
        }
        
	}