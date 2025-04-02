/**!
 * @fileOverview jQuery Nemacam plugin <https://bitbucket.org/froetek-development/nemacam>
 * @version 1.0.4
 * @license Copyright 2021 FRÖTEK-Kunststofftechnik GmbH. All rights reserved.
 */

"use strict";

// If jQuery is not available stop right here!
if (typeof jQuery === 'undefined') {
	throw new Error('The Nemacam-JavaScript requires jQuery');
}
// If jQuery version is < 3.6 stop right here!
+function($) {
	"use strict";

	var version = $.fn.jquery.split(' ')[0].split('.');

	if (version[0] < 3 && version[1] < 6) {
		throw new Error('The Nemacam-JavaScript requires jQuery version 3.6 or higher');
	}

}(jQuery.noConflict());

+function($, window) {
	"use strict";

	/* Camera manager
	 *
	 * @return   void
	 */
	function Nemacam() {
		// Add here initialisation defaults
		this._defaults = {
			debug       : false,
			interval    : 60000,	// 60000 ms which is 60s
			maxAttempts : 60,		// the interval will automatically stop after 60 repetitions,
									// means per default there's 1 poll per minute repeated 60 times until it'll end.
		};

		$.extend(this._defaults);
	}

	/* Logic */
	$.extend(Nemacam.prototype, {
		// Name of the data property used to ref. an instances settings.
		dataObjectName:     "nemacam",

		// Class name added to elements to indicate already being configured with nemacam.
		markerClassName:    "hasNemacam",

		// Class name indicating the plugin is initialising.
		_initialisingClass: "nemacam-initialising",
		// Class name indicating the browser does not support the MediaDevice API.
		_incompatibleClass: "nemacam-incompatible",
		// Class name indicating the plugin is connecting to a stream.
		_connectingClass:   "nemacam-connecting",
		// Class name indicating the plugin is loading some data.
		_accessDeniedClass: "nemacam-access-denied",
		// Class name indicating the plugin is loading some data.
		_loadingClass:      "nemacam-loading",
		// Class name indicating the plugin is processing stream data (e.g. a photo).
		_processingClass:   "nemacam-processing",
		// Class name indicating the plugin is waiting to sart/finish a task.
		_pendingClass:      "nemacam-pending",
		// Class name indicating the plugin is sending processed stream data (e.g. a photo).
		_sendingClass:      "nemacam-sending",
		// Class name indicating the plugin has an active stream.
		_streamingClass:    "nemacam-streaming",
		// Class name indicating the plugin is disabled.
		_disabledClass:     "nemacam-disabled",

		// INITIALISATION METHODS
		// ======================

		/* Override the default settings for all Nemacam instances.
		 *
		 * @param    options  (object)  The new settings to use as defaults
		 *
		 * @return   (Nemacam)  this object
		 */
		setDefaults    : function(options) {
			$.extend(this._defaults, options || {});

			return this;
		},

		/* Attach the nemacam functionality to a textarea.
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object)   The custom options for this instance
		 *
		 * @return   (Nemacam)  this object
		 */
		_attachPlugin  : function(target, options) {
			target = $(target);

			if (target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = {
				options: $.extend({}, this._defaults)
			};

			target
			.addClass([
				this.markerClassName
			])
			.data(this.dataObjectName, inst)
			.prepend( $("<div/>", {"id" : "message-container", "style" : "display:none"}) );

			this._optionPlugin(target, options);
		},

		/* Retrieve or reconfigure the settings for a control.
		 *
		 * Example call: $(selector).nemacam("option", {...});
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object|string)   The new options for this instance or an individual property name
		 * @param    value    (any)      The individual property value (omit if options is an object or to retrieve the value of a setting)
		 *
		 * @return   (any)     if retrieving a value
		 */
		_optionPlugin  : function(target, options, value) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName), self = this, name = undefined;

			// Get option
			if (!options || (typeof options === "string" && value === null)) {
				name    = options;
				options = (inst || {}).options;

				return (options && name ? options[name] : options);
			}

			target
			.addClass([
				plugin._initialisingClass
			]);

			options = options || {};

			// Map options passed as string into an object to easily extend the defaults object
			if (typeof options === "string") {
				name    = options;
				options = {};

				options[name] = value;
			}

			// Merge: passed in options > data attributes > plugin defaults
			$.extend(inst.options, target.data(), options);

			target
			.unbind("." + this.dataObjectName);

			const handler = (evt, args) => {
				target
				.removeClass([
					plugin._initialisingClass,
					plugin._streamingClass
				])
				.addClass([
					plugin._loadingClass
				]);

				const $element       = $(evt.target),
					  $video         = target.find("video"),
					  $videoWrapper  = $video.closest(".media-wrapper").removeClass("hasStream"),
					  $deviceList    = $element.prop("tagName").toUpperCase() === "SELECT" ? $element : target.find('select[name="devices"]'),
					  devices        = args.devices || plugin._getDetectedDevices(target);	// list is most recent poll result containing detected devices without any flags

				let prevNumOptions   = parseInt($deviceList.data("options")) || 0,
					currNumOptions   = devices.length,
					isDevicesChanged = false,
				  isSelectionChanged = false,
					// Selection is either the current selected list option or the first device in list when there's only 1 device.
					selectedDeviceID = ("" + $deviceList.val()).trim();

				// Device list may have changed due to disconnection.
				// Make sure to fall back to the last device in list.
				//FIXME - if there were previously more than 3+ devices and 1 got disconnected, then which one of the other 2 shall be selected?
				if (devices.length == 1) {
					selectedDeviceID = devices[0].deviceId;
				}

				// Calculate changes.
				isDevicesChanged   = parseInt(prevNumOptions) !== parseInt(currNumOptions);
				isSelectionChanged = selectedDeviceID !== $deviceList.data("selection");

				// If nothing has changed stop right here.
				if (!isDevicesChanged && !isSelectionChanged) {
					return;
				}

				let listData    = {},
					listOptions = [],
				 selectedDevice;

				// If available devices has changed update dropdown.
				if (isDevicesChanged) {
					plugin._setDetectedDevices(target, {devices: devices.filter(device => device.deviceId && device.kind === "videoinput")});
					plugin._selectDevice(target, {deviceId: selectedDeviceID});

					// Fetch dropdown data and selected device.
					listData       = plugin._createDeviceListOptions(target, $.extend({}, args, {devices: plugin._getDetectedDevices(target), select: selectedDeviceID}));
					listOptions    = listData.listOptions || ['<option value="">' + window.FTKAPP.translator.map["COM_FTK_LIST_OPTION_NO_DEVICES_FOUND_TEXT"] + '</option>'];
					selectedDevice = listData.selectedDevice;

					// If there's more than 1 device inject a hint option.
					if (devices.length > 1) {
						listOptions.unshift(
							'<option value=""> --- ' + window.FTKAPP.translator.map["COM_FTK_HINT_PLEASE_SELECT_CAMERA_TEXT"] + ' --- </option>'
						);
					}

					// Inject dropdown options.
					$deviceList
					.html( listOptions.join("") )

					// Modify dropdown attribs accordingly.
					if (devices.length) {
						$deviceList
						.addClass("hasOptions")
						.removeAttr("readonly")
						.removeAttr("disabled");
					} else {
						$deviceList
						.removeClass("hasOptions")
						.attr({
							readonly: "",
							disabled: ""
						});
					}

					// Update reference.
					selectedDeviceID = ("" + $deviceList.val()).trim();
				}

				if (isSelectionChanged) {
					selectedDevice   = plugin._selectDevice(target, {deviceId: selectedDeviceID});
					selectedDeviceID = selectedDevice ? selectedDevice.deviceId : selectedDeviceID;
				}

				// Dump current dropdown setup for later changes detection.
				$deviceList.data({
					options:   devices.length,
					selection: selectedDeviceID
				});

				if (!selectedDevice || !(selectedDevice instanceof MediaDeviceInfo)) {
					$deviceList.addClass("noSelection");

					target
					.removeClass([
						plugin._loadingClass
					])
					.addClass([
						plugin._pendingClass
					]);

					return $deviceList.focus();

				} else if (isSelectionChanged) {
					$deviceList.removeClass("noSelection");

					target
					.removeClass([
						plugin._loadingClass,
						plugin._pendingClass
					])
					.addClass([
						plugin._connectingClass
					]);

					plugin._getStream(target, {device: selectedDevice}, data => {
						// Make sure we have a proper MediaStream object.
						if (!data.stream || !(data.stream instanceof MediaStream)) {
							alert("Connection failed.");	//TODO - translate

							return;
						}

						// Get ImageCapture instance.
						let imageCapture, track = 0;

						// Older browsers may not have srcObject
						if ("srcObject" in video) {
							video.srcObject = data.stream;
						} else {
							// Avoid using this in new browsers, as it is going away.
							video.src = window.URL.createObjectURL(data.stream);
						}

						video.onloadedmetadata = function(evt) {
							// Fetch instance of ImageCapture, track capabilities and track settings in one object.
							plugin._getImageCapture(target, {device: selectedDevice, stream: data.stream, track: track}, data => {
								let precision = 0,
									   vRatio = 1.3333333333333333333333333333333,	// 640 x 480
									  vHeight = 480,
									   vWidth = Math.trunc(Math.round(vHeight * vRatio));

								if (data.hasOwnProperty('imageCapture')
									&& data.imageCapture.hasOwnProperty('capture')
									&& data.imageCapture.capture instanceof ImageCapture
									&& data.imageCapture.photoSettings.imageWidth  > 0
									&& data.imageCapture.photoSettings.imageHeight > 0
								) {
									precision = Math.pow(10, 1);	// 2nd param is the number of decimal places to preserve
									vRatio    = data.imageCapture.photoSettings.imageWidth / data.imageCapture.photoSettings.imageHeight;
									vWidth    = target.outerWidth();
									vHeight   = Math.ceil( ((vWidth * data.imageCapture.photoSettings.imageHeight) / data.imageCapture.photoSettings.imageWidth) * precision ) / precision;

									// NEW: Fix vHeight to not oversize the photoSettings.imageHeight.
									if (vHeight > data.imageCapture.photoSettings.imageHeight) {
										vHeight = data.imageCapture.photoSettings.imageHeight;
										vWidth  = Math.trunc(Math.round(vHeight * vRatio));
									}

									plugin._enableStreamControls(target, data);  // Disabled on 2022-02-04 - controls must also be enabled when ELSE is caught.
								}

								target
								.removeClass([
									plugin._initialisingClass,
									plugin._loadingClass,
									plugin._connectingClass
								])
								.addClass([
									plugin._streamingClass
								])
								// .find(".media-wrapper")
								.find("#video-wrapper")
									.css({ paddingBottom: vHeight + "px" })	// spans the container height so that it has the perfect 4:3 dimension (see: https://www.thismanslife.co.uk/projects/responsivevideo)
									.end()
								.find("video")
									.parent()
									.addClass("hasStream");

								video.play();
							});
						};
					});
				}
			};

			// Additional event handlers
			target
			.on("initList."   + this.dataObjectName, handler)
			.on("updateList." + this.dataObjectName, handler)
			.on("change."     + this.dataObjectName, (evt, args) => {
				const $element = $(evt.target);

				if ($element.prop("tagName").toUpperCase() === "SELECT") {
					return $element.trigger("updateList", {dropdown: $element.get(0)});
				}
			});
		},

		/* Disable the control.
		 *
		 * @param    target   (element)  The control to affect
		 *
		 * @return   void
		 */
		_enablePlugin  : function(target) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			target
			.prop("disabled", false)
			.removeClass([
				plugin._disabledClass
			]);
		},

		/* Disable the control.
		 *
		 * @param    target   (element)  The control to affect
		 *
		 * @return   void
		 */
		_disablePlugin : function(target) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			target
			.prop("disabled", true)
			.addClass([
				plugin._disabledClass
			]);
		},

		/* Remove the plugin functionality from a control.
		 *
		 * Example call: $(selector).nemacam("destroy");
		 *
		 * @param    target   (element)  The control to affect
		 *
		 * @return   void
		 */
		_destroyPlugin : function(target) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			plugin._stopPoll(target, {poll: target.data("poll")}, response => {
				target
				.removeClass([
					plugin._initialisingClass,
					plugin._incompatibleClass,
					plugin._connectingClass,
					plugin._loadingClass,
					plugin._streamingClass,
					plugin._processingClass,
					plugin._pendingClass,
					plugin._sendingClass,
					plugin._disabledClass,
					plugin.markerClassName
				])
				.removeData(plugin.dataObjectName)
				.unbind("." + plugin.dataObjectName);

				// Deletion of device list is important or re-detection will fail.
				try {
					delete window.mediaDevices;
				} catch (err) {}
			});
		},


		// PUBLIC METHODS (GETTERS)
		// as per definition in the 'getters' object
		// =========================================


		/* Initialises the plugin.
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object)   Initialisation options
		 *
		 * @return   void
		 */
		_initPlugin           : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			// Check for full WebRTC support or abort.
			if (!plugin._supportsMediaDevice()) {
				target
				.addClass([
					plugin._incompatibleClass
				]);

				plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
					"This web browser doesn't seem to support the " +
					"<a href='//developer.mozilla.org/en-US/docs/Web/API/WebRTC_API' target='_blank' title='more about the WebRTC API'>WebRTC API</a>. " +
					"Please try another, modern web browser."
				);

				return;
			}

			// Check for full WebRTC support or abort.
			if (!plugin._supportsImageCapture()) {
				target
				.addClass([
					plugin._incompatibleClass
				]);

				plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
					"This web browser is incompatible as it doesn't support the required " +
					"<a href='https://developer.mozilla.org/en-US/docs/Web/API/MediaStream_Image_Capture_API#browser_compatibility' target='_blank' title='more about the WebRTC API'>ImageCapture</a>-function. " +
					"Up to today <u>only Google's Chrome browser</u> supports this functionality and should therefore be used to take pictures within Nematrack."
				);

				return;
			}

			// Start watching for device changes.
			plugin._detectDevices(target, options, response =>  {
				if (!(response instanceof DOMException)) {
					plugin._startPoll(target, options);
				} else {
					plugin._destroyPlugin(target, options);

					target
					.addClass([
						plugin._accessDeniedClass
					]);
				}
			});
		},


		// PUBLIC METHODS (SETTERS)
		// as per definition in the 'setters' object
		// =========================================


		// INTERNAL METHODS (APP LOGIC)
		// must not be accessible from 'outside'
		// =====================================

		_clearMessage            : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			// let inst = target.data(this.dataObjectName);

			target
			.find("#message-container")
				.fadeOut("fast", () => {
					$(this)
					.empty()
					.removeClass();
				});
		},

		_renderMessage           : function(target, options, message) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let prefix, bootstrapClass;

			switch (("" + options.type).toLowerCase()) {
				case "danger" :
				case "error" :
					prefix         = window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT"];
					bootstrapClass = "danger";
				break;

				case "notice" :
				case "warning" :
					prefix         = window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_NOTICE_TEXT"];
					bootstrapClass = "warning";
				break;

				case "info" :
				case "primary" :
					prefix         = window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_INFO_TEXT"];
					bootstrapClass = "info";
				break;

				case "success" :
					prefix         = window.FTKAPP.translator.map["COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT"];
					bootstrapClass = "success";
				break;

				default :
					prefix         = window.FTKAPP.translator.map["COM_FTK_HEADING_ATTENTION_TEXT"];
					bootstrapClass = "secondary";
				break;
			}

			target
			.find("#message-container")
				.hide()
				.removeClass()
				.empty()
				.html('' +
					'<button type="button" class="close" data-dismiss="alert" aria-label="' + window.FTKAPP.translator.map["COM_FTK_BUTTON_TEXT_CLOSE_TEXT"] + '" ' +
							'style="padding-top:0.1rem; padding-right:0.5rem"' +
						'><span class="d-inline-block" aria-hidden="true" style="height:50%">&times;</span>' +
					'</button>'
				)
				.append( '<strong class="text-uppercase mr-2">' + prefix + ":</strong>" + message.trim() )
				.addClass([
					"alert",
					"alert-dismissible",
					"alert-" + bootstrapClass,
					"fade show",
				])
				.fadeIn("fast");
		},

		_disableStreamControls   : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName);

			target
			.find(".stream-controls")
			.prop("disabled", true)
			.addClass("d-none")
			.find("button")
				.prop("disabled", true);
		},

		_enableStreamControls    : function(target, options) {
			target   = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName);

			let $streamControls = target.find(".stream-controls");

			// NOTE:  The ':disabled' selector should only be used for selecting HTML elements that support the disabled attribute! (a DIV does NOT)
			if (!$streamControls.is(".disabled")) {
				// Container has previously been enabled. No further action required.
				return;
			}

			$streamControls
			.removeClass([
				"d-none",
				"disabled"
			])
			.find("button")
				.prop("disabled", false)
				.end()
			.find("#btnSnapper")
				.on("click" + "." + this.dataObjectName, evt => {
					console.log("Snapper clicked");

					let boxWidth   = target.find("#video-wrapper").css("width"),
						boxHeight  = target.find("#video-wrapper").css("height"),
						isBoxBG    = target.find("#media-container").css("backgroundColor"),
						shallBoxBG = "#000",
							effect = "clip",
						duration   = 50,
						direction  = "vertical",
							easing = "swing";
							
					console.log("target:", target);
					console.log("media-container:", target.find("#media-container"));

					target
					.find("#media-container")
					.css({
						backgroundColor : "#000",
						height : boxHeight,
						width  : boxWidth
					})
					// Wrap content with a temporary container
					.children()
						/* Necessary prerequisite to prevent the container from collapsing
						 * and make the desired effect appear as wished - like a camera shutter.
						 */
						.wrapAll(
							$("<div/>", {
								id  :"effect-wrapper",
								css : {
									width  : boxWidth,
									height : boxHeight
								}
							})
						)
						.end()
					// Animate temporary container
					.find("#effect-wrapper")
					.hide({
						effect:    effect,
						duration:  duration,
						direction: direction,
						easing:    easing,
						complete: () => {
							target
							.find("#effect-wrapper")
							.show({
								effect:    effect,
								duration:  duration,
								direction: direction,
								easing:    easing,
								complete: () => {
									setTimeout(() => {
										target
										.find("#media-container")
											.css({ backgroundColor: "unset" })
											.end()
										.find("#effect-wrapper")
											.children()
											.unwrap()
											.end().end()
										.removeClass([
											plugin._streamingClass
										])
										.addClass([
											plugin._loadingClass
										])
										.find("#video-wrapper")
											.data({style: {display: target.find("#video-wrapper").css("display")}})
											.hide({
												duration: 0,
												complete: () => {
													plugin._takePhoto(target, options, blob => {
														// const URL    = window.URL || window.webkitURL;
														// const imgURL = URL.createObjectURL(blob);
														const $video = target.find("#video-wrapper video");

														const fileReader = new FileReader();	// required to converts the blob to base64 (see: https://javascript.info/blob#blob-as-url)

														fileReader.addEventListener("load", progressEvent => {
															const imgAsBase64 = fileReader.result;

															target
															.find("#image-wrapper")
																.css({ paddingBottom: boxHeight })
																.queue(next => {
																	// Utilize a 1s timeout to show the "loading" indicator for the user to see something is going on
																	setTimeout(() => {
																		target
																		.find("#image-wrapper")
																			.find("img")
																				.attr({
																					src    : imgAsBase64,
																					alt    : "Snapshot",	//TODO - translate
																					width  : boxWidth,
																					height : boxHeight
																				})
																				.end()
																			.css({
																				paddingBottom       : "unset",
																				backgroundSize      : [ boxWidth, boxHeight ].join(" "),
																				backgroundImage     : "url(" + imgAsBase64 + ")",
																				backgroundPositionY : 0
																			})
																			.queue(next => {
																				plugin._enablePictureControls(target, options);

																				next();
																			})
																			.addClass("hasSnapshot")
																			.show({
																				duration: 0,
																				complete: () => {
																					target
																					.removeClass([
																						plugin._loadingClass
																					])
																				}
																			});
																	}, 1);	// 200 = fast, 600 = slow (according to jQuery's "fast" and "slow" props)

																	next();
																});
														});

														fileReader.readAsDataURL(blob);



														return;



														/* target
														.find("#image-wrapper")
															.css({ paddingBottom: boxHeight })
															.queue(next => {
																// Utilize a 1s timeout to show the "loading" indicator for the user to see something is going on
																setTimeout(() => {
																	target
																	.find("#image-wrapper")
																		.find("img")
																			.attr({
																				// src    : imgURL,
																				src    : imgAsBase64,
																				alt    : "Snapshot",	//TODO - translate
																				width  : boxWidth,
																				height : boxHeight
																			})
																			.end()
																		.css({
																			paddingBottom       : "unset",
																			backgroundSize      : [ boxWidth, boxHeight ].join(" "),
																			// backgroundImage     : "url(" + imgURL + ")",
																			backgroundImage     : "url(" + imgAsBase64 + ")",
																			backgroundPositionY : 0
																		})
																		.queue(next => {
																			plugin._enablePictureControls(target, options);

																			next();
																		})
																		.addClass("hasSnapshot")
																		.show({
																			duration: 0,
																			complete: () => {
																				target
																				.removeClass([
																					plugin._loadingClass
																				])
																			}
																		});
																}, 200);	// 200 = fast, 600 = slow (according to jQuery's "fast" and "slow" props)

																next();
															}); */

													});
												}
											});
									}, 200);	// 200 = fast, 600 = slow (according to jQuery's "fast" and "slow" props)
								}
							});
						}
					});
				});
		},

		_enablePictureControls   : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName);

			let $photoControls = target.find(".photo-controls");

			// NOTE:  The ':disabled' selector should only be used for selecting HTML elements that support the disabled attribute! (a DIV does NOT)
			if (!$photoControls.is(".disabled")) {
				// Container has previously been enabled. No further action required.
				return;
			}

			$photoControls
			.removeClass([
				"d-none",
				"disabled"
			])
			.find("button")
				.prop("disabled", false)
				.end()
			.find("#btnAdd")
				.on("click" + "." + this.dataObjectName, evt => {
					const $this = $(evt.currentTarget),
						  $form = $( "#" + $this.attr("form") ),
						  $img  = $form.find("img"),
						  $pntContainer = $(target.data("parentContainer"));

					// Copy hidden image source attribute value over to related process fieldset into a hidden field
					$("<input/>", {
						readonly : true,
						type     : "hidden",
						name     : "proofPics[]",
						value    : $img.attr("src")
					})
					.appendTo($pntContainer);

					// Reset hidden image and lip preview/camera like when btnEraser has been clicked
					plugin._clearPhoto(target, options, () => {
						target
						.find(".media-counter")
							.removeClass("d-none")
							.find("> .count")
								.html( $pntContainer.find('input[name="proofPics[]"]').length )
					});
				})
				.end()
			.find("#btnEraser")
				.on("click" + "." + this.dataObjectName, evt => {
					plugin._clearPhoto(target, options, () => {});
				});
		},

		/* Detects available devices triggering permission request if necessary.
		 *
		 * @see https://webrtc.org/getting-started/media-devices
		 */
		_detectDevices           : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName);

			// Check for WebRTC support or abort.
			if (!plugin._supportsMediaDevice()) {
				target
				.addClass([
					plugin._incompatibleClass
				]);

				plugin._renderMessage(target, {type: "info"}, "" +	//TODO - translate
					"This web browser doesn't seem to support the " +
					"<a href='https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API' target='_blank' title='more about the WebRTC API'>WebRTC API</a>. " +
					"Please try another, modern web browser."
				);

				return;
			}

			navigator.mediaDevices
			.getUserMedia({
				audio: false,
				video: true
			})
			.then(MediaStream => {
				MediaStream.getTracks().forEach(track => track.stop());

				typeof callback === "function" ? callback() : null;
			})
			.catch(err => {
				switch (err.name) {
					/* Although the user and operating system both granted access to the hardware device, and no hardware issues occurred
					 * that would cause a NotReadableError, some problem occurred which prevented the device from being used.
					 */
					case "AbortError" :
					break;

					/* One or more of the requested source devices cannot be used at this time. This will happen if the browsing context
					 * is insecure (that is, the page was loaded using HTTP rather than HTTPS). It also happens if the user has specified
					 * that the current browsing instance is not permitted access to the device, the user has denied access for the current
					 * session, or the user has denied all access to user media devices globally.
					 */
					case "NotAllowedError" :
					case "PermissionDeniedError" :
						plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
							// "Der Zugriff auf die Kamer(s) wurde verweigert. Die Photoaufnahme wird nun beendet."
							"Access to the camera(s) has been denied. The photo recording will now stop."
						);

						typeof callback === "function" ? callback(err) : null;

						return err;
					break;

					/* No media tracks of the type specified were found that satisfy the given constraints.
					 */
					case "NotFoundError" :
					break;

					/* Although the user granted permission to use the matching devices, a hardware error occurred at the operating system,
					 * browser, or Web page level which prevented access to the device.
					 */
					case "NotReadableError" :
						plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
							[
								err.message,
								" ",
								"<em>Troubleshooting measures:</em>",
								" ",
								"1. Disconnect all video devices.",
								"2. Clear the web browser's cache (could result in logging in again).",
								"3. Connect your video device.",
								"4. Make sure that your video device is switched on.",
								"5. Make sure that access to the video device is enabled in the web browser.",
								" ",
								"Still no go or need assistance? Ask your technical support."
							].join("<br>")
						);

						typeof callback === "function" ? callback(err) : null;

						return err;
					break;

					/* The specified constraints resulted in no candidate devices which met the criteria requested. The error is an object
					 * of type OverconstrainedError, and has a constraint property whose string value is the name of a constraint which was
					 * impossible to meet, and a message property containing a human-readable string explaining the problem.
					 */
					case "OverconstrainedError" :
						plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
							"The selected device does not support constraint: " + err.constraint
						);

						typeof callback === "function" ? callback(err) : null;

						return err;
					break;

					/* User media support is disabled on the Document on which getUserMedia() was called. The mechanism by which user media
					 * support is enabled and disabled is left up to the individual user agent.
					 */
					case "SecurityError" :
					break;

					/* The list of constraints specified is empty, or has all constraints set to false. This can also happen if you try to
					 * call getUserMedia() in an insecure context, since navigator.mediaDevices is undefined in an insecure context.
					 */
					case "TypeError" :
					break;

					default :
						console.error("Unspecified error:", err.message);
				}
			});
		},

		/* Dumps the current available devices list as property of the window object.
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object)   Additional options that must contain at least the detected devices list
		 *
		 * @return   array   The currently detected device list
		 */
		_setDetectedDevices      : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			window.mediaDevices = options.devices || [];

			return plugin._getDetectedDevices(target);
		},

		/* Returns the current available devices list as property of the window object.
		 *
		 * @param    target   (element)  The control to affect
		 *
		 * @return   array   The currently detected device list
		 */
		_getDetectedDevices      : function(target) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			return window.mediaDevices || [];
		},

		/* Is using Promise(s) */
		_startPoll               : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let inst = target.data(this.dataObjectName);

			let isDevicesChanged = false,
					deviceList   = [],
					numDevices   = 0,
					eventType    = "";

			/* Function to detect and watch connected media devices
			 *
			 * @param    fn           (function)  A function that will execute over a given interval. Typically this will be an API request.
			 * @param    interval     (int)       The time to wait between poll requests. Less is better to detect device changes asap.
			 * @param    maxAttempts  (int)       The upper bound for the number of polls after which it'll be stopped to prevent it from running infinitely.
			 *
			 * @return   (Nemacam)  this object
			 *
			 * @see      https://levelup.gitconnected.com/polling-in-javascript-ab2d6378705a
			 */
			const poll = async({fn, interval, maxAttempts}) => {
				let attempts = 0; maxAttempts = parseInt(maxAttempts);

				const executePoll = async(resolve, reject) => {
					const result = await fn();	// fn = mockApi()

					attempts += 1;

					if (maxAttempts && attempts === maxAttempts) {
						return reject(new Error("Exceeded max poll attempts"));	//TODO - translate
					} else {
						target.data({poll: setTimeout(executePoll, interval, resolve, reject)});	// Dump reference to generated timeout to be able to stop it
					}
				};

				return new Promise(executePoll);
			};

			const pollForMediaDevices = poll({
				fn:          () => {
					deviceList = plugin._getDetectedDevices(target);
					numDevices = deviceList.length;

					navigator.mediaDevices
					.enumerateDevices()
					.then(devices => {
						const prevIDs  = [], nowIDs = [];
						let difference = [];

						// Latest device list.
						deviceList = deviceList.filter(device => device.deviceId !== "" && device.kind === "videoinput" && prevIDs.push(device.deviceId));

						// Currently detected devices.
						devices    = devices.filter(device    => device.deviceId !== "" && device.kind === "videoinput" && nowIDs.push(device.deviceId));

						difference = (nowIDs.length > prevIDs.length)
							? nowIDs.filter(ID  => !prevIDs.includes(ID))
							: prevIDs.filter(ID => !nowIDs.includes(ID));

						isDevicesChanged = prevIDs.length != nowIDs.length;

						// no changes, nothing to do
						if (!isDevicesChanged) {
							return;
						}
						// device List CHANGED!
						else {
							if (numDevices == 0) {
								// Trigger list INITIALISATION.
								eventType  = "initList";

								numDevices = devices.length;

							} else {

								// Trigger list UPDATE.
								eventType = "updateList";

								// Inform user about changes.
								if (isDevicesChanged) {
									numDevices = devices.length;
								}
							}

							target
							.trigger(eventType, {devices: devices});
						}
					})
					/*.then(deviceIds => {
						console.warn("deviceIds:", deviceIds);
					})*/
					// .catch(err => console.error(err))	// temp disabled
					;
				},
				interval:    inst.options.interval    || plugin._defaults.interval,
				maxAttempts: inst.options.maxAttempts || plugin._defaults.maxAttempts
			})
			.then(response => console.warn("poll Response:", response))
			.catch(err => console.error(err));
		},

		_stopPoll                : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			// let inst = target.data(this.dataObjectName);
			let poll = target.data("poll");

			try {
				if (poll) {
					window.clearTimeout(poll);

					delete target.data.poll;
				}
			} catch (err) {
				console.warn("Failed to stop active poll:", err);	//TODO - translate
			}

			typeof callback === "function" ? callback(target.data("poll")) : null;

			return target.data("poll");
		},

		/* Creates a list of ready to use HTML select element options from the passed devices list
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object)   Additional options that must contain at least the detected devices list
		 *
		 * @return   object   The generated options-list and the selected device
		 */
		_createDeviceListOptions : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let cams = options.devices || [], selectedDevice = plugin._getSelectedDevice(target);

			const listOptions = []; let i = 0;

			if (cams.length) {
				cams.forEach(cams => {
					listOptions.push('' +
						'<option value="' + cams.deviceId + '"' + (cams.selected ? " selected" : "") + ">" +
							(cams.label || window.FTKAPP.translator.map["COM_FTK_LABEL_CAMERA_TEXT"] + ' ' + (++i)) +
						'</option>'
					);
				});

				return {listOptions: listOptions, selectedDevice: selectedDevice};
			} else {
				return {listOptions: null,        selectedDevice: cams.filter(device => device.deviceId === options.select).pop()};
			};
		},

		/* Selects a specified devices.
		 * This will remove the "selected" flag from all but the selected devices.
		 *
		 * @param    target   (element)  The control to affect
		 * @param    options  (object)   Additional options that must contain at least the detected
		 *                               devices list and the deviceId of the device to select.
		 *
		 * @return   object   The generated options-list and the selected device
		 */
		_selectDevice            : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let device;

			plugin._getDetectedDevices(target).forEach(MediaDevice => {
				if (MediaDevice.deviceId === options.deviceId) {
					// Flag as "selected".
					MediaDevice.selected  = true;
					// MediaDevice.active    = MediaDevice.active    || false;	// temp disabled on 06.09.2021 for stream inconsistancy debugging
					// MediaDevice.streaming = MediaDevice.streaming || false;	// temp disabled on 06.09.2021 for stream inconsistancy debugging

					// Pick for returning.
					device = MediaDevice;
				} else {
					MediaDevice.selected  = false;
					// MediaDevice.active    = false;	// temp disabled on 06.09.2021 for stream inconsistancy debugging
					// MediaDevice.streaming = false;	// temp disabled on 06.09.2021 for stream inconsistancy debugging
				}
			});

			return device;
		},

		/* Is using Promise(s) */
		_getImageCapture         : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			if (!options.stream || !(options.stream instanceof MediaStream)) {
				plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
					"Invalid stream."
				);

				return;
			}

			const tracks = options.stream.getVideoTracks();

			if (!tracks.length) {
				plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
					"The selected device's has no streaming tracks."
				);

				return;
			}

			let track = tracks[options.track || 0], prevImageCapture, imageCapture;

			if (!track || !(track instanceof MediaStreamTrack)) {
				plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
					"The requested track is not available."
				);

				return;
			}

			window.mediaStreams = window.mediaStreams || {};

			// Check if the requested stream was created before.
			$.each(window.mediaStreams, (deviceId, obj) => {
				if (deviceId == options.device.deviceId && typeof obj.imagecapture !== "undefined") {
					prevImageCapture = obj.imagecapture;

					// Exit loop.
					return false;
				}
			});

			if (prevImageCapture/*  && prevImageCapture instanceof ImageCapture */) {
				typeof callback === "function" ? callback({imageCapture: prevImageCapture}) : null;

				return prevImageCapture;
			} else {
				// Instantiate ImageCapture object.
				if (track.readyState === "live") {
					let capabilities = {}, settings = {};

					/* Instantiate ImageCapture object.
					 * NOTE:  The try/catch block is intended to handle a potential referencing error
					 *        reported in the Firefox browser (ReferenceError: ImageCapture is not defined).
					 */
					try {
						imageCapture = new ImageCapture(track);

						if (!(imageCapture instanceof ImageCapture)) {
							plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
								"Failed to create the ImageCapture instance."
							);

							return;
						}
					} catch (err) {}

					imageCapture
					.getPhotoCapabilities()
					.then(photoCapabilities => {
						capabilities = photoCapabilities;

						return imageCapture.getPhotoSettings();
					})
					// Get photo capture settings.
					.then(photoSettings => {
						settings = photoSettings;

						window.mediaStreams[options.device.deviceId] = $.extend({}, window.mediaStreams[options.device.deviceId], {
							imagecapture : {
								capture           : imageCapture,
								photoCapabilities : capabilities,
								photoSettings     : photoSettings
							},
						});

						typeof callback === "function" ? callback({imageCapture: window.mediaStreams[options.device.deviceId].imagecapture}) : null;

						return window.mediaStreams[options.device.deviceId].imagecapture;	// Will execute when there's no callback
					})
					.catch(err =>  {
						switch (err.name) {
							/* The object is in an invalid state if the readyState of the track provided in the constructor is not live.
							 */
							case "InvalidStateError" :
								console.warn("Again this fu***** InvalidStateError");
							break;

							default :
								console.error("Unspecified error:", err.message);
						}
					});

				} else {
					plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
						"The selected device' video track is not ready and can therefore not be used."
					);

					return;
				}
			}

			// return imageCapture;
		},

		/* Returns the currently selected media device.
		 *
		 * @param    target   (element)  The control to affect
		 *
		 * @return   array   The currently detected device list
		 */
		_getSelectedDevice       : function(target) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let device;

			plugin._getDetectedDevices(target).every(MediaDevice => {
				if (MediaDevice.selected == true) {
					// Pick for returning.
					device = MediaDevice;

					// Exit loop.
					return false;
				}

				/* Make sure you return true. If you don't return a value, `every()` will stop.
				 * With every(), "return false" is equivalent to "break", and "return true" is equivalent to "continue"
				 * read more about mastering loops in JS: https://masteringjs.io/tutorials/fundamentals/foreach-break.
				 */
				return true;
			});

			return device;
		},

		/* Is using Promise(s) */
		_getStream               : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			if (!options.device || !(options.device instanceof MediaDeviceInfo)) {
				plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
					"Please verify you selected a media device and this device is properly connected."
				);

				return;
			}

			let isAudioDevice, isVideoDevice;

			// The only way to detect a device' type without causing an error is this.
			try {
				options.device.kind;
			} catch (err) {
				plugin._renderMessage(target, {type: "danger"}, "" +	//TODO - translate
					"The selected device is no streaming device at all."
				);

				return;
			}

			// Is device a microphone?
			try {
				options.device.kind !== "audoinput";
			} catch (err) {
				isAudioDevice = false;
			}

			// Is device a camera?
			try {
				options.device.kind !== "videoinput";
			} catch (err) {
				isVideoDevice = false;
			}

			// If there's no video device and no audio device abort.
			if (false === isAudioDevice && false === isVideoDevice)  {
				plugin._renderMessage(target, {type: "danger"}, "" +	//TODO - translate
					"The selected device is no valid streaming device."
				);

				return;
			}

			let detectedDevices = plugin._getDetectedDevices(target), streamingDevice, prevStream;

			window.mediaStreams = window.mediaStreams || {};

			// Check if the requested stream was created before.
			$.each(window.mediaStreams, (deviceId, obj) => {
				if (deviceId == options.device.deviceId && typeof obj.stream !== "undefined") {
					prevStream = obj.stream;

					// Exit loop.
					return false;
				}
			});

			// If the requested stream was created before return that instead of creating a new one.
			if (prevStream && prevStream instanceof MediaStream) {
				typeof callback === "function" ? callback({/* device: streamingDevice,  */stream: prevStream}) : null;

				return prevStream;	// Will execute when there's no callback
			} else {
				// Base constraints
				// see: https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia
				const constraints    = {
					audio : false,
					video: {
						deviceId   : { exact: options.device.deviceId },
						// facingMode : "user",			// require the face camera
						facingMode : "environment",		// require the rear camera
						width      : { min: 640, ideal: 1280, max: 1920 },
						height     : { min: 480, ideal:  720, max: 1080 }
					}
				};

				//TODO - check device' mediaTrackCapabilities and choose constraints accordingly.

				navigator
				.mediaDevices
				.getUserMedia(constraints)
				.then(stream => {
					// Find requested stream and return it.
					// Flag selected device as "active".
					detectedDevices.forEach(device => {
						// Stop all other streams.
						if (device.deviceId !== options.device.deviceId) {
							plugin._stopStream(target, {device: device});
						} else {
							window.mediaStreams[device.deviceId] = $.extend({}, window.mediaStreams[device.deviceId], {stream: stream});
						}
					});

					typeof callback === "function" ? callback({/* device: streamingDevice,  */stream: window.mediaStreams[options.device.deviceId].stream}) : null;
				})
				.catch(err => {
					switch (err.name) {
						/* Although the user and operating system both granted access to the hardware device, and no hardware issues occurred
						 * that would cause a NotReadableError, some problem occurred which prevented the device from being used.
						 */
						case "AbortError" :
						break;

						/* One or more of the requested source devices cannot be used at this time. This will happen if the browsing context
						 * is insecure (that is, the page was loaded using HTTP rather than HTTPS). It also happens if the user has specified
						 * that the current browsing instance is not permitted access to the device, the user has denied access for the current
						 * session, or the user has denied all access to user media devices globally.
						 */
						case "NotAllowedError" :
						break;

						/* No media tracks of the type specified were found that satisfy the given constraints.
						 */
						case "NotFoundError" :
						break;

						/* Although the user granted permission to use the matching devices, a hardware error occurred at the operating system,
						 * browser, or Web page level which prevented access to the device.
						 */
						case "NotReadableError" :
							plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
								[
									err.message,
									"",
									"<em>Troubleshooting measures:</em>",
									"",
									"1. Disconnect all video devices.",
									"2. Clear the web browser's cache (could result in logging in again).",
									"3. Connect your video device.",
									"4. Make sure that your video device is switched on.",
									"5. Make sure that access to the video device is enabled in the web browser.",
									"",
									"Still no go or need assistance? Ask your technical support."
								].join("<br>")
							);

							return;
						break;

						/* The specified constraints resulted in no candidate devices which met the criteria requested. The error is an object
						 * of type OverconstrainedError, and has a constraint property whose string value is the name of a constraint which was
						 * impossible to meet, and a message property containing a human-readable string explaining the problem.
						 */
						case "OverconstrainedError" :
							plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
								"The selected device does not support constraint: " + err.constraint
							);

							return;
						break;

						/* User media support is disabled on the Document on which getUserMedia() was called. The mechanism by which user media
						 * support is enabled and disabled is left up to the individual user agent.
						 */
						case "SecurityError" :
						break;

						/* The list of constraints specified is empty, or has all constraints set to false. This can also happen if you try to
						 * call getUserMedia() in an insecure context, since navigator.mediaDevices is undefined in an insecure context.
						 */
						case "TypeError" :
						break;

						default :
							console.error("Unspecified error:", err.message);
					}
				});
			}
		},

		_startStream             : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			if (!options.stream || !(options.stream instanceof MediaStream)) {
				plugin._renderMessage(target, {type: "error"}, "" +	//TODO - translate
					"Could not start the stream. Invalid stream."
				);

				return;
			}

			target.data({stream: options.stream}); // make stream available to console

			const $videoScreens = options.videoScreen || target.find("video");

			//FIXME - where does the script know who is "video" from without variable introduction???
			// video.srcObject = target.data("stream");
			// video.play();	// Only required when video element has no attribute "autoplay"

			$.each($videoScreens, (i, video) => {
				video.srcObject = target.data("stream");

				if (video.autoplay && video.autoplay == false) {
					video.play();	// Only required when video element has no attribute "autoplay"
				}
			});

			return true;
		},

		_stopStream              : function(target, options, callback) {
			console.error("DISABLED! Implement!");
			return;
		},

		_stopAllStreams          : function(callback) {
			console.error("DISABLED! Implement!");
			return;
		},

		/* Is using Promise(s) */
		_clearPhoto              : function(target, options, callback) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			let boxWidth   = target.find("#image-wrapper").css("width"),
				boxHeight  = target.find("#image-wrapper").css("height"),
				isBoxBG    = target.find("#media-container").css("backgroundColor"),
				shallBoxBG = "#000",
					effect = options.effect   || "fade",
				duration   = options.duration || 100,
					easing = options.easing   || "swing";

			target
			.find("#media-container")
			.css({
				backgroundColor : "unset",
				height : boxHeight,
				width  : boxWidth
			})
			// Wrap content with a temporary container
			.children()
				/* Necessary prerequisite to prevent the container from collapsing
				 * and make the desired effect appear as wished - like a camera shutter.
				 */
				.wrapAll(
					$("<div/>", {
						id  :"effect-wrapper",
						css : {
							width  : boxWidth,
							height : boxHeight
						}
					})
				)
				.end()
			// Animate temporary container
			.find("#effect-wrapper")
			.hide({
				effect:   effect,
				duration: duration,
				easing:   easing,
				complete: () => {
					target
					.removeClass([
						plugin._streamingClass
					])
					.addClass([
						plugin._loadingClass
					])
					.find("#image-wrapper")
						.removeClass("hasSnapshot")
						.removeAttr("style")
						.hide()
						.end()
					.find("#effect-wrapper")
						.children()
						.unwrap()
						.end().end()
					.queue(next => {
						setTimeout(() => {
							target
							.find("#video-wrapper")
								.css({display: target.find("#video-wrapper").data("style").display})
								.end()
							.find("#media-container")
							.removeAttr("style");

							typeof callback === "function" ? callback() : null;

							next();
						}, 200);	// 200 = fast, 600 = slow (according to jQuery's "fast" and "slow" props)
					});
				}
			});
		},

		/* Is using Promise(s) */
		_takePhoto               : function(target, options, callback) {
			if (options.hasOwnProperty('imageCapture') && options.imageCapture.hasOwnProperty('capture') && options.imageCapture.capture instanceof ImageCapture) {
				options.imageCapture.capture
				.takePhoto()	// return value: A Promise that resolves with a Blob.
				.then(blob => {
					typeof callback === "function" ? callback(blob) : null;
				})
				.catch(err => console.error(err));
			}
		},

		// UTILITY FUNCTIONS
		// =================

		_supportsImageCapture    : function() {
			return typeof ImageCapture === "function";
		},

		_supportsMediaDevice     : function() {
			return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
		},

		/* Returns an object of all capabilities and their configuration ranges for a given ImageCapture's MediaTrack.
		 *
		 * Searches in the options object for a property named "imageCapture" that holds an instance of ImageCapture.
		 */
		_getDeviceCapabilities   : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			switch (true) {
				case (options.stream && options.stream instanceof MediaStream) :
					const track = options.stream.getVideoTracks()[options.track || 0];

					return track.getCapabilities();
				break;

				case (options.imageCapture && options.imageCapture instanceof ImageCapture) :

					return options.imageCapture.track.getCapabilities();
				break;

				default :
					plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
						"An instance of MediaStream or ImageCapture is required for track capabilities detection."
					);

				return;
			}
		},

		/* Returns an object of all capabilities and their current configuration for a given ImageCapture's MediaTrack.
		 *
		 * Searches in the options object for a property named "imageCapture" that holds an instance of ImageCapture.
		 */
		_getDeviceSettings       : function(target, options) {
			target = $(target);

			if (!target.hasClass(this.markerClassName)) {
				return;
			}

			switch (true) {
				case (options.stream && options.stream instanceof MediaStream) :
					const track = options.stream.getVideoTracks()[options.track || 0];

					return track.getSettings();
				break;

				case (options.imageCapture && options.imageCapture instanceof ImageCapture) :

					return options.imageCapture.track.getSettings();
				break;

				default :
					plugin._renderMessage(target, {type: "notice"}, "" +	//TODO - translate
						"An instance of MediaStream or ImageCapture is required for track settings detection."
					);

				return;
			}
		},
	});

	// The list of commands that return values and don't permit chaining.
	const getters = [
		"destroy",
		"init",
		"restart"
	];
	// The list of commands that set values.
	const setters = [];

	/* Determine whether a method is a getter and doesn't permit chaining.
	 *
	 * @param    method  (string, optional)  the method to run
	 * @param    args    ([], optional)      any other arguments for the method
	 *
	 * @return   boolean  true if the method is a getter, false if not
	 */
	function isNotChainable(method, args) {
		if (method === "option" && (args.length === 0 || (args.length === 1 && typeof args[0] === "string"))) {
			return true;
		}

		return $.inArray(method, getters) > -1;
	}

	/* Determine whether a method is private and doesn't permit access.
	 *
	 * @param	method	(string, optional)  the method to run
	 * @param	args	([], optional)      any other arguments for the method
	 *
	 * @return	boolean  true if the method is private, false if not
	 */
	function isNotCallable(method, args) {
		if (method === "option" && (args.length === 0 || args.length === 2)) {	// 0 means "no args" means "get", 2 means "args" means "set"
			return $.inArray(args, setters) > -1;
		} else if (typeof method === "string") {
			// return $.inArray(method, setters) === -1;
			return ($.inArray(method, getters) === -1 && $.inArray(method, setters) === -1);
		}

		return false;
	}

	// PLUGIN DEFINITION
	// ==========================

	/* Attach the nemacam functionality to a jQuery selection.
	 *
	 * @param	options  (object)  the new settings to use for these instances (optional) or (string) the method to run (optional)
	 *
	 * @return	object	 (jQuery)  for chaining further calls or (any) getter value
	 */
	function Plugin (options) {
		const args = [].slice.call(arguments, 1);

		// Called method is a getter and doesn't permit chaining. Just attempt to execute it.
		if (isNotChainable(options, args)) {
			return plugin["_" + options + "Plugin"].apply(plugin, [this[0]].concat(args));
		}

		// Called method is a setter private method and doesn't permit access.
		if (isNotCallable(options, args)) {
			throw "Unknown plugin method: " + options;
		}

		return this.each(() => {
			if (typeof options === "string") {	// That means, a function cannot be called with arguments like ".plugin('method','args')"
				if (!plugin["_" + options + "Plugin"]) {
					throw "Unknown plugin method: " + options;
				}

				plugin["_" + options + "Plugin"].apply(plugin, [this].concat(args));
			} else {
				plugin._attachPlugin(this, options || {});
			}
		});
	}

	const nemacam = $.fn.nemacam;

	$.fn.nemacam = Plugin;

	// PLUGIN NO CONFLICT
	// ==================

	$.fn.nemacam.noConflict = function() {
		$.fn.nemacam = nemacam;

		return this;
	};

	// Initialise the nemacam functionality as Singleton instance.
	const plugin = $.nemacam = new Nemacam();
}(jQuery.noConflict(), window);

