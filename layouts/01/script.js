jQuery( 
	function( $ )
	{
		// Modify the filter and search options
        function _search_filter_box()
        {
            var c = $( '.cpis-image-store-left' ),
                s = c.find( '[name="search_terms"]' ).css( 'width', '80%' ),
                h;
            
            // Set the search and advanced buttons
            h = s.outerHeight();
            $( '<div class="advanced-btn btn"  style="height:'+h+'px;" ><div class="btn-icon" /></div><div class="search-btn btn" style="height:'+h+'px;" ><div class="btn-icon" /></div>' ).insertAfter( s );
            c.find( '.search-btn' ).click( function(){ $( this ).closest( 'form' ).submit(); } );
            c.find( '.advanced-btn' ).click( function(){ 
                $( '.cpis-image-store-left .cpis-column-title:not(:first-child),.cpis-image-store-left .cpis-filter:not(:nth-child(2))' )[
                    ( ( $( this ).toggleClass( 'advanced-btn-active' ).hasClass( 'advanced-btn-active' ) ) ? 'show' : 'hide' )
                ]();
            } );
        } // End _search_filter_box
        
        // Main application
        _search_filter_box();
    }    
);