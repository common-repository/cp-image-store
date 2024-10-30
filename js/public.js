var codepeople_image_store = function(){
	var $ = jQuery;
	if('undefined' != typeof $.codepeople_image_store_flag) return;
	$.codepeople_image_store_flag = true;

	var xmouse, ymouse;

    $(document).bind( 'mousemove', function( e ){
        xmouse = e.pageX;
        ymouse = e.pageY;
    } );

	var cpis_mousemove = function( e ) {
        if(
            e.pageX < e.data.x1 ||
            e.pageX > e.data.x2 ||
            e.pageY < e.data.y1 ||
            e.pageY > e.data.y2
        ){
            $( document ).unbind( 'mousemove', cpis_mousemove );
            $( '.cpis-image-data' ).hide();
        }
    };

	window[ 'cpis_display_dlg' ] = function( e, id, incorner ){

        // Close all image data panels
        $( '.cpis-image-data' ).hide();

        var d = $( '#cpis_image'+id );
        if( $( window ).width() < 600 )
		{
			return;
		}

        e = $( e );
        setTimeout(
            function(){
                if(d.length){
                    var e_o = e.offset();
                    if( xmouse >= e_o.left && xmouse <= e_o.left + e.width() &&
                        ymouse >= e_o.top  && ymouse <= e_o.top  + e.height() ){

                        if( incorner ){
                            d.show().position(
                                {
                                    my: "left top",
                                    at: "left top",
                                    of: e
                                }
                            );
                        }else{
                            d.show().position(
                                {
                                    my: "center",
                                    at: "center",
                                    of: e
                                }
                            );
                        }

                       var d_o = d.offset();
                        $( document ).bind(
                            'mousemove',
                            {
                                x1: d_o.left,
                                y1: d_o.top,
                                x2: d_o.left + d.width(),
                                y2: d_o.top + d.height()
                            },
                            cpis_mousemove
                        );
                    }
                }
            },
            1000
        );
    };

    window[ 'cpis_buynow' ] = function( e ){
        if( $( e ).parents( 'form' ).find( 'input:checked' ).length ) e.form.submit();
        else if( image_store && image_store[ 'file_required_str' ] ) alert( image_store[ 'file_required_str' ] );
    };

    // Main Code
    (
        function(){
            // Carousel routines
            // Set carousel

            var c = $('[id="cpis-image-store-carousel"]');
            if( c.length ){
				c.css( 'visibility', 'hidden' );
				var li = c.find( 'li' );
                if( li.length ){
                    var l = $('<div class="cpis-carousel-left" style="" ></div>'),
                        r = $('<div class="cpis-carousel-right" style="" ></div>'),
                        i = c.find( '.cpis-carousel-container' ),
                        u = $(c.find('ul').get(0));

                    if( image_store && image_store[ 'thumbnail_h' ] ){
                        l.css( 'top', ( ( image_store[ 'thumbnail_h' ]-60 ) / 2) + 'px' );
                        r.css( 'top', ( ( image_store[ 'thumbnail_h' ]-60 ) / 2) + 'px' );
                    }
                    i.width( c.width() -  42 );
                    c.append( l ).append( r );
                    var _auto = false;
                    if( image_store && image_store[ 'carousel_autorun' ] != 0){
                        _auto = { pauseOnHover: 'resume' };
                    }

					setTimeout(
						function()
						{
							c.css( 'visibility', 'visible' );
							u.carouFredSel({
								width: '100%',
								height: 'auto',
								prev: '.cpis-carousel-left',
								next: '.cpis-carousel-right',
								auto: _auto
							});
						},
						1000
					);
                }
            }

            // Set license handle
            $( '.cpis-license-title.cpis-link' ).click(
                function(){
                    var e = $( this );
                    $( '.cpis-license-container' )
                        .toggle()
                        .position(
                            {
                                my: 'left top',
                                at: 'left top',
                                of: e
                            }
                        );
                }
            );

            $( '.cpis-license-close' ).click(
                function(){
                    $( '.cpis-license-container' ).hide();
                }
            );
        }
    )();

    timeout_counter = 10;
    window['cpis_counting'] = function()
    {
        var loc = document.location.href;
        document.getElementById( "cpis_error_mssg" ).innerHTML = timeout_text+' '+timeout_counter;
        if( timeout_counter == 0 )
        {
            document.location = loc+( ( loc.indexOf( '?' ) == -1 ) ? '?' : '&' )+'timeout=1';
        }
        else
        {
            timeout_counter--;
            setTimeout( cpis_counting, 1000 );
        }
    };

    if( $( '[id="cpis_error_mssg"]' ).length )
    {
        cpis_counting();
    }
};

jQuery(codepeople_image_store);
jQuery(window).on('load',codepeople_image_store);