+function($, window) {
	"use strict";

	$('[data-bind="nemacam"]')
	.closest(".modal")
		/*.on("show.bs.modal",   evt => {	// Is not catched
			// let $this = $(evt.target || evt.currentTarget), $container = $this.find('[data-bind="nemacam"]');

			if (!$container.is(".hasNemacam") && typeof $.nemacam === "object") {
				$container.nemacam({debug: true}).queue(next => {
					$container = $this.find('[data-bind="nemacam"]');

					next();
				});
			}
		})*/
		.on("shown.bs.modal",  evt => {
			let $this = $(evt.target || evt.currentTarget), $container = $this.find('[data-bind="nemacam"]');

			if (!$container.is(".hasNemacam") && typeof $.nemacam === "object") {
				$container
				// .nemacam({debug: true, interval: 300000, maxAttempts:  360})	// poll every  5m and stop after 1h
				// .nemacam({debug: true, interval: 30000, maxAttempts:  360})	// poll every 30s and stop after 1h
				// .nemacam({debug: true, interval: 25000, maxAttempts:  360})	// poll every 25s and stop after 1h
				// .nemacam({debug: true, interval: 20000, maxAttempts:  360})	// poll every 20s and stop after 1h
				// .nemacam({debug: true, interval: 15000, maxAttempts:  360})	// poll every 15s and stop after 1h
				// .nemacam({debug: true, interval: 10000, maxAttempts:  360})	// poll every 10s and stop after 1h
				.nemacam({debug: true, interval:  5000, maxAttempts:  720})	// poll every  5s and stop after 1h
				// .nemacam({debug: true, interval:  3000, maxAttempts: 1200})	// poll every  3s and stop after 1h
				// .nemacam({debug: true, interval:  2000, maxAttempts: 1800})	// poll every  3s and stop after 1h
				.queue(next => {
					$container = $this.find('[data-bind="nemacam"]');

					next();
				})
				.nemacam("init");
			}

			$this
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
				);
		})
		.on("hide.bs.modal",   evt => {
			let $this = $(evt.target || evt.currentTarget), $container = $this.find('[data-bind="nemacam"]');

			if ($container.is(".hasNemacam")) {
				$container.nemacam("destroy");
			}
		})
		/*.on("hidden.bs.modal", evt => {
			$.each(window.mediaStreams, (id, obj) => {
				obj.stream.getTracks().forEach(track => {
					// track.stop();
					track.enabled = false;
				});
			});
		})*/
		.end();

	/*$(".nemacam")
	// catch deviceListUpdate event
	.on("input",  evt => {
		console.warn("deviceListUpdate event");
	})
	// catch deviceSelection event
	.on("change", evt => {
		console.warn("deviceSelection event");
	});*/

}(jQuery.noConflict(), window);
