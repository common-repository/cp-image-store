jQuery(window).on('load', function(){

    var $ = jQuery,
		reports = []; //Array of reports used to hide or display items from reports

    // Sales Reports
    window[ 'cpis_reload_report' ] = function( e ){
        var e  			  = $(e),
            report_id 	  = e.attr( 'report' ),
            report  	  = reports[ report_id ],
            datasets 	  = [],
            container_id  = '#'+e.attr( 'container' ),
            type 		  = e.attr( 'chart_type' ),
            checked_items = $( 'input[report="'+report_id+'"]:CHECKED' ),
            dataObj;

        checked_items.each( function(){
            var i = $(this).attr( 'item' );
            if( type == 'Pie' ) datasets.push( report[ i ] );
            else datasets.push( report.datasets[ i ] );
        } );

        if ( type == 'Pie' ) dataObj = datasets;
        else dataObj = { 'labels' : report.labels, 'datasets' : datasets };

        new Chart( $( container_id ).find( 'canvas' ).get(0).getContext( '2d' ) )[ type ]( dataObj, { scaleStartValue: 0 } );
    };

    window[ 'cpis_load_report' ] = function( el, id, title, data, type, label, value ){
        function get_random_color() {
            var letters = '0123456789ABCDEF'.split('');
            var color = '#';
            for (var i = 0; i < 6; i++ ) {
                color += letters[Math.round(Math.random() * 15)];
            }
            return color;
        };

        if(el.checked){
            var container = $( '#'+id );

            if( container.html().length){
                container.show();
            }else{
                if( typeof cpis_global != 'undefined' ){
                    var from  = $( '[name="from_year"]' ).val()+'-'+$( '[name="from_month"]' ).val()+'-'+$( '[name="from_day"]' ).val(),
                        to    = $( '[name="to_year"]' ).val()+'-'+$( '[name="to_month"]' ).val()+'-'+$( '[name="to_day"]' ).val();

                    jQuery.getJSON( cpis_global.aurl, { 'cpis-action' : 'paypal-data', 'data' : data, 'from' : from, 'to' : to }, (function( id, title, type, label, value ){
                            return function( data ){
                                        var datasets = [],
                                            dataObj,
                                            legend = '',
                                            color,
                                            tmp,
                                            index = reports.length;


                                        for( var i in data ){
                                            var v = Math.round( data[ i ][ value ] );

                                            if( typeof tmp == 'undefined' || tmp == null || data[ i ][ label ] != tmp ){
                                                color 	= get_random_color();
                                                tmp 	= data[ i ][ label ];
                                                legend 	+= '<div style="float:left;padding-right:5px;"><input type="checkbox" CHECKED chart_type="'+type+'" container="'+id+'" report="'+index+'" item="'+i+'" onclick="cpis_reload_report( this );" /></div><div class="cpis-legend-color" style="background:'+color+'"></div><div class="cpis-legend-text">'+tmp+'</div><br />';
                                                if( type == 'Pie' ) datasets.push( { 'value' : v, 'color' : color } );
                                                else datasets.push( { 'fillColor' : color, 'strokeColor' : color, data:[ v ] } );

                                            }else{
                                                datasets[ datasets.length - 1][ 'data' ].push( v );
                                            }
                                        }

                                        var e = $( '#'+id );
                                        e.html('<div class="cpis-chart-title">'+title+'</div><div class="cpis-chart-legend"></div><div style="float:left;"><canvas width="400" height="400" ></canvas></div><div style="clear:both;"></div>');

                                        // Create legend
                                        e.find( '.cpis-chart-legend').html( legend );

                                        if( type == 'Pie' ) dataObj = datasets;
                                        else dataObj = { 'labels' : [ 'Currencies' ], 'datasets' : datasets };

                                        reports[index] = dataObj;
                                        var chartObj = new Chart( e.find( 'canvas' ).get(0).getContext( '2d' ) )[ type ]( dataObj );
                                        e.show();
                                    }
                        })( id, title, type, label, value )
                    );
                }
            }
        }else{
            $( '#'+id ).hide();
        }
    };

    // Methods definition
    window['cpis_remove'] = function(e){
        $(e).parents('.cpis-property-container').remove();
    };

    window['cpis_select_element'] = function(e, add_to, new_element_name){
        var v = e.options[e.selectedIndex].value,
            t = e.options[e.selectedIndex].text;
        if(v != 'none'){
            $('#'+add_to).append(
                '<div class="cpis-property-container"><input type="hidden" name="'+new_element_name+'[]" value="'+v+'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'+t+' [x]"></div>'
            );
        }
    };

    window['cpis_add_element'] = function(input_id, add_to, new_element_name){
        var n = $('#'+input_id),
            v = n.val();
        n.val('');
        if( !/^\s*$/.test(v)){
            $('#'+add_to).append(
                '<div class="cpis-property-container"><input type="hidden" name="'+new_element_name+'[]" value="'+v+'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'+v+' [x]"></div>'
            );
        }
    };

    window['cpis_delete_purchase'] = function(id){
        if(confirm('Are you sure to delete the purchase record?')){
            var f = $('#purchase_form');
            f.append('<input type="hidden" name="delete_purchase_id" value="'+id+'" />');
            f[0].submit();
        }
    };

    window['cpis_reset_purchase'] = function(id){
        var f = $('#purchase_form');
        f.append('<input type="hidden" name="reset_purchase_id" value="'+id+'" />');
        f[0].submit();
    };

    window['cpis_show_purchase'] = function(id){
        var f = $('#purchase_form');
        f.append('<input type="hidden" name="show_purchase_id" value="'+id+'" />');
        f[0].submit();
    };

    window["cpis_new_file"] = function(){
        var nTr =   ' \
                        <tr> \
                            <td style="width:60%;"><input type="file" name="cpis_file_new[]" style="width:100%;" class="button-secondary" /></td> \
                            <td style="width:10%;"><input type="text" name="cpis_file_width_new[]" class="cpis-short-field" /></td>     \
                            <td style="width:10%;"><input type="text" name="cpis_file_height_new[]" class="cpis-short-field" /></td>    \
                            <td style="width:10%;"><input type="text" name="cpis_file_price_new[]" class="cpis-short-field" /></td>     \
                            <td style="width:10%;"> <input type="button" onclick="cpis_remove_file(this);" value="Remove"  class="button-secondary" /></td> \
                        </tr> \
                    ';

        $('.cpis-file-list').append(nTr);
    };

    window["cpis_remove_file"] = function( e, id ){
        e = $(e);
        if(id){
            if( e.data( 'processing' ) == 1) return;
            e.data( 'processing', 1 );

            if( image_store && image_store.hurl ){
                e.parents('tr').fadeTo( 'normal', 0.5 );
                $.getJSON(
                    image_store.hurl+'?cpis-action=remove-image&image='+id,
                    function( data ){
						try
						{
							if( data.error){
								alert( data.error );
								e.parents('tr').fadeTo( 'normal', 1 );
								e.data( 'processing', 0 );
							}else{
								e.parents('tr').remove();
							}
						}
                        catch( err )
						{
							e.parents('tr').fadeTo( 'normal', 1 );
						}
                    }
                );
            }
        }else{
            e.parents('tr').remove();
        }
    };

    window ['cpis_insert_store'] = function(){
        if(send_to_editor) send_to_editor('[codepeople-image-store]');
    };

    window ['cpis_insert_product_window'] = function(){
        var tags = ( image_store ) ? image_store.tags : '',
            cont = $(tags.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"'));

        cont.dialog({
            dialogClass: 'wp-dialog',
            modal: true,
            closeOnEscape: true,
            close: function(){
                $(this).remove();
            },
            buttons: [
                {text: 'OK', click: function() {
                    var id  = $('#product_id').val(),
                        l   = $('#layout').val(),
                        sc  = '[codepeople-image-store-product id="'+id+'" layout="'+l+'"]';

                    if(send_to_editor) send_to_editor(sc);
                    $(this).dialog("close");
                }}
            ]
        });
    };

	window[ 'cpis_export_csv' ] = function( e ){
		e = $( e );
		var f = e.closest( 'form' );

		e.after( '<input type="hidden" name="cpis-action" value="csv" />' );
		f.attr( 'target', '_blank' )[ 0 ].submit();
		f.attr( 'target', '_self' )
		 .find( '[name="cpis-action"]' )
		 .remove();
	};

    $( '#cpis_layout' ).change(
		function()
		{
			var e = $( this ).find( ':selected' ),
				thumbnail_url = e.attr( 'thumbnail' );

			$( '#cpis_layout_thumbnail' ).html( ( typeof thumbnail_url != 'undefined' ) ? '<img src="'+thumbnail_url+'" title="'+e.text()+'" />' : '' );
		}
	);
});