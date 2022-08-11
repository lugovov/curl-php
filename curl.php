<?php

class Curl{
	private $options	= array();
	private $handler	= false;
	public $debug = false;
	private $lastHeaders = null;
	private $lastHeadersParsed = null;
	private $logFile=null;
	private $curlOptions=[];
	public function __construct($options=array()){
		if(is_array($options))
			$this->options=$options;
		$this->setopt (CURLOPT_FOLLOWLOCATION,true);
		$this->setopt (CURLOPT_MAXREDIRS,30);
		$this->setopt (CURLOPT_HEADER,true);
		$this->setopt (CURLINFO_HEADER_OUT,true);
		$this->setopt (CURLOPT_RETURNTRANSFER,true);
		$this->setopt (CURLOPT_CONNECTTIMEOUT,$this->getConfig('timeout',5));
		$this->setopt (CURLOPT_TIMEOUT,$this->getConfig('timeout',5));			
		return $this;
	}
	public function __destruct()
	{
		if (is_resource($this->handler))
			curl_close($this->handler);
	}
	public function __wakeup()
	{
		if (!is_resource($this->handler))
			$this->handler = curl_init();
	}
	public function setopt($opt,$value){
		if($value==null)
			unset($this->curlOptions[$opt]);
		else
			$this->curlOptions[$opt]=$value;
	}
	public function set_option($var, $value)
	{
		$var		= strtolower($var);
		$this->options[$var]	= $value;
	}
	public function getConfig($name,$default=null){
		if(array_key_exists($name,$this->options))
			return $this->options[$name];
		return $default;
	}
	public function initlog($filename){
	    $this->logFile=$filename;
	}
	private function buildHeaders($h){
		$headers=array();
		foreach($h as $k=>$v){
			$headers[]=$k.': '.$v;
		}
		return $headers;
	}
	public function get($url,$options=array()){
		if($options && !is_array($options))
			return false;
		if(!$options)
			$options=array();
		if(array_key_exists('data',$options)){
			if(is_array($options['data']))
				$options['data']=http_build_query($options['data']);
			$url.=((false!==strpos($url,'?'))?'&':'?')+$options['data'];
			unset($options['data']);
		}else{
			$options['data']=array();
		}
		$options['url']=$url;
		$options['method']='get';
		return $this->query($options);
	}
	public function post($url,$options=array()){
		if($options && !is_array($options))
			return false;
		if(!$options)
			$options=array();
		$options['url']=$url;
		$options['method']='post';
		return $this->query($options);
	}
	public function put($url,$options=array()){
		if($options && !is_array($options))
			return false;
		if(!$options)
			$options=array();
		$options['url']=$url;
		$options['method']='put';
		return $this->query($options);
	}
	public function delete($url,$options=array()){
		if($options && !is_array($options))
			return false;
		if(!$options)
			$options=array();
		$options['url']=$url;
		$options['method']='delete';
		return $this->query($options);
	}	
	public function parseHeaders(){
		if($this->lastHeadersParsed==null && $this->lastHeaders){
			$this->lastHeadersParsed=array();
			foreach($this->lastHeaders as $h){
				list($type,$val)=explode(':',trim($h),2);
				$this->lastHeadersParsed[strtolower(trim($type))]=trim($val);
			}
		}
		return $this->lastHeadersParsed;
	}
	public function query($options){
		$ch=curl_init($options['url']);
		curl_setopt_array($ch,$this->curlOptions);
		
		if('post'==$options['method']){
			curl_setopt ($ch,CURLOPT_POST,true);
		}
		if('put'==$options['method']){
			curl_setopt ($ch,CURLOPT_PUT,true);
		}
		if('delete'==$options['method']){
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}
		if(!array_key_exists('headers',$options))
			$options['headers']=array();
			
		if('post'==$options['method']|| 'put'==$options['method']){
			if(!array_key_exists('Content-type',$options['headers']))
				$options['headers']['Content-type']='application/x-www-form-urlencoded';
			 if(is_array($options['data']))
				 $data=http_build_query($options['data']);
			 else
				$data=$options['data'];
				
			curl_setopt ($ch,CURLOPT_POSTFIELDS,$data);

  		}else{
			$data=null;
		}
		curl_setopt ($ch,CURLOPT_HTTPHEADER,$this->buildHeaders($options['headers']));
		

		$text=curl_exec($ch);
    if(curl_errno($ch))
    {
		if($this->logFile){
		    file_put_contents(
				$this->logFile,
				date('Y/m/d H:i:s').
					' ['.getmypid().']: Ошибка curl: ' . 
					curl_error($ch).PHP_EOL.
					var_export($this->curlOptions,true)
				,FILE_APPEND | LOCK_EX);
		}
    }
		if($this->logFile){
		    file_put_contents($this->logFile,date('Y/m/d H:i:s').' ['.getmypid().']: '.
		    $options['url']."\n".
		    curl_getinfo($ch,CURLINFO_HEADER_OUT).
		    $data."\n".
		    "\n<<<\n".
		    $text
		    ."\n -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- \n\n",FILE_APPEND | LOCK_EX);
		}
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$this->lastHeaders=explode("\n",substr($text,0,$header_size));
		$response = substr($text, $header_size);
		return $response;
	}
}
