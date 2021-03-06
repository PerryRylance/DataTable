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
			$this->initDocument($options);
	}
	
	abstract public function getTableName();
	abstract public function getRoute();
	
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
	
	protected function getSelectColumns()
	{
		$keys		= array_keys($this->getColumns());
		$results	= [];
		
		foreach($this->getColumns() as $key => $value)
		{
			if(!isset($value['sql']))
			{
				$results []= $key;
				continue;
			}
			
			$expr	= DB::raw($value['sql']);
			$results []= $expr;
		}
		
		return $results;
	}
	
	protected function initDocument(Array $options = [])
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
		$table->data('route', $this->getRoute());

		$tr			= $this->querySelector("thead > tr");

		foreach($columns as $key => $arr)
		{
			if(isset($arr['display']) && $arr['display'] == false)
				continue;
			
			$th		= $this->createElement('th');
			
			$th->append($arr['caption']);
			
			$th->data("column-field", $key);
			$th->data("column-type", $arr['type']);
			
			$tr->append($th);
		}
		
		if(isset($options['autoInitialize']) && $options['autoInitialize'] == false)
			$table->data("auto-initialize", "false");
	}
	
	protected function getSearchContext()
	{
		return DataTable::CONTEXT_WHERE;
	}
	
	protected function isColumnSearchable($definition)
	{
		if(isset($definition['searchable']) && $definition['searchable'] == false)
			return false;
		
		if(!isset($definition['type']))
			return false;
		
		return preg_match('/^VARCHAR|TEXT$|^INT/i', $definition['type']);
	}
	
	protected function applySearch(Request $request, Builder $builder)
	{
		if(!$request->has("search"))
			return;
		
		// TODO: Support multiple, space separated words
		// TODO: Support REGEXP
		
		$search		= $request->input("search");
		$keyword	= $search['value'];
		
		if(empty($keyword))
			return;
		
		$context	= $this->getSearchContext();
		$arr		= [];
		
		foreach($this->getColumns() as $key => $definition)
		{
			if(!$this->isColumnSearchable($definition))
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
		$builder		= DB::table( $this->getTableName() )
			->select([DB::raw("SQL_CALC_FOUND_ROWS ")])
			->select($this->getSelectColumns());
		
		$this->applySearch($request, $builder);
		$this->applyOrder($request, $builder);
		$this->applyLimit($request, $builder);
		
		return $builder;
	}
	
	public function getRecords(Request $request)
	{
		$builder		= $this->getBuilder($request);
		$result			= [
			'data'				=> $builder->get()->toArray(),
			'recordsFiltered'	=> DB::select("SELECT FOUND_ROWS()")[0]->{'FOUND_ROWS()'},
			'recordsTotal'		=> DB::table($this->getTableName())->count(),
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
