(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	
	 // On Page Load
	$(function() {
		// The "Upload" button
		$('.upload_image_button').click(function() {
			var send_attachment_bkp = wp.media.editor.send.attachment;
			var button = $(this);
			wp.media.editor.send.attachment = function(props, attachment) {
				$(button).parent().prev().attr('src', attachment.url);
				$(button).prev().val(attachment.id);
				wp.media.editor.send.attachment = send_attachment_bkp;
			}
			wp.media.editor.open(button);
			return false;
		});

		// The "Remove" button (remove the value from input type='hidden')
		$('.remove_image_button').click(function() {
			var answer = confirm('Are you sure?');
			if (answer == true) {
				var src = $(this).parent().prev().attr('data-src');
				$(this).parent().prev().attr('src', src);
				$(this).prev().prev().val('');
				//$(this).closest('form').submit();
			}
			//return false;
		});

        $('#autoglot_checkon').click(function (event) {
            event.preventDefault();
            $("#autoglot_tgs :checkbox").each(function(){
                this.checked = true;
            });
            return false;
        });
        $('#autoglot_checkoff').click(function (event) {
            event.preventDefault();
            $("#autoglot_tgs :checkbox").each(function(){
                this.checked = false;
            });
            return false;
        });
        $('.autoglot_txt2').scroll(function(){
    		var sct = $(this).scrollTop();
            $('.autoglot_txt2').not(this).scrollTop(sct);    
        });

        $('.autoglot_txt2').change(function(){
            var lines = $(this).val().trim().split(/\r|\r\n|\n/).length;
    		$("#" + $(this).attr('id') + "_lines").text(lines); 
        });

        $('.autoglot_changeflag_select').change(function(){
            var id = "#flag_" + $(this).attr('id');
            $(id).removeClass();
            $(id).addClass("cssflag cssflag-" + $(this).val());
        });

        $('.autoglot_changeflag_select').each(function(){
            var id = "#flag_" + $(this).attr('id');
            $(id).removeClass();
            $(id).addClass("cssflag cssflag-" + $(this).val());
        });


    });
})( jQuery );


