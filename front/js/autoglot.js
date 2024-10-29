jQuery(document).ready(function($) {	

	$('a[name=ag_modal]').click(function(e) {
		//Cancel the link behavior
		e.preventDefault();
		
		//Get the A tag
		var id = "#" + $(this).attr('box');
	
		//Get the screen height and width
		var maskHeight = $(document).height();
		var maskWidth = $(document).width();
	
		//Set heigth and width to mask to fill up the whole screen
		$('#ag_mask').css({'width':maskWidth,'height':maskHeight});
		
		//transition effect		
		$('#ag_mask').fadeIn(1000);	
		$('#ag_mask').fadeTo("slow",0.7);	
	
		//Get the window height and width
		var winH = $(window).height();
		var winW = $(window).width();
              
		//Set the popup window to center
		var wintop = winH/2-$(id).height()/2 + $(document).scrollTop();
		$(id).css('top',  wintop<$(document).scrollTop()?$(document).scrollTop():wintop);
		$(id).css('left', winW/2-$(id).width()/2);
	
		//transition effect
		$(id).fadeIn(2000); 
	
	});
	
	//if close button is clicked
	$('.ag_window .closebox').click(function (e) {
		//Cancel the link behavior
		e.preventDefault();
		
		$('#ag_mask').hide();
		$('.ag_window').hide();
	});	
    
	//if ESC button pressed
    $(document).on('keydown', function(event) {
        if ( $("#ag_mask").css('display') != 'none' && $("#ag_mask").css("visibility") != "hidden" && event.key == "Escape") {
//            e.preventDefault();
            
            $('#ag_mask').hide();
            $('.ag_window').hide();
        }
    });	
	
	//if mask is clicked
	$('#ag_mask').click(function () {
		$(this).hide();
		$('.ag_window').hide();
	});			

	$(window).resize(function () {
	 
 		var box = $('#boxes .ag_window');
 
        //Get the screen height and width
        var maskHeight = $(document).height();
        var maskWidth = $(document).width();
      
        //Set height and width to mask to fill up the whole screen
        $('#ag_mask').css({'width':maskWidth,'height':maskHeight});
               
        //Get the window height and width
        var winH = $(window).height();
        var winW = $(window).width();

        //Set the popup window to center
		var wintop = winH/2-box.height()/2 + $(document).scrollTop();
		box.css('top',  wintop<$(document).scrollTop()?$(document).scrollTop():wintop);
		box.css('left', winW/2-box.width()/2);
	 
	});
    
});
