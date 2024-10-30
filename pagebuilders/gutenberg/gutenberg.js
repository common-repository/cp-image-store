jQuery(function(){
	( function( blocks, element ) {
		var el 					= element.createElement,
			InspectorControls  	= ('blockEditor' in wp) ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;

		/* Plugin Category */
		blocks.getCategories().push({slug: 'cpis', title: 'Images Store'});

		/* ICONS */
		const iconCPIS = el('img', { width: 20, height: 20, src:  "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAIAAADZrBkAAAAAA3NCSVQICAjb4U/gAAAACXBIWXMAAAsSAAALEgHS3X78AAAAHnRFWHRTb2Z0d2FyZQBBZG9iZSBGaXJld29ya3MgQ1M1LjGrH0jrAAAAFnRFWHRDcmVhdGlvbiBUaW1lADEwLzA2LzEzdw7Y2QAAADZJREFUKJFjZGi8zkA6YCJDz0Bp+1+nASeJAYzUCRJka3GRw9M2OKCxbWiAqrZhsmljG221AQDGTzKjgIanTQAAAABJRU5ErkJggg==" } );

		const iconCPISP = el('img', { width: 20, height: 20, src:  "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAIAAADZrBkAAAAAA3NCSVQICAjb4U/gAAAACXBIWXMAAAsSAAALEgHS3X78AAAAHnRFWHRTb2Z0d2FyZQBBZG9iZSBGaXJld29ya3MgQ1M1LjGrH0jrAAAAFnRFWHRDcmVhdGlvbiBUaW1lADEwLzA2LzEzdw7Y2QAAADhJREFUKJFjZGi8zkA6YCJDDw20/a/TgJOYgJFqfkO2B5edQ9U2TDsxATVsw+UrTJtHbcNvG/EAALOaPDPFXhQNAAAAAElFTkSuQmCC" } );

		/* Images Store Shortcode */
		blocks.registerBlockType( 'cpis/images-store', {
			title: 'Images Store',
			icon: iconCPIS,
			category: 'cpis',
			supports: {
				customClassName	: false,
				className		: false,
				html 			: false
			},
			attributes: {
				shortcode : {
					type 		: 'string',
					selector	: 'input',
					source 		: 'attribute',
					attribute	: 'value',
					default		: '[codepeople-image-store]'
				}
			},

			edit: function( props ) {
				return [
						el(
							'input',
							{
								key  : 'cpis_store',
								type : 'text',
								style:{width:'100%'},
								value: props.attributes.shortcode,
								onChange: function(evt){ props.setAttributes({shortcode: evt.target.value}); evt.preventDefault();}
							}
						),
						el(
							'div', {className: 'cpis-iframe-container', key: 'cpis_iframe_container'},
							el('div', {className: 'cpis-iframe-overlay', key: 'cpis_iframe_overlay'}),
							el('iframe',
								{
									key: 'cpis_store_iframe',
									src: cpis_settings.url+encodeURIComponent(props.attributes.shortcode),
									height: 0,
									width: 500,
									scrolling: 'no'
								}
							)
						),
						el(
							InspectorControls,
							{
								key: 'cpis_store_inspector'
							},
                            el(
                                'div',
                                {
                                    key: 'cp_inspector_container',
                                    style:{paddingLeft:'20px',paddingRight:'20px'}
                                },
                                [el('h2',{key: 'cpis_store_help_title'}, cpis_settings['store-description']['title'])].concat(
                                    Object.keys(cpis_settings['store-description']['attrs']).map(function(v, i) {
                                        return el(
                                            'p',
                                            {
                                                key: 'cpis_store_help_'+i
                                            },
                                            [
                                                el('b', {key: 'cpis_store_help_'+i+'_attr'}, v+': '),
                                                el('span', {key: 'cpis_store_help_'+i+'_desc'}, cpis_settings['store-description']['attrs'][v]),
                                            ]
                                        );
                                    })
                                )
                            )
						)
					];
			},

			save: function( props ) {
				return props.attributes.shortcode;
			}
		});

		/* Image Store Product Shortcode */
		blocks.registerBlockType( 'cpis/image-store-product', {
			title: 'Product',
			icon: iconCPISP,
			category: 'cpis',
			supports: {
				customClassName	: false,
				className		: false,
				html 			: false
			},
			attributes: {
				id: {
					type	: 'int'
				},
				layout: {
					type	: 'string',
					default	: 'single'
				}
			},

			edit: function( props ) {
				var focus = props.isSelected;

				return [
					!!focus &&
					el(
						InspectorControls,
						{key : 'cpisp-blocks-properties'},
                        el(
                            'div',
                            {
                                key: 'cp_inspector_container',
                                style:{paddingLeft:'20px',paddingRight:'20px'}
                            },
                            [
                                el(
                                    'p',
                                    {key : 'cpisp-id-label'},
                                    'Enter the Image ID'
                                ),
                                el(
                                    'input',
                                    {
                                        key  	: 'cpisp-id',
                                        type 	: 'text',
                                        value 	: props.attributes.id || '',
                                        style 	: {width : '100%'},
                                        onChange : function(evt){
                                            props.setAttributes({id : evt.target.value});
                                            evt.preventDefault();
                                        }
                                    }
                                ),
                                el(
                                    'p',
                                    {key : 'cpisp-layout-label'},
                                    'Select the Layout'
                                ),
                                el(
                                    'select',
                                    {
                                        key : 'cpisp-layout',
                                        onChange : function(evt){
                                            var layout = evt.target.querySelector('option:checked').value;
                                            props.setAttributes({layout : layout});
                                            evt.preventDefault();
                                        },
                                        value : props.attributes.layout
                                    },
                                    [
                                        el( 'option', { key : 'cpisp-layout-single', value : 'single'}, 'Single' ),
                                        el( 'option', { key : 'cpisp-layout-multiple', value : 'multiple'}, 'Multiple' )
                                    ]
                                )
                            ]
                        )
					),
					el(
						'input',
						{
							key 	: 'cpisp-shortcode-input',
							type 	: 'text',
							style	: {width: '100%'},
							value 	: (new wp.shortcode(
										{
											tag : 'codepeople-image-store-product',
											attrs : {id : props.attributes.id || '', layout : props.attributes.layout || 'single'},
											type  : 'single'
										}
									)).string(),
							onChange: function(evt){
									var sc = wp.shortcode.next('codepeople-image-store-product', evt.target.value);
									if(sc)
									{
										var id = sc.shortcode.attrs.named[ 'id' ] || '',
											layout = sc.shortcode.attrs.named[ 'layout' ] || 'single';
										props.setAttributes({
											id : id,
											layout : layout
										});
									}
								}
						}
					),
					el(
						'div', {className: 'cpis-iframe-container', key: 'cpis_iframe_container'},
						el('div', {className: 'cpis-iframe-overlay', key: 'cpis_iframe_overlay'}),
						el('iframe',
							{
								key: 'cpis_store_iframe',
								src: cpis_settings.url+encodeURIComponent(
									(new wp.shortcode(
										{
											tag : 'codepeople-image-store-product',
											attrs : {id : props.attributes.id || '', layout : props.attributes.layout || 'single'},
											type  : 'single'
										}
									)).string()
								),
								height: 0,
								width: 500,
								scrolling: 'no'
							}
						)
					)
				];
			},

			save: function( props ) {
				return (new wp.shortcode(
							{
								tag : 'codepeople-image-store-product',
								attrs : {id : props.attributes.id || '', layout : props.attributes.layout || 'single'},
								type  : 'single'
							}
						)).string()
			}
		});
	} )(
		window.wp.blocks,
		window.wp.element
	);
});