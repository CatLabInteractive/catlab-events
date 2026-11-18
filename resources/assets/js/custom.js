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

	// The header menu is handled in layouts/blocks/header.blade.php.


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

	// Venue map. The Google Maps API is only loaded on pages that show a map,
	// asynchronously, and the map is drawn from its callback.
	var mapElement = document.getElementById('map');
	if (mapElement && mapElement.getAttribute('data-maps-key')) {
		window.kvInitMap = function () {
			var eventPosition = JSON.parse(mapElement.getAttribute('data-coordinates'));
			var eventMap = new google.maps.Map(mapElement, {
				zoom: 16,
				center: eventPosition,
				scrollwheel: false
			});
			var eventMarker = new google.maps.Marker({ position: eventPosition, map: eventMap });
			var eventInfo = new google.maps.InfoWindow({ content: mapElement.getAttribute('data-address') });
			eventMarker.addListener('click', function () {
				eventInfo.open(eventMap, eventMarker);
			});
		};
		var mapsScript = document.createElement('script');
		mapsScript.async = true;
		mapsScript.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(mapElement.getAttribute('data-maps-key')) + '&callback=kvInitMap&loading=async';
		document.head.appendChild(mapsScript);
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
          	// Posters come in any aspect ratio: let colorbox size the photo to fit the screen.
          	$(".image-popup").colorbox({ photo: true, maxWidth: "95%", maxHeight: "95%" });
			$(".popup").colorbox({iframe:true, innerWidth:650, innerHeight:450});

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