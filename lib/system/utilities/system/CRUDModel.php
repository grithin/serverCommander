<?
class CRUDModel{
	static $columns,$usedColumns,$validaters;
	static $update,$insert;
	static function handleColumns(){
		$columns = Db::columnsInfo(PageTool::$model['table']);
		$usedColumns = PageTool::$model['columns'] ? PageTool::$model['columns'] : array_keys($columns);
		
		//create validation and deal with special columns
		foreach($usedColumns as $column){
			//special columns
			if($column == 'time_created'){
				Page::$in[$column] = i()->Time('now',Config::$x['timezone'])->datetime();
			}elseif($column == 'time_updated'){
				Page::$in[$column] = i()->Time('now',Config::$x['timezone'])->datetime();
			}elseif($column == 'id'){
				$validaters[$column][] = 'f:toString';
				$validaters[$column][] = '?!v:filled';
				$validaters[$column][] = '!v:existsInTable|'.PageTool::$model['table'];
			}else{
				$validaters[$column][] = 'f:toString';
				if(!$columns[$column]['nullable']){
					//column must be present
					$validaters[$column][] = '!v:exists';
				}else{
					//column may not be present.  Only validate if present
					$validaters[$column][] = '?!v:filled';
				}
				switch($columns[$column]['type']){
					case 'datetime':
						$validaters[$column][] = '!v:date';
						$validaters[$column][] = 'f:toDatetime';
					break;
					case 'date':
						$validaters[$column][] = '!v:date';
						$validaters[$column][] = 'f:toDatetime';
					break;
					case 'text':
						if($columns[$column]['limit']){
							$validaters[$column][] = '!v:lengthRange|0,'.$columns[$column]['limit'][0];
						}
					break;
					case 'int':
						$validaters[$column][] = 'f:trim';
						$validaters[$column][] = '!v:isInteger';
					break;
					case 'decimal':
					case 'float':
						$validaters[$column][] = 'f:trim';
						$validaters[$column][] = '!v:isFloat';
					break;
				}
			}
		}
		
		self::$columns = $columns;
		self::$usedColumns = $usedColumns;
		self::$validaters = $validaters;
	}
	
	static function validate(){
		self::handleColumns();
		if(method_exists('PageTool','validate')){
			PageTool::validate();
		}
		//CRUD standard validaters come after due to them being just the requisite validaters for entering db; input might be changed to fit requisite by PageTool validaters.
		if(self::$validaters){
			Page::filterAndValidate(self::$validaters);
		}
		return !Page::errors();
	}
	
	//only run db changer functions if PageTool::$model['table'] available
	static function create(){
		if(self::validate()){
			self::$insert = Arrays::extract(self::$usedColumns,Page::$in);
			unset(self::$insert['id']);
			$id = Db::insert(PageTool::$model['table'],self::$insert);
			return $id;
		}
	}
	static function update(){
		if(self::validate()){
			self::$update = Arrays::extract(self::$usedColumns,Page::$in);
			unset(self::$update['id']);
			Db::update(PageTool::$model['table'],self::$update,PageTool::$id);
			return true;
		}
	}
	static function delete(){
		return Db::delete(PageTool::$model['table'],PageTool::$id);
	}
	static function read(){
		if(Page::$data->item = Db::row(PageTool::$model['table'],PageTool::$id)){
			return true;
		}
		if(Config::$x['CRUDbadIdCallback']){
			call_user_func(Config::$x['CRUDbadIdCallback']);
		}
		
	}
}
