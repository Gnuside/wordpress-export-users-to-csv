(function ($) {
	"use strict";
	
	$(document).ready(function () {
		
		$('input[data-toggle*="gnuside-eutcvs-checkbox"]').click(function() {
			this.checked ? $(this).attr('value', 'checked') : $(this).attr('value', '');

		});
		
		$('input[data-toggle*="gnuside-eutcvs-add-field"]').click(function() {
			var $tbody = $('tr[data-toggle*="gnuside-eutcvs-usermeta-button"]'),
				rowHtml = '';
				
			rowHtml += '<tr class="alternate" >';
			
			rowHtml += '<td class="manage-column" ><label>';
			rowHtml += '<input type="checkbox" name="eutcvs_checked_usermeta_val" data-toggle="gnuside-eutcvs-checkbox" />';
			rowHtml += '<input type="text" />';
			rowHtml += '</label></td>';
			
			rowHtml += '<td>';
			rowHtml += '<input type="text" name="eutcvs_usermeta_val" value="" />';
			rowHtml += '</td>';
			
			rowHtml += '<td>';
			rowHtml += '<input type="text" />';
			rowHtml += '</td>';
			
			rowHtml += '</tr>';
			
			$tbody.before( rowHtml );
		});
	});
}(jQuery));
