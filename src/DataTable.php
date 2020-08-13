<?php

namespace PerryRylance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use PerryRylance\DOMDocument;

abstract class DataTable extends DOMDocument
{
	const CONTEXT_WHERE		= "orWhere";
	const CONTEXT_HAVING	= "orHaving";
	
	private $cachedColumns;	// NB: Used when * is supplied for columns, prevents multiple SHOW COLUMNS queries in getColumns
	private $excludeColumns;
	
	/**
	 * Constructor for the DataTable
	 * @param Array $options
	 */
	public function __construct($request, Array $options=[])
	{
		DOMDocument::__construct();
		
		$this->excludeColumns = [];
		
		if(!empty($options['excludeColumns']))
		{
			$arr = $options['excludeColumns'];
			
			if(!is_array($arr))
				throw new \Exception("excludeColumns must be an array");
			
			foreach($arr as $val)
				if(!is_string($val))
					throw new \Exception("excludeColumns must be an array of strings");
			
			$this->excludeColumns = $arr;
		}
		
		if(!($request instanceof Request && $request->wantsJson()))
			$this->initDocument();
	}
	
	abstract public function getTableName();
	abstract public function getRoute();
	
	public static function getLibraryScriptFilename()
	{
		return dirname(__DIR__) . '/lib/jquery.dataTables.min.js';
	}
	
	public static function getLibraryStyleFilename()
	{
		return dirname(__DIR__) . '/lib/jquery.dataTables.min.css';
	}
	
	public function getScriptFilename()
	{
		return dirname(__DIR__) . '/js/datatable.js';
	}
	
	public function registerRoute()
	{
		Route::get( $this->getRoute(), function(Request $request) {
			
			return $this->getRecords($request);
			
		} );
	}
	
	protected function getColumns()
	{
		if($this->cachedColumns)
			return $this->cachedColumns;
		
		$results	= DB::select('SHOW COLUMNS FROM ' . $this->getTableName());
		$columns	= [];
		
		foreach($results as $obj)
		{
			if(array_search($obj->Field, $this->excludeColumns) !== false)
				continue;
			
			$columns[$obj->Field] = [
				'caption'	=> $obj->Field,
				'type'		=> $obj->Type
			];
		}
		
		$this->cachedColumns = $columns;
				
		return $this->cachedColumns;
	}
	
	protected function initDocument()
	{
		$columns	= $this->getColumns();
		
		$this->loadHTML(<<<'EOD'
			<table class="perry-rylance-datatable">
				<thead>
					<tr></tr>
				</thead>
				<tbody>
				</tbody>
			</table>
EOD
		);
		
		$table		= $this->querySelector("table");
		$table->setAttribute('data-route', $this->getRoute());

		$tr			= $this->querySelector("thead > tr");

		foreach($columns as $key => $arr)
		{
			$th		= $this->createElement('th');
			
			$th->append($arr['caption']);
			
			$th->setAttribute("data-column-field", $key);
			$th->setAttribute("data-column-type", $arr['type']);
			
			$tr->append($th);
		}
	}
	
	protected function getSearchContext()
	{
		return DataTable::CONTEXT_WHERE;
	}
	
	protected function isColumnSearchable($type)
	{
		return preg_match('/^VARCHAR|TEXT$|^INT/i', $type);
	}
	
	protected function applySearch(Request $request, Builder $builder)
	{
		if(!$request->has("search"))
			return;
		
		// TODO: Support JSON input (including value and regex)
		// TODO: Support multiple, space separated words
		// TODO: Support REGEXP
		
		$search		= $request->input("search");
		$keyword	= $search['value'];
		
		if(empty($keyword))
			return;
		
		$context	= $this->getSearchContext();
		$arr		= [];
		
		foreach($this->getColumns() as $key => $obj)
		{
			if(!$this->isColumnSearchable($obj['type']))
				continue;
			
			$builder->$context($key, "LIKE", "%$keyword%");
		}
	}
	
	protected function applyOrder(Request $request, Builder $builder)
	{
		if(!$request->has("order"))
			return;
		
		// TODO: Support multiple sorts, not just $first
		
		$columns	= $this->getColumns();
		$keys		= array_keys($columns);
		$first		= $request->input("order")[0];
		$index		= $first['column'];
		$dir		= $first['dir'];
		
		// TODO: Security test, may need to whitelist
		
		$builder->orderBy($keys[$index], $dir);
	}
	
	protected function applyLimit(Request $request, Builder $builder)
	{
		if($request->has("start"))
			$builder->offset($request->input("start"));
		
		if($request->has("length"))
			$builder->limit($request->input("length"));
	}
	
	public function getBuilder(Request $request)
	{
		$builder		= DB::table( $this->getTableName() )->
			select( array_keys($this->getColumns()) );
		
		$this->applySearch($request, $builder);
		$this->applyOrder($request, $builder);
		$this->applyLimit($request, $builder);
		
		return $builder;
	}
	
	public function getRecords(Request $request)
	{
		$builder		= $this->getBuilder($request);
		$result			= [
			'data'	=> $builder->get()->toArray()
		];
		
		if(env('APP_DEBUG'))
		{
			$result['debug'] = [
				'sql'			=> $builder->toSql()
			];
		}
		
		return $result;
	}
}
