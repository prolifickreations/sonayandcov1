jQuery(window).on('load', function () {

	var media = window.wp.media,
		Attachment = media.model.Attachment,
		Attachments = media.model.Attachments,
		Query = media.model.Query,
		l10n = media.view.l10n = typeof _wpMediaViewsL10n === 'undefined' ? {} : _wpMediaViewsL10n,
		NewMenuItem;

	jQuery(document).on('click', '.insert-media', addMediaMenuButton);

	/**
	 * This function adds the 'design in canva'
	 * button to the media libraruy window
	 *
	 * @param event
	 */
	function addMediaMenuButton(event) {

		var workflow = wp.media.editor.get();
		var options = workflow.options;
		if (undefined == NewMenuItem) {
			NewMenuItem = new wp.media.view.RouterItem(
				_.extend(options, {text: 'Design with Canva 222', className: 'media-menu-item canva-design-button', data: '{data-type:blogGraphic}'})
			);
			workflow.menu.view.views.set('.media-router', NewMenuItem, _.extend(options, {add: true}));
		}
		// replace any button with a better button not just a link.
		// find the button we have just make and make it better.
		$button = jQuery('.media-router').find('.canva-design-button');
		if ($button != []) {
			jQuery($button[0]).replaceWith(window.canva_ajax.canvadesignmediabutton);
		}
		else {
			$button = jQuery('.media-router').append(window.canva_ajax.canvadesignmediabutton);
		}

		// make sure the canva API is loaded
		load_canva_api_and_referesh_buttons();

	}

	jQuery("body").on("click", ".media-menu-item .canva-design-button", function () {
		canva.api.load({key: canva_ajax.canvaapikey}, onApiLoaded);
		function onApiLoaded() {
			canva.api.designer.create({type: canva_ajax.canvadesigntype}, onCreateCallExport);
		}

		function onCreateCallExport(exportUrl, designId) {
			jQuery.post(canva_ajax.url, {
				action: "canva_uploader_action",
				ajaxnonce: canva_ajax.ajaxnonce,
				canvaimageurl: exportUrl,
				canvadesignid: designId
			}, function (result) {
				jQuery("#canvamask").fadeOut(500, function () {
					jQuery(this).remove();
				});
				window.send_to_editor(result);
				media.view.Modal.close();
			});
		}
	});


	// this script will alter the edit image
	// button in the script to become the 'edit in canva button'
	jQuery(document).ready(function () {
		var $button = jQuery('.canva-design-button'),
			$postID = jQuery('#post_ID').val(),
			$mediaEditButton,
			$canvaEditButton = $button[0],
			$designId;

		if (typeof $canvaEditButton === "undefined") {
			return;
		}

		$designId = $canvaEditButton.getAttribute("data-design-id");

		if ($designId != '') {
			// try to get the edit button
			$mediaEditButton = jQuery('#imgedit-open-btn-' + $postID).parent(); // <p> tags that contain the button
			if (typeof $mediaEditButton !== "undefined") {
				$mediaEditButton.html($button);
				jQuery('.compat-field-canva_designId').parent().parent().addClass('canva_hide');
			}
		}

	});


	/**
	 *
	 * this just loads the canva API and tries to initialise all buttons on page
	 *
	 */
	function load_canva_api_and_referesh_buttons() {

		jQuery.getScript("https://sdk.canva.com/v1/api.js");
		canva.api.button.init(document.querySelector(".canva-design-button"));
	}

	/**
	 *
	 * Check for canva buttons on page and if there are
	 * load API and init buttons
	 *
	 */
	function auto_check_for_buttons(){
		var $data =  jQuery("[class*='canva-design-']:not([data-exertive])");

		if($data.length > 0)
		{
			console.log("**** auto_check_for_buttons refereshing");
			load_canva_api_and_referesh_buttons();
		}
	}

	/**
	 * OK auto check very often...
	 *
	 * could not think of a better way of doing this.
	 *
	 */
	jQuery(document).ready(function () {
		var refreshId = setInterval(function () {
			auto_check_for_buttons();
		}, 1000);

	});
});

