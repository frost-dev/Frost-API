'use strict';

const randomRange = require('../helpers/random-range');

const authorizeRequestModel = require('../models/authorize-request');

module.exports = async (documentId, dbManager) => {
	const instance = {};

	instance.documentId = documentId;
	instance.dbManager = dbManager;

	instance.generatePinCodeAsync = async () => {
		var pinCode = '';
		for (var i = 0; i < 6; i++)
			pinCode += String(randomRange(0, 9));

		dbManager.updateAsync('authorizeRequests', {_id: documentId}, {pinCode: pinCode});

		return pinCode;
	};

	instance.generateRequestKeyAsync = async () => {
		const keyCode = randomRange(1, 99999);
		dbManager.updateAsync('authorizeRequests', {_id: documentId}, {keyCode: keyCode});
		const request = await dbManager.findArrayAsync('authorizeRequests', {_id: documentId})[0];

		return authorizeRequestModel.buildRequestKey(request._id, request.applicationId, request.keyCode);
	};

	instance.getRequestKeyAsync = async () => {
		const request = await dbManager.findArrayAsync('authorizeRequests', {_id: documentId})[0];

		if (request == null)
			throw new Error('authorize-request not found');

		return authorizeRequestModel.buildRequestKey(request._id, request.applicationId, request.keyCode);
	};

	return instance;
};
