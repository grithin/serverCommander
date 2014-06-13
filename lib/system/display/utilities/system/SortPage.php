<?
class SortPage{
	static $page;
	///get the sort and limit sql
	static function sortSql($options){
		
		#$acceptableSorts,$defaults,$perPage=50,$in=null
		if(!$in){
			$in = Page::$in;
		}
		
		if(!is_array($defaults)){
			$defaults = array($defaults,'desc');
		}
		
//+	filter orders
		$orders = array();
		if($in['_so']){
			if(!is_array($in['_so'])){
				$in['_so'] = array($in['_so']);
			}
			foreach($in['_so'] as $order){
				if($order == 'asc' || $order == 'desc'){
					$orders[] = $order;
				}
			}
		}else{
			if(!is_array($defaults[1])){
				$defaults[1] = array($defaults[1]);
			}
			$orders = $defaults[1];
		}
//+	}
//+	get field sorts
		$fields = array();
		$sqlSorts = array();
		if($in['_sf']){
			if(!is_array($in['_sf'])){
				$in['_sf'] = array($in['_sf']);
			}
			foreach($in['_sf'] as $field){
				if(in_array($field,$acceptableSorts) && !$fields[$field]){
					$order = array_pop($orders);
					$order = $order ? $order : 'asc';
					$fields[$field] = $order;
					$sqlSorts[] = $field.' '.$order;
				}
			}
		}
//+	}
		
		
		$sql = '';
		if($sqlSorts){
			$sql .= ' ORDER BY '.implode(', ',$sqlSorts);
		}
		
		if($in['_p']){
			$page = abs((int)$in['_p'] - 1);
		}else{
			$page = 0;
		}
		$offset = $perPage * $page;
		$sql .= ' LIMIT '.$offset.', '.$perPage;
		
		Display::$json['sort']['sorts'] = $fields;
		
		return $sql;
	}
	static function page($sql,$perPage=50,$max=null){
		if(Page::$in['_p']){
			$page = abs((int)Page::$in['_p'] - 1);
		}else{
			$page = 0;
		}
		$offset = $perPage * $page;
		$sql .= "\nLIMIT ".$offset.', '.$perPage;
		
		list($count,$rows) = Db::countAndRows(($max + 1),$sql);
		if($count > $max){
			Display::$json['sort']['total'] = $max;
			Display::$json['sort']['more'] = true;
		}else{
			Display::$json['sort']['total'] = $count;
		}
		Display::$json['sort']['page'] = self::$page = $page;
		Display::$json['sort']['perPage'] = $perPage;
		return $rows;
	}
}
