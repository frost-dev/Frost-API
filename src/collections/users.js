'use strict';

const dbConnector = require('../modules/db-connector')();
const userDoc = require('../document-models/user');

module.exports = () => new Promise((resolve, reject) => (async () => {
	const instance = {};
	const dbManager = await dbConnector.connectApidbAsync();

	instance.create = () => new Promise((resolve, reject) => (async () => {
		// TODO
		const doc = await dbManager.createAsync('users', {});

		return userDoc(doc, dbManager);
	})());

	instance.find = () => new Promise((resolve, reject) => (async () => {
		// TODO
		const doc = await dbManager.findArrayAsync('users', {});

		return userDoc(doc, dbManager);
	})());

	return instance;
})());
