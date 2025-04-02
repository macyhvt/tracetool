/**!
 * @fileOverview Add description...
 * @version 1.0
 * @license Copyright 2019 FRÖTEK-Kunststofftechnik GmbH. All rights reserved.
 */

"use strict";

// + function(window) {
// console.info("Early bindings.")

// Check for HTML5 Web Storage support
// HTML Web storage provides two objects for storing data on the client:
//   window.localStorage   - stores data with no expiration date, data persists browser restart, data is available in every browser tab
//   window.sessionStorage - stores data for one session (data is lost when the browser tab is closed) in one tab (storage may differ between different tabs of the same domain)
if (typeof Storage === "undefined") {
	// Sorry! No Web Storage support.
	throw new Error("Your web browser does not support HTML5 web storage technology.");
}

// Check for jQuery being available
if (typeof jQuery  === "undefined" || jQuery === null) {
	throw new Error("The FTK-JavaScript requires lib jQuery.");
}
else {
	// Disabling DropzoneJS autoDiscover, otherwise DropzoneJS will try to attach twice.
	if (typeof Dropzone === "function") {
		Dropzone.autoDiscover = false;
	}

	// Code borrowed from https://stackoverflow.com/a/7616484
	if (typeof String.prototype.hashCode === "undefined") {
		String.prototype.hashCode = function() {
			let hash = 0, i, chr;

			if (this.length === 0) {
				return hash;
			}

			for (i = 0; i < this.length; i++) {
				chr = this.charCodeAt(i);
				hash = ((hash << 5) - hash) + chr;
				hash |= 0; // Convert to 32bit integer
			}

			return hash;
		};
	}

	window.FTKAPP.constants = window.FTKAPP.constants || {};
	window.FTKAPP.constants.maxlengthConfig = {
		showOnReady: false,
		alwaysShow: true,
		appendToParent: true,
		placement: "bottom-right-inside",
		validate: true,
		warningClass: "small form-text text-muted",
		limitReachedClass: "small form-text text-danger",
		threshold: 10
	};
	window.FTKAPP.constants.validation = {
		errorClass:     "validation-result border-danger text-danger small",	// TWBS class(es) --- NEW: 2023-03-27 - class border-danger added
		errorElement:   "span",
		focusInvalid:   true,
		invalidHandler: function(customEvt, validator) {
			let errors = validator.numberOfInvalids();

			if (errors) {
				// Remove blocking overlay from form.
				customEvt.target.classList.remove("submitted");

				// Render message.
				window.FTKAPP.functions.renderMessage({
						type: "info",
						text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_REQUIRED_INFORMATION_IS_MISSING_TEXT"]
					},
					{autohide : false}
				);
			}
		}
	};

	// Early Form bindings
	+ function($, window) {
		"use strict";

		// console.log("################################################################################################################################################################");
		// console.info("Early bindings.")

		/* jQuery plugin to deny copy+paste capability.
		 */
		$.fn.nocp = $.fn.nocp || function() {
			return this.each(function() {
				let $this = $(this);

				if (!$this.is('.nocp')) {
					return;
				}

				$this
				.on('copy, cut, paste', function() {
					window.FTKAPP.functions.renderMessage({
						type: "info",
						text: "Copy and Paste is not allowed. To generate a password press the button."	//TODO - translate
						},
						{autohide : false}
					);

					return false;
				});
			});
		};

		/* jQuery plugin to enable form serialization to proper Javascript object.
		 *
		 * Code borrowed with slight changes from https://stackoverflow.com/a/22420377
		 */
		$.fn.serializeToObject = $.fn.serializeToObject || function(ignoreHiddenFields = false) {
			let obj = {},
				arr = (true == ignoreHiddenFields) ? this.find(":input:not(:hidden)").serializeArray() : this.serializeArray();

			$.each(arr, function() {
				if (obj[this.name]) {
					if (!obj[this.name].push) {
						obj[this.name] = [obj[this.name]];
					}

					obj[this.name].push(this.value || "");
				} else {
					obj[this.name] = this.value || "";
				}
			});

			return obj;
		};

		/* Converts any string into camel case format.
		 *
		 * @param  {string}  str  The string to be converted. (Words must be separated by a blank like "This for example")
		 *
		 * @return {string} The converted string or "" when str is empty
		 *
		 * Code borrowed with slight change from:  https://stackoverflow.com/a/2970667
		 */
		window.FTKAPP.functions.camelize = function(str = "") {
			return str.replace(/(?:^\w|[A-Z]|\b\w|\s+)/g, function(match, idx) {
				if (+match === 0) {
					return "";		// or if (/\s+/.test(match)) for white spaces
				}

				return idx === 0 ? match.toLowerCase() : match.toUpperCase();
			}).replace(/\s+/g, '');
		};

		/* Method to fetch the currently valid TWBS-breakpoint shortcut.
		 *
		 * @return {string} One of "xl, lg, md, sm, xs"
		 *
		 * Inspired by
		 *    https://stackoverflow.com/questions/18575582/how-to-detect-responsive-breakpoints-of-twitter-bootstrap-3-using-javascript and
		 *    https://stackoverflow.com/a/8876069
		 */
		window.FTKAPP.functions.getBreakpoint = function() {
			/* TWBS-breakpoints as defined in bootstrap stylesheet
			 *
			 * --breakpoint-xs: 0;
			 * --breakpoint-sm: 576px;
			 * --breakpoint-md: 768px;
			 * --breakpoint-lg: 992px;
			 * --breakpoint-xl: 1200px;
			 */
			switch (true) {
				case (window.innerWidth >= 1200) :
				return "xl";

				case (window.innerWidth >= 992 && window.innerWidth < 1200) :
				return "lg";

				case (window.innerWidth >= 768 && window.innerWidth <  992) :
				return "md";

				case (window.innerWidth >= 576 && window.innerWidth <  768) :
				return "sm";
			}

			return "xs";
		};
		/* Method to check for a supposed breakpoint.
		 *
		 * @return {bool} true if the supposed breakpoint is the currently active one or false if not
		 *
		 * Inspired by
		 *    https://stackoverflow.com/questions/18575582/how-to-detect-responsive-breakpoints-of-twitter-bootstrap-3-using-javascript and
		 *    https://stackoverflow.com/a/8876069
		 */
		window.FTKAPP.functions.isBreakpoint  = function(alias) {
			/* TWBS-breakpoints as defined in bootstrap stylesheet
			 *
			 * --breakpoint-xs: 0;
			 * --breakpoint-sm: 576px;
			 * --breakpoint-md: 768px;
			 * --breakpoint-lg: 992px;
			 * --breakpoint-xl: 1200px;
			 */
			switch (alias) {
				case "xl" :	// 1200px
				return	window.innerWidth >= 1200;

				case "lg" :	// 992px
				return	window.innerWidth >= 992 && window.innerWidth < 1200;

				case "md" :	// 768px
				return	window.innerWidth >= 768 && window.innerWidth <  992;

				case "sm" :	// 576px
				return	window.innerWidth >= 576 && window.innerWidth <  768;

				case "xs" :	// 0
				return	window.innerWidth >    0 &&
						window.innerWidth <  576 &&
						window.innerWidth <  768 &&
						window.innerWidth <  992 &&
						window.innerWidth < 1200;
			}

			return false;
		};
		/* Method to check if a JavaScript Object is a DOM Element
		 *
		 * @param  {object}  elem  The element to check
		 *
		 * @return {boolean}
		 *
		 * @see https://stackoverflow.com/a/384380
		 */
		window.FTKAPP.functions.isHTMLElement = function(elem) {
			return (
				typeof HTMLElement === "object"
					? elem instanceof HTMLElement	// DOM2
					: elem && typeof elem === "object" && elem !== null && elem.nodeType === 1 && typeof elem.nodeName === "string"
			);
		};
		/* Method to check if a JavaScript Object is a DOM Node
		 *
		 * @param  {object}  elem  The element to check
		 *
		 * @return {boolean}
		 *
		 * @see https://stackoverflow.com/a/384380
		 */
		window.FTKAPP.functions.isNode = function(elem) {
			return (typeof Node === "object"
				? elem instanceof Node
				: elem && typeof elem === "object" && typeof elem.nodeType === "number" && typeof elem.nodeName === "string"
			);
		};

		// Code borrowed with slight changes from https://www.sitepoint.com/build-javascript-countdown-timer-no-dependencies/
		window.FTKAPP.functions.initIdleTimer = function(lifeTime) {
			// Get monitor element.
			const $monitor = $("#idleTimer").empty();

			// Create clock element.
			$('<div class="xsmall text-secondary">' +
				'<label for="clockdiv" class="d-inline-block my-0 py-0 mr-2">' + window.FTKAPP.translator.map["COM_FTK_LABEL_AUTOMATIC_LOGOFF_TEXT"] + ':</label>' +
				'<div class="d-inline-block text-monospace xsmall" id="clockdiv" style="display:none">' +
					// '<span class="d-inline-block days">00</span>:<span class="d-inline-block hours mr-2">00</span>' +
					'<span class="d-inline-block minutes">00</span>:<span class="d-inline-block seconds">00</span>' +
				'</div>' +
			'</div>'
			)
			.appendTo($monitor)
			.fadeIn();

			// Implementation without schedule

			/* Define deadline */

			// The ISO 8601 format:
			// const deadline = '2015-12-31';
			// The short format:
			// const deadline = '31/12/2015';
			// Or, the long format:
			// const deadline = 'December 31 2015';
			// Or, with exact time and a time zone (or an offset from UTC in the case of ISO dates)
			// const deadline = 'December 31 2015 23:59:59 GMT+0200';

			/* Or define time until e.g. auto-log off */
			const timeInMinutes = lifeTime;
			const currentTime   = Date.parse(new Date());
			const deadline      = new Date(currentTime + (timeInMinutes * 60 * 1000));

			/* Calculate the Time Remaining */
			function getTimeRemaining(endtime) {
				const total   = Date.parse(endtime) - Date.parse(new Date());	// Hold remaining time until deadline. The Date.parse() function converts a time string into a value in milliseconds. This allows us to subtract two times from each other and get the amount of time in between.
				const seconds = Math.floor((total /  1000) % 60);				// Convert the Time to a Usable Format
				const minutes = Math.floor((total /  1000  / 60) % 60);
				const hours   = Math.floor((total / (1000  * 60  * 60)) % 24);
				const days    = Math.floor( total / (1000  * 60  * 60 * 24));

				/* Return data as a reusable object.
				 * This object allows you to call your function and get any of the calculated values.
				 * Here’s an example of how you’d get the remaining minutes.
				 *    getTimeRemaining(deadline).minutes
				 */
				return {
					total,
					days,
					hours,
					minutes,
					seconds
				};
			}

			/* Display the Clock and Stop It When It Reaches Zero */

			// Function that outputs the clock data inside our new div
			// It takes two parameters. These are the id of the element that contains our clock, and the countdown’s end time.
			function initializeClock(id, endtime) {
				const clock       = document.getElementById(id);		// Reference to our clock container

				// Show container.
				// clock.style.display = 'block';

				const daysSpan    = clock.querySelector('.days');		// Reference to the days-element
				const hoursSpan   = clock.querySelector('.hours');		// Reference to the hours-element
				const minutesSpan = clock.querySelector('.minutes');	// Reference to the minmutes-element
				const secondsSpan = clock.querySelector('.seconds');	// Reference to the seconds-element
				// let warnLevel     = '';

				function updateClock() {
					// Calculate the remaining time.
					const t = getTimeRemaining(endtime);

					/*//FIXME - calculation is wrong
					if (((t.total / 1000) < (timeInMinutes * 60)) && t.seconds <= ((timeInMinutes * 60) * 0.5)) {
						warnLevel = 'orange';

						if (t.seconds == ((timeInMinutes * 60) * 0.5)) {
							$("#system-message-container").remove();

							window.FTKAPP.functions.renderMessage({
								type: "info",
								// text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_SESSION_EXPIRED_TEXT"]
								// text: window.FTKAPP.translator.sprintf(
									// window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"], t.seconds
								// )
								text: "Sie werden in " + t.seconds + " Sekunden wegen Inaktivität abgemeldet."	// TODO - translate
							});
						}

						$(clock).removeClass("text-" + warnLevel).addClass("text-" + warnLevel);
					}

					if (((t.total / 1000) < (timeInMinutes * 60)) && t.seconds <= ((timeInMinutes * 60) * 0.25)) {
						warnLevel = 'red';

						if (t.seconds == ((timeInMinutes * 60) * 0.25)) {
							$("#system-message-container").remove();

							window.FTKAPP.functions.renderMessage({
								type: "danger",
								// text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_SESSION_EXPIRED_TEXT"]
								// text: window.FTKAPP.translator.sprintf(
									// window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"], t.seconds
								// )
								text: "Sie werden in " + t.seconds + " Sekunden wegen Inaktivität abgemeldet."	// TODO - translate
							});
						}

						$(clock).removeClass("text-" + warnLevel).addClass("text-" + warnLevel);
					}*/

					// Output the remaining time to our container.
					$(daysSpan).text(t.days);
					$(hoursSpan).text(('0' + t.hours).slice(-2));		// Display value with a leading zero
					$(minutesSpan).text(('0' + t.minutes).slice(-2));	// Display value with a leading zero
					$(secondsSpan).text(('0' + t.seconds).slice(-2));	// Display value with a leading zero

					// If the remaining time gets to zero, stop the clock.
					if (t.total <= 0) {
						clearInterval(timeinterval);

						$(window).trigger("user.session.expired");
					}
				}

				// Run function once at first to avoid display delay of 1 second
				updateClock();

				let timeinterval = setInterval(updateClock, 1000);
			}

			/* At this point, the only remaining step is to run the clock like so */
			initializeClock('clockdiv', deadline);

			/* Specify the dates between which the clock should show up. This will replace the deadline variable */
			// As noted above, it is possible to include times and time zones.
			/*// Uncomment to your likes
			const schedule = [
				// start-Date  ,  end-Date
				['Jul 25 2015' , 'Sept 20 2015'],
				['Sept 21 2015', 'Jul 25 2016'],
				['Jul 25 2016' , 'Jul 25 2030']
			];*/
			// When a user loads the page, we need to check if we are within any of the specified time frames.
			// This code should replace the previous call to the initializeClock function.

			/* Set Timer for 10 Minutes from When the User Arrives */
			// Set a timer for 10 minutes here, but you can use any amount of time you want.
			/*// Replace the deadline variable above with this code:
			const timeInMinutes = 10;
			const currentTime   = Date.parse(new Date());
			const deadline      = new Date(currentTime + (timeInMinutes * 60 * 1000));*/
			// This code takes the current time and adds ten minutes. The values are converted into milliseconds, so they can be added together and turned into a new deadline.
		}

		window.FTKAPP.functions.createRandomString = function(ln = 12) {
			let str = "", len = ln;

			for ( ; str.length < len; str += Math.random().toString(36).substr(2) );

			// Split string into equal chunks
			// Code taken from:  https://stackoverflow.com/a/7033662
			return str.substr(0, len).match(/.{1,3}/g).join("-").toUpperCase();
		};

		window.FTKAPP.functions.sendEncryptedMail = function(EncryptedAddress) {
			let StringArray = EncryptedAddress.split("|"),
				RealMailAddress = "";

			for (let i = 0; i <= StringArray.length - 1; i += 1) {
				RealMailAddress = RealMailAddress + String.fromCharCode(StringArray[i]);
			}

			document.location.href = "mailto:" + RealMailAddress;
		};

		/* Checks a browser's support for localStorage/sessionStorage.
		 *
		 * @param {string}  type  The label of the storage to eval.  Default: localStorage
		 *
		 * @return bool
		 *
		 * Code borrowed with slight changes from: https://developer.mozilla.org/en-US/docs/Web/API/Web_Storage_API/Using_the_Web_Storage_API
		 */
		window.FTKAPP.functions.supportsWebStorage = function(type) {
			let storage;

			try {
				storage = window[type];

				let x = '__storage_test__';

				storage.setItem(x, x);
				storage.removeItem(x);

				return true;
			} catch (err) {
				return err instanceof DOMException && (
					// everything except Firefox
					err.code ===   22 ||
					// Firefox
					err.code === 1014 ||
					// test name field too, because code might not be present
					// everything except Firefox
					err.name === 'QuotaExceededError' ||
					// Firefox
					err.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
				// acknowledge QuotaExceededError only if there's something already stored
				(storage && storage.length !== 0);
			}
		}
		/* Creates a webStorage object to persist userdata on client side.
		 *
		 * @param {string}  driver  The preferred driver to use. Use one between
		 *     localStorage and sessionStorage.  Default: localStorage
		 * @param {string}  name  The name of the database. This is used as prefix
		 *     for all keys stored in the offline storage.  Default: webStorage
		 *
		 * @return void
		 */
		window.FTKAPP.functions.clearWebStorage = function(driver/*, name*/) {
			switch (driver) {
				case "local" :
					localStorage.clear();
				break;

				case "session" :
				default :
					sessionStorage.clear();
				break;
			}
		};

		// Method to close all autocompletable datalists in the document, except the one passed as an argument:
		window.FTKAPP.functions.closeAllDatalists = function() {
			$(this).parent().find(".autocomplete-items").remove();	// hide + remove all autocomplete-lists
		};

		/* Method to check if an object is a real Array.
		 *
		 * @param {Array}  arr  The array to process.
		 *
		 * @return int|string|null
		 *
		 * Implementation inspired by : https://stackoverflow.com/a/26633883
		 */
		window.FTKAPP.functions.isArray = function(arr) {
			return arr.constructor === Array;
		}
		/* Method to get the first key of an array
		 * Adapted to provide the self-named PHP-function.
		 *
		 * @param {Array}  arr  The array to process.
		 *
		 * @return int|string|null
		 */
		window.FTKAPP.functions.array_key_first = function(arr) {
			if (!window.FTKAPP.functions.isArray(arr)) {
				throw "Error in array_key_first: Argument must be an array."
			}

			return (arr.length == 0) ? null : arr.slice(0, 1).shift();
		}
		/* Method to get the last key of an array
		 * Adapted to provide the self-named PHP-function.
		 *
		 * @param {Array}  arr  The array to process.
		 *
		 * @return int|string|null
		 */
		window.FTKAPP.functions.array_key_last = function(arr) {
			if (!window.FTKAPP.functions.isArray(arr)) {
				throw "Error in array_key_first: Argument must be an array."
			}

			return (arr.length == 0) ? null : arr.slice(arr.length - 1).shift();
		}
		// Method to convert an array of numerical values to an array of real Integers.
		// TODO - convert to accept as a second argument a callback to be applied and rename to 'array_map'
		window.FTKAPP.functions.array_mapToIntegers = function(arr) {
			let numbers = [], cnt = arr.length;

			for (let i = 0; i < cnt; i += 1) {
				numbers.push(parseInt(arr[i]));
			}

			return numbers;
		}

		// Code taken from:  https://stackoverflow.com/a/13691499
		window.FTKAPP.functions.utf8_encode = function(str) {
			return unescape(encodeURIComponent(str));
		};
		window.FTKAPP.functions.utf8_decode = function(str) {
			return decodeURIComponent(escape(str));
		};

		/* Implementation of a CRC32 checksum generator
		 * Code borrowed with slight changes from https://stackoverflow.com/a/18639999
		 *
		 * @param {string}  str  The input string to generate the checksum for
		 *
		 * @return string
		 */
		window.FTKAPP.functions.CRC32 = function(str) {
			console.warn("window.FTKAPP.functions.CRC32");
			console.log("input:", typeof str, str);

			let crc = 0 ^ (-1),
				crcTable = window.crcTable || (window.crcTable = window.FTKAPP.functions.makeCRCTable());

			for (let i = 0; i < str.length; i += 1) {
				crc = (crc >>> 8) ^ crcTable[(crc ^ str.charCodeAt(i)) & 0xFF];
			}

			return (crc ^ (-1)) >>> 0;
		};
		/* Utility function required for the CRC32() function
		 * Code borrowed with slight changes from https://stackoverflow.com/a/18639999
		 *
		 * @return array
		 */
		window.FTKAPP.functions.makeCRCTable = function() {
			console.warn("window.FTKAPP.functions.makeCRCTable");

			let c, crcTable = [];

			for (let n = 0; n < 256; n += 1) {
				c = n;

				for (let k = 0; k < 8; k += 1) {
					c = ((c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1));
				}

				crcTable[n] = c;
			}

			return crcTable;
		};

		window.FTKAPP.functions.convertToHash = function(str) {
			console.warn("convertToHash");
			console.log("input:", typeof str, str);

			if (str == "") return 0;

			let hashString = 0;

			for (let character of str) {
				let charCode = character.charCodeAt(0);

				hashString = hashString << 5 - hashString;
				hashString += charCode;
				hashString |= hashString;
			}

			console.log("The original string is: " + str);
			console.log("The hash string related to original string is: " + hashString);

			return hashString;
		};
		window.FTKAPP.functions.hashUsingReduce = function(str) {
			console.warn("hashUsingReduce");
			console.log("input:", typeof number, number);

			if (str == "") return 0;

			let charArray = str.split('');

			let hash = charArray.reduce((hash, char) => ((hash << 5 - hash) + char.charCodeAt(0)) | hash, 0);

			console.log("The original string is: " + str);
			console.log("The hash string related to original string is: " + hash);

			return hash;
		};

		/* Utility function to convert decimal to hexadecimal in JavaScript
		 *
		 * @param {integer}  number  The value to convert
		 *
		 * Code borrowed with no changes from https://stackoverflow.com/a/697841
		 */
		window.FTKAPP.functions.decToHex = function(number) {
			console.warn("decToHex");
			console.log("input:", typeof number, number);

			if (number < 0) {
				number = 0xFFFFFFFF + number + 1;
			}

			console.log("return:", number.toString(16).toUpperCase());

			return number.toString(16).toUpperCase();
		};

		window.FTKAPP.functions.linkToText = function(elem) {
			alert("Further processing is temp. disabled because this functionality is currently instable. Kindly require further notice from the development department.");
			return false;

			/*let target = elem || this, $target = $(target), text = $target.text();

			$target.replaceWith( text );*/
		};

		// Code borrowed from:  https://stackoverflow.com/a/8649003
		window.FTKAPP.functions.parseQuery = function(query) {
			return JSON.parse( '{"' + query.replace(/&/g, '","').replace(/=/g,'":"') + '"}', function(key, value) { return key === "" ? value : decodeURIComponent(value) } );
		};

		// Code borrowed from:  https://www.sitepoint.com/get-url-parameters-with-javascript
		window.FTKAPP.functions.getAllUrlParams = function(url) {
			// if no URL was passed fall back to current window location.
			url = url || window.location.href;

			// von Tino: The url expected must be a fully qualified URL (incl. host and path).
			//           A query string alone will not be parsed.
			//           The source document in the internet does exactly the same but not within this function.
			if (!url.match(/^https?:/i) && !url.match(/\/\/www\./i) && url.split("?").length == 1) {
				url = "https://example.com/?" + url.split("?").pop();
			}

			// von Tino: Be sure the URL is not encoded so that special characters like brackets/braces will be detected.
			url = decodeURI(url);

			// get query string from url (optional) or window
			let queryString = url ? url.split('?')[1] : document.location.search.slice(1);

			// we'll store the parameters here
			let obj = {};

			// if query string exists
			if (queryString) {
				// stuff after # is not part of query string, so get rid of it
				queryString = queryString.split('#')[0];

				// split our query string into its component parts
				let arr = queryString.split('&');

				for (let i = 0; i < arr.length; i++) {
					// separate the keys and the values
					let a = arr[i].split('=');

					// set parameter name and value (use 'true' if empty)
					let paramName = a[0];
					let paramValue = typeof (a[1]) === 'undefined' ? true : a[1];

					// (optional) keep case consistent
					paramName = paramName.toLowerCase();

					if (typeof paramValue === 'string') paramValue = paramValue.toLowerCase();

					// if the paramName ends with square brackets, e.g. colors[] or colors[2]
					if (paramName.match(/\[(\d+)?\]$/)) {

						// create key if it doesn't exist
						let key = paramName.replace(/\[(\d+)?\]/, '');

						if (!obj[key]) obj[key] = [];

						// if it's an indexed array e.g. colors[2]
						if (paramName.match(/\[\d+\]$/)) {
							// get the index value and add the entry at the appropriate position
							let index = /\[(\d+)\]/.exec(paramName)[1];
							obj[key][index] = paramValue;
						} else {
							// otherwise add the value to the end of the array
							obj[key].push(paramValue);
						}
					} else {
						// we're dealing with a string
						if (!obj[paramName]) {
							// if it doesn't exist, create property
							obj[paramName] = paramValue;
						} else if (obj[paramName] && typeof obj[paramName] === 'string') {
							// if property does exist and it's a string, convert it to an array
							obj[paramName] = [obj[paramName]];
							obj[paramName].push(paramValue);
						} else {
							// otherwise add the property
							obj[paramName].push(paramValue);
						}
					}
				}
			} else {
				console.warn("getAllUrlParams NOTICE: The passed in URL is no fully qualified URL. If there is a query string it could not be found.");
			}

			return obj;
		};
		// Code borrowed with slight changes from:  https://html-online.com/articles/get-url-parameters-javascript
		window.FTKAPP.functions.getUrlParam = function(param, fallback) {
			// Init return value with fallback value.
			let urlParam = fallback;

			// If the requested parameter is in the URL return its value.
			if (window.location.href.indexOf(param) > -1 && typeof window.FTKAPP.functions["getAllUrlParams"] === "function") {
				return window.FTKAPP.functions.getAllUrlParams()[param];
			}

			// The requested parameter is NOT in the URL. Return fallback value.
			return urlParam;
		};

		window.FTKAPP.functions.clearMessages = function() {
			// Hide all currently open messages.
			try {
				$("#system-message-container").fadeOut("fast", function() { $(this).remove(); });
			} catch (err) {}
		};
		window.FTKAPP.functions.renderMessage = function(data, options = {}) {
			let $parent  = $("main:first-of-type"), autohide;
				$parent  = $parent.length ? $parent : $("#wrapper:first-of-type");
				autohide = options.autohide || true;

			if (!$parent.length) {
				console.warn("Message cannot be rendered: No parent element.");
				return;
			}

			// Hide all currently open messages.
			try {
				window.FTKAPP.functions.clearMessages();
			} catch (err) {}

			// Create new message container.
			$("" +
			'<aside class="container text-left static" id="system-message-container" data-count="1">' +
				'<div class="system-message system-message-1 alert alert-dismissible ' + (autohide ? 'autohide' : '') + ' rounded-0 fade show" id="system-message-1" role="alert">' +
					'<div class="alert-message alert-message-' + (data.type || 'notice' ).toLowerCase().trim() + ' border-0 bg-white">' +
						'<button type="button" class="close system-message-toggle" ' +
								'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_CLOSE_TEXT"] + '" ' +
								'data-dismiss="alert" ' +
								'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_CLOSE_TEXT"] + '" ' +
						'>' +
							'<span aria-hidden="true">×</span>' +
						'</button>' +
						'<h4 class="alert-heading h5 mb-1">' + window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_" + (data.type || "notice").toUpperCase() + "_TEXT"] + '</h4>' +
						'<div class="messages">' +
							'<p class="message mb-0"></p>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</aside>')
			// Add container to the DOM.
			.prependTo( $parent )
			// Inject message.
			.find("p.message")
				.html( (data.text || window.FTKAPP.translator.map["COM_FTK_NA_TEXT"]).trim() )
				.end()
			// Fix container position.
			.queue(function(next) {
				let $this = $(this),
					$relativeParent = $(document.body);

				$this.css({
					"left" : 0,	// Force window outer left border to be reference for next calculation
					"margin-left" : (($relativeParent.outerWidth() / 2) - $this.outerWidth() / 2) + "px"	// half the window width - half the element width
				})

				next();
			});
		};
		// see: https://getbootstrap.com/docs/4.5/components/popovers/
		window.FTKAPP.functions.renderPopover = function(elem, data = {}, options = {}) {
			// console.warn("renderPopover");
			// console.log("data:", data);
			// console.log("options:", options);

			let $element = $(elem),
				   title = function() {
						if (data.title) {
							return data.title;
						} else {
							return options.title || ($element.data("title") || $element.attr("title"));
						}
				   },
				 content = function() {
						if (data.content) {
							return data.content;
						} else if (data.text) {
							return data.text;
						} else {
							return options.content || $element.data("content");
						}
				   },
				// We set the template markup explicitely, because this is the only way to inject our custom class
				// "popover-(primary|secondary|success|danger|error|warning|info|dark)" for styling.
				template = options.template || '' +
				'<div class="' + ['popover', 'popover-' + (data.type || '')].join(' ') + '" role="tooltip">' +
					'<div class="arrow"></div>' +
					'<h3 class="popover-header"></h3>' +
					'<div class="popover-body"></div>' +
				'</div>',
				  config = {
						 trigger : options.trigger      || "click",					// string : How a tooltip is triggered - click | hover | focus | manual. You may pass multiple triggers; separate them with a space.
					popperConfig : options.popperConfig || null,					// null | object : To change Bootstrap's default Popper.js config, see https://popper.js.org/docs/v1/#Popper.Defaults
					animation    : options.animation    || true,
//					container    : elem                 || false,					// string | element | false : Appends the tooltip to a specific element. Allows to position the tooltip in the flow of the document near the triggering element - which will prevent the tooltip from floating away from the triggering element during a window resize.
					delay        : options.delay        || {show: 0, hide: 1000},
					html         : options.html         || true,
					placement    : options.placement    || "auto",
					offset       : options.offset       || 0,						// number | string | function
					selector     : options.selector     || false,
					sanitize     : options.sanitize     || true,					// If activated 'template' and 'title' options will be sanitized.
//					sanitizeFn   : options.sanitizeFn   || null,					// null | function : Supply own sanitize function. This can be useful if you prefer to use a dedicated library to perform sanitization.
//					whiteList    : options.whiteList    || {},
					foo          : null
				  };

			if (title) {
				$element.attr("title", (typeof title === "function" ? title() : title));
			}

			if (content) {
				$element.attr("data-content", content);
			}

			if (template) {
				config.template = template;
			}

			// console.log("rendering config:", config);

			$element
			.popover("dispose")
			.popover(config)
			.popover("show");
		};
		// see: https://getbootstrap.com/docs/4.5/components/toasts/
		window.FTKAPP.functions.renderToast   = function(elem, data = {}, options = {}) {
			console.warn("renderToast");
			console.log("data:", data);
			console.log("options:", options);
			alert('Not implemented');
			return false;

			let $element = $(elem),
				template = options.template,
				   title = function() {
						if (data.text) {
							return data.text;
						} else {
							return options.title || ($element.data("title") || $element.attr("title"));
						}
				   },
				  config = {
						 trigger : options.trigger      || "click",					// string : How a tooltip is triggered - click | hover | focus | manual. You may pass multiple triggers; separate them with a space.
					popperConfig : options.popperConfig || null,					// null | object : To change Bootstrap's default Popper.js config, see https://popper.js.org/docs/v1/#Popper.Defaults
					animation    : options.animation    || true,
//					container    : elem                 || false,					// string | element | false : Appends the tooltip to a specific element. Allows to position the tooltip in the flow of the document near the triggering element - which will prevent the tooltip from floating away from the triggering element during a window resize.
					delay        : options.delay        || {show: 0, hide: 1000},
					html         : options.html         || true,
					placement    : options.placement    || "auto",
					offset       : options.offset       || 0,						// number | string | function
					selector     : options.selector     || false,
					sanitize     : options.sanitize     || true,					// If activated 'template' and 'title' options will be sanitized.
//					sanitizeFn   : options.sanitizeFn   || null,					// null | function : Supply own sanitize function. This can be useful if you prefer to use a dedicated library to perform sanitization.
//					whiteList    : options.whiteList    || {},
					foo          : null
				  };

			if (title) {
				$element.attr("title", (typeof title === "function" ? title() : title));
			}

			if (template) {
				config.template = template;
			}

			// console.log("rendering config:", config);

			$element
			.tooltip(config)
			.tooltip("show");
		};
		// see: https://getbootstrap.com/docs/4.5/components/tooltips/
		window.FTKAPP.functions.renderTooltip = function(elem, data = {}, options = {}) {
			// console.warn("renderTooltip");
			// console.log("data:", data);
			// console.log("options:", options);

			let $element = $(elem),
				template = options.template,
				   title = function() {
						if (data.text) {
							return data.text;
						} else {
							return options.title || ($element.data("title") || $element.attr("title"));
						}
				   },
				  config = {
						 trigger : options.trigger      || "click",					// string : How a tooltip is triggered - click | hover | focus | manual. You may pass multiple triggers; separate them with a space.
					popperConfig : options.popperConfig || null,					// null | object : To change Bootstrap's default Popper.js config, see https://popper.js.org/docs/v1/#Popper.Defaults
					animation    : options.animation    || true,
//					container    : elem                 || false,					// string | element | false : Appends the tooltip to a specific element. Allows to position the tooltip in the flow of the document near the triggering element - which will prevent the tooltip from floating away from the triggering element during a window resize.
					delay        : options.delay        || {show: 0, hide: 1000},
					html         : options.html         || true,
					placement    : options.placement    || "auto",
					offset       : options.offset       || 0,						// number | string | function
					selector     : options.selector     || false,
					sanitize     : options.sanitize     || true,					// If activated 'template' and 'title' options will be sanitized.
//					sanitizeFn   : options.sanitizeFn   || null,					// null | function : Supply own sanitize function. This can be useful if you prefer to use a dedicated library to perform sanitization.
//					whiteList    : options.whiteList    || {},
					foo          : null
				  };

			if (title) {
				$element.attr("title", (typeof title === "function" ? title() : title));
			}

			if (template) {
				config.template = template;
			}

			// console.log("rendering config:", config);

			$element
			.tooltip(config)
			.tooltip("show");
		};

		window.FTKAPP.functions.replaceElement = function(elem, callback) {
			let $element = $(elem),
				$target  = $( $element.data("target") ),
				$parent  = $target.parent(),
				animate  = $element.data("animation") || false,
				options  = $element.data("replacementOptions"),
			 stateClass  = (!animate ? "" : "loading"),
			 $loaderIcon = (!animate ? "" : $('' +
					'<span class="d-block overlay-spinner text-center position-absolute">' +
						'<i class="fas fa-spinner fa-pulse fa-2x"></i>' +
					'</span>'
				)
				.css({
					"visibility" : "hidden",
					"width" : $element.innerWidth() + "px",
					"top"  : "0",
					"margin-top" : "2px",
					"background" : $element.css("background-color"),
					"color" : $element.css("color")
				}));

			// Inject process icon.
			if (animate) {
				$parent
				.append($loaderIcon)
				.queue(function(next) {
					if ($loaderIcon) {
						$loaderIcon.css({"visibility" : "visible"});
					}

					next();
				})
				.addClass(stateClass);
			}

			// Set timeout of min. 10ms to allow the process icon to display
			// and tell the user that there's something happening.
			setTimeout(function() {
				let isAppend = $element.data("append")  || false,
				   isPrepend = $element.data("prepend") || false,
				   isReplace = $element.data("replace") || false,

				// Create widget
				$widget = $("<" + options.element + "/>", options.attributes)
				.append(
					!options.icon
						? ""
						: $("<i/>", {"class" : options.icon})
				)
				.append(
					!options.icon
						? ""
						: $("<span/>", {"class" : "d-md-inline ml-lg-2", "html" : options.text})
				)
				.append(function() {
					let html = options.html || '';

					if (typeof Base64 === "object" && Base64.extendString) {
						// We have to explicitly extend String.prototype prior using the Base64-methods.
						Base64.extendString();

						// Once extended, we can do the following to decode the data.
						html = html.fromBase64();
					} else {
						html = atob(html);
					}

					return html;
				});

				// Inject widget
				if (true === isReplace) {
					$target.replaceWith( $widget );
				} else {
					if (true === isPrepend) {
						$target.prepend( $widget );
					} else {
						$target.append( $widget );
					}
				}

				// Render widget
				$widget
				/*
				.tooltip(
					$.extend(
						{
							container : "body",
							boundary  : "window"
						},
						(true
							? {delay : $(this).data("delay")}
							: {}
						)
					)
				)
				*/
				.queue(function(next) {
					// $(this)
					/*.parent()
						.find(".collapse__OFF")
							//TODO - create 1 function to bind bootstrap behaviour to dynamically loaded content, call it here passing $widget as context
							.on("show.bs.collapse",   function(evt) {
								// console.warn("show.bs.collapse handler");
								// console.log("this:", this);
							})
							.on("shown.bs.collapse",  function(evt) {
								// console.warn("shown.bs.collapse handler");
								// console.log("this:", this);
								// Scroll to expanded collapsible
								$('html, body').animate({
									scrollTop: $( $(this).data("parent") ).offset().top
								}, 750);
							})
							.on("hide.bs.collapse",   function(evt) {
								// console.warn("hide.bs.collapse handler");
								// console.log("this:", this);
							})
							.on("hidden.bs.collapse", function(evt) {
								// console.warn("hidden.bs.collapse handler");
								// console.log("this:", this);
							})
							.collapse({
								toggle: false
							})
						.end()*/
					/*.parent()
						.find(".popover__OFF")
							.on("show.bs.popover",     function(evt) {
								// console.info("show.bs.popover handler");
								// console.log("event:", evt);
							})
							.on("shown.bs.popover",    function(evt) {
								// console.info("shown.bs.popover handler");
								// console.log("event:", evt);
							})
							.on("inserted.bs.popover", function(evt) {
								// console.info("inserted.bs.popover handler");
								// console.log("event:", evt);
							})
							.on("hide.bs.popover",     function(evt) {
								// console.info("hide.bs.popover handler");
								// console.log("event:", evt);
							})
							.on("hidden.bs.popover",   function(evt) {
								// console.info("hidden.bs.popover handler");
								// console.log("event:", evt);
							})
							.popover()
						.end()*/
					/*.parent()
						.find(".tooltip")
							.tooltip(
								$.extend(
									{
										container : "body",
										boundary  : "window"
									},
									(true
										? {delay : $(this).data("delay")}
										: {}
									)
								)
							)
						.end();*/

					$parent
						/*.find(".overlay-spinner__OFF")
							.remove()
						.end()*/
					.find($loaderIcon)
						.remove()
						.end()
					.removeClass(stateClass);

					next();
				})
				/*.find('[data-toggle="tooltip"]__OFF')
					.tooltip(
						$.extend(
							{
								container : "body",
								boundary  : "window"
							},
							(true
								? {delay : $(this).data("delay")}
								: {}
							)
						)
					)*/
				// .fadeIn("fast")
				;

				if (typeof callback === "function") {
					callback(elem, $widget);
				}
			}, 1);
		};

		window.FTKAPP.functions.fetchHTML = function(elem) {
			let $element = $(elem),
				$target = $( $element.data("target") ),
				action = $element.data("action"),
				format = $element.data("format") || "html",
				data = {
					"format" : format
				},
				animate = $element.data("animation") || false,
				// options = $element.data("replacementOptions"),
				stateClass  = (!animate ? "" : "loading"),
				$loaderIcon = (!animate ? "" : $('' +
					'<span class="d-block overlay-spinner text-center position-absolute">' +
						'<i class="fas fa-spinner fa-pulse fa-2x"></i>' +
					'</span>'
				)
				.css({
						"visibility" : "hidden",
						"width" : $element.innerWidth() + "px",
						"top"  : "0",
						"margin-top" : "3px",
						"background" : $element.css("background-color"),
						"color" : $element.css("color")
				}));

			// Inject process icon.
			if (animate) {
				$target
				.parent()
					.append($loaderIcon)
					.queue(function(next) {
						if ($loaderIcon) {
							$loaderIcon.css({"visibility" : "visible"});
						}

						next();
					})
					.end()
				.addClass(stateClass);
			}

			$target.addClass(stateClass);

			$.get(action, data, function(response, statusText, jqXHR) {
				let content;

				/*
				 * Utilize an external 3rd party library since base64-decoding with Window.atob() does not properly workd.
				 * It doesn't decode Umlauts and special characters. Thus the dankogai/js-base64 library must be utilized
				 * to do the job and provide properly decoded Base64 encoded content.
				 *
				 * require:  https://github.com/dankogai/js-base64
				 */
				if (typeof Base64 === "object" && Base64.extendString) {
					// We have to explicitly extend String.prototype prior using the Base64-methods.
					Base64.extendString();

					// Once extended, we can do the following to decode the data.
					content = response.html.fromBase64();
				} else {
					content = atob(response.html);
				}

				// Inject content.
				$target
				.fadeOut("fast", function(evt) {
					$target
					.removeClass(stateClass)
					.addClass("loaded")
					.queue(function(next) {
						// Set timeout of min. 10ms to allow the process icon to display
						// and tell the user that there's something happening.
						let isAppend = $element.data("append")  || false,
						   isPrepend = $element.data("prepend") || false,
						   isReplace = $element.data("replace") || false;

						// Inject widget
						if (true === isReplace) {
							$target.replaceWith( content );
						} else {
							if (true === isPrepend) {
								$target.prepend( content );
							} else {
								$target.append( content );
							}
						}

						next();
					})
					.fadeIn("fast");
				});
			}, data.format)
			/* The request failed.
			 */
			.fail(function(jqXHR, statusText, errorThrown) {
				try {
					//TODO - display error in modal window rather than alerting it.
					alert(errorThrown);
				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			/* The request was successful.
			 */
			.done(function(response, statusText, jqXHR) {})
			/* The request has completed.
			 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
			 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
			 */
			/*.always(function(response, statusText, jqXHR) {
				// Propagate
				// $(document.body).trigger('loaded.k2s.template');
			})*/;
		};

		// Code borrowed with slight changes from:  https://stackoverflow.com/a/23625419/1014412
		window.FTKAPP.functions.convertBytes = function(bytes, unit = "KB", decimals = 2) {
			let marker    = 1024,								// Change to 1000 if required
				kiloBytes = marker,								// One KB is 1024 B
				megaBytes = marker * marker,					// One MB is 1024 KB
				gigaBytes = marker * marker * marker,			// One GB is 1024 MB
				teraBytes = marker * marker * marker * marker;	// One TB is 1024 GB

			switch (unit) {
				// return input value if less than a KB
				case "B" :
					return bytes + " " + unit;

				// return KB if less than a MB
				case "KB" :
					return (bytes / kiloBytes).toFixed(decimals) + " " + unit;

				// return MB if less than a GB
				case "MB" :
					return (bytes / megaBytes).toFixed(decimals) + " " + unit;

				// return GB if less than a TB
				case "GB" :
					return (bytes / gigaBytes).toFixed(decimals) + " " + unit;

				// return GB if less than a PB
				case "TB" :
					return (bytes / teraBytes).toFixed(decimals) + " " + unit;
			}

			return bytes;
		};

		// Code borrowed from:  https://stackoverflow.com/a/18650828/1014412
		window.FTKAPP.functions.convertBytesALTERNATIVE = function(bytes, decimals = 2) {
			if (bytes === 0) {
				return 0;
			}

			const     k = 1024;
			const    dm = decimals < 0 ? 0 : decimals;
			const sizes = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

			const     i = Math.floor( Math.log(bytes) / Math.log(k) );

			return parseFloat( ( bytes / Math.pow(k, i) ).toFixed( dm ) ) + " " + sizes[i];
		};

		/* Generates a unique function name consisting of a prefix and a random numer.
		 * For a JsonP callback function name the prefix is 'jsonpCallback', otherwise the prefix is 'fn'.
		 *
		 * Code inspired by https://www.youtube.com/watch?v=3AoeiQa8mY8
		 *
		 * @param {bool}  forJsonp  Is the function name required for a JsonP callback or not
		 *
		 * @return string
		 */
		window.FTKAPP.functions.generateRandomFunctionName = function(forJsonp) {
			forJsonp = forJsonp || false;

			const prefix = forJsonp ? 'jsonpCallback' : 'fn';

			return prefix + '_' + Math.round(1000000 * Math.random());
		};

		/* Removes from an HTML DOM object a defined attribute.
		 *
		 * @param {object}  elem  The HTML element to set the attribute for
		 * @param {string}  attribute  The attribute to remove
		 *
		 * @return void
		 */
		window.FTKAPP.functions.removeAttribute = function(elem, attribute) {
			// console.warn("removeAttribute");
			// console.log("elem:", typeof elem, elem);
			// console.log("attribute:", typeof attribute, attribute);

			// console.log("isHTMLElement:", window.FTKAPP.functions.isHTMLElement(elem));
			// console.log("isNode:",        window.FTKAPP.functions.isNode(elem));

			if (!window.FTKAPP.functions.isHTMLElement(elem) && !window.FTKAPP.functions.isNode(elem)) {
				// Render error.
				window.FTKAPP.functions.renderMessage({
						type: "notice",
//						text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_REQUIRED_INFORMATION_IS_MISSING_TEXT"]
						text: "Failed to remove element attribute. Function 'removeAttribute' expects argument 1 to be a DOM Element."	// TODO - translate
					},
					{autohide : false}
				);

				return false;
			}

			elem.removeAttribute(attribute);
		}
		/* Adds to an HTML DOM object a given attribute + value.
		 *
		 * @param {object}  elem  The HTML element to set the attribute for
		 * @param {string}  attribute  The attribute to add
		 * @param {string}  value  value to set
		 *
		 * @return void
		 *
		 * @see https://stackoverflow.com/a/11286667
		 */
		window.FTKAPP.functions.setAttribute = function(elem, attribute, value) {
			// console.warn("setAttribute");
			// console.log("elem:", typeof elem, elem);
			// console.log("attribute:", typeof attribute, attribute);
			// console.log("value:", typeof value, value);

			// console.log("isHTMLElement:", window.FTKAPP.functions.isHTMLElement(elem));
			// console.log("isNode:",        window.FTKAPP.functions.isNode(elem));

			if (!window.FTKAPP.functions.isHTMLElement(elem) && !window.FTKAPP.functions.isNode(elem)) {
				// Render error.
				window.FTKAPP.functions.renderMessage({
						type: "notice",
//						text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_REQUIRED_INFORMATION_IS_MISSING_TEXT"]
						text: "Failed to set element attribute. Function 'setAttribute' expects argument 1 to be a DOM Element."	// TODO - translate
					},
					{autohide : false}
				);

				return false;
			}

			let obj = {}, attrIsDataAttribute = /^data-/.test(attribute);

			if (attrIsDataAttribute) {
				elem.setAttribute(attribute, value);

				// Adds attribute(s) as new element attribute like so: data="{attribute: value, ...}"
//				obj[attribute.replace("data-", "")] = value;
//				elem.setAttribute("data", JSON.stringify(obj));
			} else {
				// TODO
			}
		}

		//################################################################################################################################################################


		// Hide DOM elements that shall be initially hidden - via JS to ensure the element will be available to users with no JS support
		$(".js-hide")
		.hide();


		// Snippet to auto-hide system messages
		setTimeout(function() {
			let $this, $parent, $overlay;

			$(".alert-dismissible.autohide")
			.parent()
				.fadeOut(function(evt) {
					$this    = $(this);
					$parent  = $this.parent();
					$overlay = $this.closest(".overlay");

					$this.remove();

					if (!$overlay.children().length) {
						$overlay.fadeOut(function() {
							$(this).remove();
						});
					}
				});
		}, 8000);

		/*$(document)
		.ajaxError(function(evt, jqxhr, settings, thrownError) {
			console.warn("Triggered ajaxError handler.");
			console.log("event:", evt);
			console.log("jqxhr:", jqxhr);
			console.log("settings:", settings);
			console.log("thrownError:", thrownError);
		});*/


		$(".observable")
		.each(function() {
			/*console.info("Add MutationObserver to element:", this);
			console.warn("... currently disabled until further decision!");*/

			/*let $this  = $(this),
			observable = this,
			observe = ($this.data("observe") || "").split(" "),
			observationConfig = [];

			$.each(observe, function(i, target) {
				observationConfig[target] = true;
			});

			$this
			.data({
				observer: new MutationObserver( mutations => {
					mutations.forEach( mutationRecord => {
						console.log("+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++")
						console.log("mutationRecord:", mutationRecord);

						// Observe added Node(s)
						if (mutationRecord.addedNodes.length > 0) {
							console.log("Number of nodes added:", mutationRecord.addedNodes.length);

							mutationRecord.addedNodes.forEach( node => {
								console.log("childNode added:", $(node));
								console.log("children of childNode added:", $(node).children().length, $(node).children());
							});
						}

						// Observe removed Node(s)
						if (mutationRecord.removedNodes.length > 0) {
							console.log("Number of nodes removed:", mutationRecord.removedNodes.length);

							mutationRecord.removedNodes.forEach( node => {
								console.log("childNode removed:", $(node));
								console.log("children of childNode removed:", $(node).children().length, $(node).children());
							});
						}
					});
				})
				.observe(observable, observationConfig)
			});*/
		});

		// Dropzone.
		if (1 > 2) {	//DISABLED on 2020-12-23
		$(".dropzone")
		.each(function() {
			throw "No longer supported.";

			return false;


			let
			// dropzone = new Dropzone("#" + $(this).attr("id"), dzOptions),
			dropzone, config  = {
				// Override translation depending on UI language
				dictDefaultMessage:           window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_DEFAULT_MESSAGE_TEXT"],
				dictFallbackMessage:          window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_FALLBACK_MESSAGE_TEXT"],
				dictFallbackText:             window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_FALLBACK_TEXT"],
				dictFileTooBig:               window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_FILE_TOO_BIG_TEXT"],
				dictInvalidFileType:          window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_INVALID_FILE_TYPE_TEXT"],
				dictResponseError:            window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_RESPONSE_ERROR_TEXT"],
				dictCancelUpload:             window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_CANCEL_UPLOAD_TEXT"],
				dictUploadCanceled:           window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_UPLOAD_CANCELED_TEXT"],
				dictCancelUploadConfirmation: window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_CANCEL_UPLOAD_CONFIRMATION_TEXT"],
				dictRemoveFile:               window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_REMOVE_FILE_TEXT"],
				dictRemoveFileConfirmation:   window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_REMOVE_FILE_CONFIRMATION_TEXT"],
				dictMaxFilesExceeded:         window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_MAX_FILES_EXCEEDED_TEXT"],
				/* If you want to use an actual HTML element instead of providing a String as a config option,
				 * you could create a div with the id `tpl`,
				 * put the template inside it and provide the element like this:
				 *
				 * previewTemplate : document.querySelector('#tpl').innerHTML
				 */
				previewTemplate : '' +
					'<div class="dz-details">' +
						'<div class="dz-filename">' +
							'<span data-dz-name=""></span>' +
						'</div>' +
						'<div class="dz-size" data-dz-size>' +
							'<img data-dz-thumbnail="" alt="">' +
						'</div>' +
						'<div class="dz-progress">' +
							'<span class="dz-upload" data-dz-uploadprogress=""></span>' +
						'</div>' +
						'<div class="dz-success-mark">' +
							'<span>✔</span>' +
						'</div>' +
						'<div class="dz-error-mark">' +
							'<span>✘</span>' +
						'</div>' +
						'<div class="dz-error-message">' +
							'<span data-dz-errormessage=""></span>' +
						'</div>' +
					'</div>'
			},

			// Extract dropzone configuration from data attributes via regular expression.
			// Code borrowed with slight change from https://stackoverflow.com/q/4187032
			pattern = /^data-dropzone-param-(.+)$/;

			$.each(this.attributes, function(index, attr) {
				if (pattern.test(attr.nodeName)) {
					let key = attr.nodeName.match(pattern)[1];

					if (key.match("-")) {
						key = window.FTKAPP.functions.camelize( key.replace("-", " ") );
					}

					config[key] = attr.nodeValue;
				}
			});
			// End

			// Get new Dropzone instance.
			dropzone = new Dropzone("#" + $(this).attr("id"), config);

			// Attach event handlers.
			dropzone
			// The next 6 events receive the "Event" as first function parameter.
			.on("_dragenter", function(evt) {	// The user dragged a file onto the Dropzone
				// console.warn("handling dragenter");
			})
			.on("_dragover",  function(evt) {	// The user is dragging a file over the Dropzone
				// console.warn("handling dragover");
			})
			.on("_dragleave", function(evt) {	// The user dragged a file out of the Dropzone
				// console.warn("handling dragleave");
			})
			.on("_dragstart", function(evt) {	// The user started to drag anywhere
				// console.warn("hadling dragstart");
			})
			.on("_dragend",   function(evt) {	// The user stopped to dragging
				// console.warn("handling dragend");
			})
			.on("_drop",      function(evt) {	// The user dropped something onto the dropzone
				// console.warn("handling drop");

				// Maybe display some more file information on your page
			})
			// The next 6 events receive the "File" as first function parameter.
			.on("addedfile",        function(file) {	// A file is added to the list
				// console.warn("handling addedfile");

				// Example code from docs.
				// file.previewElement = Dropzone.createElement(dropzone.options.previewTemplate);
				// Now attach this new element somewhere in your page
			})
			.on("_processing",       function(file) {	// A file gets processed (since there is a queue not all files are processed immediately). This event was called processingfile previously.
				// console.warn("handling processing");
			})
			.on("_sending",          function(file, xhr, formData) {	// Called just before each file is sent. Gets the xhr object and the formData objects as second and third parameters, so you can modify them (for example to add a CSRF token) or add additional data.
				// console.warn("handling sending");
			})
			.on("_uploadprogress",   function(file, progress, bytesSent) {	// Gets called periodically whenever the file upload progress changes. Gets the progress parameter as second parameter which is a percentage (0-100) and the bytesSent parameter as third which is the number of the bytes that have been sent to the server. When an upload finishes dropzone ensures that uploadprogress will be called with a percentage of 100 at least once.
				// console.warn("handling uploadprogress");

				// Display the progress
			})
			.on("error",            function(file/*, errorMessage, xhr*/) {	// An error occurred. Receives the errorMessage as second parameter and if the error was due to the XMLHttpRequest the xhr object as third.
				// console.warn("handling error");

				dropzone.cancelUpload(file);
				// dropzone.disable();
			})
			.on("_success",          function(file) {	// The file has been uploaded successfully. Gets the server response as second argument. (This event was called finished previously)
				// console.warn("handling success");
			})
			.on("_thumbnail",        function(file, dataUrl) {	// When the thumbnail has been generated. Receives the dataUrl as second parameter.
				// console.warn("handling thumbnail");

				// Display the image in your file.previewElement
			})
			.on("_maxfilesreached",  function(file) {	// The number of files accepted reaches the maxFiles limit.
				// console.warn("handling maxfilesreached");
			})
			.on("maxfilesexceeded", function(file) {	// The number of files accepted reaches the maxFiles limit.
				// console.warn("handling maxfilesexceeded");

				// Display message.
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_ERROR_DROPZONE_MAX_FILES_EXCEEDED_EXTENDED_TEXT"]
				});
			})
			.on("_canceled",         function(file) {	// A file upload gets canceled.
				// console.warn("handling canceled");
			})
			.on("_removedfile",      function(file) {	// A file is removed from the list. You can listen to this and delete the file from your server if you want to.
				// console.warn("handling removedfile");
			})
			.on("complete",         function(file) {	// The upload was either successful or erroneous.
				// console.warn("handling complete");

				if (file.accepted && file.upload.progress === 100 && file.status === "success") {
					window.FTKAPP.functions.renderMessage({
						type: "success",
						text: window.FTKAPP.translator.map["COM_FTK_HINT_DONT_FORGET_TO_SAVE_CHANGES_TEXT"]
					});
				}

				if (file.previewElement) {
					$(file.previewElement)
					.append(
						$("<button/>", {
							"type"  :  "button",
							"class" : "btn btn-sm btn-danger mt-2 px-3"
						})
						.append(
							$("<span/>", {
								"title"       : window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_DROPZONE_FILE_DELETE_THIS_TEXT"],
								"aria-label"  : window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_DROPZONE_FILE_DELETE_THIS_TEXT"],
								"data-toggle" : "tooltip",
								"html"        : '<i class="far fa-trash-alt"></i>'
							})
						)
					)
					.on("click", function() {
						$(this).find('[data-toggle="tooltip"]').tooltip("hide");

						dropzone.removeFile(file);
					})
					.find('[data-toggle="tooltip"]')
						.tooltip(
							$.extend(
								{
									container : "body",
									boundary  : "window"
								},
								({delay: $(this).data("delay")})
							)
						);
				}
			})
			// Special events
			.on("queuecomplete",    function(evt) {	// All files in the queue finish uploading.
				// console.log("this:", this);
			})
			.on("reset",            function(evt) {	// All files in the list are removed and the dropzone is reset to initial state.
				// console.warn("handling reset");
			});
		});
		}

		// Snippet to show/hide "scrollTop" button
		$(window)
		.scroll(function() {
			if ($(this).scrollTop() > 200) {
				$('#scrollTopBtn').stop().fadeIn()
			} else {
				$('#scrollTopBtn').stop().fadeOut()
			}
		});

		$('#scrollTopBtn')
		.on("click", function(evt) {
			$('html, body').animate({scrollTop: 0}, 250);

			return !1;
		});

		// BOOTSTRAP Alerts.
		$('[role="alert"]')
		.on("close.bs.alert",  function(evt) {
			// Since our HTML markup for the system messages container is differently constructed (close-button does not reference elements having class 'alert')
			// we are forced to use this workaround to listen and handle TWBS-alert events.
			$(this).trigger("closed.bs.alert");
		})
		.on("closed.bs.alert", function(evt) {
			// This handler is not listend to and handled as originally intended, thus its related event is triggered within the event chain piece before.
			$(this).closest(".overlay").fadeOut(function() {
				$(this).remove();
			});
		});

		// Don't forget the hidden Bootstrap Alerts
		$(".alert-memorizable")
		.on("close.bs.alert",  function(evt) {});


		// BOOTSTRAP Collapsibles.
		$(".collapse")
		.on("show.bs.collapse",   function(evt) {})
		.on("shown.bs.collapse",  function(evt) {
			$(this)
			.parent(".card")
			.find(".fa-caret-right")
				.addClass("fa-caret-down")
				.removeClass("fa-caret-right");
		})
		.on("hide.bs.collapse",   function(evt) {})
		.on("hidden.bs.collapse", function(evt) {
			$(this)
			.parent(".card")
			.find(".fa-caret-down")
				.addClass("fa-caret-right")
				.removeClass("fa-caret-down");
		})
		.collapse({
			toggle: false
		});


		/*$("a.dropdown-toggle")
		.on("click", function(e) {
			$this.toggleClass("show");

			return false;
		});*/


		// BOOTSTRAP Dropdowns.
		$(".dropdown-menu a.dropdown-toggle")
		.on("click", function(evt) {
			let $this = $(this),
				$subMenu = $this.next(".dropdown-menu");

			if (!$this.next().hasClass("show")) {
				$this
				.parents(".dropdown-menu")
					.first()
					.find(".show")
						.removeClass("show");
			}

			$subMenu.toggleClass("show");

			$this
			.parents("li.nav-item.dropdown.show")
			.on("hidden.bs.dropdown", function(evt) {
				$(".dropdown-submenu .show")
				.removeClass("show");
			});

			if ($this.attr("aria-expanded") == "false") {
				$this.attr({"aria-expanded" : "true"});
			} else {
				$this.attr({"aria-expanded" : "false"});
			}

			return false;
		});

		$(".dropdown-menu")
		.on("change", ":checkbox", function(evt) {
			let $this = $(this),
				$parent = $this.parents('.dropdown-menu');

			if (!$parent.data("multiple")) {
				$parent.find(':checkbox[name="' + $this.attr("name") + '"]').not($this).attr("checked", "").prop("checked", false);
			}
		});


		// BOOTSTRAP Popovers are opt-in for performance reasons, so it must be initialized separately.
		$('[data-toggle="popover"]')
		.on("show.bs.popover",     function(evt) {
			let $this = $(this);

			// Automatically hide popover.
			if ($this.is(".autohide")) {
				setTimeout(function autohidePopover() {
					if ($this.attr("aria-describedby") !== undefined) {
						$this.click();
					}
				}, $this.data("timeout") || 15000);
			}

			// Bind to click event allowing to close the widget when clicking on it.
			/*$this.data("bs.popover").tip()
			.addClass("dismissable")
			.attr({
				title: window.FTKAPP.translator.map["COM_FTK_BUTTON_CLOSE_TEXT"]
			})
			.on("click", function(evt) {
				$this.click();
			});*/
		})
		// .on("shown.bs.popover",    function(evt) {})
		// .on("inserted.bs.popover", function(evt) {})
		// .on("hide.bs.popover",     function(evt) {})
		// .on("hidden.bs.popover",   function(evt) {})
		.popover();

		// BOOTSTRAP Tabs are opt-in for performance reasons, so it must be initialized separately.
		$('.nav-tabs')
		.each(function() {
			let $this = $(this),
				  url = document.location.toString();

			// Auto-activate tab pane that if it is part of the window location string.
			// Code borrowed from https://stackoverflow.com/a/9393768
			if (url.match("#")) {
				$this
				.find('a[href="#' + url.split('#')[1] + '"]')
					.tab("show")
					.parent(".nav-item").css("z-index", "1");
			} else {
				$this
				.children(":first-child")
				.css("z-index", "1");
			}
		});

		$('[data-toggle="tab"]')
		.on("show.bs.tab",   function(evt) {		// on the to-be-shown tab
			// evt.preventDefault();

			// console.log("evtTarget:", evt.target);			// the active tab
			// console.log("relTarget:", evt.relatedTarget);	// the previous active tab (if available)

			$(this).parent(".nav-item").css("z-index", "1");
		})
		.on("shown.bs.tab",  function(evt) {		// on the newly-active just-shown tab, the same one as for the show.bs.tab event
			// evt.preventDefault();

			// console.log("evtTarget:", evt.target);			// the active tab
			// console.log("relTarget:", evt.relatedTarget);	// the previous active tab (if available)

			// Set/change hash in browser address bar
			document.location.hash = evt.target.hash;

			// Set/change href attribute of every link in top right language-toggler widget.
			$(".language-toggler .language-toggle").attr("href", function() {
				let $this = $(this),
					 href = $this.attr("href").split("#")[0].trim();

				return href + evt.target.hash;
			});

			// Set/change hash in hidden form fields that return URI fragment
			$(':input[type="hidden"][name="fragment"]').val( evt.target.hash.replace("#","").trim() );
		})
		.on("hide.bs.tab",   function(evt) {		// on the current active tab
			// evt.preventDefault();

			// console.log("evtTarget:", evt.target);			// the current active tab
			// console.log("relTarget:", evt.relatedTarget);	// the new soon-to-be-active tab

			$(this).parent(".nav-item").css("z-index", "");
		})
		.on("hidden.bs.tab", function(evt) {		// on the previous active tab, the same one as for the hide.bs.tab event
			// evt.preventDefault();

			// console.log("evtTarget:", evt.target);			// the previous active tab
			// console.log("relTarget:", evt.relatedTarget);	// the new active tab
		});

		// BOOTSTRAP Tooltips are opt-in for performance reasons, so it must be initialized separately.
		$('[data-toggle="tooltip"]')
		// .on("show.bs.tooltip",     function(evt) {})
		// .on("shown.bs.tooltip",    function(evt) {})
		// .on("inserted.bs.tooltip", function(evt) {})
		// .on("hide.bs.tooltip",     function(evt) {})
		// .on("hidden.bs.tooltip",   function(evt) {})
		.tooltip(
			$.extend(
				{
					container : "body",
					boundary  : "window"
				},
				(true
					? {delay : $(this).data("delay")}
					: {}
				)
			)
		);


		// Twitter Bootstrap Colorpicker (3rd party plugin) event handler
		$(".bs-colorpicker")
		.colorpicker()
		.on("colorpickerChange",  function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") );

			if ($this.is(".bs-colorpicker-background")) {
				$target.css("background-color", (evt.color === null ? "#FFF" : evt.color.toString()));
			}

			if ($this.is(".bs-colorpicker-font")) {
				$target.css("color", (evt.color === null ? "inherit" : evt.color.toString()));
			}
		});

		// Twitter Bootstrap Tab proxy
		$(".bs-tab-proxy")
		.on("click", function(evt) {
			evt.preventDefault();

			let $this = $(this),
				targetID = $this.data("target");

			try {
				switch (true) {
					case $this.is(".bs-tab-proxy-prev") :
						$(targetID + " li a.active").parent("li").prev("li").find("> a").trigger("click");
					break;

					case $this.is(".bs-tab-proxy-next") :
						$(targetID + " li a.active").parent("li").next("li").find("> a").trigger("click");
					break;
				}
			} catch (err) {
				console.error("The following error occured:", err)
			}
		});


		/* Twitter Bootstrap Datepicker (3rd party plugin) event handler
		 *
		 * I M P O R T A N T   N O T E S :
		 *
		 *   This widget binds to the container that wraps the actual <input/> element.
		 *
		 * see: https://bootstrap-datepicker.readthedocs.io/en/latest/options.html#daysofweekdisabled
		 * see: https://bootstrap-datepicker.readthedocs.io/en/1.3.0-rc.4/options.html
		 */
		$('.bootstrap-datepicker')
		.datepicker({
			// "inputs": $(".datepicker")	// This option will trigger the widget to display on input focused ... disabled on 2022-06-11 to force widget to display on addon button click to sync with timepicker behaviour
		})
		/*.on("changeMonth",        function(evt) {})
		.on("changeYear",         function(evt) {})
		.on("changeDecade",       function(evt) {})
		.on("changeCentury",      function(evt) {})
		.on("clearDate",          function(evt) {})
		.on("show.bs.datepicker", function(evt) {})*/
		.on("keydown", 'input.datepicker', function(evt) {
			switch (evt.keyCode) {
				// Pass these key-events through to the datepicker key-events handler.
				case 38 :	// arrow UP
				case 39 :	// arrow RIGHT
				case 40 :	// arrow DOWN
				case 37 :	// arrow LEFT
				case 27 :	// ESC
				case  9 :	// TAB
				// case 13 :	// RETURN	... this event is handled by the datepicker and it is therefore impossible to catch its keyCode, which makes custom handling impossible
				break;

				// Deny user input.
				default :
					evt.preventDefault();
			}
		})
		.on("changeDate",         function(evt) {
			let $this = $(this);

			$this						// 'this' refers to the parent container element holding the config params (usually identified via 'data-provide="datepicker"' attribute)
			.addClass("dateChanged")
			.find( $( $this.data("target") ) )
				.val("match")	//FIXME - does not work
				.end()
			.closest("form")
				.find("table.clearable > tbody > tr")	// SM requested to clear the table content after date(s) changed to give the user a visual feedback
					.fadeOut();
		})
		.on("hide.bs.datepicker", function(evt) {
			/*let $this  = $(this),	// 'this' refers to the parent container element holding the config params (usually identified via 'data-provide="datepicker"' attribute)
				$input = $this.find(":input[type]").blur(),	// find input field and take focus off it
				$form;

			if ($this.is(".dateChanged") && $this.is(".auto-submit")) {
				$form = $this.closest("form").addClass("submitted");	// Disabled on 2021-07-22 - consolidation of where this class is added ... there were too many places. Class is now added in $("form.form").on("submit")
			}*/
		});

		// To init a range picker pass in the involved input elements
		$('.bootstrap-datepicker.date-range')
		.datepicker({ "inputs": $(".rangepicker") });


		/* Twitter Bootstrap Timepicker (3rd party plugin) event handler
		 *
		 * I M P O R T A N T   N O T E S :
		 *
		 *   This widget binds to the actual <input/> element rather than to its container.
		 *
		 *   While the bootstrap-datepicker plugin requires to be initialised as in line(s) 1482 and 1524,
		 *   this plugin is self-initializing when the target element has the following data-attribute: data-provide="timepicker" .
		 *   This data-attribute is also added to every desired datepicker element. However, it does not trigger self-initialization.
		 *
		 * see: https://jdewit.github.io/bootstrap-timepicker
		 */
		$('.bootstrap-timepicker input')
		// Enabled this function if custom configuration that overrides plugin parameters is necessary.
		// I M P O R T A N T :   This solution prevents the widget from appearing on input focus. The user must click the widget symbol instead.
		.queue(function(next) {
			let $this = $(this), options = {
				"appendWidgetTo"    : $this.closest(".bootstrap-timepicker").data("appendWidgetTo")       || "body",
				// "defaultTime"       : $this.closest(".bootstrap-timepicker").data("defaultTime")       || false,		// one of 'false', e.g. '11:45 AM', 'current' (default)
				// "disableFocus"      : $this.closest(".bootstrap-timepicker").data("disableFocus")      || false,		// false or true
				// "disableMousewheel" : $this.closest(".bootstrap-timepicker").data("disableMousewheel") || false,		// false or true
				// "explicitMode"      : $this.closest(".bootstrap-timepicker").data("explicitMode")      || false,		// true or false (default)
				// "maxHours"          : $this.closest(".bootstrap-timepicker").data("maxHours")          || 24,		// 24 (default)
				"minuteStep"        : $this.closest(".bootstrap-timepicker").data("minuteStep")        || 15,		// numeric value
				// "secondStep"        : $this.closest(".bootstrap-timepicker").data("secondStep")        || 15,		// numeric value
				// "showInputs"        : $this.closest(".bootstrap-timepicker").data("showInputs")        || false,		// true or false (default) ... when set to true the time is editable via <input/>s, otherwise it is just displayed in <span/>s
				"showMeridian"      : $this.closest(".bootstrap-timepicker").data("showMeridian")      || false,		// false or true (default) ... when set to true a time of '17:45' will appear as '5:45 PM'
				// "showSeconds"       : $this.closest(".bootstrap-timepicker").data("showSeconds")       || false,		// true or false (default)
				// "snapToStep"        : $this.closest(".bootstrap-timepicker").data("snapToStep")        || false,		// false or true
				// "template"          : $this.closest(".bootstrap-timepicker").data("template")          || "modal",	// one of 'false', 'modal', 'dropdown' (default) --- IMPORTANT: 'modal' does NOT work !!!
				// "modalBackdrop"     : $this.closest(".bootstrap-timepicker").data("modalBackdrop")     || false,		// true or false (default)
				"foo"               : "bar"
			};

			$this.timepicker(options);

			next();
		})
		// Disabled this event handler, if above function is enabled to prevent the widget from appearing on input element focused.
		// .on("focusin", function(evt) { evt.preventDefault(); return false; })
		/*.queue(function(next) {
			let $this = $(this),
				 inst = $this.data("timepicker");

			console.log("inst:", inst);

			next();
		})*/
		.on('show.timepicker', function(evt) {
			$(".bootstrap-timepicker-widget").find("input[type]").first().focus();	// Does not work!!!
		})
		/*.on('changeTime.timepicker', function(evt) {
			console.warn("changeTime.timepicker event handler");
		})
		.on('hide.timepicker', function(evt) {
			console.warn("hide.timepicker event handler");
		})*/;


		// Bind list filter to dropdown list via jQuery Chosen plugin.
		// Complete list of available config options is available here: https://harvesthq.github.io/chosen/options.html
		$("select.filterable")
		/* Triggered after Chosen has been fully instantiated.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("chosen:ready", function(evt, params) {
			// console.warn("select.chsn > chosen:ready event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);
			// console.log("this.data:", $(this).data());
		})
		/* Triggered when Chosen’s dropdown is opened.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("chosen:showing_dropdown", function(evt, params) {
			// console.warn("select.chsn > chosen:showing_dropdown event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);
			// console.log("this.data:", $(this).data());
		})
		/* Triggered when Chosen’s dropdown is closed.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("chosen:hiding_dropdown", function(evt, params) {
			// console.warn("select.chsn > chosen:hiding_dropdown event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);
			// console.log("this.data:", $(this).data());
		})
		/* Chosen triggers the standard DOM event whenever a selection is made
		 * (it also sends a selected or deselected parameter that tells you which option was changed).
		 *
		 * N O T E :   The selected and deselected parameters are not available for Prototype.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("change", function(evt, params) {
			// console.warn("select.chsn > change event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);
			// console.log("this.data:", $(this).data());
		})
		/* Triggered when a search returns no matching results.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("chosen:no_results", function(evt, params) {
			// console.warn("select.chsn > no_results:ready event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);

		})
		/* Triggered if max_selected_options is set and that total is broken.
		 *
		 * @param  {Event}  evt     The triggered event
		 * @param  {object} params  Processing parameters object containing the chosen configuration data and the form field and some more information
		 *
		 * @see  https://harvesthq.github.io/chosen/options.html#triggered-events
		 */
		.on("chosen:maxselected", function(evt, params) {
			// console.warn("select.chsn > chosen:maxselected event params");
			// console.log("args:", evt, params);
			// console.log("this:", this);
			// console.log("this.data:", $(this).data());
		})
		.queue(function(next) {
			let $this = $(this);

			/* Read plugin configuration parameters from the element's data-attributes if there are any.
			 * If no data-attribute is found for a specific parameter, fall back to the plugin's default value.
			 * see plugin documentation on vendor's website: https://harvesthq.github.io/chosen/options.html#options
			 */
			const config = {
				allow_single_deselect: $this.data("chosenAllowSingleDeselect") || false,							// When set to true on a single select, Chosen adds a UI element which selects the first element (if it is blank).
				case_sensitive_search: $this.data("chosenCaseSensitiveSearch") || false,							// By default, Chosen's search is case-insensitive. When set to true the search case-sensitive.
				disable_search: $this.data("chosenDisableSearch") || false,											// When set to true, Chosen will not display the search field (single selects only).
				disable_search_threshold: $this.data("chosenDisableSearchThreshold") || 0,							// Hide the search input on single selects if there are n or fewer options.
																													// Means, no text input field is displaying if the list contains n or fewer options
				display_disabled_options: $this.data("chosenDisplayDisabledOptions") != null ? $this.data("chosenDisplayDisabledOptions") : true,	// By default, Chosen includes disabled options in search results with a special styling. When set to false disabled results are hidden + excluded them from searches.
				display_selected_options: $this.data("chosenDisplaySelectedOptions") != null ? $this.data("chosenDisplaySelectedOptions") : true,	// By default, Chosen includes selected options in search results with a special styling. When set to false selected results are hidden + excluded them from searches.
																													// N O T E :   This is for multiple selects only. In single selects, the selected result will always be displayed.
				enable_split_word_search: $this.data("chosenEnableSplitWordSearch")  != null ? $this.data("chosenEnableSplitWordSearch")  : true,	// By default, searching will match on any word within an option tag. When set to false it's matched only on the entire text of an option tag.
				group_search: $this.data("chosenGroupSearch") != null ? $this.data("chosenGroupSearch") : true,		// By default, Chosen will search group labels as well as options, and filter to show all options below matching groups. When set to false it's searched only in the options.
				hide_results_on_select: $this.data("chosenHideResultsOnSelect") || true,							// By default, Chosen's results are hidden after a option is selected. When set to false the results list is kept open after selection.
																													// N O T E :   This is for multiple selects only.
				include_group_label_in_selected: $this.data("chosenIncludeGroupLabelInSelected") || false,			// By default, Chosen only shows the text of a selected option. When set to true the text and group (if any) of the selected option will be shown.
				inherit_select_classes: $this.data("chosenInheritSelectClasses") || false,							// When set to true, Chosen will grab any classes on the original select field and add them to Chosen’s container div.
				// FIXME - evaluate the 'multiple="?"' attribute ot the select element + search for a 'max(imum)' attribute and set this as the option value
				max_selected_options: $this.data("chosenMaxSelectedOptions") || Infinity,							// Limits how many options the user can select. When the limit is reached, the chosen:maxselected event is triggered.
				max_shown_results: $this.data("chosenMaxShownResults") || Infinity,									// Only show the first (n) matching options in the results. This can be used to increase performance for selects with very many options.
				no_results_text: $this.data("chosenNoResultsText") || window.FTKAPP.translator.map["COM_FTK_MSG_CHOSEN_NO_RESULT_MATCHES_TEXT"],	// TODO - translate	// The text to be displayed when no matching results are found. The current search is shown at the end of the text (e.g., No results match "Bad Search").
				placeholder_text_multiple: $this.data("chosenPlaceholderTextMultiple") || "Select Some Options",	// TODO - translate	// The text to be displayed as a placeholder when no options are selected.
				placeholder_text_single: $this.data("chosenPlaceholderTextSingle") || "Select an Option",			// TODO - translate	// The text to be displayed as a placeholder when no options are selected for a single select.
																													// N O T E :   This is for multiple selects only.
				rtl: $this.data("chosenRtl") || false,																// Chosen supports right-to-left text in select boxes. Set this option to true to support right-to-left text options.
																													// N O T E :   The chosen-rtl class on the select has precedence over this option.
																													//			   However, the classname approach is deprecated and will be removed in future versions of Chosen.
				search_contains: $this.data("chosenSearchContains") || false,										// By default, Chosen’s search matches starting at the beginning of a word.
																													// When set to true matches start from anywhere within a word.
																													// This is especially useful for options that include a lot of special characters or phrases in ()s and []s.
				single_backstroke_delete: $this.data("chosenSingleBackstrokeDelete") != null ? $this.data("chosenSingleBackstrokeDelete") : true,	// By default, pressing delete/backspace on multiple selects will remove a selected choice.
																													// When set to false, pressing delete/backspace will highlight the last choice, and a second press deselects it.
			};

			// Attach functionality with parsed config.
			$this.chosen(config);

			next();
		});


		// Bind maxlength indication functionality to elements.
		$("textarea[maxlength], .maxlength")
		.maxlength(window.FTKAPP.constants.maxlengthConfig);


		// JQUERY UI drop-in(s)
		if (false && typeof $.ui === "object") {
			// Resize autocomplete datalist to related input element's with
			$.ui.autocomplete.prototype._resizeMenu = function() {
				let ul = this.menu.element;

				ul.outerWidth( this.element.outerWidth() );
			};

			// Autocomplete handlers
			$("#articlesList")
			/* pre-load articles list */
			.queue(function(next) {
				let $this  = $(this),
				stateClass = "loading",
				 loadWhat  = $this.data("load") || undefined,
				   action  = $this.data("action"),
					ajax   = {
						type: "GET",
						url:   action,
						data:  "format=json",
						cache: false
					};
				 loadWhat  = (typeof loadWhat !== "undefined" ? loadWhat + "List" : loadWhat);

				$this.addClass(stateClass);

				$.ajax(ajax)	// Fetch articlesList
				/* The request failed.
				 */
				.fail(function(jqXHR, statusText, errorThrown) {
					try {
						//TODO - implement error handler
					} catch (err) {
						console.error("The following error occured:", err)
					}
				})
				/* The request was successful.
				 */
				.done(function(response, statusText, jqXHR) {
					let resParsed = JSON.parse(response),
							 list = $.makeArray( Object.keys(resParsed) ),
						 isObject = $.isPlainObject(list),
						  isArray = $.isArray(list);

					$this.autocomplete("option", "source", list);
				})
				/* The request has completed.
				 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
				 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
				 */
				.always(function(response, statusText, jqXHR) {});

				next();
			})
			.autocomplete({
				context: this,
				autoFocus: true,
				minLength: 3,
				delay: 100,
				position: {
					"my": "left top",
					"at": "left bottom"
				},
				classes: {
					"ui-autocomplete": "list-unstyled autocomplete-items autocomplete-list autocomplete-datalist"
				},
		/* 1 */	search:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("search-event");
					// console.log("ui:", ui);
					// console.log("this:", this);
					// console.log("this.data:", $(this).data());
					// console.log("this.data:", $(this).data("uiAutocomplete"));
					// document.location.href = ui.item.url;
				},
		/* 2 */	response: function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("response-event");
					// console.log("ui:", ui);
					// document.location.href = ui.item.url;
				},
		/* 3 */	focus:    function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("focus-event");
					// evt.preventDefault(); // without this: keyboard movements reset the input to '' - INFO taken from https://yuji.wordpress.com/2011/09/22/jquery-ui-autocomplete-focus-event-via-keyboard-clears-input-content/
					// console.log("ui.item:", ui.item);
					// $(this).val(ui.item.question);
				},
		/* 4 */	open:     function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("open-event");
					// document.location.href = ui.item.url;
					// console.log("this:", this);
					// console.log("event:", evt);
					// console.log("ui:", ui);
				},
		/* 5 */	select:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("select-event");
					// document.location.href = ui.item.url;
					// console.log("ui.item:", ui.item);

					// if (!confirm("Soll das Formular abgesendet werden? 3")) return false;
				},
		/* 6 */	close:    function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("close-event");
					// console.log("evt:", evt);
					// console.log("evt.target:", evt.target);
					// console.log("ui:", ui);
					// document.location.href = ui.item.url;

					// console.log("ui.item:", ui.item);

					let $this = $(this),
						$form = $this.closest("form"),
						$target = $("#articles-list"),
						$listItems = $target.find("> .list-item"),
						$link = undefined,
						val = typeof ui.item !== "undefined" ? ui.item.value : $(evt.target).val();
						val = val.trim();

					// console.log("selected:", val);
					// console.log("$listItems:", $listItems);

					if (val == "") {
						// console.warn("  SUCHFELD IST LEER!");

						$form.removeClass("hasKeyword");

						$listItems.show();

					} else {
						// console.warn("  SUCHFELD HAT WERT:", val);

						$form.addClass("hasKeyword");

						$listItems.each(function() {
							let $el = $(this);

							if ($el.data("rel").toLowerCase() !== val.toLowerCase()) {
								$el.hide();
							} else {
								$link = $el.find("> a:first-child");

								// console.log("first child", $link);
								// console.log("navigate to part", $link.attr("href"));

								// document.location.url = $el.find("> a:first-child").attr("href");
								// return;
							}
						});

					}

					// console.log("click:", $link);
					// console.log("window.location:", window.location);
					// $link.click();

					// if (!confirm("Soll das Formular abgesendet werden? 1")) return false;

					evt.preventDefault();

				},
		/* 7 */	change:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("change-event");
					// document.location.href = ui.item.url;
					// console.log("ui.item:", ui.item);

					// if (!confirm("Soll das Formular abgesendet werden? 2")) return false;
				}
			});

			$("#partsList")
			/* pre-load parts list */
			.queue(function(next) {
				let $this  = $(this),
				stateClass = "loading",
				 loadWhat  = $this.data("load") || undefined,
				   action  = $this.data("action"),
					ajax   = {
						type: "GET",
						url:   action,
						data:  "format=json",
						cache: false
					};
				 loadWhat  = (typeof loadWhat !== "undefined" ? loadWhat + "List" : loadWhat);

				$this.addClass(stateClass);

				$.ajax(ajax)	// Fetch partsList
				/* The request failed.
				 */
				.fail(function(jqXHR, statusText, errorThrown) {
					try {
						//TODO - implement error handler
					} catch (err) {
						console.error("The following error occured:", err)
					}
				})
				/* The request was successful.
				 */
				.done(function(response, statusText, jqXHR) {
					let resParsed = JSON.parse(response),
							 list = $.makeArray( Object.keys(resParsed) ),
						 isObject = $.isPlainObject(list),
						  isArray = $.isArray(list);

					$this.autocomplete("option", "source", list);
				})
				/* The request has completed.
				 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
				 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
				 */
				.always(function(response, statusText, jqXHR) {});

				next();
			})
			.autocomplete({
				context: this,
				autoFocus: true,
				minLength: 1,
				delay: 100,
				position: {
					"my": "left top",
					"at": "left bottom"
				},
				classes: {
					"ui-autocomplete": "list-unstyled autocomplete-items autocomplete-list autocomplete-datalist"
				},
		/* 1 */	search:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("search-event");
					// console.log("ui:", ui);
					// console.log("this:", this);
					// console.log("this.data:", $(this).data());
					// console.log("this.data:", $(this).data("uiAutocomplete"));
					// document.location.href = ui.item.url;
				},
		/* 2 */	response: function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("response-event");
					// console.log("ui:", ui);
					// document.location.href = ui.item.url;
				},
		/* 3 */	focus:    function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("focus-event");
					// evt.preventDefault(); // without this: keyboard movements reset the input to '' - INFO taken from https://yuji.wordpress.com/2011/09/22/jquery-ui-autocomplete-focus-event-via-keyboard-clears-input-content/
					// console.log("ui.item:", ui.item);
					// $(this).val(ui.item.question);
				},
		/* 4 */	open:     function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("open-event");
					// document.location.href = ui.item.url;
					// console.log("this:", this);
					// console.log("event:", evt);
					// console.log("ui:", ui);
				},
		/* 5 */	select:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("select-event");
					// document.location.href = ui.item.url;
					// console.log("ui.item:", ui.item);

					// if (!confirm("Soll das Formular abgesendet werden? 3")) return false;
				},
		/* 6 */	close:    function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("close-event");
					// console.log("evt:", evt);
					// console.log("evt.target:", evt.target);
					// console.log("ui:", ui);
					// document.location.href = ui.item.url;

					// console.log("ui.item:", ui.item);

					let $this = $(this),
						$form = $this.closest("form"),
						$target = $("#parts-list"),
						$listItems = $target.find("> .list-item"),
						$link = undefined,
						val = typeof ui.item !== "undefined" ? ui.item.value : $(evt.target).val();
						val = val.trim();

					// console.log("selected:", val);
					// console.log("$listItems:", $listItems);

					if (val == "") {
						// console.warn("  SUCHFELD IST LEER!");

						$form.removeClass("hasKeyword");

						$listItems.show();
					} else {
						// console.warn("  SUCHFELD HAT WERT:", val);

						$form.addClass("hasKeyword");

						$listItems.each(function() {
							let $el = $(this);

							if ($el.data("rel").toLowerCase() !== val.toLowerCase()) {
								$el.hide();
							} else {
								$link = $el.find("> a:first-child");

								// console.log("first child", $link);
								// console.log("navigate to part", $link.attr("href"));

								// document.location.url = $el.find("> a:first-child").attr("href");
								// return;
							}
						});

					}

					// console.log("click:", $link);
					// console.log("window.location:", window.location);
					// $link.click();

					// if (!confirm("Soll das Formular abgesendet werden? 1")) return false;

					evt.preventDefault();
				},
		/* 7 */	change:   function(evt, ui) {
					// console.log("------------------------------------------");
					// console.warn("change-event");
					// document.location.href = ui.item.url;
					// console.log("ui.item:", ui.item);

					// if (!confirm("Soll das Formular abgesendet werden? 2")) return false;
				}
			});
		}

		// Hide autocomplete status messages
		$(".timeline .form-control")
		.on("keydown", function(evt) {
			if (evt.keyCode == 13) {
				// Do not respond to return key after input forcing the users to navigate via TAB and save/exit via button
				evt.preventDefault();
			}
		});

		var urlParams = new URLSearchParams(window.location.search); //get all parameters
		var foo = urlParams.get('filter');
		if(foo ==111){
			//alert('jek');
			$("table td[class='orgdat']").html("NEMATECH Kft.");
			/*$("table tr[id='NEMATECH Kft.']").css("visibility","visible");
			$("table tr[id='FRÖTEK-Kunststofftechnik GmbH (OHA)']").css("display","none");
			$("table tr[id='FRÖTEK-Kunststofftechnik GmbH (OHA)']").css("visibility","hidden");
			$("table tr[id='NEMECTEK TOW']").css("display","none");
			$("table tr[id='NEMECTEK TOW']").css("visibility","hidden");*/
		}else if(foo ==112){
			$("table td[class='orgdat']").html("FRÖTEK-Kunststofftechnik GmbH (OHA)");
			/*$("table tr[id='NEMATECH Kft.']").css("visibility","hidden");
			$("table tr[id='NEMATECH Kft.']").css("display","none");
			$("table tr[id='NEMECTEK TOW']").css("visibility","hidden");
			$("table tr[id='NEMECTEK TOW']").css("display","none");
			$("table tr[id='FRÖTEK-Kunststofftechnik GmbH (OHA)']").css("visibility","visible");*/
		}else if(foo ==113){
			$("table td[class='orgdat']").html("NEMECTEK TOW");
			/*$("table tr[id='NEMATECH Kft.']").css("visibility","hidden");
			$("table tr[id='NEMATECH Kft.']").css("display","none");
			$("table tr[id='FRÖTEK-Kunststofftechnik GmbH (OHA)']").css("visibility","hidden");
			$("table tr[id='FRÖTEK-Kunststofftechnik GmbH (OHA)']").css("display","none");
			$("table tr[id='NEMECTEK TOW']").css("visibility","visible");*/
		}


		// Change mouse cursor icon to simulate "loading" state
		$('a[data-toggle="cursorLoading"]')
		.on("click", function(evt) {
			evt.preventDefault();

			// Change mouse cursor
			$(document.body).css("cursor","wait");

			// custom delay to allow the mouse cursor to change and notify the user
			setTimeout(function() {
				document.location.href = $(evt.currentTarget).attr("href");
			}, 500);
		});

		// Code borrowed with slight changes from https://stackoverflow.com/a/4150192
		$('[data-bind="countdown"]')
		.each(function() {
			let $this = $(this);

			let _end = new Date();
				_end.setSeconds(
					1 +	// fix for offset between initial display rendered via PHP and this script finishing to generate and inject the counter
					_end.getSeconds() +
					( parseInt($this.data("countHours"))   * 60 * 60 ) +
					( parseInt($this.data("countMinutes")) * 60 ) +
					( parseInt($this.data("countSeconds")) )
				);

			let _second = 1000;
			let _minute = _second * 60;
			let _hour   = _minute * 60;
			let _day    = _hour   * 24

			let timer;

			function showRemaining() {
				let now = new Date();

				let distance = _end - now;

				// If timer has expired, clear timer and reload window.
				if (distance > 0) {
					let days    = Math.floor(  distance / _day);
					let hours   = Math.floor( (distance % _day )   / _hour   );
					let minutes = Math.floor( (distance % _hour)   / _minute );
					let seconds = Math.floor( (distance % _minute) / _second );

					let display = [];
						display.push(hours   < 10 ? "0" + hours   : (hours   <= 0 ? 0 : hours));
						display.push(minutes < 10 ? "0" + minutes : (minutes <= 0 ? 0 : minutes));
						display.push(seconds < 10 ? "0" + seconds : (seconds <= 0 ? 0 : seconds));

					if (minutes < 1) {
						$this
						.removeClass("text-white")
						.addClass("text-gold text-bold");
					}

					$this
					.html(display.join(":"));
				} else {
					$this.html("00:00");

					clearInterval(timer);

					setTimeout(function() {
						window.location.reload();
					}, 1000);
				}
			}

			timer = setInterval(showRemaining, 1000);
		});


		// Register form event handlers
		$("form.form")
		.on("select",      function(evt) {	// Fires after some text has been selected in an element
			// console.log("---------------------------------------------------");
			// console.info("form.select event");
		})
		.on("input",       function(evt) {	// Script to be run when an element gets user input
			// console.log("---------------------------------------------------");
			// console.info("form.input event");
		})
		.on("change",      function(evt) {	// Fires the moment when the value of the element is changed
			// console.log("---------------------------------------------------");
			// console.info("form.change event");
		})
		.on("blur",        function(evt) {	// Fires the moment that the element loses focus
			// console.log("---------------------------------------------------");
			// console.info("form.blur event");
		})
		.on("contextmenu", function(evt) {	// Script to be run when a context menu is triggered
			// console.log("---------------------------------------------------");
			// console.info("form.contextmenu event");
		})
		.on("focus",       function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("form.focus event");
		})
		.on("focusin",     function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("form.focusin event");
		})
		.on("focusin",     ':input[value="n/a"]', function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("input.focusin event");
			let $element = $(evt.target);

			// Ignore disabled elements.
			if ( $element.is(".disabled") ||
				 $element.is(".readonly") ||
				($element.attr("disabled") && $element.attr("disabled") != false) ||
				($element.attr("readonly") && $element.attr("readonly") != false)) return;

			$element.val("");
		})
		.on("focusin",     'textarea', function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("input.focusin event");
			let $element = $(evt.target);

			// Ignore disabled elements.
			if ( $element.is(".disabled") ||
				 $element.is(".readonly") ||
				($element.attr("disabled") && $element.attr("disabled") != false) ||
				($element.attr("readonly") && $element.attr("readonly") != false)) return;

			if ($element.val().trim() == window.FTKAPP.translator.map["COM_FTK_NA_TEXT"]) {
				$element.val("");
			}
		})
		.on("focusout",    function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("form.focusout event");
			// console.log("---------------------------------------------------");
			// console.info("input.focusout event");

			// I M P O R T A N T :   Events "blur" and "focus" are no longer triggered

			let $element = $(evt.target);

			if ($element.get(0).type !== "file" && typeof $element.val() === "string") {
				$element.val( $element.val().trim() );
			}
		})
		.on("focusout",    ':input[value="n/a"]', function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("input.focusout event");

			let $element = $(evt.target),
					 val = $element.val().trim();

			// Ignore disabled elements.
			if ( $element.is(".disabled") ||
				 $element.is(".readonly") ||
				($element.attr("disabled") && $element.attr("disabled") != false) ||
				($element.attr("readonly") && $element.attr("readonly") != false)) return;

			$element.val(val == "" ? window.FTKAPP.translator.map["COM_FTK_NA_TEXT"] : val);
		})
		.on("focusout",    'textarea', function(evt) {	// Fires the moment when the element gets focus
			// console.log("---------------------------------------------------");
			// console.info("input.focusout event");
			let $element = $(evt.target),
				val = $element.val().trim();

			if (!$element.attr("required")) return;

			// Ignore disabled elements.
			if ( $element.is(".disabled") ||
				 $element.is(".readonly") ||
				($element.attr("disabled") && $element.attr("disabled") != false) ||
				($element.attr("readonly") && $element.attr("readonly") != false)) return;

			$element.val(val == "" ? window.FTKAPP.translator.map["COM_FTK_NA_TEXT"] : val);
		})
		.on("invalid",     function(evt) {	// Script to be run when an element is invalid
			// console.log("---------------------------------------------------");
			// console.info("form.invalid event");
		})
		.on("reset",       function(evt) {	// Fires when the Reset button in a form is clicked
			// console.log("---------------------------------------------------");
			// console.info("form.reset event");

			$(this).find(":input.form-control").each(function(i, el) {
				$(el).val("");
			});
		})
		.on("search",      function(evt) {	// Fires when the user writes something in a search field (for <input="search">)
			// console.log("---------------------------------------------------");
			// console.info("form.search event");
		})
		.on("submit",      function(evt) {	// Fires when a form is submitted to validate it
			// console.log("---[ line 2791 ]----------------------------------------------");
			// console.warn("form.submit event 1: --> add stateClass");
			// console.log("submit-event:", evt);
			// return false;

			let $form = $(this), stateClass = "submitted";

			// if ($form.is(".validate") && $form.data("validator")/*  && $form.valid() */) {	// function 'valid' Checks whether the selected form is valid or whether all selected elements are valid.
																							// Disabled on 2022-08-10 , after it was discovered that this call triggers form validation a second time
																							//                          after validation initialisation below.
				$form.addClass(stateClass);
			// } else {
				// $form.removeClass(stateClass);
			// }

			// console.log("---[ Done ]---------------------------------------------------");
		});

		// Bind form validation to such tagged forms.
		//FIXME - Resolve code duplication (see: Code from ~ line 1932)
		$("form.validate")
		.queue(function(next) {
			// console.log("---[ line 2813 ]----------------------------------------------");
			// console.warn("form.validate event: --> validate form");

			if (typeof $.validator === "object" || typeof $.validator === "function") {
				let  $form = $(this),
					config = $.extend(window.FTKAPP.constants.validation || {}, {
						/*// debug: true,				// prevents form submit for debuggingl purpose
						highlight:   function(element, errorClass, validClass) {	// workaround to set different classes for the validated element and the feedback-element, borrowed from https://stackoverflow.com/a/27022404
							$(this.settings.errorElement)
							// .addClass("col-md-8 offset-3")
							.addClass("col-auto")
							// .attr({"data-toggle" : "tooltip", "data-html" : "true", "data-placement" : "right", "title" : $(this.settings.errorElement).html()})
							// .html("")
							// .tooltip("show");

							$(element)
							.addClass(this.settings.errorElementClass)
							.removeClass(errorClass);
						},
						unhighlight: function(element, errorClass, validClass) {	// workaround to set different classes for the validated element and the feedback-element, friendly borrowed from https://stackoverflow.com/a/27022404
							$(element)
							.addClass(this.settings.validClass)
							.removeClass(this.settings.errorElementClass)
							.removeClass(errorClass);
						}*/
						// errorClass:   "validation-result border-danger text-danger small",	// TWBS class(es) --- NEW: 2023-03-27 - class border-danger added + moved up to window.FTKAPP.constants definitions to make it a global definition
						// errorElement: "span",	// MOVED: 2023-03-27 - moved up to window.FTKAPP.constants definitions to make it a global definition
						// focusInvalid: true,		// MOVED: 2023-03-27 - moved up to window.FTKAPP.constants definitions to make it a global definition
						// focusCleanup: false
						// Rules for the project monitoring compositing view
						rules: {
							"pids[]": {
								required: true,
								require_from_group: [1, ".monitor-process"]
							},
							"proids[]": {
								required: true,
								require_from_group: [1, ".monitor-project"]
							}
						},
						// Messages for the project monitoring compositing view
						messages: {
							"pids[]": {
								required: window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ADD_MINIMUM_ONE_PROCESS_TEXT"],
							},
							"proids[]": {
								required: window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ADD_MINIMUM_ONE_PROJECT_TEXT"],
							}
						},
						// errorElementClass: "is-invalid",	// this is not a plugin property, but a property that is used by the methods 'highlight' and 'unhighlight', borrowed from https://stackoverflow.com/a/27022404
						/* Callback for custom code when an invalid form is submitted.
						 *
						 * @param {Object} A custom-event
						 * @param {Object} The validator object
						 */
						/*invalidHandler: function(customEvt, validator) {	// MOVED: 2023-03-27 - moved up to window.FTKAPP.constants definitions to make it a global definition
							// console.warn("validator.invalidHandler!");
							// console.log("evt:",  customEvt);
							// console.log("validator:", validator);

							let errors = validator.numberOfInvalids();

							if (errors) {
								// Remove blocking overlay from form.
								$(customEvt.target).removeClass("submitted");

								// Render message.
								window.FTKAPP.functions.renderMessage({
										type: "info",
										text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_REQUIRED_INFORMATION_IS_MISSING_TEXT"]
									},
									{autohide : false}
								);
							}
						},*/
						/* Replaces the default submit. The right place to submit a form via Ajax after it is validated.
						 * Use this handler to process something and then using the default submit.
						 * Note that "form" refers to a DOM element, this way the validation isn't triggered again.
						 *
						 * IMPORTANT:  For some reason this submit handler is able to bypass the .on("submit", "form") handler
						 *
						 * @param {Object} The form DOM element
						 * @param {Object} The submit-event
						 */
						/*submitHandler: function(form, submitEvt) {		// W A R N i N G :   conflicts with other "submit" event handlers. !!! breaks AutoTrack !!!
							// console.warn("validator.submitHandler!");
							// console.log("evt:",  submitEvt);
							// console.log("form:", form);

							// Without this explicit call a validated form won't submit.
							form.submit();
						},*/
						// validClass: "text-success",	// TWBS class
					});

				// If there's a data attribute givin a custom identifier for ignorable elements add it to the validator config.
				if ($form.data("validatorIgnore")) {
					$.extend(config, {
						ignore: $form.data("validatorIgnore")
					});
				}

				// console.log("Add validator to form", $form);

				// Attach validator to form using the configuration object validating the form initially.
				$form.validate(config);
			}

			// console.log("---[ Done ]---------------------------------------------------");

			next();
		})

		// BOOTSTRAP Popovers are opt-in for performance reasons, so it must be initialized separately.
		$(".modal")
		// This event fires immediately when the show instance method is called. If caused by a click, the clicked element is available as the relatedTarget property of the event.
		.on("show.bs.modal",   function(evt, args) {
			// console.log("show.bs.modal");

			let $this     = $(this),
				  $button = $(evt.relatedTarget),	// HTML element that triggered the modal
				     size = $button.data("size")             || "small",	// one out of small, medium, large (see: https://getbootstrap.com/docs/4.5/components/modal/#optional-sizes)
				    title = $button.data("modalTitle")       || "",
				  content = $button.data("modalContent")     || "",
			  classHeader = $button.data("modalHeaderClass"),
			   classTitle = $button.data("modalTitleClass"),
				classBody = $button.data("modalBodyClass"),
			 classContent = $button.data("modalContentClass"),
			  classFooter = $button.data("modalFooterClass"),
			isSubmittable = $button.data("modalSubmittable") || false;

			// Calculate the size class attribute.
			switch (size) {
				case "sm" :
				case "small" :
					size = "modal-sm";
				break;

				case "lg" :
				case "large" :
					size = "modal-lg";
				break;

				case "xl" :
				case "extra-large" :
					size = "modal-xl";
				break;

				case "md" :
				case "medium" :
				default :
					size = "";
				break;
			}

			// first base64_decode the content received and second properly decode special chars
			// content = /=$/i.test(content) ? window.FTKAPP.functions.utf8_decode( atob(content) ) : '&hellip;';
			content = atob(content);
			content = content == "" ? "&hellip;" : window.FTKAPP.functions.utf8_decode( content );

			$this
			.find(".modal-dialog")
				.removeClass("modal-sm")
				.removeClass("modal-lg")
				.removeClass("modal-xl")
				.addClass(size)
				.end()
			.find(".modal-header")
				.addClass(classHeader)
				.end()
			.find(".modal-title")
				.html(title.trim())
				.addClass(classTitle)
				.end()
			.find(".modal-body")
				.html(content.trim())
				.addClass(classBody)
				.end()
			.find(".modal-content")
				.addClass(classContent)
				.end()
			.find(".modal-footer")
				.addClass(classFooter);

			if (isSubmittable && !$this.find(".modal-footer .btn-submit").length) {
				$this
				.find(".modal-footer")
					.append('<button type="submit" class="btn btn-sm btn-primary btn-submit" form="' + $this.find('.modal-body > form').attr("id") + '">' +
								window.FTKAPP.translator.map["COM_FTK_BUTTON_SUBMIT_TEXT"] +
							'</button>');

				// Add click-event handler
				let $btn = $this.find(".modal-footer > .btn-submit");

				if ($btn.length) {
					$btn.on("click", function() {
						 return confirm( window.FTKAPP.translator.map["COM_FTK_DIALOG_ARE_YOU_SURE_TEXT"] + "\r\n" +
										 window.FTKAPP.translator.map["COM_FTK_HINT_THIS_ACTION_CANNOT_BE_REVERTED_TEXT"] );
					});
				}
			}
		})
		// This event is fired when the modal has been made visible to the user (will wait for CSS transitions to complete). If caused by a click, the clicked element is available as the relatedTarget property of the event.
		.on("shown.bs.modal",  function(evt) {
			// console.log("shown.bs.modal");

			let $autofocus = $(this).find("input.autofocus").filter(":first-of-type");	// Find first input field in modal dialog

			$autofocus.trigger("focus");
		})
		// This event is fired immediately when the hide instance method has been called.
		.on("hide.bs.modal",   function(evt) {
			// console.log("hide.bs.modal");

			/*let $this = $(this),
				  $button = $(evt.relatedTarget),	// HTML element that triggered the modal
				 callback = $button.data('modalHide') || undefined;

			if (callback !== "undefined" && typeof callback === "function") {
				try {
					callback.call(this);
				} catch (err) {
					console.warn("Warning:", ex);
				}
			}*/
		})
		// This event is fired when the modal has finished being hidden from the user (will wait for CSS transitions to complete).
		.on("hidden.bs.modal", function(evt) {
			// console.log("hidden.bs.modal");

			$(this)
			.find(".modal-body")
				.html("&hellip;")
				.end()
			.find(".modal-footer")
				.find(".btn").not(".btn-close")
				.remove();
		});

	}(jQuery.noConflict(), window);

	// Late Form bindings
	jQuery(window)
	.on("user.session.expired", function(evt) {
		let appLang  = location.search.match(/\bhl=([a-z]{2})\b/) || [];
		let redirect = location.href.split("/");
		redirect.pop();
		redirect.push("index.php?&view=user&task=logout&se=1&" + appLang.shift());
		redirect = redirect.join("/");

		window.location.replace(redirect);
	})
	.on("load",         function(evt) {	// Script to be run after the page finished loading
		"use strict";

		// console.log("################################################################################################################################################################");
		// console.info("Late bindings.")

		const $ = jQuery;

		// Smooth Scrolling to HTML-anchor tags - UNCOMPRESSED
		// Select all links with hashes
		$('a[href*="#"]')
		// Remove links that don't actually link to anything
		.not('[href="#"]')
		.not('[href="#0"]')
		.click(function(evt) {
			// On-page links
			if (
				location.pathname.replace(/^\//, "") == this.pathname.replace(/^\//, "") &&
				location.hostname == this.hostname
			) {
				// Figure out element to scroll to
				let target = $(this.hash);
				target = target.length ? target : $("[name=" + this.hash.slice(1) + "]");
				// Does a scroll target exist?
				if (target.length) {
					// Only prevent default if animation is actually gonna happen
					evt.preventDefault();

					$("html, body").animate({
						scrollTop: target.offset().top
					}, 1000, function() {
						// Callback after animation
						// Must change focus!
						let $target = $(target);

						// $target.focus();	// prev. working solution - drop line below and uncomment this line if focusing isn't working any longer
						$target.trigger("focus");

						if ($target.is(":focus")) { // Checking if the target was focused
							return false;
						} else {
							$target.attr("tabindex", "-1"); // Adding tabindex for elements not focusable
							// $target.focus(); // Set focus again	- // prev. working solution - drop line below and uncomment this line if focusing isn't working any longer
							$target.trigger("focus");
						};
					});
				}
			}
		});

		// Slick carousel
		if (typeof slick !== "undefined") {
			$("#slider-article.slick-slider")
			.on("init", function(evt, slick) {
				$(this)
				.find("> .slick-list")
					.addClass("modal-image")
					.end()
				// while on it also init the Chocolat lightbox plugin
				.queue(function(next) {
					$(this).addClass("chocolat-initialized").Chocolat();

					next();
				})
				.fadeIn("slow");
			})
			.slick({
				adaptiveHeight: true,
				draggable: false,
				accessibility: false,
				infinite: false,
				arrows: false
			});
		}


		// Create localStorage- oder sessionStorage object if not exists
		$('[data-require="localStorage"]')		// persistent storage valid until explicitely cleared via 'localStorage.clear()' command
		.each(function() {
			// window.FTKAPP.functions.createWebStorage( "local",   window.FTKAPP.client.ID || undefined );
			if (!window.FTKAPP.functions.supportsWebStorage("localStorage")) {
				console.warn("Element", this.name || (this.id || this.className), "requires localStorage support, but your browser does not support it. Functionality issues may occur.");
			}
		});
		$('[data-require="sessionStorage"]')	// temp storage valid only for currently open browser tab
		.each(function() {
			// window.FTKAPP.functions.createWebStorage( "session", window.FTKAPP.client.ID || undefined );
			if (!window.FTKAPP.functions.supportsWebStorage("sessionStorage")) {
				console.warn("Element", this.name || (this.id || this.className), "requires sessionStorage support, but your browser does not support it. Functionality issues may occur.");
			}
		});


		// Replace HTML content with content provided via data-attribute
		$('.dynamic-content [data-toggle="replaceElement"]')
		.each(function() {
			window.FTKAPP.functions.replaceElement(this, function(element, widget) {
				$(widget)
				.closest("fieldset")
					.find(".loading-indicator")
						.fadeOut(function() {
							$(this).remove()
						})
			});
		});

		// Fetch HTML content on demand
		$('.dynamic-content [data-toggle="fetchHTML"]')
		.each(function() {
			// console.warn('.dynamic-content [data-toggle="fetchHTML"] executing');
			window.FTKAPP.functions.fetchHTML(this);
		});


		// On page load initially submit all autosubmit-forms (intentionally used by the AutoTrack-feature)
		$("form.form-autosubmit")
		.removeClass("form-autosubmit")
		.submit();

		/* Method to read serialized form data from window.sessionStorage and populate form fields.
		 * Works in conjunction with "form.autoSerializable"-submit handler, where the form data is
		 * serialized and dumped in the browser sessionStorage object.
		 */
		$("form.autoFillable")
		.each(function() {
			// console.log("---[ line 3190 ]----------------------------------------------");
			// console.warn("form.autoFillable event: --> read serialized form data from window.sessionStorage");

			if (typeof window.sessionStorage === "object") {
				let $form = $(this), $field,
				 formData = window.sessionStorage.getItem( "forms." + $form.attr("id") + ".data" );

				// Abort if formData is no string or empty.
				if (typeof formData !== "string" || (typeof formData === "string" && formData.trim().length <= 0)) {
					// Remove any data related to this form from sessionStorage.
					window.sessionStorage.removeItem( "forms." + $form.attr("id") + ".data" );

					// Render notification.
					window.FTKAPP.functions.renderMessage({
						text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT"] + '<br>' +
							  window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_EMPTY_TEXT"] + '<br>' +
							  window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_REQUIRED_TEXT"]
					});

					// Abort further action.
					return false;
				}

				// Parse data from sessionStorage into proper JS object.
				formData = JSON.parse( formData );

				// Fill data into form fields.
				$.each(formData, function(key, val) {
					$field = $(':input[name="' + key + '"]');

					// Skip fields that must not be autofilled.
					if (!$field.is(".no-autofillable")) {
						$field.val( val );
					}
				});

				// If form is flagged as auto-submittable then submit the filled data.
				if ($form.is(".autoTrackable.autoSubmittable")) {
					// alert("Form autoFilled and ready for autoSubmit!"); return false;
					$form.submit();
				}
			} else {
				console.warn("SessionStorage object is unavailable.");
			}
		});

		$('form[data-monitor-changes="true"]')
		.each(function() {
			let $form = $(this);

			$form
			.data({
				hashCode1: $form.serialize().hashCode()
			})
			.find(":input").not(":button")
			.change(function(evt) {
				$form
				.data({
					hashCode2: $form.serialize().hashCode()
				})
				.attr("data-is-changed", true);
			});
		});

		$('form[data-toggle="loadData"]')
		.each(function() {
			let $this  = $(this),
			stateClass = "loading",
			 loadWhat  = $this.data("load") || undefined,
			   action  = $this.data("action"),
			   format  = $this.data("format") || "json",
				ajax   = {
					type: "GET",
					url:   action,
					data:  "format=" + format,
					cache: false
				};
			 loadWhat  = (typeof loadWhat !== "undefined" ? loadWhat + "List" : loadWhat);

			$this.addClass(stateClass);

			$.ajax(ajax)	// Fetch data
			/* The request failed.
			 */
			.fail(function(jqXHR, statusText, errorThrown) {
				try {
					//TODO - implement error handler
				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			/* The request was successful.
			 */
			.done(function(response, statusText, jqXHR) {
				let resParsed = JSON.parse(response),
					 isObject = $.isPlainObject(resParsed),
					  isArray = $.isArray(resParsed);

				$this
				.data(loadWhat, ( (isArray || isObject) ? resParsed : [] ));
			})
			/* The request has completed.
			 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
			 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
			 */
			.always(function(response, statusText, jqXHR) {
				$this
				.data({
					// Initialize this variable required for the datalist navigation via arrow keys
					currentFocus: undefined
				})
				.removeClass(stateClass);

				// Propagate
				// document.trigger(eAfter, [response, statusText, jqXHR]);
			});
		});

		// On page load initially put focus onto first autofocus element
		$(":input[autofocus]")
		.queue(function(next) {
			$(this).trigger("focus");

			next();
		});


		$(document.body)
		//FIXME - Resolve code duplication (see: Code from ~ line 3030 ff)
		.on("beforeReset.ftk.element",                             function(evt, element) {	// event before search filter cleared
			// do nothing (yet)
		})
		.on("afterReset.ftk.element",                              function(evt, element) {	// event after search filter cleared
			$(evt.target)
			.nextAll(".autocomplete-list").remove();	// hide + remove all autocomplete-lists
		})

		.on("focus",  ":input.nocp",                               function(evt, element) {
			$(this).nocp();
		})

		.on("change", ":input.auto-submit",                        function(evt) {
			let $this   = $(this),
				$target = $( $this.data("target") ),
				form    = $this.attr("form"),
			stateClass  = "submitted",

			$form = $( $this.attr("form") );
			$form = ($form.length ? $form : $this.closest("form"));
			$form = ($form.length ? $form : $("document.forms." + form));

			if ($target.length) {
				$target
				// .addClass(stateClass)		// Disabled on 2021-07-22 - consolidation of where this class is added ... there were too many places. Class is now added in $("form.form").on("submit")
				.queue(function(next) {
					$form
					// .removeClass(stateClass)	// Disabled on 2021-07-22 - consolidation of where this class is added ... there were too many places. Class is now added in $("form.form").on("submit")
					.submit();
				});
			} else {
				if ($form.length) {
					$form
					// .removeClass(stateClass)	// Disabled on 2021-07-22 - consolidation of where this class is added ... there were too many places
					.submit();
				}
			}
		})

		.on("change", "#inputLocale",                              function(evt) {
			let $this = $(this),
				query = document.location.search,
				   hl = undefined;

			// Modern browser alternative
			// see: https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams/URLSearchParams
			//
			// I M P O R T A N T :   Not supported by Internet Explorer (IE) !!!
			let url = new URL(window.location),
			 params = new URLSearchParams(url.search);

			// If form contains no user input reload the page otherwise
			// just select the value for form submittance.
			if ($this.prevAll("input[type]").val().trim() == "") {
				// Parse URL query string into an object.
				query = window.FTKAPP.functions.getAllUrlParams(document.location.href);
				// Set selected app language accordingly.
				query.hl = $this.val();

				// Apply user selected language via browser redirect.
				document.location.search = $.param(query);
			}
		})
		.on("change", '[data-toggle="buttons"]',                   function(evt) {
			let $this = $(this),
				$target = $(evt.target),
				checked = $target.is(":checked");

			if (checked == true) {
				$target
				.attr({checked: "checked"});
			} else {
				$target
				.removeAttr("checked");
			}
		})

		.on("keyup",  'input[data-toggle="calculateMeasurementResultValidity"]', function(evt) {
			// Trigger only if the comma, the dot or a numeric key was pressed.
			if (evt.keyCode == 8 || evt.keyCode == 188 || evt.keyCode == 190 || (evt.keyCode >= 48 && evt.keyCode <= 57) || (evt.keyCode >= 96 && evt.keyCode <= 105)) {
				return $(this).trigger("change");
			}
		})
		.on("change", '[data-toggle="calculateMeasurementResultValidity"]',      function(evt) {
			let $this = $(this),
			 $nominal = $( $this.data("nominal") ),
	  $lowerTolerance = $( $this.data("toleranceLower") ),
	  $upperTolerance = $( $this.data("toleranceUpper") ),
	 $toleranceFactor = $( $this.data("toleranceFactor") ),
			  $target = $( $this.data("target") ),
			  $option = $this.find(":selected"),
			    value = ( $option.length ? $option.val() : $this.val() ),
			  nominal = $nominal.val(),
	   lowerTolerance = $lowerTolerance.val(),
	   upperTolerance = $upperTolerance.val(),
	  toleranceFactor = $toleranceFactor.val(),
		  elemIsInput = this.tagName === "INPUT" && this.type === "text",
		   elemIsList = this.tagName === "SELECT",
			  isValid = false,
 isConditionallyValid = false,	// isConditionallyValid means "not valid within pre-defined tolerance, but valid within the calculated tolerance range (pre-defined tolerance * toleranceFactor)"
			 cssClass = "";

			if (elemIsList) {
				value = $this.val();

				isValid = (value === nominal);

				// NEW: 2023-03-27 - classes border-danger/border-success added
				// NEW: 2023-04-25 - classes border-warning/text-warning added
				cssClass = (isValid
					? "border-success text-success"
					: (isConditionallyValid
						? "border-warning text-warning"
						: (!isValid && !isConditionallyValid ? "border-danger text-danger" : "")));
			}

			if (elemIsInput) {
				value = parseFloat(value);		// user input.
			  nominal = parseFloat(nominal);	// defined nominal value
				lowerTolerance  = parseFloat(lowerTolerance);	// defined upper tolerance boundary
				upperTolerance  = parseFloat(upperTolerance);	// defined lower tolerance boundary
				toleranceFactor = parseFloat(toleranceFactor);	// defined tolerance factor

				// Do the Math.
				isValid     = ( ((nominal - lowerTolerance) <= value) && (value <= (nominal + upperTolerance)) );
				isConditionallyValid = ( (false === isValid) && ( ((nominal - (lowerTolerance * toleranceFactor)) <= value) && (value <= (nominal + (upperTolerance * toleranceFactor))) ) );

				switch (true) {
					case (false === isValid && false === isConditionallyValid) :
						cssClass = "border-danger text-danger";	// NEW: 2023-03-27 - class border-danger added
					break;

					case (false === isValid &&  true === isConditionallyValid) :
						cssClass = "text-warning";
					break;

					case ( true === isValid) :
						cssClass = "text-success";
					break;

					default :
						cssClass = "";
				}
			}

			// Clear current list selection.
			$target
			.find("option")
				.removeAttr("selected")
				.prop("selected", false)
				.end()
			.val("");

			// Select related option.
			if ((elemIsInput && !isNaN(value)) || (elemIsList && (value === "true" || value === "false"))) {
				$target
				.removeClass("border-danger")	// NEW: added on 2023-03-27
				.removeClass("text-danger")
				.removeClass("border-warning")	// NEW: added on 2023-03-27
				.removeClass("text-warning")
				.removeClass("border-success")	// NEW: added on 2023-03-27
				.removeClass("text-success")
				.addClass(cssClass)
//				.find('option[value="' + (isValid ? 'valid' : 'invalid') + '"]')													// DiSABLED on 2023-04-25
				.find('option[value="' + (isValid ? 'valid' : (isConditionallyValid ? 'conditionally_valid' : 'invalid')) + '"]')	// ADDED on 2023-04-25 because new option 'conditionally_valid' was introduced
					.attr("selected", "")
					.prop("selected", true)
					.end()
//				.val(isValid ? 'valid' : 'invalid');													// DiSABLED on 2023-04-25
				.val(isValid ? 'valid' : (isConditionallyValid ? 'conditionally_valid' : 'invalid'));	// ADDED on 2023-04-25 because new option 'conditionally_valid' was introduced
			} else {
				$target
				.val("");
			}
		})

		.on("keyup",  '[data-bind="fixDecimal"]',                  function(evt) {
			// console.warn("fixDecimal keyup-event handler");
			let $this = $(this);
			// $this.val( $this.val().replace(",", ".") );

			// console.log("key pressed:", evt.keyCode);	// ',' = 188 ; '.' = 190 ; 1-0 on keypad = 48 - 57 ; 1-0 on numpad = 96 - 105
			console.log("this.value:",  this.value);
			console.log("this.value contains a comma:",  /[,\.]/ig.test(this.value.toString()));

			if (/[,\.]/ig.test(this.value.toString()) && (evt.keyCode >= 48 && evt.keyCode <= 57) || (evt.keyCode >= 96 && evt.keyCode <= 105)) {
				console.log("fix decimal");
//				this.value = this.value.toString().replace(",", ".");
				$this.val( $this.val().replace(",", ".") );
			}
		})
		/*.on("blur",   '[data-bind="fixDecimal"]',                  function(evt) {
			// console.warn("fixDecimal change-event handler");
			// let $this = $(this); $this.val( $this.val().replace(",", ".") );

			// console.log("this.value:", this.value);

			this.value = this.value.toString().replace(",", ".");	// This doesn't work, because the element lost focus and 'this' not valid any longer.
		})*/

		.on("blur",   '[data-bind="fixDoubleQuotesToQuotes"]',     function(evt) {
			// console.warn("fixDoubleQuotesToQuotes change-event handler");
			let $this = $(this);

			// console.log("key pressed:", evt.keyCode);	// ',' = 188 ; '.' = 190 ; 1-0 on keypad = 48 - 57 ; 1-0 on numpad = 96 - 105
			// console.log("this.value:",  this.value);
			// console.log("this.value contains double quote(s):",  /"/ig.test(this.value.toString()));

			if (/"/ig.test(this.value.toString())) {
				$this.val( $this.val().replaceAll('"', "'") );

				window.FTKAPP.functions.renderMessage({
						type: "info",
						text: window.FTKAPP.translator.map["COM_FTK_HINT_ENTERED_TEXT_WAS_CLEANED_UP_TEXT"] +
							  "<br>" +
							  window.FTKAPP.translator.map["COM_FTK_HINT_INADMISSIBLE_CHARACTERS_REMOVED_TEXT"]
					},
					{autohide : false}
				);
			}
		})

		.on("change", '[data-bind="changeFormLocation"]',          function(evt) {
			let $this = $(this),
			 redirect = [
					window.location.origin,
					[
						window.location.pathname,
						[
							window.location.search,
							$this.attr("name") + "=" + $this.find(":selected").val().trim()
						].join("&")
					].join("")
			].join("");

			// Redirect
			window.location.href = redirect;
		})

		.on("change", '[data-toggle="checkAllAndSubmit"]',         function(evt) {
			let $this = $(this),
			  $target = $( $this.data("target") );

			$target.find('input[type="checkbox"]').prop("checked", true);

			if ($target.is("form")) {
				$target.submit();
			} else {
				$target.closest("form").submit();
			}
		})
		.on("change", '[data-toggle="uncheckAllAndSubmit"]',       function(evt) {
			let $this = $(this),
			  $target = $( $this.data("target") );

			$target.find('input[type="checkbox"]').prop("checked", false);

			if ($target.is("form")) {
				$target.submit();
			} else {
				$target.closest("form").submit();
			}
		})

		// Transform user input into UPPERCASE format while typing or after focusout.
		.on("keyup change", '[data-bind="transformUpperCase"]',    function(evt) {
			let value = this.value.trim();

			this.value = value.toUpperCase();
		})

		.on("keyup",  '[data-bind="convertGermanUmlauts"]',        function(evt) {
			let value = this.value.trim();

			value = value.replace(/Ä/, 'Ae');
			value = value.replace(/ä/, 'ae');

			value = value.replace(/Ö/, 'Oe');
			value = value.replace(/ö/, 'oe');

			value = value.replace(/Ü/, 'Ue');
			value = value.replace(/ü/, 'ue');

			value = value.replace(/ß/, 'ss');

			this.value = value;
		})

		.on("change", '[data-toggle="loadHTML"]',                  function(evt) {
			let $this  = $(this),
			  $target  = $( $this.data("target") ),
			  article  = undefined,
			stateClass = "loading",
			   action  = $this.data("action"),
			   format  = $this.data("format") || "html",
				 data  = {
					"article" : $this.find("option:selected").text().trim(),
					"format"  : format
				};

			$target.addClass(stateClass);

			$.get(action, data, function(response, statusText, jqXHR) {
				let content;

				/* Utilize an external 3rd party library since base64-decoding with Window.atob() does not properly workd.
				 * It doesn't decode Umlauts and special characters. Thus the dankogai/js-base64 library must be utilized
				 * to do the job and provide properly decoded Base64 encoded content.
				 *
				 * require:  https://github.com/dankogai/js-base64
				 */
				if (typeof Base64 === "object" && Base64.extendString) {
					// We have to explicitly extend String.prototype prior using the Base64-methods.
					Base64.extendString();

					// Once extended, we can do the following to decode the data.
					content = response.html.fromBase64();
				} else {
					content = atob(response.html);
				}

				// Inject content.
				$target
				.fadeOut("fast", function(evt) {
					$target
					.removeClass(stateClass)
					.addClass("loaded")
					.html(function(i, html) {
						/*
						 * jQuery.get() does no longer accept fragments in target URL to trigger
						 * extracttion of a specified content identifier from the response data.
						 *
						 * This workaround is used to extract the specified content.
						 *
						 * Code borrowed from:  https://www.roelvanlisdonk.nl/2014/01/31/loading-html-fragments-with-jquery/
						 */
						return $("<div/>")
								.append( $.parseHTML(content) )
								.find("#articleProcessTree")
								.html();
					})
					.fadeIn();
				});
			}, format)
			/* The request failed.
			 */
			.fail(function(jqXHR, statusText, errorThrown) {
				try {
					//TODO - display error in modal window rather than alerting it.
					alert(errorThrown);

				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			/* The request was successful.
			 */
			.done(function(response, statusText, jqXHR) {})
			/* The request has completed.
			 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
			 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
			 */
			.always(function(response, statusText, jqXHR) {
				// Propagate
				// $(document.body).trigger('loaded.k2s.template');
			});
		})

		.on("change", '[data-toggle="openOrganisationUsers"]',     function(evt) {
			let $this = $(this),
			  $target = $( $this.data("target") ),
			  $option = $this.find(":selected");

			document.location.href = [
				document.location.origin,
				document.location.pathname,
				"?",
				$option.val().split("?").pop()
			].join("");
		})

		// Handler for article process measuring data type list.
		// Altered: 2022-05-13
		.on("change", '[data-bind="dataTypeSelected"]',            function(evt) {
			// console.warn("data-bind=\"dataTypeSelected\"");

			let $this = $(this),
			  $target = $( $this.data("target") ),			// Note: This attribute is blocked by another purpose. Hence, the next attribute is required.
			$elements = $( $this.data("toggleElements") ),	// Elements to flip, e.g. dataType textfield vs. select
			  $option = $this.find(":selected"),
			 dataType = ("" + $option.val()).toLowerCase();

			switch (dataType) {
				case "boolval" :
					// console.log("CASE boolval");
					// console.log("  hide:", $elements);

					$target
					.css("color", "transparent")
					.show()
					.prop("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						// console.log("disabled for element:", this, retVal);
						return retVal;
					})
					/*.attr("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						return retVal;
					})*/
					.filter(".mpToleranceFactor")
						.prop("disabled", true);

					$elements
					.hide()
					.prop("disabled", true)
					.attr("disabled", true)
					.filter("select")
						.prop("required", true)
						.prop("disabled", false)
						/*.attr("disabled", false)*/
						.show();
				break;

				case "number" :
					// console.log("CASE number");
					// console.log("  hide:", $elements);

					$target
					.css("color", "unset")
					.show()
					.prop("disabled", false)
					/*.attr("disabled", false)*/
					.filter(".mpToleranceFactor")
						// .prop("disabled", true)	// DiSABLED on 2023-04-27 because hidden elements stored in $target were not properly reactivated
						;

					$elements
					.css("color", "unset")	// ADDED on 2023-04-27 because hidden elements stored in $target were not properly reactivated
					.hide()
					.prop("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						// console.log("disabled for element:", this, retVal);
						return retVal;
					})
					/*.attr("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						return retVal;
					})*/
					.filter("input[type]")
						.prop("required", true)
						.prop("disabled", false)
						/*.attr("disabled", false)*/
						.show();
				break;

				case "string" :
					// console.log("CASE string");
					// console.log("  hide:", $elements);

					$target
					.css("color", "transparent")
					.show()
					.prop("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						// console.log("disabled for element:", this, retVal);
						return retVal;
					})
					/*.attr("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						return retVal;
					})*/
					.filter(".mpToleranceFactor")
						.prop("disabled", true);

					$elements
					.hide()
					.prop("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						// console.log("disabled for element:", this, retVal);
						return retVal;
					})
					/*.attr("disabled", function() {
						let retVal = (false === $(this).prop("readonly"));
						return retVal;
					})*/
					.filter("input[type]")
						.css("color", "transparent")
						.prop("required", false)
						.show();
				break;

				default :
					// console.log("CASE DEFAULT");

					$target
					.add($elements)
					.hide()
					.prop("disabled", true)
					//.attr("disabled", true)
					.filter(".mpToleranceFactor")
						.prop("disabled", true);
				break;
			}
		})
		/*.on("change", '[data-toggle="dataTypeSelected"]',          function(evt) {
			// console.log('[data-togle="dataTypeSelected"] handler');

			let $this = $(this),
			  $target = $( $this.data("target") ),
			  $option = $this.find(":selected"),
			 dataType = $option.val();

			if (dataType == "number") {
				$target.prop("disabled", false).attr("disabled", false);
			} else {
				$target.prop("disabled", true).attr("disabled", true);
			}
		})*/

		// Handler for article process dropdown list.
		.on("change", '[data-bind="processSelected"]',             function(evt) {
			// console.warn("processSelected");
			let $this = $(this),
			  $parent = $( $this.data("parent") ),
			  $target = $( $this.data("target") ),
			  $option = $this.find(":selected"),
				  pid = parseInt( $option.val() ),
				  tmp = {};

			if ( !isNaN( pid ) ) {
				$target.prop("disabled", false).attr("disabled", false);
			} else {
				$target.prop("disabled", true).attr("disabled", true);
			}
		})
		// Handler for
		/*.on("input",  '[data-bind="processDrawingSelected"]',      function(evt) {
			console.warn('input:data-bind="processSelected" handler');
		})*/
		// Handler for
		.on("change", '[data-bind="processDrawingSelected"]',      function(evt) {
			// console.warn('change:data-bind="processSelected" handler');

			let  $this = $(this),
				 acceptedMimes = this.accept.split(",").map(function(mime, i) { return mime.trim() }),
				 acceptedTypes = acceptedMimes.map(function(mime, i) { return mime.split("/").pop().trim().toUpperCase() }),
					 $monitors = $( $this.data("monitor") ) || $("<div/>"),
			$monitorFileNumber = $monitors.filter(function() { return this.id.match(/-filenumber$/i) }),
			  $monitorFileSize = $monitors.filter(function() { return this.id.match(/-filesize$/i) });

			if (!this.files.length) {
				if ($monitorFileNumber.length) {
					$monitorFileNumber
					.removeClass("file-selected")
					.addClass("text-red");

					if ($monitorFileNumber.is(":input")) {
						$monitorFileNumber.val( window.FTKAPP.translator.map["COM_FTK_HINT_NO_FILE_SELECTED_TEXT"] );
					} else {
						$monitorFileNumber
						.html( window.FTKAPP.translator.map["COM_FTK_HINT_NO_FILE_SELECTED_TEXT"] );
					}
				}

				if ($monitorFileSize.length) {
					$monitorFileSize
					.removeClass("file-selected")
					.addClass("text-red")

					if ($monitorFileSize.is(":input")) {
						$monitorFileSize.val("");
					} else {
						$monitorFileSize
						.html( window.FTKAPP.translator.map["COM_FTK_NA_TEXT"] );
					}
				}

				return;
			}

			if ($.inArray(this.files.item(0).type, acceptedMimes) === -1) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"],
							acceptedTypes.join(", ")
						  )
				});

				return false;
			}

			if ($monitorFileNumber.length) {
				$monitorFileNumber
				.addClass("file-selected")
				.removeClass("text-red")
				.val( this.files.item(0).name.split(/\.(pdf|PDF)$/)[0] )
				.trigger("focusout");

				$this
				.trigger("focusout");
			}

			if ($monitorFileSize.length) {
				$monitorFileSize
				.addClass("file-selected")
				.removeClass("text-red")
				.text( window.FTKAPP.functions.convertBytes( this.files.item(0).size, window.FTKAPP.translator.map["COM_FTK_UNIT_MEGABYTE"] ) );
			}
		})
		// Handler for article drawing - is executed first.
		/*.on("input",  '[data-bind="articleDrawingSelected"]',      function(evt) {	// DiSABLED
			console.warn('input:data-bind="articleDrawingSelected" handler');
		})*/
		// Clears or populates file metadata monitor depending on whether there's a selection.
		.on("change", '[data-bind="articleDrawingSelected"]',      function(evt) {
			// console.warn('change:data-bind="articleDrawingSelected" handler');

			let  $this = $(this),
			   $button = $this.closest(".form-group").find(".fileSelectToggle.text-red"),
				 // acceptedMimes = this.accept.split(",").map(function(mime, i) { return mime.trim() }),
				 // acceptedTypes = acceptedMimes.map(function(mime, i) { return mime.split("/").pop().trim().toUpperCase() }),
					  $monitor = $( $this.data("monitor") ) || $("<div/>"),
			$monitorFileNumber = $monitor.filter(function() { return this.id.match(/-filenumber-/i) }),
			  $monitorFileSize = $monitor.filter(function() { return this.id.match(/-filesize-/i) });

			// console.log("event:", evt);
			// console.log("this:", $this);
			// console.log("toggle:", $button);
			// console.log("monitor:", $monitor);
			// console.log("monitorFileNumber:", $monitorFileNumber);
			// console.log("monitorFileMetadata:", $monitorFileNumber.find(".file-metadata"));
			// console.log("monitorFileSize:", $monitorFileSize);

			// No file(s) selected. Clear monitor(s).
			if (!this.files.length) {
				if ($monitorFileNumber.length) {
					$monitorFileNumber.removeClass("file-selected");

					if ($monitorFileNumber.is(":input")) {
						$monitorFileNumber.val("");
					} else {
						$monitorFileNumber.empty();
					}
				}

				if ($monitorFileSize.length) {
					$monitorFileSize.removeClass("file-selected");

					if ($monitorFileSize.is(":input")) {
						$monitorFileSize.val("");
					} else {
						$monitorFileSize.empty();
					}
				}

				return;
			} else {
				// Remove "no drawing" hint.
				if ($button.length) {
					$button.removeClass("text-red").text("");
				}
			}

			// Duplicate. Is checked below in 'CHANGE input[type="file"].form-control-input-file-article-drawing'.
			/*if ($.inArray(this.files.item(0).type, acceptedMimes) === -1) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"], acceptedTypes.join(", ")
						  )
				});

				return false;
			}
			else
			{
				console.log("Check 2:", "OK");
			} */

			// File(s) selected. Populate monitor(s) with metadata.
			if ($monitorFileNumber.length) {
				$monitorFileNumber
				.addClass("file-selected")
				.removeClass("text-red");

				if ($monitorFileNumber.is(":input")) {
					$monitorFileNumber
					.val( this.files.item(0).name.split(/\.(pdf|PDF)$/)[0] );
				} else {
					$monitorFileNumber
					.find(".file-metadata")
						.remove()
						.end()
					.prepend(
						$("<div/>", {
							"class" : "file-metadata position-absolute small text-left w-100 h-100 p-3",
							"css"   : "top:0; left:0; font-family:Verdana",
							"html"  : function() {
								return "" +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_FILE_TEXT"] +
										':</label>' + $this.get(0).files.item(0).name +
									'</p>' +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_SIZE_TEXT"] +
										':</label>' + window.FTKAPP.functions.convertBytes( $this.get(0).files.item(0).size, window.FTKAPP.translator.map["COM_FTK_UNIT_MEGABYTE"] ) +
									'</p>';
							}
						})
					);
				}
			}
		})

		// Handler for article drawing - is executed BEFORE change-handler.
		.on("input",  'input[type="file"].form-control-input-file-article-drawing', function(evt) {
			// console.warn('INPUT input[type="file"].form-control-input-file-article-drawing handler');

			/*.let $this = $(this),
			currentDrawingNumber = $( $this.data("currentDrawingNumber") ).val().trim(),
			currentDrawingIndex  = $( $this.data("currentDrawingIndex") ).val().trim(),
			selectedFile,
			selectedDrawingNumber,
			selectedDrawingIndex;

			// console.log(this.files);
			// console.log("currentDrawingNumber:", currentDrawingNumber);
			// console.log("currentDrawingIndex:",  currentDrawingIndex);

			if (this.files.length) {
				selectedFile = this.files.item(0);
				selectedDrawingNumber = selectedFile.name.split(".").slice(0, selectedFile.name.split(".").length - 1);
				selectedDrawingIndex  = selectedDrawingNumber.pop();
				selectedDrawingNumber = selectedDrawingNumber.join(".");

				// console.log("selectedFile:", selectedFile);
				// console.log("selectedDrawingNumber:", selectedDrawingNumber);
				// console.log("selectedDrawingIndex:", selectedDrawingIndex);

				if (selectedDrawingNumber !== currentDrawingNumber) {
					window.FTKAPP.functions.renderMessage({
						type: "notice",
//						text: window.FTKAPP.translator.map["COM_FTK_HINT_DONT_FORGET_TO_SAVE_CHANGES_TEXT"]
						text: "The selected drawing was rejected. The number does not belong to this article."	// TODO - translate
					});

					return false;
				}

				if (selectedDrawingIndex !== currentDrawingNumber) {
					window.FTKAPP.functions.renderMessage({
						type: "notice",
//						text: window.FTKAPP.translator.map["COM_FTK_HINT_DONT_FORGET_TO_SAVE_CHANGES_TEXT"]
						text: "The selected drawing was rejected. Its index does not match."	// TODO - translate
					});

					return false;
				}
			}*/
		})
		// Handler for article drawing - is executed
		// AFTER 'INPUT   input[type="file"].form-control-input-file-article-drawing'
		// AFTER 'CHANGE  [data-bind="articleDrawingSelected"]'
		// Only fires when after value is committed, such as by select a file and close the dialog, press the enter key, select a value from a list of options, and the like.
		// Validates selected file(s).
		.on("change", 'input[type="file"].form-control-input-file-article-drawing', function(evt) {
			// console.warn('CHANGE input[type="file"].form-control-input-file-article-drawing handler');

			let  $this = $(this),
					  $monitor = $( $this.data("monitor") ) || $("<div/>"),
			$monitorFileNumber = $monitor.filter(function() { return this.id.match(/-filenumber$/i) }),
			  $monitorFileSize = $monitor.filter(function() { return this.id.match(/-filesize$/i) });

			// Hide all previously rendered system messages.
			window.FTKAPP.functions.clearMessages();

			// Hide all previously rendered validation messages.
			$this.closest("form").valid();

			// No file(s) selected. Abort.
			if (!this.files.length) {
				// console.warn("Event handling aborted. No file(s) selected.");

				$monitor
				.removeClass("file-selected");

				if ($monitorFileNumber.length) {
					$monitorFileNumber.removeClass("file-selected");

					if ($monitorFileNumber.is(":input")) {
						$monitorFileNumber.val("");
					} else {
						$monitorFileNumber.empty();
					}
				}

				if ($monitorFileSize.length) {
					$monitorFileSize.removeClass("file-selected");

					if ($monitorFileSize.is(":input")) {
						$monitorFileSize.val("");
					} else {
						$monitorFileSize.empty();
					}
				}

				return;
			}

			let
			abort                = false,
			message,
			acceptedMimes        = this.accept.split(",").map(function(mime, i) { return mime.trim() }),
			acceptedTypes        = acceptedMimes.map(function(mime, i) { return mime.split("/").pop().trim().toUpperCase() }),
			currentDrawingNumber = ("" + $( $this.data("currentDrawingNumber") ).val()).trim(),	// The ID of the HTML element holding the current drawing number
			currentDrawingIndex  = ("" + $( $this.data("currentDrawingIndex")  ).val()).trim(),	// The ID of the HTML element holding the current drawing index
			selectedFile         = this.files.item(0),
			selectedDrawingNumber,
			selectedDrawingIndex;

			selectedDrawingNumber = selectedFile.name.split(".").slice(0, selectedFile.name.split(".").length - 1);	// skip portion ".0"
			selectedDrawingIndex  = selectedDrawingNumber.pop();		// extract index
			selectedDrawingNumber = selectedDrawingNumber.join(".");	// re-join for string comparison

			// Validate the file name matches the article number only if the corresponding data-attribute is present.
			if (typeof $this.data("currentDrawingNumber") !== "undefined") {
				switch (true) {
					case (!currentDrawingNumber) :
						abort   = true;
						message = window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ENTER_ARTICLE_NUMBER_FIRST_TEXT"];
					break

					case (!currentDrawingIndex) :
						abort   = true;
						message = window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ENTER_DRAWING_INDEX_FIRST_TEXT"];
					break

					case (selectedDrawingNumber !== currentDrawingNumber) :
						abort   = true;
						message = [
							window.FTKAPP.translator.map["COM_FTK_HINT_SELECT_DRAWING_WAS_REJECTED_TEXT"],
							window.FTKAPP.translator.map["COM_FTK_HINT_ARTICLE_NUMBER_ENTERED_VS_ARTICLE_DRAWING_SELECTED_MISMATCH_TEXT"]
						].join("<br>");
					break

					case (selectedDrawingIndex  !== currentDrawingIndex) :
						abort   = true;
						message = [
							window.FTKAPP.translator.map["COM_FTK_HINT_SELECT_DRAWING_WAS_REJECTED_TEXT"],
							window.FTKAPP.translator.map["COM_FTK_HINT_ARTICLE_INDEX_ENTERED_VS_ARTICLE_INDEX_SELECTED_MISMATCH_TEXT"]
						].join("<br>");
					break
				}
			}

			if (abort) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: message
				});

				$monitor
				.removeClass("file-selected")
				.find(".file-metadata")
					.remove();

				try {
					this.value = "";
				} catch (err) {
					window.FTKAPP.functions.renderMessage({
						type: "danger",
						text: window.FTKAPP.translator.map["Your browser does not support clearing a file selection.<br>Kindly report this issue to an administrator."]	//TODO - translate
					});
				}

				return;
			}

			// Validate file mime type.
			if ($.inArray(selectedFile.type, acceptedMimes) === -1) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"],
							acceptedTypes.join(", ")
						  )
				});

				return;
			}

			// Validate file size.
			if (selectedFile.size > window.FTKAPP.constants.maxUploadFileSize) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_OF_X_TEXT"],
							window.FTKAPP.functions.convertBytes(window.FTKAPP.constants.maxUploadFileSize, "MB", 0)
						  )
				});

				return;
			}

			/* // Disabled on 20220810 because is appeared to be duplicate code. Same code was executed between line 3328 - 3361
			// File(s) selected. Populate monitor(s) with metadata.
			if ($monitorFileNumber.length) {
				$monitorFileNumber
				.addClass("file-selected");

				if ($monitorFileNumber.is(":input")) {
					$monitorFileNumber
					.val( selectedFile.name.split(/\.(pdf|PDF)$/)[0] );
				} else {
					$monitorFileNumber
					.find(".file-metadata")
						.remove()
						.end()
					.prepend(
						$("<div/>", {
							"class" : "file-metadata position-absolute small text-left w-100 h-100 p-3",
							"css"   : "top:0; left:0; font-family:Verdana",
							"html"  : function() {
								return "" +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_FILE_TEXT"] +
										':</label>' + $this.get(0).files.item(0).name +
									'</p>' +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_SIZE_TEXT"] +
										':</label>' +
										window.FTKAPP.functions.convertBytes( $this.get(0).files.item(0).size, window.FTKAPP.translator.map["COM_FTK_UNIT_MEGABYTE"] ) +
									'</p>';
							}
						})
					);
				}
			} */

			// This will empty the files-list of the element to force triggering this event handler after EVERY select event.
			// see: https://stackoverflow.com/a/65886575
			// this.value = null;
		})

		// Handler for article process drawing - is executed after processDrawingSelected input-handler and change-handler.
		// Only fires when after value is committed, such as by file selected and dialog closed, or return key pressed, select a value from a list of options, and the like.
		.on("change", 'input[type="file"].form-control-input-file-process-drawing', function(evt) {
			// console.warn("change#fileProcessDrawing");

			let  $this = $(this),
					  $monitor = $( $this.data("monitor") ) || $("<div/>"),
			$monitorFileNumber = $monitor.filter(function() { return this.id.match(/-filenumber$/i) }),
			  $monitorFileSize = $monitor.filter(function() { return this.id.match(/-filesize$/i) });

			// Hide any previously rendered system message.
			window.FTKAPP.functions.clearMessages();

			// No file(s) selected. Abort.
			if (!this.files.length) {
				console.warn("Event handling aborted. No file(s) selected.");

				$monitor
				.removeClass("file-selected");

				if ($monitorFileNumber.length) {
					$monitorFileNumber.removeClass("file-selected");

					if ($monitorFileNumber.is(":input")) {
						$monitorFileNumber.val("");
					} else {
						$monitorFileNumber.empty();
					}
				}

				if ($monitorFileSize.length) {
					$monitorFileSize.removeClass("file-selected");

					if ($monitorFileSize.is(":input")) {
						$monitorFileSize.val("");
					} else {
						$monitorFileSize.empty();
					}
				}

				return;
			}

			let
			abort                = false,
			message,
			acceptedMimes        = this.accept.split(",").map(function(mime, i) { return mime.trim() }),
			acceptedTypes        = acceptedMimes.map(function(mime, i) { return mime.split("/").pop().trim().toUpperCase() }),
			currentDrawingNumber = ("" + $( $this.data("currentDrawingNumber") ).val()).trim(),	// The ID of the HTML element holding the current drawing number
			currentDrawingIndex  = ("" + $( $this.data("currentDrawingIndex")  ).val()).trim(),	// The ID of the HTML element holding the current drawing index
			selectedFile         = this.files.item(0),
			selectedDrawingNumber,
			selectedDrawingIndex;

			selectedDrawingNumber = selectedFile.name.split(".").slice(0, selectedFile.name.split(".").length - 2);	// skip portion ".000.0"
			selectedDrawingIndex  = selectedDrawingNumber.pop();		// extract index
			selectedDrawingNumber = selectedDrawingNumber.join(".");	// re-join for string comparison

			// Validate file name matches article number.
			switch (true) {
				case (!currentDrawingNumber) :
					abort   = true;
					message = window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ENTER_ARTICLE_NUMBER_FIRST_TEXT"];
				break

				case (!currentDrawingIndex) :
					abort   = true;
					message = window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_ENTER_DRAWING_INDEX_FIRST_TEXT"];
				break

				case (!new RegExp("^" + selectedDrawingNumber, "i").test(currentDrawingNumber)) :
					abort   = true;
					message = [
						window.FTKAPP.translator.map["COM_FTK_HINT_SELECT_DRAWING_WAS_REJECTED_TEXT"],
						window.FTKAPP.translator.map["COM_FTK_HINT_ARTICLE_NUMBER_ENTERED_VS_PROCESS_DRAWING_SELECTED_MISMATCH_TEXT"]
					].join("<br>");
				break
			}

			if (abort) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: message
				});

				/*$monitor
				.removeClass("file-selected")
				.find(".file-metadata")
					.remove();

				try {
					this.value = "";
				} catch (err) {
					window.FTKAPP.functions.renderMessage({
						type: "danger",
						text: window.FTKAPP.translator.map["Your browser does not support clearing a file selection.<br>Kindly report this issue to an administrator."]
					});
				}*/

				return;
			}

			// Validate file mime type.
			if ($.inArray(selectedFile.type, acceptedMimes) === -1) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_HINT_ONLY_FILE_TYPE_X_IS_ALLOWED_TEXT"],
							acceptedTypes.join(", ")
						  )
				});

				return;
			}

			// Validate file size.
			if (selectedFile.size > window.FTKAPP.constants.maxUploadFileSize) {
				window.FTKAPP.functions.renderMessage({
					type: "notice",
					text: window.FTKAPP.translator.map["COM_FTK_HINT_FILE_TYPE_IS_NOT_ALLOWED_TEXT"] + "<br>" +
						  window.FTKAPP.translator.sprintf(
							window.FTKAPP.translator.map["COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_OF_X_TEXT"],
							window.FTKAPP.functions.convertBytes(window.FTKAPP.constants.maxUploadFileSize, "MB", 0)
						  )
				});

				return;
			}

			/*if ($monitorFileNumber.length) {
				$monitorFileNumber
				.addClass("file-selected");

				if ($monitorFileNumber.is(":input")) {
					$monitorFileNumber
					.val( selectedFile.name.split(/\.(pdf|PDF)$/)[0] );
				} else {
					$monitorFileNumber
					.find(".file-metadata")
						.remove()
						.end()
					.prepend(
						$("<div/>", {
							"class" : "file-metadata position-absolute small text-left w-100 h-100 p-3",
							"css"   : "top:0; left:0; font-family:Verdana",
							"html"  : function() {
								return "" +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_FILE_TEXT"] +
										':</label>' + $this.get(0).files.item(0).name +
									'</p>' +
									'<p class="file-metadata-item small my-1">' +
										'<label class="d-inline-block text-bold mr-2" Xstyle="min-width:3rem">' +
											window.FTKAPP.translator.map["COM_FTK_LABEL_SIZE_TEXT"] +
										':</label>' +
										window.FTKAPP.functions.convertBytes( $this.get(0).files.item(0).size, window.FTKAPP.translator.map["COM_FTK_UNIT_MEGABYTE"] ) +
									'</p>';
							}
						})
					);
				}
			}*/

			// This will empty the files-list of the element to force triggering this event handler after EVERY select event.
			// see: https://stackoverflow.com/a/65886575
			// this.value = null;
		})

		// Handler for part process image (proof pic) - is executed after input-handler.
		.on("input",  'input[type="file"].custom-file-input.file-input-image', function(evt) {
			// console.warn("input#filePartImage");

			$(this).parent().find("label").addClass("loading");
		})
		.on("change", 'input[type="file"].custom-file-input.file-input-image', function(evt) {
			// console.warn("change#filePartImage");

			let $this = $(this),
				$form = $this.closest("form"),
				 form = $form.get(0),
			 ajaxData = {
				      type : "POST",
				       url : "/index.php?hl=de&service=provide&what=image.stream&format=json",
				    enctype: "multipart/form-data",
				contentType: false,
				processData: false,
				      cache: false
			};

			// Hide any previously rendered system message.
			window.FTKAPP.functions.clearMessages();

			// Abort if no file(s) selected.
			if (!this.files.length) {
				console.warn("Event handling aborted. No file(s) selected.");

				return;
			}

			// Define helper method to toggle between file select and file submit widget.
			const toggleFieldsets = function(form) {
				$(form).find("fieldset").each(function() {
					let $fieldset = $(this);

					if ($fieldset.is(".d-none")) {
						$fieldset.find("input[type]")
						.removeClass("disabled")
						.prop("disabled", false);
					} else {
						$fieldset.find("input[type]")
						.addClass("disabled")
						.prop("disabled", true);
					}

					$fieldset.toggleClass("d-none");
				});
			};

			// Validate user input (check for required data).
			if (!$form.valid()) {
				return false;
			}

			// Auto-submit image via AJAX to receive the path to the preview-file or the preview-data.
			if ($this.is(".previewable")) {
				// We have to explicitly extend String.prototype prior using the Base64-methods.
				Base64.extendString();

				// Get AJAX-endpoint URI.
				const endpoint = ($this.data("previewEndpoint") || '').fromBase64();

				if (!endpoint || typeof endpoint === "undefined") {
					window.FTKAPP.functions.renderMessage({
						type: "error",
						text: window.FTKAPP.translator.map["COM_FTK_ERROR_APPLICATION_PREVIEW_ENDPOINT_UNAVAILABLE_TEXT"]
						},
						{autohide : false}
					);

					return false;
				}

				$form.addClass("submitted");

				$this.parent().find("label").removeClass("loading").addClass("busy");

				setTimeout(function() {
					// For an example of form submit in conjunction with the FormData object see: https://codeone.in/jquery-ajax-form-submit-with-formdata-example/
					$.ajax({
						type: "POST",
						enctype: "multipart/form-data",
						url: endpoint,
						data: new FormData(form),
						processData: false,
						contentType: false,
						cache: false,
						timeout: 120000 // PHP's max_execution_time (in seconds) converted into milliseconds
					})
					.done(function(response, statusText, jqXHR) {
						// console.log("Done.", statusText/*, jqXHR*/);

						// If response is a JSON-string, parse it into an object.
						// Otherwise, response is very likely an application error or data dump.
						response = response.charAt(0) == "{" ? JSON.parse(response) : response;

						let resIsObject = $.isPlainObject(response),
							 resIsArray = $.isArray(response);

						// If response is not a JSON string then chances are response is an application error or data dump.
						if (!resIsArray && !resIsObject) {
							window.FTKAPP.functions.renderMessage({
								type: "info",
								html: response
								},
								{autohide : true}
							);

							return false;
						}

						$this.closest(".wrapper").toggleClass("isPreview");

						/*// Prepare styling for text that is going to be injected next.
						$this
						.parent()
							.find("label")
								.addClass("text-muted")
								.queue(function(next) {
									// Append image to form for the user to have a preview.
									$("<p/>", {
										"class":  "h4 mt-md-4 mt-lg-5 mb-3",
										"text":   window.FTKAPP.translator.map["COM_FTK_HEADING_PREVIEW_OF_SELECTED_FILE_TEXT"]
									})
									.appendTo($form.removeClass("submitted"));

									$("<img/>", {
										"src":    response.stream,
										"class":  "img-thumbnail mb-3",
										"alt":    "response.fileName",
										"width":  "",
										"height": ""
									})
									.appendTo($form);

									// Hack to alter the CSS "content" property.
									// Code borrowed from:  https://stackoverflow.com/a/21032999
									let styleElem = document.head.appendChild(document.createElement("style"));
									styleElem.innerHTML = ".custom-file-label.label-warning::before { content: '" + response.storePathRel + "'; }";

									next();
								});*/

						// Inject second fieldset.
						let $fieldset = $('' +
							'<fieldset class="input-group my-2 Xd-none" id="input-group-submit-file">' +
								'<div class="input-group-prepend">' +
									'<span class="input-group-text" id="button-addon1">' + window.FTKAPP.translator.map["COM_FTK_LABEL_PATH_TEXT"] + ':&nbsp;&ast;</span>' +
								'</div>' +
								'<input type="text" class="form-control text-muted" id="ipt-filePath" name="filePath" value="" aria-label="The file path" aria-describedby="button-addon2" />' +
								'<div class="input-group-append" id="button-addon2">' +
									'<button type="reset" class="btn btn-danger btn-reset" form="' + $form.attr("name") + '" data-toggle="reset" data-target="#' + $form.attr("id") + '">' +
										'<i class="far fa-trash-alt"></i>' +
										'<span class="btn-text ml-md-2 d-none d-md-inline">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_DISCARD_TEXT"] + '</span>' +
									'</button>' +
									'<button type="submit" class="btn btn-submit btn-warning" form="' + $form.attr("name") + '" name="task" value="handlePictureUpload">' +
										'<i class="fas fa-save"></i>' +
										'<span class="btn-text ml-md-2 d-none d-md-inline">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_SAVE_TEXT"] + '</span>' +
									'</button>' +
								'</div>' +
							'</fieldset>'
						)
						// Display file path (NOTE: the solution in line 3686 is incompatible with form.on("reset")-handler)
						.find("input[type='text']")
							.val(response.storePathRel)
							.end()
						// Add event handler to "Reset"-button to also hide the preview-section.
						.find("button[type='reset']")
							.on("click", function(evt) {
								// toggleFieldsets(form);

								$fieldset.add(".preview-container").remove();

								// This will empty the files-list of the element to force triggering this event handler after EVERY select event.
								// see: https://stackoverflow.com/a/65886575
								$this.closest(".wrapper").toggleClass("isPreview").end().get(0).value = null;
							})
							.end();

						$fieldset
						.add(
							// Add image-preview container.
							$("<div/>", {
								"class": "preview-container",
								"id":    "preview-file"
							})
							// Add hidden field for the full image path to be submitted to the application.
							.append(
								$("<input/>", {
									"type" : "hidden",
									"name" : "filePath",
									"value": response.filePathRel || response.storePathRel
								})
							)
							// Add image-heading to form for the user to have a preview.
							.append(
								$("<h5/>", {
									"class": "mt-3 mt-md-4 mt-lg-5 mb-3",
									"text":  window.FTKAPP.translator.map["COM_FTK_HEADING_PREVIEW_TEXT"]
								})
							)
							// Add image-preview for the user to decide whether to keep or drop the image.
							.append(
								$("<img/>", {
									"src":    response.stream,
									"class":  "img-thumbnail d-block mx-auto",
									"alt":    window.FTKAPP.translator.map["COM_FTK_HINT_IMAGE_NOT_FOUND_TEXT"],
									"width":  "",
									"height": ""
								})
							)
						).appendTo( $form.removeClass("submitted").find("#upload-wrapper") );

						// Hide file-selection widget and show preview-widget.
						// toggleFieldsets(form);
					})
					.fail(function(jqXHR, statusText, errorThrown) {
						// console.log("Error.", statusText, errorThrown);

						try {
							window.FTKAPP.functions.renderMessage({
								type: "error",
								text: errorThrown
								},
								{autohide : false}
							);
						} catch (err) {
							console.error("The following error occured:", err)
						}
					})
					.always(function(response, statusText, jqXHR) {
						// console.log("Complete.", statusText/*, response*/);

						$this
						.parent()
							.find("label")
								.removeClass("loading")
								.removeClass("busy")
								// .removeClass("label-info")
								// .addClass("label-warning")
								;
					});
				}, 250);
			}

			// This will empty the files-list of the element to force triggering this event handler after EVERY select event.
			// see: https://stackoverflow.com/a/65886575
			// this.value = null;
		})

		.on("change", '[data-toggle="textBolder"]',                function(evt) {
			let $this = $(this),
			  $target = $( $this.data("target") ),
			   $label = $this,
			    $icon = $label.find("> i"),
			   $input = undefined,
			  checked = false;

			$input  = ($label.is(".btn-checkbox") ? $this.find(':input[type="checkbox"]') : undefined);
			$input  = ($label.is(".btn-radio")    ? $this.find(':input[type="radio"]')    : $input);
			checked = $input.is(":checked");

			if (checked) {
				$this
				.addClass("active")
				.find("> .label")
					.text( $this.data("labelUnchecked") );
			} else {
				$this
				.removeClass("active")
				.find("> .label")
					.text( $this.data("labelChecked") );
			}

			$target
			.css({"font-weight" : (checked ? "bold" : "normal")});
		})

		.on("click",  '[data-bind="delegateClick"]',               function(evt) {
			let $this = $(this),
			  $target = $( $this.data("target") );

			// If target element is a file selection widget and there is a monitor, display the selectee file name after file selection
			if ($target.length) {
				$target.click();
			}
		})

		.on("change", '[data-bind="toggleChecked"]',               function(evt) {
			// "Web Developer Form Filler"-plugin compatible solution.
			let $this = $(this),
			   $label = $this.find(".checkbox-label"),
			   $input = $label.find(':input[type="checkbox"]') || $this.find(':input[type="checkbox"]');

			if ($input.is(":checked")) {
				$this.addClass("btn-info").removeClass("btn-secondary");
			} else {
				$this.addClass("btn-secondary").removeClass("btn-info");
			}
		})

		// Add class 'submitted' to any related element.
		// Altered: 2022-05-13
		.on("click",  '[data-bind="toggleSubmitted"]',             function(evt) {
			// console.warn("data-bind=\"toggleSubmitted\"");

			let $this = $(this),
			  $target = $( $this.data("target") );

			// If target element is a file selection widget and there is a monitor,
			// display the selected file name after file selection.
			if ($target.length) {
				$target
				.addClass("submitted")
				// .parents("form#" + $this.attr("form"))
				;
			}
		})

		.on("click",  '[data-toggle="buttons"]',                   function(evt) {
			// console.warn('[data-toggle="buttons"] executing');

			let $this = $(this),
			  $target = $(evt.target),
			  $parent = $this.closest( $this.data("parent") ),
			   $label = $this.find("> label"),
			    $icon = $label.find("> i"),
			   $input = undefined,
			  checked = false;

			// console.log("this:", $this);
			// console.log("label:", typeof $label, $label, $label.length);

			if ($label.length) {
				// console.log("Find checkbox and read it's state...");

				$input  = ($label.is(".btn-checkbox") ? $label.find(':input[type="checkbox"]') : $parent.find(':input[type="checkbox"]'));
				$input  = ($label.is(".btn-radio")    ? $label.find(':input[type="radio"]')    : $parent.find(':input[type="checkbox"]'));

				checked = $input.length && $input.is(":checked");
			}

			// console.log("input:",   $input);
			// console.log("checked:", checked);

			$this
			.queue(function(next) {
				// Toggle button-icon and -text if present.
				if (!checked) {
					$icon.removeClass( $icon.data("iconChecked") ).addClass( $icon.data("iconUnchecked") );

					$label.find( $label.attr("data-target") ).text( $label.data("labelUnchecked") );

				} else {
					$icon.removeClass( $icon.data("iconUnchecked") ).addClass( $icon.data("iconChecked") );

					$label.find( $label.attr("data-target") ).text( $label.data("labelChecked") );
				}

				// Toggle background color of container.
				if (typeof $parent !== "undefined") {
					if (checked) {
						$parent.addClass("alert-success");
					} else {
						$parent.removeClass("alert-success");
					}
				}

				next();
			});
		})

		.on("click",  '[data-bind="addArticleProcess"]',           function(evt) {
			let $this    = $(this),
				$target  = $( $this.data("target") ),
				$parent  = $this.parent(),
				    cnt  = $parent.find('.process').length,
				$depends = $( $this.data("ruleRequired") ),
				options  = ['<option value="">&ndash; ' + window.FTKAPP.translator.map["COM_FTK_LIST_OPTION_SELECT_TEXT"] + ' &ndash;</option>'],

				// Sort processes object by object value.
				/*
				 * Sort object properties (only own properties will be sorted).
				 *
				 * @param  {object}  obj object to sort properties
				 * @param  {bool}    isNumericSort true - sort object properties as numeric value, false - sort as string value.
				 *
				 * @return {Array}   array of items in [[key,value],[key,value],...] format.
				 *
				 * @author https://gist.github.com/umidjons/9614157
				 */
				processes = (function(obj, isNumericSort) {
					let sortable = [];

					for (let key in obj) {
						if (obj.hasOwnProperty(key)) {
							sortable.push([key, obj[key]]);
						}
					}

					if (isNumericSort) {
						sortable.sort(function(a, b) {
							return a[1] - b[1];
						});
					} else {
						sortable.sort(function(a, b) {
							let x = a[1].toLowerCase(),
								y = b[1].toLowerCase();

							return (x < y ? -1 : (x > y ? 1 : 0));
						});
					}

					let sorted = {};

					for (let i = 0; i < sortable.length; i++) {
						sorted[ sortable[i][1] ] = sortable[i][0];
					}

					return sorted;

				}(window.FTKAPP.processes)),
				rdmPid = window.btoa( new Date().getTime() ).replace(/=/g, "");	// temporary unique list item id required for further referencing until form submit

			// Prepare options list from sorted processes list.
			$.each(processes, function(key, val) {
				options.push('<option value="' + val + '">' + key + '</option>');
			});

			// Prepare new element.
			let $li = $('' +
				'<div class="list-item dynamic-content position-relative mt-sm-2" id="p-' + rdmPid + '" style="display:none">' +
				  '<div class="row form-group ml-sm-0 mb-0">' +
					'<div class="col-sm-6 col-md-5 col-lg-4 px-sm-0">' +
						'<div class="input-group" style="background:#e8edf3">' +
							'<div class="input-group-prepend d-inline-block">' +
								'<button type="button" ' +
										 'class="btn btn-secondary left-radius-0 right-radius-0 px-2" ' +
										 'id="card-p-' + rdmPid + '-toggle" ' +
										 'data-toggle="collapse" ' +
										 'data-target="#card-' + rdmPid + '" ' +
										 'tabindex="" ' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
										 'disabled ' +
										 'onclick="window.FTKAPP.functions.renderMessage({' +
											'&quot;type&quot;:&quot;info&quot;,' +
											'&quot;text&quot;:&quot;' + window.FTKAPP.translator.map["COM_FTK_HINT_NEW_PROCESS_INITIAL_STORE_TEXT"] + '&quot;})"' +
								'>' +
									'<span class="px-1" ' +
										 'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_DEFINE_MEASURMENT_POINTS_TEXT"] + '" ' +
										 'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_DEFINE_MEASURMENT_POINTS_TEXT"] + '" ' +
										 'data-toggle="tooltip" ' +
										 'data-html="true"" ' +
									'>' +
										'<small class="btn-text sr-only">' + window.FTKAPP.translator.map["COM_FTK_LABEL_MEASURING_POINTS_TEXT"] + '</small>' +
										'<i class="fas fa-cogs"></i>' +
									'</span>' +
								'</button>' +
							'</div>' +
							'<label for="processes" ' +
									'class="col-form-label col-auto sr-only" ' +
									'style="margin-left:-3px; vertical-align:middle; background-color:unset"' +
							'>' + window.FTKAPP.translator.map["COM_FTK_LABEL_PROCESSES_TEXT"] + ':' +
							'</label>' +
							'<select name="processes[]" ' +
									'class="form-control custom-select selectProcess" ' +
									'id="ipt-processes-' + rdmPid + '" ' +
									'data-bind="processSelected" ' +
									'data-parent="#p-' + rdmPid + '" ' +
									'data-target="#card-p-' + rdmPid + '-toggle,' +
												 '#drw-p-'  + rdmPid + '-filenumber,' +
												 '#drw-p-'  + rdmPid + '-filesize,' +
												 '#drw-p-'  + rdmPid + '-toggle,' +
												 '#drw-p-'  + rdmPid + '-file" ' +
									'data-rule-required="true" ' +
									'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_SELECT_TEXT"] + '" ' +
									'required ' +
									'tabindex=""' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
							'>' + options.join('') + '</select>' +
						'</div>' +
					'</div>' +

					// Select drawing file
					'<div class="col-sm-6 col-md-7 col-lg-8">' +
						'<div class="input-group">' +
							// Input drawing file name (stem must equal article name)
							'<label for="drawings" ' +
									'class="col-form-label sr-only"' +
							'>' + window.FTKAPP.translator.map["COM_FTK_LABEL_DRAWING_NUMBER_TEXT"] + ':' +
							'</label>' +
							'<input type="text" ' +
									'name="drawings[]" ' +
									'class="form-control form-control-drawing-number text-red" ' +
									'id="drw-p-' + rdmPid + '-filenumber" ' +
									'value="" ' +
									'readonly '  +
									'required '  +
									'aria-live="polite" ' +	// // content is dynamically set via Javascript
									'data-rule-required="true" ' +
									'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_FIELD_IS_AUTOFILLED_TEXT"] + '" ' +
									'tabindex=""' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
							'>' +

							// Info about file size
							'<span class="form-control col-2 text-right text-red" ' +
								   'id="drw-p-' + rdmPid + '-filesize" ' +
								   'readonly '  +
								   'disabled'   +
							'>' +
								window.FTKAPP.translator.map["COM_FTK_NA_TEXT"] +
							'</span>' +

							'<div class="input-group-append">' +
								'<div class="btn-group btn-group-sm" role="group" aria-label="Buttons">' +
									// File upload togle for this process drawing
									'<button type="button" ' +
											'class="btn btn-sm btn-info" ' +
											'id="drw-p-' + rdmPid + '-toggle" ' +
											'data-bind="delegateClick" ' +
											'data-target="#drw-p-' + rdmPid + '-file" ' +
											'style="border-top-left-radius:0; border-bottom-left-radius:0" ' +
											'tabindex="" ' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
											'disabled' +
									'>' +
										'<span class="px-2" ' +
											   'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_UPLOAD_PROCESS_DRAWING_TEXT"] + '" ' +
											   'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_UPLOAD_PROCESS_DRAWING_TEXT"] + '" ' +
											   'data-toggle="tooltip"' +
										'>' +
											// '<i class="fas fa-folder-open"></i> ' +
											'<i class="fas fa-recycle" style="vertical-align:text-top; font-size:1.2rem"></i> ' +
											'<span class="btn-text ml-md-2">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_CHANGE_DRAWING_TEXT"] + '</span>' +
										'</span>' +
									'</button>' +
									// File preview toggle for this process drawing
									'<span class="btn btn-secondary disabled" ' +
										  'tabindex="" ' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
										  'disabled ' +
										  'aria-disabled="true"' +
									'>' +
										'<span class="px-2" style="vertical-align:sub">' +
											'<i class="far fa-file icon-file" style="vertical-align:text-top; font-size:1.2rem"></i>' +
											'<span class="btn-text ml-md-2">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_PDF_MISSING_TEXT"] + '</span>' +
										'</span>' +
									'</span>' +
									// File pool for this process --- OBSOLETE
									// Delete process button
									'<button type="button" ' +
											 'class="btn btn-danger left-radius-0 right-radius-0 px-2" ' +
											 'id="drw-p-' + rdmPid + '-trasher" ' +
											 'data-bind="deleteListItem" ' +
											 'data-target="#p-' + rdmPid + '" ' +
											 'data-confirm-delete="true" ' +
											 'data-confirm-delete-empty="false" ' +
											 'data-confirm-delete-message="' +
												window.FTKAPP.translator.map["COM_FTK_DIALOG_PROCESS_CONFIRM_DELETION_TEXT"] + "\r\n" +
												window.FTKAPP.translator.map["COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT"] + '" ' +
											 'tabindex=""' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
									'>' +
										'<span class="px-2" ' +
											   'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT"] + '" ' +
											   'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT"] + '" ' +
											   'data-toggle="tooltip"' +
										'>' +
											'<i class="far fa-trash-alt"></i> ' +
											'<span class="btn-text sr-only">' + window.FTKAPP.translator.map["COM_FTK_LABEL_DELETE_TEXT"] + '</span>' +
										'</span>' +
									'</button>' +
								'</div>' +
							'</div>' +

							'<input type="hidden" name="MAX_FILE_SIZE[]" value="' + window.FTKAPP.constants.maxUploadFileSize + '">' +
							'<label for="drawings" class="col-form-label sr-only">' + window.FTKAPP.translator.map["COM_FTK_LABEL_DRAWING_SELECTOR_TEXT"] + ':</label>' +
							'<input type="file" ' +
									'name="drawings[]" ' +
									'multiple="false" ' +
									'class="form-control form-control-input-file form-control-input-file-drawing form-control-input-file-process-drawing d-none" ' +
									'id="drw-p-' + rdmPid + '-file" ' +
									'accept="application/pdf" ' +
									'data-bind="processDrawingSelected" ' +
									'data-monitor="#drw-p-' + rdmPid + '-filenumber, #drw-p-' + rdmPid + '-filesize" ' +
									'data-current-drawing-number="#ipt-number" ' +
									'data-current-drawing-index="#ipt-index" ' +
									'required ' +
									'data-rule-required="true" ' +
									'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									'style="font-size:90%; padding-top:.3rem; padding-left:0; border:unset; background:inherit"' +
							'>' +
						'</div>' +
					'</div>' +
				  '</div>' +

				  // Element to be dynamically replaced by other content
				  '<span id="dynamic-table-' + rdmPid + '" ' +
					  'data-toggle="fetchHTML" ' +
					  'data-load="articleProcessQuality" ' +
					  'data-format="json" ' +
					  'data-target="#dynamic-table-' + rdmPid + '" ' +
					  'data-action="index.php?hl=&view=article&layout=process_quality&aid=&pid=' + rdmPid + '&service=provide&what=articleProcessQuality" ' +
					  'data-replace="true" ' +
				  '>' +
				  '</span>' +
				'</div>'
			);

			// Inject new element.
			$li
			.appendTo($target)
			.fadeIn(function() {
				$(this)
				.find('[data-toggle="tooltip"]')
				.tooltip(
					$.extend(
						{
							container : "body",
							boundary  : "window"
						},
						(true
							? {delay : $(this).data("delay")}
							: {}
						)
					)
				)
				.end()
				// Find first visible input/select/textarea (excluding buttons) and focus it.
				.find("input[type=text], textarea, select").filter(":input:visible:first")
					.trigger("focus")
					.end();
			});
		})

		.on("click",  '[data-toggle="addErrorCatalogItem"]',       function(evt) {
			// console.warn("addErrorCatalogItem");

			let $this      = $(this),
				tabindex   = parseInt($this.data("tabindex")) || 0,	// Do not mix up with the non-data-attribute 'tabindex'. This value must be set before this handler is executing. Hence, ensure to set it via the window.FTKAPP.functions.setAttribute function
				    form   = $this.attr("form"),
				$target    = $( $this.data("target") ),
				$parent    = $this.parent(),
				$children  = $target.find(".card"),
				$lastChild = $children.last(),	// ENABLE this line WHEN new children are APPENDed
				    maxID  = $target.data("nextId"),
					lastID = $children.length ? parseInt( $lastChild.find(":input.form-control").attr("name").match(/[0-9]+/).shift() ) : maxID,
				    newID  = lastID < maxID ? maxID : ($children.length > 0 ? lastID + 1 : lastID);

			// console.log("this:", this);
			// console.log("form:", form);
			// console.log("tabindex:", tabindex);

			// row template
			$('' +
			  '<div class="card mb-3" id="card-' + newID + '">' +
					'<div class="card-header border-bottom-0" id="heading-' + newID + '">' +
						'<div class="row">' +

							'<div class="col col-2">' +
								'<input type="text" ' +
									   'name="errors[' + newID + '][number]" ' +
									   'class="form-control font-weight-bold" ' +
									   'form="' + form + '" ' +
									   'value="' + newID.toString().padStart(6, '0') + '" ' +	// generate a 6 characters long error number by zero-filling blanks with '0'
									   'placeholder="' + window.FTKAPP.translator.map["COM_FTK_INPUT_PLACEHOLDER_ERROR_ID_TEXT"] + '" ' +
									   'minlength="4" ' +
									   'maxlength="10" ' +
									   'pattern="' + window.FTKAPP.constants.regexPatterns.FTKREGEX_ERROR_NUMBER + '" ' +
									   'required ' +
									   'readonly ' +
									   'data-rule-required="true" ' +
									   'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									   'data-rule-minlength="4" ' +
									   'data-msg-minlength="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_SHORT_TEXT"] + '" ' +
									   'data-rule-maxlength="10" ' +
									   'data-msg-maxlength="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_LONG_TEXT"] + '" ' +
									   'data-rule-pattern="' + window.FTKAPP.constants.regexPatterns.FTKREGEX_ERROR_NUMBER + '" ' +
									   'data-msg-pattern="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_NUMBER_TEXT"] + '"' +
								'>' +
							'</div>' +	// END col col-2

							'<div class="col col-2">' +
								'<input type="text" ' +
									   'name="errors[' + newID + '][wincarat]" ' +
									   'class="form-control font-weight-bold maxlength" ' +
									   'form="' + form + '" ' +
									   'value="" ' +
									   'placeholder="' + window.FTKAPP.translator.map["COM_FTK_INPUT_PLACEHOLDER_ERROR_CODE_WINCARAT_TEXT"] + '" ' +
									   'title="' + window.FTKAPP.translator.map["Der WinCarat-Code ist optional und für die Kollegen in HU relevant."] + '" ' +
									   'minlength="4" ' +
									   'maxlength="10" ' +
									   'pattern="' + window.FTKAPP.constants.regexPatterns.FTKREGEX_ERROR_WINCARAT_CODE + '" ' +
									   'tabindex="' + (++tabindex) + '" ' +
//									   'required ' +
									   'data-rule-required="false" ' +
									   'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									   'data-rule-minlength="4" ' +
									   'data-msg-minlength="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_SHORT_TEXT"] + '" ' +
									   'data-rule-maxlength="10" ' +
									   'data-msg-maxlength="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_LONG_TEXT"] + '" ' +
									   'data-rule-pattern="' + window.FTKAPP.constants.regexPatterns.FTKREGEX_ERROR_WINCARAT_CODE + '" ' +
									   'data-msg-pattern="' + window.FTKAPP.translator.map["COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_WINCARAT_CODE_TEXT"] + '" ' +
									   'data-toggle="tooltip" ' +
									   'data-trigger="focus"' +
								'>' +
							'</div>' +	// END col col-2

							'<div class="col col-6">' +
								'<input type="text" ' +
									   'name="errors[' + newID + '][name]" ' +
									   'class="form-control font-weight-bold maxlength" ' +
									   'form="' + form + '" ' +
									   'value="" ' +
									   'placeholder="' + window.FTKAPP.translator.map["COM_FTK_INPUT_PLACEHOLDER_ERROR_TITLE_TEXT"] + '" ' +
									   'minlength="3" ' +
									   'maxlength="100" ' +
									   'tabindex="' + (++tabindex) + '" ' +
									   'required ' +
									   'data-rule-required="true" ' +
									   'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									   'data-rule-minlength="3" ' +
									   'data-msg-minlength="' + window.FTKAPP.translator.map["COM_FTK_HINT_TITLE_TOO_SHORT_TEXT"] + '" ' +
									   'data-rule-maxlength="100" ' +
									   'data-msg-maxlength="' + window.FTKAPP.translator.map["COM_FTK_HINT_TITLE_TOO_LONG_TEXT"] + '"' +
									   'data-bind="fixDoubleQuotesToQuotes"' +
								'>' +
							'</div>' +	// END col col-8

							'<div class="col col-2">' +
								'<div class="btn-toolbar float-right" role="toolbar" aria-label="Toolbar with button groups">' +	// TODO - translate
									'<div class="btn-group" role="group" aria-label="Button group ' + newID + '">' +				// TODO - translate
										// ADD-Button
										'<button type="button" ' +
												'class="btn btn-outline-secondary btn-add" ' +
												'form="' + form + '" ' +
												'data-toggle="addErrorCatalogItem" ' +
												'data-target="#errorCatalog" ' +
												'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_ADD_TEXT"] + '" ' +
												'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_ADD_TEXT"] + '" ' +
												'tabindex="' + (tabindex = tabindex + 2) + '" ' +
												'onclick="window.FTKAPP.functions.setAttribute(this, \'data-tabindex\', document.querySelector(\'#editCatalogForm input[name=&quot;tabindex&quot;]\').value)"' +
										'>' +
											'<i class="fas fa-plus"></i>' +
											'<span class="sr-only">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_ADD_TEXT"] + '</span>' +
										'</button>' +

										// SAVE-Button
										'<button type="button" ' +
												'class="btn btn-outline-secondary btn-edit btn-submit btn-save allow-window-unload" ' +
												'form="' + form + '" ' +
												'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT"] + '" ' +
												'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT"] + '" ' +
												'tabindex="' + (++tabindex) + '" ' +
												'onclick="document.querySelector(\'button[value=&quot;submit&quot;]\').click()"' +
										'>' +
											'<i class="fas fa-save"></i>' +
											'<span class="sr-only">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_SAVE_TEXT"] + '</span>' +
										'</button>' +

										// DELETE-Button
										'<button type="button" ' +
												'class="btn btn-outline-secondary btn-edit btn-trashbin" ' +
												'form="' + form + '" ' +
												'title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_ENTRY_DELETE_THIS_TEXT"] + '" ' +
												'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_ENTRY_DELETE_THIS_TEXT"] + '" ' +
												'tabindex="' + (++tabindex) + '" ' +
												'data-bind="deleteListItem" ' +
												'data-target="#card-' + newID + '" ' +
												'data-parent="#errorCatalog" ' +
												'data-confirm-delete="true" ' +
												'data-confirm-delete-empty="false" ' +
												'data-confirm-delete-message="' +
													window.FTKAPP.translator.map["COM_FTK_DIALOG_PROCESS_ERROR_CONFIRM_DELETION_TEXT"] + "\r\n" +
													window.FTKAPP.translator.map["COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT"] + '"' +
										'>' +
											'<i class="far fa-trash-alt"></i>' +
											'<span class="sr-only">' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_DELETE_TEXT"] + '</span>' +
										'</button>' +
									'</div>' +
								'</div>' +
							'</div>' +	// END col col-2

						'</div>' +	// END row
					'</div>' +	// END card-header

					'<div id="collapse-' + newID + '" class="collapse show" aria-labelledby="heading-' + newID + '">' +
						'<div class="card-body pb-4">' +
							'<textarea name="errors[' + newID + '][description]"' +
									  'class="form-control maxlength" ' +
									  'form="' + form + '" ' +
									  'placeholder="' +
										window.FTKAPP.translator.map["COM_FTK_INPUT_PLACEHOLDER_ERROR_DESCRIPTION_TEXT"] + ' (' +
										window.FTKAPP.translator.map["COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT"] + ')" ' +
									  'rows="3" ' +
									  'minlength="0" ' +
									  'maxlength="500" ' +
									  'tabindex="' + (tabindex - 3) + '" ' +
									  'data-rule-maxlength="500" ' +
									  'data-msg-maxlength="' + window.FTKAPP.translator.map["COM_FTK_HINT_TEXT_TOO_LONG_TEXT"] + '" ' +
									  'data-bind="fixDoubleQuotesToQuotes"' +
							'></textarea>' +
						'</div>' +
					'</div>' +	// END collapse
			  '</div>'	// END card
			)
			.appendTo($target)		// ENABLE this line WHEN new children are APPENDed
			.fadeIn(function() {
				$(this)
				// Update tabindex in hidden form element
				.closest('form')
					.find('input[name="tabindex"]')
						.val(tabindex)
						.end()
					.end()
				// Bind maxlength indication functionality to elements.
				.find(".maxlength")
					.maxlength(window.FTKAPP.constants.maxlengthConfig)
					.end()
//				.find("input[type='text']:enabled:visible:first")
//				.find("input[type='text']:enabled:visible:not([readonly])")
//				.find('input[name="errors[' + newID + '][wincarat]"]:enabled:visible:not([readonly])')
				.find('input[name="errors[' + newID + '][name]"]:enabled:visible:not([readonly])')
					.trigger("focus");
			});
		})

		.on("click",  '[data-toggle="addMeasurementDefinition"]',  function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") ),
				$dummy  = $target.data("rowTemplate"),
				row,
				$children = $target.find("> tr"),
				$lastChild = $children.last(),
				maxID = $target.data("nextId"),
				lastID = $children.length ? parseInt( $lastChild.attr("id").split("-")[2] ) : maxID,
				newID = lastID < maxID ? maxID : ($children.length > 0 ? lastID + 1 : lastID);

			if (typeof Base64 === "object" && Base64.extendString) {
				// We have to explicitly extend String.prototype prior using the Base64-methods.
				Base64.extendString();

				// Once extended, we can do the following to decode the data.
				row = $dummy.fromBase64();
			} else {
				row = atob($dummy);
			}

			row = row.replace(/%CNT%/gi, newID);

			$(row)
			.hide()
			.appendTo( $target.attr({"data-next-id" : newID}) )
			.fadeIn("fast", function() {
				$(this)
				.removeAttr("style")
				.find('input[type]').not(":hidden").not(":disabled").first()
					.focus();
			});
		})

		// Renders a technical parameter input field.
		.on("click",  '[data-bind="addTechnicalParam"]',           function(evt) {
			let $this   = $(this),
				$target = $( $this.data("target") ),
				$parent = $this.parent(),
				$children  = $target.find(".form-control.dynamic-datalist"),
				$lastChild = $children.last(),
				maxID  = $target.data("nextId"),
				lastID = $children.length ? parseInt( $lastChild.attr("id").split("-").pop() ) : maxID,
				newID  = lastID < maxID ? maxID : ($children.length > 0 ? lastID + 1 : lastID),
				rdmPid = window.btoa( new Date().getTime() ).replace(/=/g, "");	// temporary unique list item id required for further referencing until form submit;

			let $li = $('' +
			  '<li class="list-item dynamic-content position-relative" id="tp-' + rdmPid + '" style="display:none">' +
				  '<div class="form-row procParam my-md-1 my-lg-2">' +
					'<div class="col">' +
						'<div class="input-group">' +
							'<input type="text" ' +
									'name="params[' + newID + ']" ' +
									'class="form-control dynamic-datalist" ' +
									'id="param-' + newID + '" ' +
									'minlength="3" ' +
									'maxlength="100" ' +
									'title="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									'placeholder="' + window.FTKAPP.translator.map["COM_FTK_LABEL_PARAMETER_NAME_TEXT"] + '" ' +
									'required ' +
									'data-bind="parseDatalist" ' +
									'data-list="techParams" ' +
									'data-rule-required="true" ' +
									'data-msg-required="' + window.FTKAPP.translator.map["COM_FTK_HINT_MANDATORY_FIELD_TEXT"] + '" ' +
									'data-rule-minlength="3" ' +
									'data-msg-minlength="' + window.FTKAPP.translator.map["COM_FTK_HINT_NAME_TOO_SHORT_TEXT"] + '" ' +
									'data-rule-maxlength="100" ' +
									'data-msg-maxlength="' + window.FTKAPP.translator.map["COM_FTK_HINT_NAME_TOO_LONG_TEXT"] + '" ' +
							'/>' +
							'<div class="input-group-append">' +
								'<button type="button" ' +
										'class="btn btn-danger" ' +
										'title="' + window.FTKAPP.translator.map["COM_FTK_LABEL_TECHNICAL_PARAMETER_DELETE_THIS_TEXT"] + '" ' +
										'aria-label="' + window.FTKAPP.translator.map["COM_FTK_LABEL_TECHNICAL_PARAMETER_DELETE_THIS_TEXT"] + '" ' +
										'data-bind="deleteListItem" ' +
										'data-target="#tp-' + rdmPid + '" ' +
										'data-confirm-delete="true" ' +
										'data-confirm-delete-empty="false" ' +
										'data-confirm-delete-message="' +
											window.FTKAPP.translator.map["COM_FTK_DIALOG_TECHNICAL_PARAMETER_CONFIRM_DELETION_TEXT"] + "\r\n" +
											window.FTKAPP.translator.map["COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT"] + '" ' +
										'tabindex=""' +	//FIXME - Decide, should we find the previous field and read its tabindex or should we leave it blank?
								'>' +
									'<span title="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT"] + '" ' +
										  'aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT"] + '" ' +
										  'data-toggle="tooltip"' +
									'>' +
										'<i class="far fa-trash-alt"></i> ' +
										'<span class="btn-text sr-only">' + window.FTKAPP.translator.map["COM_FTK_LABEL_DELETE_TEXT"] + '</span>' +
									'</span>' +
								'</button>' +
							'</div>' +
						'</div>' +
					'</div>' +
				  '</div>' +
			  '</li>'
			)
			.appendTo($target)
			.fadeIn(function() {
				$(this)
				.find('[data-toggle="tooltip"]')
				.tooltip(
					$.extend(
						{
							container : "body",
							boundary  : "window"
						},
						(true
							? {delay : $(this).data("delay")}
							: {}
						)
					)
				)
				.end()
				// Find first visible input/select/textarea (excluding buttons) and focus it.
				.find("input[type=text], textarea, select").filter(":input:visible:first")
					.trigger("focus")
					.end();
			});
		})

		.on("click",  '[data-toggle="deleteErrorCatalogItem"]',    function(evt) {
			// console.warn("deleteErrorCatalogItem");
			// console.log("this:", this);

			// Render message.
			/*window.FTKAPP.functions.renderMessage({
				type: "notice",
//				text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_REQUIRED_INFORMATION_IS_MISSING_TEXT"]
				text: "Einen Moment bitte. Es wird geprüft, ob dieser Fehler bereits getrackt wurde."	// TODO - translate
			});*/
			window.FTKAPP.functions.renderPopover(this, {
				type: "dark",
				title: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_NOTICE_TEXT"],
				text: [
					window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_ONE_MOMENT_PLEASE_TEXT"],
					window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_CHECKING_IF_THE_ERROR_IS_ALREADY_TRACKED_TEXT"],
					window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_WORK_CAN_CONTINUE_TEXT"],
					window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_PLEASE_DO_NOT_REFRESH_THE_PAGE_TEXT"]
				].join(" ")
			});

//			return false;

			let $this   = $(this),
				$target = $( $this.data("target") ) || $($this.data("parent") || $this.parent()),
				$inputs = $target.find("input[type=text], input[type=file], textarea, select").filter(":input:visible"),
			stateClass  = "busy-state state-loading",

			// 1. Check if error item is already used for tracking.
			action = $this.data("action"),
			ajax   = {
				type: "GET",
				url:   action,
				data:  "format=json",
				cache: false
			};

			$this.addClass(stateClass);

			$.ajax(ajax)
			/* The request failed.
			 */
			.fail(function(jqXHR, statusText, errorThrown) {
				// console.warn("AJAX error");
				// console.warn("jqXHR:", jqXHR);
				// console.warn("statusText:", statusText);
				// console.warn("errorThrown:", errorThrown);

				try {
					//TODO - implement error handler
				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			/* The request was successful.
			 */
			.done(function(response, statusText, jqXHR) {
				// console.warn("AJAX success");
				// console.warn("response:", response);
				// console.warn("statusText:", statusText);
				// console.warn("jqXHR:", jqXHR);

				/*let resParsed = JSON.parse(response),
						 list = $.makeArray( Object.keys(resParsed) ),
					 isObject = $.isPlainObject(list),
					  isArray = $.isArray(list);

				console.warn("resParsed:", resParsed);
				console.warn("list:", list);
				console.warn("isObject:", isObject);
				console.warn("isArray:", isArray);*/

				// Error is not yet tracked.
				// Prepare list item for deletion.
				if (false == response) {
					// disable all input fields
					$inputs
					.prop("disabled", true)
					.attr("disabled", "disabled");

					// add class "deleted" for CSS styling
					$this
					.popover("dispose")
					.closest(".card")
						.addClass("deleted");
				} else {
					$this
					.prop("disabled", true)
					.attr("disabled", "disabled");

					// Render message.
					window.FTKAPP.functions.renderPopover($this.get(0), {
						type: "error",
						title: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_INFORMATION_TEXT"],
						text: window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_DELETION_IS_NOT_POSSIBLE_TEXT"] + '<br>' +
							  window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_ERROR_IS_ALREADY_TRACKED_TEXT"]
					});
				}
			})
			/* The request has completed.
			 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
			 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
			 */
			.always(function(response, statusText, jqXHR) {
				console.warn("AJAX always");
				console.warn("response:", response);
				console.warn("statusText:", statusText);
				console.warn("jqXHR:", jqXHR);

				$this
				.removeClass(stateClass);

				// console.log("---[ Done ]---------------------------------------------------");
			});

			// Adds to the event target a data-attribute that triggers immediate deletion of the related list item from the list
//			window.FTKAPP.functions.setAttribute(this,    "data-bind", "deleteListItem");
			// Removes from the event target the data-attribute that triggers immediate deletion of the related list item from the list
//			window.FTKAPP.functions.removeAttribute(this, "data-toggle");
		})

		.on("click",  '[data-bind="deleteListItem"]',              function(evt) {
			// alert("deleteListItem");
			// console.warn("deleteListItem");
			// return false;

			let $this   = $(this),
				$target = $( $this.data("target") ) || $($this.data("parent") || $this.parent()),
				$inputs = $target.find("input[type=text], input[type=file], textarea, select").filter(":input:visible"),
				$empty  = $inputs.filter(function() { return !this.value }),
			mustConfirm      = ($this.data("confirmDelete")      == true),
			mustConfirmEmpty = ($this.data("confirmDeleteEmpty") == true),
			confirmMsg       =  $this.data("confirmDeleteMessage") || window.FTKAPP.translator.map["COM_FTK_DIALOG_ARE_YOU_SURE_TEXT"];

			// console.log("this:", this);
			// console.log("target:", $target);
			// console.log("inputs:", $inputs);
			// console.log("empty:", $empty);
			// console.log("mustConfirm:", mustConfirm);
			// console.log("mustConfirmEmpty:", mustConfirmEmpty);
			// console.log("confirmMsg:", confirmMsg);

			switch (true) {
				case ( mustConfirmEmpty && ($inputs.length == $empty.length)) :
					// console.log("CASE 1");
					if (false === confirm(confirmMsg)) {
						return false;
					}
				break;

				case ( mustConfirm && ($inputs.length > $empty.length)) :
					// console.log("CASE 2");
					if (false === confirm(confirmMsg)) {
						return false;
					}
				break;
			}

			$target
			.queue(function(next) {
				if ($this.is('[data-toggle="tooltip"]')) {
					$this.tooltip("hide");
				} else if ($this.find('[data-toggle="tooltip"]').length > 0) {
					$this.find('[data-toggle="tooltip"]').tooltip("hide");
				}

				next();
			})
			.fadeOut("fast", function() {
				// Get element parent prior deletion.
				let $parent = $(this).parent();

				// Delete element off the DOM.
				$(this).remove();

				// Propagate event.
				$(document.body)
				.trigger("table:rowDeleted", $parent);
			});
		})

		/*.on("click",  '[data-toggle="generatePartDuplicate"]__OFF',function(evt) {
			let $this = $(this),
				$part = $( $this.data("part") ),
				$fieldsets = undefined;

			// Data object to hold all data of all the fieldset to consider.
			let data = new Object;
				data.base = new Array;
				data.processes = new Array;

			// Data object to hold the IDs of the processes selected for duplication.
			let selected = [];

			// The Total duplicates to create.
			let numCopies = 0;

			let urlParams = window.FTKAPP.functions.getAllUrlParams( document.location.href );
			// let partID = urlParams.ptid || 0;

			$fieldsets = $part.find("fieldset.duplicable")
			.each(function(o, fieldset) {
				let $fieldset = $(fieldset),	// same as $(fieldset)
					$fields = undefined,
					procID = $fieldset.data("procId");

				if (typeof procID !== "undefined") {
					data.processes[procID] = data.processes[procID] || new Array;

					$fields = $fieldset.find(":input").not(":hidden")
					.each(function(i, input) {
						let $input = $(input);

						data.processes[procID][$input.attr("name")] = $input.val().trim();
					});
				} else {
					data.base = data.base || new Array;

					$fields = $fieldset.find(":input").not(":hidden")
					.each(function(i, input) {
						let $input = $(input);

						data.base[$input.attr("name")] = $input.val().trim();
					});
				}
			});

			// Update and render main modal dialog.
			let $modal = $("#mainModal");

			$modal
			.on("show.bs.modal", function(evt) {
				console.info("show.bs.modal handler 2");

				let $modal = $(this), $duplicationForm;

				$duplicationForm = $("<form/>", {
					"action" : document.location.href,
					"method" : "POST",
					"class"  : "form-horizontal",
					"id"     : "partProcessesForm"
				});

				// Create and render every process as Checkbox
				for (let procID in data.processes) {
					if (data.processes.hasOwnProperty(procID)) {
						let process = data.processes[procID],
							$checkbox = $("<div/>", {"class": "form-check my-2"});

						$checkbox
						.append(
							$("<input/>", {
								"type"    : "checkbox",
								"class"   : "form-check-input",
								"id"      : "pid-" + procID,
								"name"    : "pid[]",
								"value"   : procID,
								"checked" : "checked"
							})
							.on("change", function(evt) {
								$(this).parent().toggleClass("active");
							})
						)
						.append(
							$("<label/>", {
								"for"   : "pid-" + procID,
								"class" : "form-check-label ml-1",
								"text"  : $('fieldset[data-proc-id="' + procID + '"] > .fieldset-title').text()
							})
						)

						$duplicationForm.append( $checkbox );
					}
				}

				// Update and render main modal dialog.
				$modal
				.find(".modal-title")
					.text(window.FTKAPP.translator.map["COM_FTK_LABEL_PART_DUPLICATE_TEXT"])
					.end()
				.find(".modal-body")
					.html('<p class="alert alert-info">' + window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_WHICH_PROCESSES_TO_DUPLICATE_TEXT"] + '</p>')
					.append( $duplicationForm )
					.end()
				.find(".modal-footer")
					.append(function() {
						// If button exists from previous creation don't create anything.
						if ( $(this).find("button#btnContinue").length > 0 ) {
							return;
						}

						// Return button element.
						return $("<button/>", {
							"class" : "btn btn-sm btn-info btn-submit",
							"id"    : "btnContinue",
							"form"  : "partProcessesForm",
							"text"  : window.FTKAPP.translator.map["COM_FTK_BUTTON_NEXT_TEXT"],
							"title" : window.FTKAPP.translator.map["COM_FTK_LINK_TITLE_CONTINUE_TO_THE_NEXT_STEP_TEXT"],
							"aria-label" : window.FTKAPP.translator.map["COM_FTK_LINK_TITLE_CONTINUE_TO_THE_NEXT_STEP_TEXT"]
						})
						.on("click", function(evt) {
							selected = $duplicationForm.serialize();
							selected = decodeURI( selected );
							selected = window.FTKAPP.functions.getAllUrlParams( "https://webservice.froetek.website?" + selected );
							selected = selected.pid || [];

							$modal
							.find(".modal-footer")
								.find("#btnContinue")
									.remove()
									.end()
								.append(
									$("<button/>", {
										"class" : "btn btn-sm btn-info btn-submit",
										"form"  : "partProcessesForm",
										"text"  : window.FTKAPP.translator.map["COM_FTK_BUTTON_CREATE_TEXT"],
										"title" : window.FTKAPP.translator.map["COM_FTK_BUTTON_CREATE_BATCH_TEXT"],
										"aria-label" : window.FTKAPP.translator.map["COM_FTK_BUTTON_CREATE_BATCH_TEXT"]
									})
									.on("click", function(evt) {
										$duplicationForm.submit();
									})
								)
								.end()
							.find(".modal-body")
								.find("p.alert-info")
									.text(window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_HOW_MANY_DUPLICATES_TO_CREATE_TEXT"])
									.end()
								.find("#partProcessesForm")
									.empty()
									.append(
										$("<input/>", {
											"type"  : "hidden",
											"name"  : "task",
											"value" : "genbatch"
										})
									)
									.queue(function(next) {
										let $form = $(this);

										for (let pid in selected) {
											$(
												$("<input/>", {
													"type"  : "hidden",
													"name"  : "pids[]",
													"value" : selected[pid]
												})
											)
											.appendTo($form);
										}

										next();
									})
									.append(
										$("<label/>", {
											"for"   : "totalDuplicates",
											"class" : "col-form-label sr-only",
											"text"  : window.FTKAPP.translator.map["COM_FTK_LINK_TITLE_NUMBER_OF_DUPLICATES_TEXT"]
										})
									)
									.append(
										$("<input/>", {
											"class" : "form-control",
											"id"    : "totalDuplicates",
											"type"  : "number",
											"name"  : "batch",
											"min"   : "1",
											"max"   : "1000",
											"step"  : "1",
											"placeholder" : window.FTKAPP.translator.map["COM_FTK_LINK_TITLE_NUMBER_OF_DUPLICATES_TEXT"]
										})
										.on("change", function(evt) {
											numCopies = parseInt( $(this).val() );
										})
									)
									.end();
						})
					});
			})
			.modal("show");
		})*/
		.on("click",  '[data-toggle="generatePartID"]',            function(evt) {
			let $this      = $(this),
				$form      = $( "#" + $this.attr("form") ),
				$target    = $( $this.data("target") ),
				action     = $form.data("action") || $form.attr("action"),
				stateClass = "loading",
				$loaderIcon,
				data;

			evt.preventDefault();

			$loaderIcon = $('' +
				'<span class="d-block overlay-spinner position-absolute" style="background: ' + $this.css("background-color") + '; color: ' + $this.css("color") + '">' +
					'<i class="fas fa-spinner fa-pulse fa-2x"></i>' +
				'</span>'
			)/*.css({
				"visibility" : "hidden",
				"width" : $this.innerWidth() + "px",
				"top"  : "0",
				// "left" : "0",
				"margin-left" : (($this.outerWidth() - $this.innerWidth()) / 2) + "px",
				"margin-top" : "2px",
				"background" : $this.css("background-color"),
				"color" : $this.css("color")
			})*/;

			// Inject process icon.
			$this
			.parent()
				.append($loaderIcon)
				.end()
			.addClass(stateClass);

			// Serialize form data.
			data = $form.serialize() + "&format=json&task=" + encodeURI(document.activeElement.getAttribute("value"));	// appended code borrowed from https://stackoverflow.com/a/45717300

			// Parse serialized string into object.
			data = window.FTKAPP.functions.parseQuery( data );

			// Send request.
			$.post(action, data, function(data, textStatus, jqXHR) {
				$target
				.val(data)
				.trigger("focusout");

			}, "json")
			.done(function(response, statusText, jqXHR) {})
			.fail(function(jqXHR, statusText, errorThrown) {
				try {
					//FIXME - display error in modal window rather than executing an undefined callback.
					/* if (typeof window.FTKAPP.functions[callbackOnError] !== "undefined" && typeof window.FTKAPP.functions[callbackOnError] === "function") {
						window.FTKAPP.functions[callbackOnError].call(callbackOnErrorTarget);
					} */

					//TODO - display error in modal window rather than alerting it.
					alert(errorThrown);

				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			.always(function(response, statusText, jqXHR) {
				$this
				.parent()
					.find(".overlay-spinner")
						.remove()
					.end()
				.removeClass(stateClass);
			});
		})

		.on("click",  '[data-toggle="generatePassword"]',          function(evt) {
			let $this   = $(this),
				$form   = $( "#" + $this.attr("form") ),
				$target = $( $this.data("target") ),
				action  = $form.data("action") || $form.attr("action"),
				stateClass = "loading",
				data, $loaderIcon;

			evt.preventDefault();

			$loaderIcon = $('' +
				'<span class="d-block overlay-spinner text-center position-absolute">' +
					'<i class="fas fa-spinner fa-pulse fa-2x"></i>' +
				'</span>'
			)
			.css({
				"visibility" : "hidden",
				"width" : $this.innerWidth() + "px",
				"top"  : "0",
				"margin-left" : (($this.outerWidth() - $this.innerWidth()) / 2) + "px",
				"margin-top" : "2px",
				"background" : $this.css("background-color"),
				"color" : $this.css("color")
			});

			// Inject process icon.
			$this
			.parent()
				.append($loaderIcon)
				.queue(function(next) {
					$loaderIcon
					.css({"visibility"  : "visible"});

					next();
				})
				.end()
			.addClass(stateClass);

			// Serialize form data.
			data = $form.serialize() + "&format=json&task=" + encodeURI(document.activeElement.getAttribute("value"));	// appended code borrowed from https://stackoverflow.com/a/45717300

			// Parse serialized string into object.
			data = window.FTKAPP.functions.parseQuery( data );

			// Send request.
			$.post(action, data, function(data, textStatus, jqXHR) {
				$target
				.val(data)
				.trigger("focusout");

			}, "json")
			.done(function(response, statusText, jqXHR) {})
			.fail(function(jqXHR, statusText, errorThrown) {
				try {
					//FIXME - display error in modal window rather than executing an undefined callback.
					/* if (typeof window.FTKAPP.functions[callbackOnError] !== "undefined" && typeof window.FTKAPP.functions[callbackOnError] === "function") {
						window.FTKAPP.functions[callbackOnError].call(callbackOnErrorTarget);
					} */

					//TODO - display error in modal window rather than alerting it.
					alert(errorThrown);

				} catch (err) {
					console.error("The following error occured:", err)
				}
			})
			.always(function(response, statusText, jqXHR) {
				$this
				.parent()
					.find(".overlay-spinner")
						.remove()
					.end()
				.removeClass(stateClass);
			});
		})

		.on("click",  '[data-toggle="hideElement"]',               function(evt) {
			let $this  = $(this),
				$elem  = $( $this.data("toggleElement") ),
				effect = $( $this.data("toggleEffect") || "fade" );

			if ($elem.length) {
				$elem.each(function() {
					switch (effect) {
						case "fade" :
							$elem.fadeOut(function() {
								$elem.remove();
							});
						break;

						case "slide" :
							$elem.slideUp(function() {
								$elem.remove();
							});
						break;

						default :
							$elem.hide(function() {
								$elem.remove();
							});
					}
				});
			}
		})

		.on("click", '[data-toggle="bootstrap4dialog"]',           function(evt) {
			let $this = $(this), content = "";

			/* The Bootstrap4Dialog is rendered on the fly.
			 * Hence, the element that toggles such a dialog on click-event must have a data-attribute
			 * with the base64-encoded Bootstrap4Dialog initialisation script.
			 * This base64-encoded string is then decoded and passed to the eval() function for execution.
			 */
			content = $this.data("dialog") || "";

			if (typeof Base64 === "object" && Base64.extendString) {
				// We have to explicitly extend String.prototype prior using the Base64-methods.
				Base64.extendString();

				// Once extended, we can do the following to decode the data.
				content = content.fromBase64();
			} else {
				content = atob(content);
			}

			/* This is how the dialog is toggled in the Bootstrap4Dialog examples file, where
			 * every example is executed on click onto a button labelled "Run code".
			 * see: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/eval
			 */
			eval(content);
		})

		.on("click",  '[data-toggle="reset"]',                     function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") ),
				eBefore = $.Event("beforeReset.ftk.element"),
				eAfter  = $.Event("afterReset.ftk.element");

			$target
			.trigger(eBefore, $target)
			.val("")
			.trigger(eAfter, $target);
		})
		.on("click",  '[data-toggle="resetCellStyle"]',            function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") );

			$target.each(function(i, elem) {
				let $this = $(this);

				if ($this.is(".bs-colorpicker")) {
					$this
					.val("")
					.colorpicker("setValue", null);
				}

				if ($this.is(":checkbox")) {
					$this
					.attr({"checked" : false})
					.prop({"checked" : false});
				}

				if ($this.is(".btn-checkbox.active")) {
					$this
					.removeClass("active")
					.trigger("click");
				}
			});
		})
		.on("click",  '[data-toggle="resetCellStyleAll"]',         function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") );

			$target.trigger("click");
		})

		.on("click",  '[data-bind="switchButtonText"]',            function(evt) {
			let $this = $(this),
				$icon = $this.find("i"),
				$text = $this.find(".btn-text"),
			initialText     = $this.data("textInitial"),
			replacementText = $this.data("textReplacement");

			if ($text.text() == initialText) {
				$text.text(replacementText);

				$icon
				.addClass("fa-caret-up")
				.removeClass("fa-caret-down");
			} else {
				$text.text(initialText);

				$icon
				.addClass("fa-caret-down")
				.removeClass("fa-caret-up");
			}
		})

		/* Forces window object to navigate to clicked URI.
		 * It happends that when a URI ends with a hash (#anchor) the window doesn't reload but scroll to the anchor instead.
		 * This handler is a workaround.
		 */
		.on("click",  '[data-bind="forceWindowNavigation"]',       function(evt) {
			evt.preventDefault();

			document.location.href = $(this).attr("href");
		})

		.on("click",  '[data-bind="windowClose"]',                 function(evt) {
			if (window.opener !== null) {
				evt.preventDefault();

				window.opener.focus();

				if (true == $(this).data("forceReload")) {
					window.opener.location.reload();
				}

				setTimeout(function() { window.close() }, 200);
			} else {
				// console.log("previous URL was:", document.referrer, window.history);
				// window.location.replace(document.referrer);
				// window.close();	// Not working + ending up with console message: "Scripts may close only the windows that were opened by them."
				window.history.back();
			}
		})
		.on("click",  '[data-bind="windowOpen"]',                  function(evt) {
			evt.preventDefault();

			window.open( $(this).data("location"), $(this).data("locationTarget" || "_blank") );
		})

		// Handler for book parts view.
		// Changes button color(s) and icon(s) according to current processing state (fetched from the form's AJAX state).
//		.on("click",  "form.bookPartsForm > button",               function(evt) {
		.on("click",  'form.bookPartsForm > button[data-bind="copyValue"]', function(evt) {
			// console.warn('button[data-bind="copyValue"] click-Event handler');
			// return false;

			// Init vars.
			let $button = $(this),
				$buttonForm = $( "#" + $button.attr("form") ),
				$buttonIcon = $button.find("> i");

			// Add "processing"-state to the clicked button.
			if ($button.data("classProcessing")) {
				$button
				.removeClass( $button.data("classDefault") )
				.addClass( $button.data("classProcessing") );
			}

			// Add visual feedback to the clicked button to reflect its current state.
			if ($buttonIcon.data("classProcessing")) {
				$buttonIcon
				.removeClass( $buttonIcon.data("classDefault") )
				.addClass( $buttonIcon.data("classProcessing") );
			}

			// Submit form via AJAX.
			if ($button.attr("type").toLowerCase() == "submit") {
				// Prevent the form from being submitted, which is gonna happen when the button is of type submit.
				$button
				.attr({"type" : "button"});

				// Bind to events that are triggered by the global AJAX form submit handler in line 5533 ff.
				$buttonForm
				.on("submit.ftk.form",    function(evt) {})
				.on("fail.ftk.form",      function(evt) {
					$buttonIcon
					.removeClass( $buttonIcon.data("classProcessing") )
					.addClass( $buttonIcon.data("classFail") );

					$button
					.removeClass( $button.data("classProcessing") )
					.addClass( $button.data("classFail") );
				})
				.on("done.ftk.form",      function(evt) {
					$buttonIcon
					.removeClass( $buttonIcon.data("classProcessing") )
					.addClass( $buttonIcon.data("classSuccess") );

					$button
					.removeClass( $button.data("classProcessing") )
					.addClass( $button.data("classSuccess") )
					.trigger( $button.data("bind") );

					// Next is to trigger the 'form.bookPartsForm > button[data-bind="copyValue"]' in line 5263

					$(document)
					.trigger("")
				})
				.on("submitted.ftk.form", function(evt, args) {
					// console.log("Request submitted-handler");
					// console.log("Event:", evt);
					// console.log("Args:", args);
				})
				.on("always.ftk.form",    function(evt, args) {
					// console.log("Request always-handler");
					// console.log("Event:", evt);
					// console.log("Args:", args);
				})
				.submit();
			}
		})
		// Handler for book parts view.
		// Copies number of parts booked from column "not booked" to column "current date".
//		.on("copyValue", "form.bookPartsForm > button",            function(evt) {
		.on("copyValue", 'form.bookPartsForm > button[data-bind="copyValue"]', function(evt) {
			// console.warn('button[data-bind="copyValue"] copyValue-Event handler');
			// return false;

			let $this = $(this),
				$elemCopyFrom = $( $this.data("copyFrom") ),
				$elemCopyTo   = $( $this.data("copyTo") ),
				copyData      = $elemCopyFrom.text(),
				copyDelete    = $this.data("copyDelete") || false,
				copyDisable   = $this.data("copyDisable") || false;

			if ($elemCopyFrom && $elemCopyTo && copyData) {
				let $elemCopyToChildren = $elemCopyTo.children();

				if (copyDelete) {
					$elemCopyFrom.empty();
				}

				if ($elemCopyToChildren.length) {
					$elemCopyToChildren.filter(":last").text( copyData );
				} else {
					$elemCopyTo.text( copyData );
				}

				if (copyDisable) {
					$this
					.prop({disabled : true})
					.attr({"disabled" : "disabled"});
				}
			}
		})

		// Handler for the banderole toggle in the article process measurement definitions section.
		// Changes visibility of the toggle after all rows are deleted.
		.on("click", 'table.process-measurement-tracking > thead > tr > th[id$="mpToolbar"] > .btn-add', function(evt) {
			$(this).closest("table").find("> thead").addClass("banderolable");
		})
		.on("click", 'table.process-measurement-tracking > tbody > tr > td[id$="mpToolbar"]', function(evt) {
			let $this = $(this),	// should be the trashbin button
				$table = $this.closest("table"),
				$tbody = $table.find("> tbody");

			// This event is triggered before the row is removed. Hence, we must evaluate for
			// at least 1 row that is getting removed after this handler finished its job.
			if ($tbody.children().length <= 1) {
				$table
				.closest(".card.alert-success")
					.removeClass("alert-success")
					.end()
				.find("> thead")
					.removeClass("banderolable")
					.find(".btn-banderole.active")
						.removeClass("active")
						.find("> :input[checked]")
							.removeAttr("checked")
							.end()
						.end()
					.end();
			}
		})

		// Handler for navigation within process tracking measuring data table via keyboard arrow keys.
		// FIXME - use event-binding to get rid of hardcoded form names and provide more flexibility
		.on("keydown", 'form[name="editPartForm"] .list-item.process.tracking .table.process-measuring-data td .form-control', function(evt) {
			// console.warn("keydown-event:", evt.keyCode, evt);

			let $this     = $(this),						// input element
				$listItem = $this.closest(".list-item"),	// process list item container
				$parentTD = $this.closest("td"),			// parent <td/> element
				$parentTR = $parentTD.parent(),				// parent <tr/> element
				$tbody    = $this.closest("tbody"),
				$thisPrev = $parentTD.prev().find(":input"),
				$thisNext = $parentTD.next().find(":input"),
				fieldName = $this.attr("class").match(new RegExp(/\bmp[^\s]+\b/));
				fieldName = fieldName.length ? fieldName.shift() : "mpInput";
			let
			isAltKeyPressed  = evt.altKey,
			isCtrlKeyPressed = evt.ctrlKey || evt.metaKey, // Mac support
				$thisPrevRow = $parentTR.prev().find(":input." + fieldName),
				$thisNextRow = $parentTR.next().find(":input." + fieldName);

			// console.warn("keydown-event:", evt.keyCode, evt, isCtrlKeyPressed, isAltKeyPressed);

			switch (evt.keyCode) {
				case  9 :	// console.info("   TAB-key pressed");
					evt.preventDefault();

					window.FTKAPP.functions.renderMessage({
							type: "info",
							text: window.FTKAPP.translator.map["COM_FTK_HINT_NAVIGATION_VIA_ARROW_KEYS_TEXT"]
						}
					);
				break;

				case 37 :	// console.info("   arrow-LEFT-key pressed");
					evt.preventDefault();

					if ($thisPrev.length) {
						$thisPrev.focus();
					}
				break;

				case 38 :	// console.info("   arrow-UP-key pressed");
					evt.preventDefault();

					if ($thisPrevRow.length) {
						$thisPrevRow.focus();
					}
				break;

				case 39 :	// console.info("   arrow-RIGHT-key pressed");
					evt.preventDefault();

					if ($thisNext.length) {
						$thisNext.focus();
					}
				break;

				case 40 :	// console.info("   arrow-DOWN-key pressed");
					evt.preventDefault();

					if ($thisNextRow.length) {
						$thisNextRow.focus();
					}
				break;

				case 8 :	// console.info("   BACKSPACE-key pressed");
				break;

				case 13 :	// console.info("   RETURN-key pressed");
					// NOTE:  Content is generated with manual laser scanner which sends a "RETURN"-key
					//        as final character that causes a form submit after every scan, which is undesired behavior.
					evt.preventDefault();

					if (isCtrlKeyPressed) {
						$listItem
						.find('button[type="submit"][data-target="#' + $listItem.attr("id") + '"')
							.click();
					} else {
						// Prevent form submit event.
						// Instead, we jump into same field in next line.
						if ($thisNextRow.length) {
							$thisNextRow.focus();
						}
					}
				break;

				case 27 :	// console.info("   ESC-key pressed");
				break;
			}
		})

		// DISABLED ON 2020-02-28 - Handling is buggy
		/*.on("keyup__DISABLED",  '[data-toggle="fixTrackingcodeFormat"]',     function(evt) {
			// Don't respond to navigation and deletion.
			if (
				evt.keyCode == "8"	// backspace
				||
				evt.keyCode == "37"	// arrow-left
				||
				evt.keyCode == "39"	// arrow-right
			) {
				return;
			}

			let $this = $(this),
				  val = "" + $this.val().replaceAll("--", "-").trim();

			switch (val.length) {
				// Inject hyphen
				case 3 :
					$this.val(val += "-");
				break;

				case 7 :
					$this.val(val += "-");
				break;

				// Build proper trackingcode (clean first and group afterwards)
				case 9 :
					val = val.replace("-", "");

					$this.val([val.substr(0, 3), val.substr(3,3), val.substr(6,3)].join("-"));
				break;

				// Clean input
				default :
					val = val.replace("--", "-");

					if (val.trim().length > 9) {
						val = val.replace(/(^.*)-$/, "$1");
					}

					$this.val(val);
			}
		})*/

		.on("submit",  "form",                                     function(evt) {	// Fires when a form is submitted to submit the data to the sever.
			// console.log("---[ line 6343 ]----------------------------------------------");
			// console.warn("form.submit event 2: --> send data");
			// return false;

			let $this    = $(this),
				$form    = $(evt.target),
				$target  = $($form.data("target")),
				trigger  = $form.data("trigger") || "load",
				eBefore  = $.Event(trigger + ".ftk.form"),
				eFail    = $.Event("fail.ftk.form"),
				eDone    = $.Event("done.ftk.form"),
				eAlways  = $.Event("always.ftk.form"),
				eAfter   = $.Event((trigger.substring(trigger.length - 1) !== 't' ? trigger : trigger + 't') + "ed.ftk.form"),
			  stateClass = "submitted",
			 submitType  = $form.data("submit"),
				 format  = $form.data("format"),
				 action  = $form.data("action") || $form.attr("action"),
				 method  = $form.attr("method"),
				 ajax, data, callbackName = window.FTKAPP.functions.generateRandomFunctionName(true);

			// console.log("submitType:", submitType);
			// console.log("format:", format);
			// console.log("method:", method);

			//FIXME - Find out why callback function is not a function and thus not callable !!!

			let callbackOnError         = $form.data("modalOnError"),
				callbackOnErrorTarget   = $form.data("modalOnErrorTarget"),
				callbackOnSuccess       = $form.data("modalOnSuccess"),
				callbackOnSuccessTarget = $form.data("modalOnSuccessTarget");

			if (typeof callbackOnError   !== "undefined") {
				callbackOnError     = callbackOnError.split(":").pop();
			}

			if (typeof callbackOnSuccess !== "undefined") {
				callbackOnSuccess   = callbackOnSuccess.split(":").pop();
			}

			//@debug - disabled to prevent AJAX requests from being executed while debugging why function linkToText is not available
			// return false;

			$form
			.addClass(stateClass)
			.find('input[name="format"]')
				.val((format === "json" ? "json" : ""));

			// AJAX
			if (submitType === "ajax") {
				// alert("AJAX-submit");
				evt.preventDefault();
				// return false;

				// Propagate
				// $form
				// .trigger(eBefore);

				$target
				.addClass(stateClass);

				ajax = {
					type:     method.toUpperCase(),
					url:      action,
					data:     null,
					dataType: format,
					cache:    false
				};

				/*// HTTP authentication. Do not use, because the information is cached in the PHP global $_SERVER and cannot be unset.
				const $authName = $form.find('input[name="authname"]'),
					  $authPass = $form.find('input[name="authpass"]');

				if ($authName && $authPass) {
					ajax = $.extend(ajax, {
						// headers: {"Authorization": "Basic " + btoa($authName.val() + ":" + $authPass.val())},
						beforeSend: function(jqXHR) {
							jqXHR.setRequestHeader("Authorization", "Basic " + btoa($authName.val() + ":" + $authPass.val()));
						}
					});
				}

				$authName.add($authPass).remove();*/

				if (format === "jsonp") {
					// console.warn("AJAX with jsonp");
					// console.log("callbackName:", callbackName);

					// Add JsonP callback function name to the jQuery AJAX configuration object.
					ajax = $.extend(ajax, {
						jsonpCallback: callbackName
					});

					// Make callback function callable.
					// Inspired by https://www.youtube.com/watch?v=3AoeiQa8mY8
					window[callbackName] = window[callbackName] || function(data) {
						// console.warn("Executing json callback");
						// console.log(data);

						// Display message if there is any.
						if (data.hasOwnProperty("feedback") &&
							typeof data.feedback === "object" &&
							data.feedback.hasOwnProperty("message") &&
							typeof data.feedback.message === "object" &&
							data.feedback.message.display
						) {
							window.FTKAPP.functions.renderMessage({
								type: data.feedback.message.type || "notice",
								text: data.feedback.message.text
							});
						}

						// Execute task(s) if there are any.
						if (data.hasOwnProperty("action") && data.action !== null) {
							const action = data.action;

							// console.log("action:", action);

							if ((action.hasOwnProperty("redirect") && action.redirect) &&
								(action.hasOwnProperty("target")   && action.target)
							) {
								const redirect = function() {
									window.location.replace(action.target);
								};

								if (action.hasOwnProperty("delay") && parseInt(action.delay) > 0) {
									setTimeout(redirect, parseInt(action.delay));
								} else {
									redirect();
								}
							}
						}
					};
				}

				// console.log("AJAX object:", ajax);

				// $target.fadeOut("fast");

				data = $form.serialize();

				// console.log("POST data:", data);
				// return false;

				// Don't forget the payload.
				ajax.data = data;

				$.ajax(ajax)	// Submit form data
				/* The request failed.
				 */
				.fail(function(jqXHR, statusText, errorThrown) {
					// console.warn("AJAX error");
					// console.log("jqXHR:", jqXHR);
					// console.log("statusText:", statusText);
					// console.log("errorThrown:", errorThrown);

					// Propagate
					$form
					.trigger(eFail, [jqXHR, statusText, errorThrown]);

					try {
						if (typeof window.FTKAPP.functions[callbackOnError] !== "undefined" && typeof window.FTKAPP.functions[callbackOnError] === "function") {
							window.FTKAPP.functions[callbackOnError].call(callbackOnErrorTarget);
						}
					} catch (err) {
						console.error("The following error occured:", err)
					}
				})
				/* The request was successful.
				 */
				.done(function(response, statusText, jqXHR) {
					// console.warn("AJAX success");
					// console.log("response:", response, typeof response);
					// console.log("statusText:", statusText);
					// console.log("jqXHR:", jqXHR);

					try {
						// $form.removeOverlay();

						$target
						.fadeOut("fast", function() {
							$target
							.html(response)
							.queue(function(next) {
								if (typeof window.FTKAPP.functions[callbackOnSuccess] !== "undefined" && typeof window.FTKAPP.functions[callbackOnSuccess] === "function") {
									window.FTKAPP.functions[callbackOnSuccess].call(callbackOnSuccessTarget);
								}

								if ($target.is(":hidden")) {
									$target.fadeIn("slow", function() {
										$(this).removeClass(stateClass);
									});
								}

								next();
							})
						})
						.removeClass(stateClass);

						$form
						// Clear hashCodes to reset form changes monitor
						.data({
							hashCode1: undefined,
							hashCode2: undefined
						})
						// Propagate
						.trigger(eDone, [response, statusText, jqXHR]);

					} catch (err) {
						console.error("The following error occured:", err)
					}
				})
				/* The request has completed.
				 * In case the request was successful the function parameters equal those of done() and are: data, statusText, jqXHR
				 * In case the request failed the function parameters equal those of fail() and are: jqXHR, statusText, errorThrown
				 */
				.always(function(response, statusText, jqXHR) {
					// console.warn("AJAX always");
					// console.log("response:", response, typeof response);
					// console.log("statusText:", statusText);
					// console.log("jqXHR:", jqXHR);

					// console.log("---[ Done ]---------------------------------------------------");

					$form
					.removeClass(stateClass)
					// Propagate
					.trigger(eAfter,  [response, statusText, jqXHR])
					.trigger(eAlways, [response, statusText, jqXHR]);
				});
			}
			// REGULAR
			else if (submitType !== "ajax") {
				// alert("regular submit");
				// evt.preventDefault();
				// return false;

				// Clear hashCode(s) to reset form changes monitor.
				$form.data({
					hashCode1: undefined,
					hashCode2: undefined
				});

				// console.log("---[ Done ]---------------------------------------------------");
			}
			else {
				throw "Unhandled submit type";
				return false;
			}
		})

		/* Method to serialize form data into window.sessionStorage prior submit.
		 * Works in conjunction with "form.autoFillable"-handler, where form data
		 * is read from the browser sessionStorage object and filled into the form.
		 */
		.on("submit",  "form.autoSerializable",                    function(evt) {	// Fires when a form is submitted to serialize it
			// console.log("---[ line 6598 ]----------------------------------------------");
			// console.warn("form.submit event 3: --> serialize form data and dump it in window.sessionStorage");
			// alert("form submit event interrupted"); return false;

			let formData;

			if (typeof window.sessionStorage === "object") {
				formData = JSON.stringify( $(this).serializeToObject(true) );

				// console.dir(formData);
				// console.log("forms." + $(this).attr("id") + ".data");

				window.sessionStorage.setItem( "forms." + $(this).attr("id") + ".data", JSON.stringify( $(this).serializeToObject(true) ) );
			} else {
				console.warn("SessionStorage object is unavailable.");
			}

			// return false;

			console.log("---[ Done ]---------------------------------------------------");
		})

		/* Method to disable all empty required fields of a form.
		 * This method disabled validation errors for required fields on form submit.
		 */
		.on("click",   '[data-bind="disableEmptyFields"]',         function(evt) {
			let $this = $(this),
				form  = $this.attr("form"),
				$form = $(document.forms[form]);

			if ($form.length) {
				$form.find(":input[required]:empty")
				.prop("disabled", true)
				.attr("disabled", true);
			}
		})

		/*// Filter for list views (disabled in June 2021 after replacing the simple button with a popout menu)
		.on("click",   '[data-bind="filterList"]',                 function(evt) {
			let $this = $(this),
				$target = $( $this.data("target") );

			$target
			.find(".list-item-hidden")
				.toggleClass("d-none");
		})*/

		/* Handler for autocompletion on form input elements having a dynamically attached datalist like dropdown
		 * like those added in the [data-bind="addTechnicalParam"] event handler.
		 * Implementation inspired by this article on the Internet:   https://www.w3schools.com/howto/howto_js_autocomplete.asp
		 * More about the difference between jQuery's change-, input- and keyX-events can be found here:   https://stackoverflow.com/a/17047607
		 * Event handlers are listed in the order they are fired.
		 */
		.on("keydown", '[data-bind="parseDatalist"]',              function(evt) {
			// console.warn('[data-bind="parseDatalist"] - keydown-event handler');

			let $this = $(this),
				  val = $this.val(),
				$form = $this.closest("form"),
				$target = $( $this.data("target") ),
				$list   = $this.parent().find("#" + this.id + "-autocomplete-list"),
				$listItems = $target.find("> .autocomplete-item"),
				dataList;

			// Get reference to the autocomplete-list for the currently focused element
			dataList = document.getElementById( this.id + "-autocomplete-list" );

			if (dataList) {
				dataList = dataList.getElementsByTagName("div");
			}
			/*else {
				// console.log("   datalist for this element NOT found.");
			}*/

			// console.log("   keydown-event dataList:", dataList);

			// Method to classify an item as "active":
			function addActive(dataList) {
				// console.info("addActive");

				// console.log('   3. currentFocus is:', currentFocus);
				// console.log('   3. $form.data("currentFocus") is:', $form.data("currentFocus"));

				if (!dataList) {
					// console.log("  datalist NOT found.");
					return false;
				}
				/*else {
					// console.log("   datalist found.");
				}*/

				/* start by removing the "active" class on all items: */
				removeActive(dataList);

				// console.log('   Set as $form.data("currentFocus"): ', '0');

				if ($form.data("currentFocus") >= dataList.length) {
					$form.data({currentFocus: 0});
				}

				// console.log('   4. $form.data("currentFocus") is:', $form.data("currentFocus"));
				// console.log('   Set as $form.data("currentFocus"): ', (dataList.length - 1));

				if ($form.data("currentFocus") < 0) {
					$form.data({currentFocus: (dataList.length - 1)});
				}

				// console.log('   5. $form.data("currentFocus") is:', $form.data("currentFocus"));

				// currentFocus = $form.data("currentFocus");

				// console.log("   6. currentFocus is:", currentFocus);
				// console.log('   6. $form.data("currentFocus") is:', $form.data("currentFocus"));

				//FIXME
				/* add class "autocomplete-active": */
				dataList[$form.data("currentFocus")].classList.add("autocomplete-active");
			}
			// Method to remove the "active" class from all autocomplete items:
			function removeActive(dataList) {
				// console.info("removeActive");

				// console.log('   7. currentFocus is:', currentFocus);
				// console.log('   7. $form.data("currentFocus") is:', $form.data("currentFocus"));

				if (!dataList) {
					// console.log("   datalist NOT found.");
					return false;
				// } else {
					// console.log("   datalist found.");
				}

				for (let i = 0; i < dataList.length; i++) {
					dataList[i].classList.remove("autocomplete-active");
				}
			}

			// console.log('   8. currentFocus is:', currentFocus);
			// console.log('   8. $form.data("currentFocus") is:', $form.data("currentFocus"));

			// console.warn("   Key pressed:", evt.keyCode);

			// arrow DOWN
			if (evt.keyCode == 40) {
				// console.info("   arrow DOWN key pressed");
				/* If the arrow DOWN key is pressed, increase the currentFocus variable: */

				// console.log('   Increment $form.data("currentFocus")');
				// $form.data({currentFocus: currentFocus++});
				$form.data({currentFocus: $form.data("currentFocus") + 1});

				// console.log('   9. $form.data("currentFocus") is now:', $form.data("currentFocus"));
				// currentFocus = $form.data("currentFocus");

				/* and and make the current item more visible: */
				addActive(dataList);

				// console.log('   10. $form.data("currentFocus") is now:', $form.data("currentFocus"));
			}
			// arrow UP
			else if (evt.keyCode == 38) {
				// console.info("   arrow UP key pressed");
				/* If the arrow UP key is pressed, decrease the currentFocus variable: */

				// console.log('   Decrement $form.data("currentFocus")');
				// $form.data({currentFocus: currentFocus--});
				$form.data({currentFocus: $form.data("currentFocus") - 1});

				// console.log('   10. $form.data("currentFocus") is now:', $form.data("currentFocus"));
				// currentFocus = $form.data("currentFocus");

				/* and and make the current item more visible: */
				addActive(dataList);

				// console.log('   10. $form.data("currentFocus") is now:', $form.data("currentFocus"));
			}
			// ESC
			else if (evt.keyCode == 27) {
				// console.info("   ESC key pressed");
				/* If the ESC key is pressed, just close the list and leave the element: */

				// console.log('   Decrement $form.data("currentFocus")');
				// $form.data({currentFocus: currentFocus--});
				// $form.data({currentFocus: $form.data("currentFocus") - 1});

				// Clear reset element
				$this.val("");

				if (typeof window.FTKAPP.functions["closeAllDatalists"] !== "undefined" && typeof window.FTKAPP.functions["closeAllDatalists"] === "function") {
					window.FTKAPP.functions["closeAllDatalists"].call($this.get(0));	// ESC key pressed
				}

				// console.log('   10. $form.data("currentFocus") is now:', $form.data("currentFocus"));
			}
			// RETURN
			else if (evt.keyCode == 13) {
				// console.info("   RETURN key pressed");
				/*If the ENTER key is pressed, prevent the form from being submitted,*/
				evt.preventDefault();

				// console.info("   Form submit event supressed");

				// console.log('   10. $form.data("currentFocus") is now:', $form.data("currentFocus"));

				if ($form.data("currentFocus") > -1) {
					/* and simulate a click on the "active" item: */
					if (dataList) {
						dataList[$form.data("currentFocus")].click();
					}
				}
				else {
					if (typeof window.FTKAPP.functions["closeAllDatalists"] !== "undefined" && typeof window.FTKAPP.functions["closeAllDatalists"] === "function") {
						window.FTKAPP.functions["closeAllDatalists"].call($this.get(0));	// RETURN key pressed
					}
				}

				// Hide all but the currently selected list item in the list.
				$listItems.each(function() {
					let $el = $(this);

					if ($el.data("rel").toLowerCase() !== $this.val().trim().toLowerCase()) {
						$el.hide();
					}
				});
			}

			// console.log("   keydown-event dataList:", dataList);
		})
		.on("input",   '[data-bind="parseDatalist"]',              function(evt) {
			// console.warn('[data-bind="parseDatalist"] - input-event handler');

			let $this = $(this),
				  val = $this.val(),
				$form   = $this.closest("form"),
				$target = $( $this.data("target") ),
				$list   = $this.parent().find("#" + this.id + "-autocomplete-list"),
				$listItems = $target.find("> .autocomplete-item"),
				a, b, i, dataList, regexp, isArray = false, isObject = false;

			/* close any already open lists of autocompleted values */
			/*if (typeof window.FTKAPP.functions["closeAllDatalists"] !== "undefined" && typeof window.FTKAPP.functions["closeAllDatalists"] === "function") {
				window.FTKAPP.functions["closeAllDatalists"].call($this.get(0));
			}*/

			if (!val) {
				// Reset list and show all previously hidden list elements (filtered by user input)
				$list.remove();

				return false;
			}

			$form.data({currentFocus: -1});

			/* append the DIV element as a child of the autocomplete container: */
			if (!$list.length) {
				/* create a DIV element that will contain the items (values): */
				a = document.createElement("DIV");
				a.setAttribute("id",    this.id + "-autocomplete-list");
				a.setAttribute("class", "autocomplete-items autocomplete-list autocomplete-datalist bg-white border-dark py-2");
				a.setAttribute("style", "display:none");

				$this.parent().append(a);
			} else {
				/* create a DIV element that will contain the items (values): */
				a = document.createElement("DIV");
				a.setAttribute("id",    this.id + "-autocomplete-list");
				a.setAttribute("class", "autocomplete-items autocomplete-list autocomplete-datalist bg-white border-dark py-2");
				a.setAttribute("style", "display:none");

				$list.replaceWith(a);
			}

			// Set size of datalist element calculated from its related input element
			// TODO - find a way how to trigger recalculation on wind
			$(a).css({
				"width": $this.outerWidth(),
				"margin-left": parseInt($this.prev().width()) - parseInt( window.getComputedStyle(this)["border-left-width"] )
			});

			/* utilize a variable to reference the previously loaded data array */

			dataList = $form.data( $form.data("load") + "List" );
			isObject = $.isPlainObject(dataList);
			isArray  = $.isArray(dataList);

			/* for each item in the array... */
			if (isArray) {
				for (i = 0; i < dataList.length; i++) {
					/* check if the item starts with the same letters as the text field value: */
					if (dataList[i].substr(0, val.length).toUpperCase() == val.toUpperCase()) {
						/* create a DIV element for each matching element: */
						b = document.createElement("DIV");
						b.setAttribute("class", "autocomplete-item");
						/* make the matching letters bold: */
						b.innerHTML  = "<strong>" + dataList[i].substr(0, val.length) + "</strong>";
						b.innerHTML += dataList[i].substr(val.length);
						/* insert a input field that will hold the current array item's value: */
						b.innerHTML += "<input type='hidden' value='" + dataList[i] + "'>";

						/* execute a function when someone clicks on the item value (DIV element): */
						b.addEventListener("click", function() {
							// console.info("datalist item click-event");
							// console.log("this:", this);
							/* insert the value for the autocomplete text field: */
							$this.val(this.getElementsByTagName("input")[0].value);

							/*$listItems.each(function() {
								let $el = $(this);

								if ($el.data("rel").toLowerCase() !== $this.val().trim().toLowerCase()) {
									$el.hide();
								}
							});*/
							$(a).remove();

							/* close the list of autocompleted values, (or any other open lists of autocompleted values: */
							if (typeof window.FTKAPP.functions["closeAllDatalists"] !== "undefined" && typeof window.FTKAPP.functions["closeAllDatalists"] === "function") {
								window.FTKAPP.functions["closeAllDatalists"].call($this.get(0));
							}
							/*else {
								console.warn("Function 'closeAllDatalists' is undefined.");
							}*/
						});

						// a.appendChild(b);
						$(a).append(b);
					}
					/*else {
						console.warn(dataList[i], "does not match input value", val);
					}*/

					// Apply filter and hide/show matching list elements
					regexp = new RegExp("^" + val.toLowerCase(), "ig");
					// console.log("filter using regex:", regexp);

					$listItems.filter(function(idx) {
						let $el = $(this);

						// Element matches user input --> SHOW
						if ($el.data("rel").toLowerCase().match(regexp)) {
							$el.show();

							return true;
						}
						// Element does not match user input --> HIDE
						else {
							$el.hide();

							return false;
						}
					});
				}
			}
			else if (isObject) {
				// console.warn("datalist isObject (" + dataList.length + ") ");

				// ES 6 - Ansatz
				/*Object.keys(dataList).forEach(key => {
					let value = obj[key];
					//use key and value here
				});*/

				// ES 5 - Ansatz
				for (let key in dataList) {
					if (dataList.hasOwnProperty(key)) {
						if (key.substr(0, val.length).toUpperCase() === val.toUpperCase()) {
							/* create a DIV element for each matching element: */
							b = document.createElement("DIV");
							b.setAttribute("class", "autocomplete-item");
							/* make the matching letters bold: */
							b.innerHTML  = "<strong>" + key.substr(0, val.length) + "</strong>";
							b.innerHTML += key.substr(val.length);
							b.innerHTML += "<span class='text-muted ml-3'>(" + dataList[key] + ")</span>";
							/* insert a input field that will hold the current array item's value: */
							b.innerHTML += "<input type='hidden' value='" + key + "'>";

							/* execute a function when someone clicks on the item value (DIV element): */
							b.addEventListener("click", function() {
								// console.info("datalist item click-event");
								// console.log("this:", this);
								/* insert the value for the autocomplete text field: */
								$this.val(this.getElementsByTagName("input")[0].value);

								/* $listItems.each(function() {
									let $el = $(this);

									if ($el.data("rel").toLowerCase() !== $this.val().trim().toLowerCase()) {
										$el.hide();
									}
								}); */
								$(a).remove();

								/* close the list of autocompleted values, (or any other open lists of autocompleted values: */
								if (typeof window.FTKAPP.functions["closeAllDatalists"] !== "undefined" && typeof window.FTKAPP.functions["closeAllDatalists"] === "function") {
									window.FTKAPP.functions["closeAllDatalists"].call($this.get(0));
								}
								/*else {
									console.warn("Function 'closeAllDatalists' is undefined.");
								}*/
							});

							a.appendChild(b);
						}
					}
					/*else {
						console.warn(dataList[i], "does not match input value:", val);
					}*/

					// Apply filter and hide/show matching list elements
					regexp = new RegExp("^" + val.toLowerCase(), "ig");
					// console.log("filter using regex:", regexp);

					$listItems.each(function() {
						let $el = $(this);

						// Element matches user input --> SHOW
						if ($el.data("rel").toLowerCase().match(regexp)) {
							$el.show();
						}
						// Element does not match user input --> HIDE
						else {
							$el.hide();
						}
					});
				}
			}

			let $a = $(a);

			if ($a.children().length) {
				$a.fadeIn("fast");
			}
		})
		.on("keyup",   '[data-bind="parseDatalist"]',              function(evt) {
			// console.warn('[data-bind="parseDatalist"] - keyup-event handler');
		})

		/* Handler for nominal value fields in view "project throughput" aka article matrix
		 * that trigger the automatic application of background colour (according to the legend)
		 * to the corresponding monitoring cells
		 */
		.on("keydown", '[data-bind="dataMirroring"]',              function(evt) {
//			console.warn('[data-bind="dataMirroring"] - keydown-event handler', evt.keyCode);

			// Ensure there is only numerical input or some functional keys are pressed.
			// This way the keyup handler is relieved.
			switch (true) {
				// acceptable keyCodes
				case (evt.keyCode  >= 48 && evt.keyCode <=  57) || (evt.keyCode >= 96 && evt.keyCode <= 105) : // numbers on keypad or in upper line (above letters)
				case  evt.keyCode  ==  35 :	// End
				case  evt.keyCode  ==  36 :	// Pos1
				case  evt.keyCode  ==  37 :	// Arrow-LEFT
				case  evt.keyCode  ==  38 :	// Arrow-UP
				case  evt.keyCode  ==  39 :	// Arrow-RIGHT
				case  evt.keyCode  ==  40 :	// Arrow-DOWN
				case  evt.keyCode  ==  46 :	// Del
				case  evt.keyCode  == 116 :	// F5
				case  evt.keyCode  ==   9 :	// TAB
				case  evt.keyCode  ==   8 :	// Backspace
					return true;
				break;

				// this way the keyup-event is also not handled and bad user input ignored
				default :
					return false;
			}
		})
		.on("keyup",   '[data-bind="dataMirroring"]',              function(evt) {
//			console.warn('[data-bind="dataMirroring"] - keyup-event handler');

			// Don't continue on tab key pressed (user is navigating through the form).
			if (evt.keyCode == "9") {
				return false;
			}

			// Get default colorizing thresholds as defined by MS and confirmed by SM from JavaScript FTKAPP-object.
			const range = window.FTKAPP.functions.array_mapToIntegers( Object.keys( FTKAPP.config.defaults.project.matrix.colors.cell ) ),
				  count = range.length,
				  first = window.FTKAPP.functions.array_key_first(range),
				   last = window.FTKAPP.functions.array_key_last(range);

			// Input test passed. Continue processing.
			let $this = $(this),
			  $target = $this.closest("tr").find(".matrix-cell"),
			threshold = $this.val().trim(),
	 thresholdCleaned = threshold.replace(/[^\d]+/ig, ''),			// clear out all non-numerical characters
			  isDigit = (evt.keyCode >= 48 && evt.keyCode <=  57) ||	// digits on keyboard upper line are allowed
						(evt.keyCode >= 96 && evt.keyCode <= 105),		// digits on right sided keyboard numblock are allowed
				regex = RegExp('-(%PERCENT%|[0-9]+)$', 'i');

			// Replace bad user input with cleaned input.
			if (!isDigit) {
				$this.val( thresholdCleaned );
			}

			// Convert threshold to real Integer.
			threshold = parseInt(threshold);

			// Function to get the matching colorizing value from range.
			const calcPercentage = function(needle) {
				// Sanitize input data.
				needle = parseInt(needle);

				// Iterate over range and find the matching or closest value.
				for (let i = 0; i < count; i += 1) {
					let current = range[i],
						   next = range[i + 1];

					switch (true) {
						case (needle <= first) :
							needle = first;
						break;

						case (needle >= last) :
							needle = last;
						break;

						case (needle > current && needle < next) :
							needle = current;
						break;
					}
				}

				return needle;
			};

			// Process all table cells in this table row.
			$target.each(function(i, td) {
				let $td = $(td),
					classes = $td.attr("class").trim();

				// Cell has no value and must therefore not be colourised.
				if (!classes.match(regex)) {
					return;
				}

				// Threshold input field is empty. Inject CSS-class placeholder.
				if (!thresholdCleaned.length) {
					// Reset element class attribute.
					$td.attr("class", $td.attr("class").replace(/-\d+/i, '-%PERCENT%'));	// Enable this line to remove colours if threshold input field is empty (will be red if value is 0)
//					$td.attr("class", $td.attr("class").replace(/-\d+/i, '-0'));			// Enable this line to keep red color even if threshold input field is empty or has value 0

					return;
				}

				// Cell has a numerical value. Read it and convert to Integer.
				let processPartsCountAbsolute = parseInt($td.find(".parts-processed").text().trim()),
					processPartsCountRelative = 0;

					// Ensure that we have a proper numerical value.
					processPartsCountAbsolute = isNaN(processPartsCountAbsolute) ? 0 : processPartsCountAbsolute;

				// Calculate processPartsCountRelative if both threshold and processPartsCountAbsolute are set.
				if (Number.isInteger(threshold) && threshold > 0 &&
					Number.isInteger(processPartsCountAbsolute) && processPartsCountAbsolute > 0)
				{
					if (processPartsCountAbsolute == 0) {
						processPartsCountRelative = 0;
					} else if (processPartsCountAbsolute == 1) {
						processPartsCountRelative = 1;
					} else if (threshold > 0) {
						processPartsCountRelative = (processPartsCountAbsolute * 100) / threshold;
					} else {
						processPartsCountRelative = 0;
					}
				}

				if (processPartsCountRelative > 0) {
					processPartsCountRelative = Math.ceil(processPartsCountRelative);
				}

				// Calculate which value in range the table cell value matches or is closest too.
				let percentage = calcPercentage(Number.isFinite(processPartsCountRelative) ? processPartsCountRelative : 0);

				// Set calculated percentage to activate CSS styling that has already been calculatd in PHP.
				$td.attr("class", $td.attr("class").replace(/-(%PERCENT%|[0-9]+)$/i, '-' + percentage));
			});
		})

		//FIXME - Resolve code duplication (see: Code from ~ line 1288 ff)
		// .on("beforeReset.ftk.element",                             function(evt, element) {})
		.on("afterReset.ftk.element",                              function(evt, element) {
			let $this = $(this),
				$elem = $(element),
				  val = $this.val(),
				$target = $( $elem.data("target") || [] ),
				$listItems = $target.find("> .list-item");

			$listItems.show();
		})

		// .on("submit.ftk.form",                                     function(evt, element) {})
		// .on("submitted.ftk.form",                                  function(evt, element) {})
	})
	.on("beforeunload", function(evt) {	// Script to be run when the document is about to be unloaded
		"use strict";

		// Unfortunately overriding the browsers message dialog is not supported anymore.
		// Read more about it at:   https://developers.google.com/web/updates/2016/04/chrome-51-deprecations#remove_custom_messages_in_onbeforeunload_dialogs
		if (document.querySelectorAll('form[data-is-changed="true"]').length > 0) {
			if ( evt.target &&
				 evt.target.activeElement &&
				 evt.target.activeElement.getAttribute("class") &&
				!evt.target.activeElement.getAttribute("class").match("allow-window-unload")
			) {
				return false;
			}
		}
	})
	.on("unload",       function(evt) {	// Script to be run once a page has unloaded (or the browser window has been closed)
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.unload event");

		/*if (window.opener !== null) {
			window.opener.location.reload();
			window.opener.focus();
		}*/
	})
	.on("error",        function(evt) {	// Script to be run when an error occurs
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.error event");
	})
	.on("beforeprint",  function(evt) {	// Script to be run before the document is printed
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.beforeprint event");
	})
	.on("afterprint",   function(evt) {	// Script to be run after the document is printed
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.afterprint event");
	})
	.on("hashchange",   function(evt) {	// Script to be run when there has been changes to the anchor part of the a URL
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.hashchange event");
	})
	.on("message",      function(evt) {	// Script to be run when the message is triggered
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.message event");
	})
	.on("offline",      function(evt) {	// Script to be run when the browser starts to work offline
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.offline event");
	})
	.on("online",       function(evt) {	// Script to be run when the browser starts to work online
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.online event");
	})
	.on("pagehide",     function(evt) {	// Script to be run when a user navigates away from a page
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.pagehide event");
	})
	.on("pageshow",     function(evt) {	// Script to be run when a user navigates to a page
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.pageshow event");

		jQuery(document.forms[0])
		.find(":input.form-control").filter(":first")
		.focus(function() {
			console.log("Element focused");
		})
		.select();
	})
	.on("popstate",     function(evt) {	// Script to be run when the window's history changes
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.popstate event");
	})
	.on("resize",       function(evt) {	// Script to be run when the browser window is resized
		"use strict";

		// console.log("---------------------------------------------------");
		// console.info("window.resize event");
		// console.log("breakpoint:", window.FTKAPP.functions.getBreakpoint(window.innerWidth));

		let modalDialog = document.querySelector(".modal-dialog"),
			modalDialogClass = modalDialog.className;

		// console.log("modalDialog:", modalDialog);
		// console.log("modalDialogClass:", modalDialogClass);

		modalDialogClass = modalDialogClass.replace(/\bmodal-(xs|sm|md|lg|xl)\b/, "");

		// console.log("new class:", modalDialogClass + " " + "modal-" + window.FTKAPP.functions.getBreakpoint(window.innerWidth));

		modalDialog.className = modalDialogClass + " " + "modal-" + window.FTKAPP.functions.getBreakpoint(window.innerWidth);
	})
	.on("storage",      function(evt) {	// Script to be run when a Web Storage area is updated
		"use strict";

		console.log("---------------------------------------------------");
		console.info("window.storage event");
	});
}
