<?
///Supplementing language time functions
class Time extends DateTime{
	///creates a DateTime object with some additional functionality
	/**
	@param	time	various forms:
		- DateTime object
		- relative time ("-1 day")
		- variously formatted date ("2011-04-04 01:01:01")
	@param	zone	zone as accepted by Time::getZone
	@param	relative	a time which the first time parameter is relative to.  If not specified, a relative first time is considered relative to current time.  Has the same forms as "time" parameter.
	*/
	function __construct($time=null,$zone=null,$relativeTo=null){
		$zone = $this->getZone($zone);
		if($relativeTo !== null){
			$return = parent::__construct($this->getTime($relativeTo,$zone),$zone);
			$this->modify($time);
			return $return;
		}
		return parent::__construct($this->getTime($time,$zone),$zone);
	}
	function __toString(){
		return $this->datetime();
	}
	///creates a DateTimeZone object based on variable input
	/**
	@param	zone	various forms:
		- DateTimeZone objects; will just return the object
		- =false; will use date_default_timezone_get
		- !=false; will pass to DateTimeZone constructor and return result
	*/
	function getZone($zone){
		if(!is_a($zone,'DateTimeZone')){
			$zone = $zone ? new DateTimeZone($zone) : new DateTimeZone(date_default_timezone_get());
		}
		return $zone;
	}
	///used to conform a variable time and timezone to something DateTime::__construct will accept
	function getTime($time,$zone=null){
		$Date = $this->getDateTime($time,$zone);
		return $Date->format('Y-m-d H:i:s.u');
	}
	///used to get DateTime object based on non-DateTime::__construct-conforming input parameters
	function getDateTime($time,$zone=null){
		if(is_a($time,'DateTime')){
			return $time;
		}
		$zone = $this->getZone($zone);
		if(Tool::isInt($time)){
			$Date = new DateTime(null,$zone);
			$Date->setTimestamp($time);
			return $Date;
		}
		return new DateTime($time,$zone);
	}
	///Used to get format of current Time object using relative times
	/**
	@param	format	DateTime::format() format
	@param	zone	The zone of the output time
	@param	relation	see php relative times; ex "-1 day".
	*/
	function format($format,$zone=null,$relation=null){
		if($relation){
			$newDate = new Time($relation,$this->getTimezone(),$this);
			return $newDate->format($format,$zone);
		}
		if($zone){
			$currentZone = $this->getTimezone();
			$this->setZone($zone);
			$return = parent::format($format);
			$this->setZone($currentZone);
			return $return;
		}else{
			return parent::format($format);
		}
		
		
	}
	///Get the common dateTime format "Y-m-d H:i:s"
	/**
	@param	zone	The zone of the output time
	@param	relation	see Time::format()
	*/
	function datetime($zone=null,$relation=null){
		return $this->format("Y-m-d H:i:s",$zone,$relation);
	}
	///Get the common date format "Y-m-d"
	/**
	@param	zone	The zone of the output time
	@param	relation	see Time::format()
	*/
	function date($zone=null,$relation=null){
		return $this->format("Y-m-d",$zone,$relation);
	}
	function setZone($zone){
		return parent::setTimezone($this->getZone($zone));
	}
	///get DateInterval object based on current Time instance.
	/**
	@param	time	see Time::__construct()
	@param	zone	see Time::__construct(); defaults to current instance timezone
	@param	absolute	see DateTime::diff() "absolute" param
	*/
	function instance_diff($time,$zone=null,$absolute=null){
		if(is_a($time,'DateTime')){
			$absolute = $zone;
		}else{
			$zone = $zone ? $zone : $this->getTimezone();
			$time = new $class($time,$zone);
		}
		return $this->diff($time,$absolute);
	}
	///get DateInterval without having to separately make DateTime instances
	/**
	@param	time1	see Time::__construct()
	@param	time2	see Time::__construct()
	@param	timeZone1	timezone corresponding to time1; see Time::__construct()
	@param	timeZone2	timezone corresponding to time2; see Time::__construct()
	@param	absolute	see DateTime::diff() "absolute" param
	*/
	static function static_diff($time1, $time2=null, $timeZone1 = null, $timeZone2 = null, $absolute = null){
		$class = __class__;
		$Time1 = new $class($time1,$timeZone1);
		$Time2 = new $class($time2,$timeZone2);
		return $Time1->diff($Time2,$absolute);
	}
	///Get the start of the day.
	/**
	@param	newTimeObject	whether to return a new Time object or just a datetime string
	*/
	function dayStart($newTimeObject=false){
		$datetime = $this->format('Y-m-d 00:00:00');
		if($newTimeObject){
			return new Time($datetime,$this->getTimezone());
		}
		return $datetime;
	}
	///Get the end of the day.
	/**
	@param	newTimeObject	whether to return a new Time object or just a datetime string
	*/
	function dayEnd($newTimeObject=false){
		$datetime = $this->format('Y-m-d 23:59:59');
		if($newTimeObject){
			return new Time($datetime,$this->getTimezone());
		}
		return $datetime;
	}
	///Date validator
	/**
	@param	y	year
	@param	m	month (numeric representation starting a 1)
	@param	d	day (numeric representation starting a 1)
	*/
	static function validate($y,$m,$d){
		$d = abs((int)$d);
		$m = abs((int)$m);
		$y = abs((int)$y);
		
		if($y && $m && $d){
			$date = explode('-',$date);
			if(in_array($m,array(1,3,5,7,8,10,12))){
				//months with 31 days
				if($d>31){
					return false;
				}
			}elseif(in_array($m,array(4,6,9,11))){
				//months with 30 days
				if($d>30){
					return false;
				}
			}elseif($m == 2){
				//check for leap year
				if($d>(($y % 4 == 0) ? 29 : 28)){
					return false;
				}
			}
			return true;
		}
		return false;
	}
	///Get Diff object comparing current object ot current time
	function age(){
		return $this->diff(Datetime());
	}
	///Date validator
	/**
	@param	micro	true to return time + micro time in TIME.MICROTIME format
	*/
	function unix($micro=false){
		if($micro){
			return $this->format('U.u');
		}else{
			return $this->format('U');
		}
	}
	///Date validator
	/**
	@param	relative	string to apply as relative to current time object without modifying current time object
	*/
	function relative($relative){
		$copy = clone $this;
		$copy->modify($relative);
		return $copy;
	}
}
