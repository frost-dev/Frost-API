const moment = require('moment');
const { ObjectId } = require('mongodb');
const MongoAdapter = require('../modules/MongoAdapter');
const { sortObject } = require('../modules/helpers/GeneralHelper');
const { MissingArgumentsError } = require('../modules/errors');
const UsersService = require('./UsersService');

class PostsService {
	/**
	 * @param {MongoAdapter} repository
	 * @param {UsersService} usersService
	*/
	constructor(repository, config, usersService) {
		if (repository == null || config == null|| usersService == null)
			throw new MissingArgumentsError();

		this._repository = repository;
		this._config = config;
		this._usersService = usersService;
	}

	async serialize(document, includeEntity) {
		if (document == null || includeEntity == null)
			throw new MissingArgumentsError();

		const res = Object.assign({}, document);

		// createdAt
		res.createdAt = parseInt(moment(res._id.getTimestamp()).format('X'));

		// id
		res.id = res._id.toString();
		delete res._id;

		// userId
		res.userId = res.userId.toString();

		if (includeEntity) {
			// user
			const user = await this._repository.findById('users', res.userId);
			if (user != null) {
				res.user = await this._usersService.serialize(user);
			}
		}

		return sortObject(res);
	}

	// helpers

	/**
	 * @param {String | ObjectId} userId
	 * @param {String} text
	 * @returns {PostDocument}
	*/
	async createStatusPost(userId, text) {
		if (userId == null || text == null)
			throw new MissingArgumentsError();

		let document;
		try {
			document = await this._repository.create('posts', {
				type: 'status',
				userId,
				text
			});
		}
		catch (err) {
			console.log(err);
		}
		return document;
	}

	/**
	 * @param {String | ObjectId} userId
	 * @param {String} text
	 * @param {String} title
	 * @returns {PostDocument}
	*/
	async createArticlePost(userId, text, title) {
		if (userId == null || text == null || title == null)
			throw new MissingArgumentsError();

		let document;
		try {
			document = await this._repository.create('posts', {
				type: 'article',
				userId,
				title,
				text
			});
		}
		catch (err) {
			console.log(err);
		}
		return document;
	}
}
module.exports = PostsService;
