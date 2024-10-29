/*! Automatically Hierarchic Categories in Menu v2.0.7 | (c) Atakan Au */
jQuery(document).ready(function($){
	$( '#submit-aau-ahcm' ).on( 'click', function ( e ) {
		// call registerChange like any add
		wpNavMenu.registerChange();

		// call our custom function
		aauAhcmAddWidgettoMenu();
	} );

	/**
	 * Add our custom Shortcode object to Menu
	 * 
	 * @returns {Boolean}
	 */
	function aauAhcmAddWidgettoMenu( ) {

		// get the description
		description = $( '#aau-ahcm-shortcode' ).val();

		// initialise object
		menuItems = { };

		// the usual method for ading menu Item
		processMethod = wpNavMenu.addMenuItemToBottom;

		var t = $( '.aau-ahcm-div' );

		// Show the ajax spinner
		t.find( '.spinner' ).show();

		// regex to get the index
		re = /menu-item\[([^\]]*)/;

		m = t.find( '.menu-item-db-id' );
		// match and get the index
		listItemDBIDMatch = re.exec( m.attr( 'name' ) ),
			listItemDBID = 'undefined' == typeof listItemDBIDMatch[1] ? 0 : parseInt( listItemDBIDMatch[1], 10 );

		// assign data
		menuItems[listItemDBID] = t.getItemData( 'add-menu-item', listItemDBID );
		menuItems[listItemDBID]['menu-item-description'] = description;

		if ( menuItems[listItemDBID]['menu-item-title'] === '' ) {
			menuItems[listItemDBID]['menu-item-title'] = '(Untitled)';
		}

		// get our custom nonce
		nonce = $( '#aau-ahcm-description-nonce' ).val();

		// set up params for our ajax hack
		params = {
			'action': 'aau_ahcm_description_hack',
			'description-nonce': nonce,
			'menu-item': menuItems[listItemDBID]
		};

		// call it
		$.post( ajaxurl, params, function ( objectId ) {

			// returns the incremented object id, add to ui
			$( '#aau-ahcm-div .menu-item-object-id' ).val( objectId );

			// now call the ususl addItemToMenu
			wpNavMenu.addItemToMenu( menuItems, processMethod, function () {
				// Deselect the items and hide the ajax spinner
				t.find( '.spinner' ).hide();
				// Set form back to defaults
				$( '#aau-ahcm-title' ).val( '' ).blur();
				$( '#aau-ahcm-shortcode' ).val( '' );

			} );
		} );
	}
} );
