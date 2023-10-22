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
			
		    $this->RegisterTimer("DimTimer",0,"DIM_SyncStation(\$_IPS['TARGET']);");


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
            
            $id = @$this->GetIDForIdent('DIMStatus');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            
            //Reset buffer
            $this->SetBuffer('LastMessage', json_encode([]));

		}
		
		public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
    
                case 'DIMStatus':
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
        public function RunDimmer($On)
        {
            $this->SendDebug('Dimmer', 'Dimme nach ' . $On, 0);
            
            
        }
	}