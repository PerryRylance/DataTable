jQuery(function($) {
	
	if(!window.PerryRylance)
		window.PerryRylance = {};
	
	PerryRylance.DataTables.DataTable = function(element)
	{
		
	}
	
	PerryRylance.DataTables.DataTable.createInstance = function(element)
	{
		return new PerryRylance.DataTables.DataTable(element);
	}
	
	$(window).on("load", function(event) {
		
		$("table.perry-rylance-datatable").each(function(index, el) {
			
			el.dataTable = PerryRylance.DataTables.DataTable.createInstance(el);
			
		});
		
	});
	
});