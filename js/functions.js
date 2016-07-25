/* global screenReaderText */
/**
 * Theme functions file.
 *
 * Contains handlers for navigation and widget area.
 */

( function( $ ) {
	var $body, $window, $sidebar, adminbarOffset, top = false,
	    bottom = false, windowWidth, windowHeight, lastWindowPos = 0,
	    topOffset = 0, bodyHeight, sidebarHeight, resizeTimer,
	    secondary, button;

	function initMainNavigation( container ) {
		// Add dropdown toggle that display child menu items.
		container.find( '.menu-item-has-children > a' ).after( '<button class="dropdown-toggle" aria-expanded="false">' + screenReaderText.expand + '</button>' );

		// Toggle buttons and submenu items with active children menu items.
		container.find( '.current-menu-ancestor > button' ).addClass( 'toggle-on' );
		container.find( '.current-menu-ancestor > .sub-menu' ).addClass( 'toggled-on' );

		container.find( '.dropdown-toggle' ).click( function( e ) {
			var _this = $( this );
			e.preventDefault();
			_this.toggleClass( 'toggle-on' );
			_this.next( '.children, .sub-menu' ).toggleClass( 'toggled-on' );
			_this.attr( 'aria-expanded', _this.attr( 'aria-expanded' ) === 'false' ? 'true' : 'false' );
			_this.html( _this.html() === screenReaderText.expand ? screenReaderText.collapse : screenReaderText.expand );
		} );
	}
	initMainNavigation( $( '.main-navigation' ) );

	// Re-initialize the main navigation when it is updated, persisting any existing submenu expanded states.
	$( document ).on( 'customize-preview-menu-refreshed', function( e, params ) {
		if ( 'primary' === params.wpNavMenuArgs.theme_location ) {
			initMainNavigation( params.newContainer );

			// Re-sync expanded states from oldContainer.
			params.oldContainer.find( '.dropdown-toggle.toggle-on' ).each(function() {
				var containerId = $( this ).parent().prop( 'id' );
				$( params.newContainer ).find( '#' + containerId + ' > .dropdown-toggle' ).triggerHandler( 'click' );
			});
		}
	});

	secondary = $( '#secondary' );
	button = $( '.site-branding' ).find( '.secondary-toggle' );

	// Enable menu toggle for small screens.
	( function() {
		var menu, widgets, social;
		if ( ! secondary || ! button ) {
			return;
		}

		// Hide button if there are no widgets and the menus are missing or empty.
		menu    = secondary.find( '.nav-menu' );
		widgets = secondary.find( '#widget-area' );
		social  = secondary.find( '#social-navigation' );
		if ( ! widgets.length && ! social.length && ( ! menu || ! menu.children().length ) ) {
			button.hide();
			return;
		}

		button.on( 'click.twentyfifteen', function() {
			secondary.toggleClass( 'toggled-on' );
			secondary.trigger( 'resize' );
			$( this ).toggleClass( 'toggled-on' );
			if ( $( this, secondary ).hasClass( 'toggled-on' ) ) {
				$( this ).attr( 'aria-expanded', 'true' );
				secondary.attr( 'aria-expanded', 'true' );
			} else {
				$( this ).attr( 'aria-expanded', 'false' );
				secondary.attr( 'aria-expanded', 'false' );
			}
		} );
	} )();

	/**
	 * @summary Add or remove ARIA attributes.
	 * Uses jQuery's width() function to determine the size of the window and add
	 * the default ARIA attributes for the menu toggle if it's visible.
	 * @since Twenty Fifteen 1.1
	 */
	function onResizeARIA() {
		if ( 955 > $window.width() ) {
			button.attr( 'aria-expanded', 'false' );
			secondary.attr( 'aria-expanded', 'false' );
			button.attr( 'aria-controls', 'secondary' );
		} else {
			button.removeAttr( 'aria-expanded' );
			secondary.removeAttr( 'aria-expanded' );
			button.removeAttr( 'aria-controls' );
		}
	}

	// Sidebar scrolling.
	function resize() {
		windowWidth = $window.width();

		if ( 955 > windowWidth ) {
			top = bottom = false;
			$sidebar.removeAttr( 'style' );
		}
	}

	function scroll() {
		var windowPos = $window.scrollTop();

		if ( 955 > windowWidth ) {
			return;
		}

		sidebarHeight = $sidebar.height();
		windowHeight  = $window.height();
		bodyHeight    = $body.height();

		if ( sidebarHeight + adminbarOffset > windowHeight ) {
			if ( windowPos > lastWindowPos ) {
				if ( top ) {
					top = false;
					topOffset = ( $sidebar.offset().top > 0 ) ? $sidebar.offset().top - adminbarOffset : 0;
					$sidebar.attr( 'style', 'top: ' + topOffset + 'px;' );
				} else if ( ! bottom && windowPos + windowHeight > sidebarHeight + $sidebar.offset().top && sidebarHeight + adminbarOffset < bodyHeight ) {
					bottom = true;
					$sidebar.attr( 'style', 'position: fixed; bottom: 0;' );
				}
			} else if ( windowPos < lastWindowPos ) {
				if ( bottom ) {
					bottom = false;
					topOffset = ( $sidebar.offset().top > 0 ) ? $sidebar.offset().top - adminbarOffset : 0;
					$sidebar.attr( 'style', 'top: ' + topOffset + 'px;' );
				} else if ( ! top && windowPos + adminbarOffset < $sidebar.offset().top ) {
					top = true;
					$sidebar.attr( 'style', 'position: fixed;' );
				}
			} else {
				top = bottom = false;
				topOffset = ( $sidebar.offset().top > 0 ) ? $sidebar.offset().top - adminbarOffset : 0;
				$sidebar.attr( 'style', 'top: ' + topOffset + 'px;' );
			}
		} else if ( ! top ) {
			top = true;
			$sidebar.attr( 'style', 'position: fixed;' );
		}

		lastWindowPos = windowPos;
	}

	function resizeAndScroll() {
		resize();
		scroll();

	}
  function floatImages(){
		$("article img").each(function() {
		var imgWidth = $(this).width();
	  var imgHeight = $(this).height();
		var position = $(this).position();
		var positionTop = (position.top + imgHeight - 26);
    if (imgWidth < 400 && imgWidth > 30){
				$(this).parent().css("float", "right");
				$(this).parent().css("margin-left", "20px");
				
    }
		
	});
  }
	
	function captionImages(){
		$("article img").each(function() {
				var imageCaption = $(this).attr("title");
				if (imageCaption !== "" && typeof(imageCaption)!=='undefined') {
						var imgWidth = $(this).width();
						var imgHeight = $(this).height();
						var position = $(this).position();
						var positionTop = (position.top + imgHeight - 26);
						$("<div class='img-caption'><em>" + imageCaption +
								"</em></div>").css({
								"width": imgWidth + "px",
								"font-size": "80%"
						}).insertAfter(this);
        }
		});
	}

	$( document ).ready( function() {
		$body          = $( document.body );
		$window        = $( window );
		$sidebar       = $( '#sidebar' ).first();
		adminbarOffset = $body.is( '.admin-bar' ) ? $( '#wpadminbar' ).height() : 0;

		$window
			.on( 'scroll.twentyfifteen', scroll )
			.on( 'load.twentyfifteen', onResizeARIA )
			.on( 'resize.twentyfifteen', function() {
				clearTimeout( resizeTimer );
				resizeTimer = setTimeout( resizeAndScroll, 500 );
				onResizeARIA();
			} );
		$sidebar.on( 'click.twentyfifteen keydown.twentyfifteen', 'button', resizeAndScroll );

		resizeAndScroll();

		for ( var i = 1; i < 6; i++ ) {
			setTimeout( resizeAndScroll, 100 * i );
		}

		$('#container').imagesLoaded().done( function( instance ) {
		  console.log('DONE  - all images have been successfully loaded');
		  	setTimeout( floatImages, 100 );
		  	setTimeout( floatImages, 500 );
		  	setTimeout( captionImages, 1000 );
		});

	} );

} )( jQuery );


