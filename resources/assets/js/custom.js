/*

	Template Name: Eventor - Conference & Event HTML Template
	Author: Themewinter
	Author URI: https://themeforest.net/user/themewinter
	Description: Eventor - Conference & Event HTML Template
	Version: 1.0

	1. Mobile Menu
	2. Main Slideshow
	3. Gallery popup
	4. Counter
	5. Contact form
	6. Back to top
  
*/
var NEXT_EVENT_DATE;

jQuery(function($) {
  "use strict";


	/* ----------------------------------------------------------- */
	/*  Mobile Menu
	/* ----------------------------------------------------------- */

	jQuery(".nav.navbar-nav li a").on("click", function() { 
		jQuery(this).parent("li").find(".dropdown-menu").slideToggle();
		jQuery(this).find("i").toggleClass("fa-angle-down fa-angle-up");
	});


	/* ----------------------------------------------------------- */
	/*  Event counter 
	/* -----------------------------------------------------------*/

	if (NEXT_EVENT_DATE) {
        if ($('.countdown').length > 0) {
            $(".countdown").jCounter({
                date: NEXT_EVENT_DATE,
                serverDateSource: '/dateandtime.php',
				timezone: 'Europe/Brussels',
                fallback: function () {
                    console.log("count finished!")
                }
            });
        }
    }

	/* ----------------------------------------------------------- */
	/*  Event Map 
	/* -----------------------------------------------------------*/

	if ( $( '#map' ).length > 0 ) {

	    var eventmap = $.parseJSON($('#map').attr('data-coordinates'));

      $('#map')
      .gmap3({
         zoom: 16,
         center: eventmap,
         mapTypeId : google.maps.MapTypeId.ROADMAP,
         scrollwheel: false
      })

      .marker({
        position: eventmap
      })

      .infowindow({
         position: eventmap,
         content: $('#map').attr('data-address')
      })

      .then(function (infowindow) {
         var map = this.get(0);
         var marker = this.get(1);
         marker.addListener('click', function() {
            infowindow.open(map, marker);
         });
      });
	}

 


	/* ----------------------------------------------------------- */
	/*  Main slideshow
	/* ----------------------------------------------------------- */

		$('#main-slide').carousel({
			pause: true,
			interval: 100000,
		});


	/* ----------------------------------------------------------- */
	/*  Gallery popup
	/* ----------------------------------------------------------- */

	  $(document).ready(function(){

			$(".gallery-popup").colorbox({photo: true, rel:'gallery-popup', transition:"fade", innerHeight:"700"});
          	$(".image-popup").colorbox({ photo: true, innerHeight: 700});
			$(".popup").colorbox({iframe:true, innerWidth:650, innerHeight:450});

	  });



	/* ----------------------------------------------------------- */
	/*  Counter
	/* ----------------------------------------------------------- */

		$('.counterUp').counterUp({
		 delay: 10,
		 time: 1000
		});


	
	/* ----------------------------------------------------------- */
	/*  Contact form
	/* ----------------------------------------------------------- */

	$('#contact-form').submit(function(){

		var $form = $(this),
			$error = $form.find('.error-container'),
			action  = $form.attr('action');

		$error.slideUp(750, function() {
			$error.hide();

			var $name = $form.find('.form-control-name'),
				$email = $form.find('.form-control-email'),
				$subject = $form.find('.form-control-subject'),
				$message = $form.find('.form-control-message');

			$.post(action, {
					name: $name.val(),
					email: $email.val(),
					subject: $subject.val(),
					message: $message.val()
				},
				function(data){
					$error.html(data);
					$error.slideDown('slow');

					if (data.match('success') != null) {
						$name.val('');
						$email.val('');
						$subject.val('');
						$message.val('');
					}
				}
			);

		});

		return false;

	});


	/* ----------------------------------------------------------- */
	/*  Back to top
	/* ----------------------------------------------------------- */

		$(window).scroll(function () {
			if ($(this).scrollTop() > 50) {
				 $('#back-to-top').fadeIn();
			} else {
				 $('#back-to-top').fadeOut();
			}
		});

		// scroll body to 0px on click
		$('#back-to-top').on('click', function () {
			 $('#back-to-top').tooltip('hide');
			 $('body,html').animate({
				  scrollTop: 0
			 }, 800);
			 return false;
		});
		
		$('#back-to-top').tooltip('hide');


		// Find faq containers
		$('.faq-container').each(function() {

			// Find faq items
			var faqItems = $(this).find('li');

			// Loop through each faq item
			faqItems.each(function() {

				// Find faq item header
				var faqItemHeader = $(this).find('h5');
				faqItemHeader.css('cursor', 'pointer');

				// Find faq item body
				var faqItemBody = $(this).find('p');
				faqItemBody.hide();
			

				// Add click event to faq item header
				faqItemHeader.on('click', function() {

					// Toggle faq item body
					faqItemBody.slideToggle();

				});

			});

		});

});