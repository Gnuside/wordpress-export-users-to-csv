(function ($) {
	"use strict";
	
	$(document).ready(function () {
		
		$('input[data-name="gnuside-eutcvs-save"]').click( function(){
			var nameValue = this.getAttribute('data-name'),
				inputCsvName = $( 'input:text[data-name]' );
				
			$(this).attr('name', nameValue); 
			
			inputCsvName.each( function(){
				var nameValue = this.getAttribute('data-name');
				$(this).attr('name', nameValue); 
			});
		});
		
		$( "tbody" ).on("click",
			'input[data-toggle*="gnuside-eutcvs-checkbox"]',
			function() {
				this.checked ? $(this).attr('value', 'checked') : $(this).attr('value', '');
			}
		);
		
		$( "tbody" ).on(
			"click",
			'input[data-toggle*="gnuside-eutcvs-remove"]',
			function() {
				$( this ).closest('tr').remove();
			}
		);
		
		$( "tbody" ).on(
			"input",
			'input[data-toggle*="eutcvs_meta_id"]',
			function() {
				var $this = $(this),
					value = $this.val(),
					id = $this.attr('data-id');
				
				$this.attr('data-id', value);
				$this.attr('name', 'eutcvs_meta['+value+'][db_id]');
				$('[name*="eutcvs_meta['+id+'][checked]"]').attr('name', 'eutcvs_meta['+value+'][checked]');
				$('[name*="eutcvs_meta['+id+'][name]"]').attr('name', 'eutcvs_meta['+value+'][name]');
				$('[name*="eutcvs_meta['+id+'][desc]"]').attr('name', 'eutcvs_meta['+value+'][desc]');
			}
		);
		
		$('input[data-toggle*="gnuside-eutcvs-add-field"]').click(function() {
			var $metaButton = $('tr[data-toggle*="gnuside-eutcvs-usermeta-button"]'), 
				rowHtml = '',
				id = parseInt( Math.random() * 100000 );
				
			rowHtml += '<tr class="alternate" >';
			
			rowHtml += '<td class="manage-column" ><label>';
			rowHtml += '<input type="checkbox" name="eutcvs_meta['+id+'][checked]" data-toggle="gnuside-eutcvs-checkbox" />';
			rowHtml += '<input data-toggle="eutcvs_meta_id" data-id="'+id+'"type="text"  name="eutcvs_meta['+id+'][db_id]" />';
			rowHtml += '</label></td>';
			
			rowHtml += '<td>';
			rowHtml += '<input type="text" name="eutcvs_meta['+id+'][name]" value="" />';
			rowHtml += '</td>';
			
			rowHtml += '<td>';
			rowHtml += '<input type="text" name="eutcvs_meta['+id+'][desc]" />';
			rowHtml += '</td>';
			
			rowHtml += '<td>';
			rowHtml += '<input type="button" class="button-secondary" value="remove" data-toggle="gnuside-eutcvs-remove" />';
			rowHtml += '</td>';
			
			rowHtml += '</tr>';
			
			$metaButton.before( rowHtml );
		});
	});
}(jQuery));
