<?php

namespace PerryRylance;

use PerryRylance\DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

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
	public function __construct(Array $options=[])
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
		
		$this->initDocument();
	}
	
	abstract public function getTableName();
	abstract public function getRoute();
	
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
	
	protected function applySearch(Request $request, Builder $query)
	{
		if(!$request->has("search"))
			return;
		
		// TODO: Support JSON input (including value and regex)
		// TODO: Support multiple, space separated words
		
		$keyword	= $request->input("search");
		$context	= $this->getSearchContext();
		$arr		= [];
		
		foreach($this->getColumns() as $key => $obj)
		{
			if(!$this->isColumnSearchable($obj['type']))
				continue;
			
			$query->$context($key, "LIKE", "%$keyword%");
		}
	}
	
	protected function applyOrder(Request $request, Builder $query)
	{
		if(!$request->has("order"))
			return;
		
		// TODO: Test this
		
		$columns	= $this->getColumns();
		$keys		= array_keys($columns);
		$index		= $request->input("order");
		$dir		= "asc";	// TODO: Get this from request
		
		$query->orderBy($keys[$index], $dir);
	}
	
	protected function applyLimit(Request $request, Builder $query)
	{
		if($request->has("start"))
			$query->offset($request->input("start"));
		
		if($request->has("length"))
			$query->limit($request->input("length"));
	}
	
	public function getQuery(Request $request)
	{
		$query		= DB::table( $this->getTableName() )->
			select( array_keys($this->getColumns()) );
		
		$this->applySearch($request, $query);
		$this->applyOrder($request, $query);
		$this->applyLimit($request, $query);
		
		return $query;
	}
	
	public function getRecords(Request $request)
	{
		$query		= $this->getQuery($request);
		
		return $query->get()->toArray();
	}
}
