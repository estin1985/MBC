var mbc;


(function($) {
	mbc = {
		object_id 	: 0,
		object_type : '',
		edit_row 	: '',
		column_row 	: '',


		init : function() {

			switch(pagenow) {

				case 'edit-post-tag' :
				case 'edit-category' :

					mbc.taxonomy_Type();
					break;

				case 'edit-post' :
				case 'edit-'+typenow :

					mbc.post_Type();
					break;

				case 'edit-comments' :

					mbc.comment_Type();
					break;
			}
		},

		comment_Type : function() {
			$inline_edit = commentReply.open;

			if($inline_edit) {
				commentReply.open = function( id ) {

					// Call Original WP Inline Edit
					$inline_edit.apply( this, arguments );

					// Set ID if Available
					mbc.parse_id( this, id );

					if( mbc.object_id > 0 ) {

						mbc.edit_row   = $( '#replyrow' );
						mbc.column_row = $( '#comment-' + mbc.object_id );

						mbc.populate_inputs();

					}
				}
			}
		},

		taxonomy_Type : function() {
			$inline_edit = inlineEditTax.edit;

			if($inline_edit) {
				inlineEditTax.edit = function( id ) {

					// Call Original WP Inline Edit
					$inline_edit.apply( this, arguments );

					// Set ID if Available
					mbc.parse_id( this, id );

					if( mbc.object_id > 0 ) {

						mbc.edit_row   = $( '#edit-'+ mbc.object_id);
						mbc.column_row = $( '#tag-' + mbc.object_id );

						mbc.populate_inputs();

					}
				}
			}
		},

		post_Type : function() {
			$inline_edit = inlineEditPost.edit;

			if($inline_edit) {
				inlineEditPost.edit = function( id ) {

					// Call Original WP Inline Edit
					$inline_edit.apply( this, arguments );

					// Set ID if Available
					mbc.parse_id( this, id );

					if( mbc.object_id > 0 ) {

						mbc.edit_row   = $( '#edit-'+ mbc.object_id);
						mbc.column_row = $( '#post-' + mbc.object_id );

						mbc.populate_inputs();

					}
				}
			}
		},

		parse_id : function(object, id) {
			object_id = 0;

			// For Taxonomy and Post Types
			if ( typeof( id ) == 'object' ) {
			    object_id = parseInt( object.getId( id ) );
			}

			// For Comment Type
			if ( !isNaN( id ) ) {
				object_id = parseInt( id );
			}

			mbc.object_id = object_id;
		},

		populate_inputs : function() {
			column_data = mbc.column_row.find( $("div[class^='MBC_']") );

			input_fields = column_data.map(function() {
				return $(this).attr('class').substr(4).split(' ')[0];
			});

			$.each( input_fields, function(key, value) {
				$( 'input[name="' + value + '"]', mbc.edit_row ).val( $('.MBC_' + value, mbc.column_row ).html() );
			});

		}
	};

	jQuery(document).ready(function($) {

		mbc.init();

	});

})(jQuery);