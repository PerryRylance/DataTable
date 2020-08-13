jQuery(function($) {
	
	if(!window.PerryRylance)
		window.PerryRylance = {};
	
	PerryRylance.DataTable = function(element)
	{
		if($.fn.dataTable.ext)
			$.fn.dataTable.ext.errMode = "throw";
		
		this.$element = $(element);
		
		var fields	= this.getColumnFields();
		var columns	= [];
		
		fields.forEach(function(field) {
			columns.push({
				"data": field
			});
		});
		
		this.$element.DataTable({
			"ajax":			this.$element.attr("data-route"),
			"processing":	true,
			"serverSide":	true,
			"columns":		columns
		});
	}
	
	PerryRylance.DataTable.createInstance = function(element)
	{
		return new PerryRylance.DataTable(element);
	}
	
	PerryRylance.DataTable.prototype.getColumnFields = function()
	{
		var results = [];
		
		this.$element.find("th[data-column-field]").each(function() {
			results.push( $(this).attr("data-column-field") );
		});
		
		return results;
	}
	
	$(window).on("load", function(event) {
		
		$("table.perry-rylance-datatable").each(function(index, el) {
			
			el.dataTable = PerryRylance.DataTable.createInstance(el);
			
		});
		
	});
	
});