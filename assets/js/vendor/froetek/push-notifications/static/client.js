"use strict";

// Make connection to notifyer service.
if (typeof io !== "function") {
	throw "Socket.io library not available. Please make sure you include that library for push notifications to work!";
}

var
	socket = io.connect("http://localhost:49876", {	// configuration object code friendly borrowed from https://github.com/socketio/socket.io/issues/2476
    // socket = io.connect("https://webservice.froetek.website:49876", {
		"force new connection" : true,
		// transports: ["websocket"],
		timeout : 10000,						// Time before connect_error and connect_timeout are emitted
		reconnection: true,
		reconnectionAttempts: Infinity,			// Set any numeric value to limit connection attempts or "Infinity" (without quotes) to avoid users have to reconnect manually in order to prevent dead clients after a server restart
		reconnectionDelay: 1000,
		reconnectionDelayMax : 5000
});
var $ = (typeof window.$ === "function" ? $ : window.jQuery);

// Emit events.
$("#btnSubmit")
.on("click", function(evt) {
	// Emit takes 2 args.
	//   arg 1: A fully customizable event name. This name is to be used on server side to catch and handle this event
	//   arg 2. A data object. In this case the name of the logged in user, the message type (as selectable from dropdown list) and the message itself
	socket.emit( $("#type").find(":selected").val(), {
		user:    $("#user").val().trim(),
		msgType: $("#type").find(":selected").val(),
		message: $("#message").val().trim()
	});
});

// Listen for events.
socket
.on("connect",       function () {
	console.log('Socket connection established.');
})
.on("connect_error", function () {
	console.log('Socket not connected.');
})
.on("disconnect",    function () {
	console.log('Socket lost connection.');
})
.on("notification",  function(data) {
	let $ = (typeof window.$ === "function" ? $ : window.jQuery),
   $modal = $("#mainModal");

	$modal
	.on("show.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").addClass("alert-success").find("button").remove();
		$modal.find("#mainModalTitle").html( data.msgType.charAt(0).toUpperCase() + data.msgType.substr(1, data.msgType.length).toLowerCase() );
		$modal.find("#mainModalBody").html( data.message );
	})
	.on("hidden.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").removeClass("alert-warning");
		$modal.find("#mainModalTitle").text( "Lade..." );
		$modal.find("#mainModalBody").html( "&hellip;" );
	})
	.modal({
		backdrop: "static",
		keyboard: false
	})
	.modal("show");
})
.on("warning",       function(data) {
	let $ = (typeof window.$ === "function" ? $ : window.jQuery),
   $modal = $("#mainModal");

	$modal
	.on("show.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").addClass("alert-warning").find("button").remove();
		$modal.find("#mainModalTitle").html( data.msgType.charAt(0).toUpperCase() + data.msgType.substr(1, data.msgType.length).toLowerCase() );
		$modal.find("#mainModalBody").html( data.message );
	})
	.on("hidden.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").removeClass("alert-warning");
		$modal.find("#mainModalTitle").text( "Lade..." );
		$modal.find("#mainModalBody").html( "&hellip;" );
	})
	.modal({
		backdrop: "static",
		keyboard: false
	})
	.modal("show");
})
.on("alert",         function(data) {
	let $ = (typeof window.$ === "function" ? $ : window.jQuery),
   $modal = $("#mainModal");

	$modal
	.on("show.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").addClass("alert-danger").find("button").remove();
		$modal.find("#mainModalTitle").html( data.msgType.charAt(0).toUpperCase() + data.msgType.substr(1, data.msgType.length).toLowerCase() );
		$modal.find("#mainModalBody").html( data.message );
		// $modal.find("#mainModalFooter").remove();
	})
	.on("hidden.bs.modal", function(evt) {
		$modal.find("#mainModalHeader").removeClass("alert-warning");
		$modal.find("#mainModalTitle").text( "Lade..." );
		$modal.find("#mainModalBody").html( "&hellip;" );

		window.location.reload();
	})
	.modal({
		backdrop: "static",
		keyboard: false
	})
	.modal("show");
});
