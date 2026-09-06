( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.domReady ) {
		return;
	}

	var select = wp.data.select;
	var subscribe = wp.data.subscribe;
	var wasSaving = false;

	function isProposal() {
		var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		// Fallback: post title/status alone isn't enough; PHP localizes a flag.
		return !!( window.dcpEditor && window.dcpEditor.isProposal );
	}

	function relabelPublish() {
		if ( ! isProposal() ) {
			return;
		}
		var nodes = document.querySelectorAll(
			'.editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-publish-button__button'
		);
		nodes.forEach( function ( el ) {
			if ( el && el.textContent && el.textContent.indexOf( 'Publish live' ) === -1 ) {
				if ( /Publish|Update|Submit/.test( el.textContent ) ) {
					el.textContent = el.textContent.replace( /Publish(…|\.\.\.)?|Update|Submit for review/i, 'Publish live' );
				}
			}
		} );
	}

	wp.domReady( function () {
		if ( ! isProposal() ) {
			return;
		}
		relabelPublish();
		setInterval( relabelPublish, 1000 );

		subscribe( function () {
			var saving = select( 'core/editor' ).isSavingPost();
			var autosaving = select( 'core/editor' ).isAutosavingPost();
			if ( wasSaving && ! saving && ! autosaving ) {
				var post = select( 'core/editor' ).getCurrentPost();
				if ( post && post.dcp_redirect ) {
					window.location.href = post.dcp_redirect;
					return;
				}
				// Header from our REST filter isn't on the post object; poll redirect option via localized URL after publish attempt.
				if ( window.dcpEditor && window.dcpEditor.sourceEditUrl && post && post.status === 'trash' ) {
					window.location.href = window.dcpEditor.sourceEditUrl;
				}
			}
			wasSaving = saving;
		} );
	} );
} )( window.wp );