/*\
|*|
|*|  :: cookies.js ::
|*|
|*|  A complete cookies reader/writer framework with full unicode support.
|*|
|*|  Revision #1 - September 4, 2014
|*|
|*|  https://developer.mozilla.org/en-US/docs/Web/API/document.cookie
|*|  https://developer.mozilla.org/User:fusionchess
|*|
|*|  This framework is released under the GNU Public License, version 3 or later.
|*|  http://www.gnu.org/licenses/gpl-3.0-standalone.html
|*|
|*|  Syntaxes:
|*|
|*|  * docCookies.setItem(name, value[, end[, path[, domain[, secure]]]])
|*|  * docCookies.getItem(name)
|*|  * docCookies.removeItem(name[, path[, domain]])
|*|  * docCookies.hasItem(name)
|*|  * docCookies.keys()
|*|
\*/

var docCookies = {
  getItem: function (sKey) {
    if (!sKey) { return null; }
    return decodeURIComponent(document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*" + encodeURIComponent(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1")) || null;
  },
  setItem: function (sKey, sValue, vEnd, sPath, sDomain, bSecure) {
    if (!sKey || /^(?:expires|max\-age|path|domain|secure)$/i.test(sKey)) { return false; }
    var sExpires = "";
    if (vEnd) {
      switch (vEnd.constructor) {
        case Number:
          sExpires = vEnd === Infinity ? "; expires=Fri, 31 Dec 9999 23:59:59 GMT" : "; max-age=" + vEnd;
          break;
        case String:
          sExpires = "; expires=" + vEnd;
          break;
        case Date:
          sExpires = "; expires=" + vEnd.toUTCString();
          break;
      }
    }
    document.cookie = encodeURIComponent(sKey) + "=" + encodeURIComponent(sValue) + sExpires + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "") + (bSecure ? "; secure" : "");
    return true;
  },
  removeItem: function (sKey, sPath, sDomain) {
    if (!this.hasItem(sKey)) { return false; }
    document.cookie = encodeURIComponent(sKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT" + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "");
    return true;
  },
  hasItem: function (sKey) {
    if (!sKey) { return false; }
    return (new RegExp("(?:^|;\\s*)" + encodeURIComponent(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=")).test(document.cookie);
  },
  keys: function () {
    var aKeys = document.cookie.replace(/((?:^|\s*;)[^\=]+)(?=;|$)|^\s*|\s*(?:\=[^;]*)?(?:\1|$)/g, "").split(/\s*(?:\=[^;]*)?;\s*/);
    for (var nLen = aKeys.length, nIdx = 0; nIdx < nLen; nIdx++) { aKeys[nIdx] = decodeURIComponent(aKeys[nIdx]); }
    return aKeys;
  }
};


function openEditBox(boxId, bottom){
	console.log("#synopsis" + boxId);
	$("#synopsis" + boxId).hide() 
	$("#entryedit" + boxId).show(); 
	$("#formcontent" + boxId).focus(); 
	
	
	if (bottom) {
		currContent = $("#formcontent" + boxId).val();
		 $("#formcontent" + boxId).val(currContent.replace(/^\s+|\s+$/g, ""));
		  $("#formcontent" + boxId).val( $("#formcontent" + boxId).val() + "\n\n");
	//	var len = $("#formcontent" + boxId).val().length * 2;
  //  $("#formcontent" + boxId).setSelection(len, len);
    $("#formcontent" + boxId).animate({ scrollTop: 10000 }, 800);
		//$("#formcontent" + boxId).scrollTop = 999999 // $("#formcontent" + boxId).scrollHeight;
		// $("#formcontent" + boxId).selectionStart = $("#formcontent" + boxId).selectionEnd = $("#formcontent" + boxId).value.length;

	} else {
		$("#formcontent" + boxId).scrollTop = 0;	
	}
}

function updateUrlList (val, op){
	$("#checkedUrlsDiv").show();
	if (op) {
		if ($("#checkedUrls").text() ==''){
			$("#checkedUrls").append("[[" + val + "]]");
		} else {
			$("#checkedUrls").append("\n[[" + val + "]]");
		}
	} else {
		newText = $("#checkedUrls").text().replace("[[" + val + "]]","");
	  newText = newText.replace("\n\n","\n");
		newText = newText.replace(/\n+$/, "");
		$("#checkedUrls").text(newText);
	}
  if ($("#checkedUrls").text().length) {
		$("#numcards").text($("#checkedUrls").text().split("\n").length);
  } else {
		$("#numcards").text("0");
  }
}

function sendToBigEditor(id, op){
	simplemde.value($("#formcontent"+id).val());
	$("#formtitle").val($("#formtitle"+id).val());
	$("html, body").animate({ scrollTop: 0 }, "slow");
	$('#formcontent').addClass("attention");  
}