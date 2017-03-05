'use strict';

module.exports = (statusCode, message, data) => {
	const instance = {};
	instance.statusCode = statusCode;
	instance.message = message;
	instance.data = data;

	return instance;
};
