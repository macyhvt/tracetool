"use strict";

// config file / defines things like host URL, Port, etc.

var config = {};

config.app = {
	"name" : "push-service"
};

config.server = {
	"protocol" : "http",
	"domain"   : "localhost"
};

config.web = {
	"port" : process.env.WEB_PORT || 49876
};

module.exports = config;