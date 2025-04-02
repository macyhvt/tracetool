"use strict";

// main file / app entry point

// Define request callback handler.
// Code friendly borrowed with slight changes from: https://github.com/socketio/socket.io/issues/2294#issuecomment-266595966
// Also see: https://developer.mozilla.org/de/docs/Web/HTTP/CORS
function onRequest(req, res) {
	res.writeHead(200, {
		"Access-Control-Allow-Origin"  : "*",
		"Access-Control-Allow-Headers" : "X-Requested-With",
		"Set-Cookie" : "HttpOnly;Secure;SameSite=Strict"
		// "Set-Cookie" : "HttpOnly;Secure;SameSite=None"
		// "Set-Cookie" : "Secure;SameSite=None"
	});
};


var config = require('./config'),	// See: https://stackoverflow.com/a/5870544
   express = require("express"),
    socket = require("socket.io");

// App setup.
var  app = express(),
    port = config.web.port;

app.all('/', onRequest);

var server = app.listen(port, function() {
	console.log("Listening to requests on port", port);
});

// Static content.
// For more info regarding app.all() and app.use() see: https://stackoverflow.com/a/24192057
app.use(express.static("static"));

// Socket setup.
var io = socket(server, {
	cookie: false,			// see: https://stackoverflow.com/a/59485508
	key: privateKey,		// see: https://stackoverflow.com/a/26290188
	cert: certificate,		// see: https://stackoverflow.com/a/26290188
	ca: ca					// see: https://stackoverflow.com/a/26290188
});

// Define connection options.
// For more info regarding app.all() and app.use() see: https://stackoverflow.com/a/33912510
io.origins([
	    "[::1]:49876",
	"127.0.0.1:49876",
	"localhost:49876",
	"webservice.froetek.website:*"
]);
// Define allowed transport protocols.
// Code friendly borrowed with slight changes from: https://stackoverflow.com/a/54053629
io.set("transports", [
	"polling",
	"websocket"
]);
    // io.set("transports", ["websocket"]);

io.on("connection", function(socket) {
	// console.log("Socket connection established from socket #" + socket.client.id);

	// Broadcast message to all connected clients.
	// The event names handled match the options selectable in notification form and event names used in socket.js where these events are emitted.
	socket.on("notification", function(data) {		// data is the object sent by related function in socket.js
		// io.sockets.emit("notification", data);	// data is just passed to all connected clients INCL. the sender
		socket.broadcast.emit(data.msgType, data);		// data is just passed through to all connected clients BUT NOT the sender
	});
	socket.on("warning", function(data) {			// data is the object sent by related function in socket.js
		// io.sockets.emit("warning", data);		// data is just passed to all connected clients INCL. the sender
		socket.broadcast.emit(data.msgType, data);		// data is just passed through to all connected clients BUT NOT the sender
	});
	socket.on("alert", function(data) {				// data is the object sent by related function in socket.js
		// io.sockets.emit("alert", data);			// data is just passed to all connected clients INCL. the sender
		socket.broadcast.emit(data.msgType, data);		// data is just passed through to all connected clients BUT NOT the sender
	});
});
