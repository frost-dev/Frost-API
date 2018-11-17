const ApiContext = require('../../modules/ApiContext');
const DataTypeIdHelper = require('../../modules/helpers/DataTypeIdHelper');
const RedisEventEmitter = require('../../modules/RedisEventEmitter');
const $ = require('cafy').default;
const MongoAdapter = require('../../modules/MongoAdapter');
const { getStringSize } = require('../../modules/helpers/GeneralHelper');

/** @param {ApiContext} apiContext */
exports.get = async (apiContext) => {
	await apiContext.proceed({
		body: {
			postId: { cafy: $().string().pipe(i => MongoAdapter.validateId(i)) }
		},
		scopes: ['post.read']
	});
	if (apiContext.responsed) return;

	const post = await apiContext.repository.findById('posts', apiContext.body.postId);
	if (post == null) {
		apiContext.response(404, 'post not found');
		return;
	}

	apiContext.response(200, { post: await apiContext.postsService.serialize(post, true) });
};

/** @param {ApiContext} apiContext */
exports['create-chat'] = async (apiContext) => {
	await apiContext.proceed({
		body: {
			text: { cafy: $().string().range(1, 256).pipe(i => !/^\s*$/.test(i)) },
			attachments: { cafy: $().array($().string()).unique().max(4).each(i => MongoAdapter.validateId(i)), default: [] }
		},
		scopes: ['post.write']
	});
	if (apiContext.responsed) return;

	const userId = apiContext.user._id;
	const text = apiContext.body.text;
	const attachmentIds = apiContext.body.attachments.map(i => MongoAdapter.buildId(i));

	// check existing files
	const fileCount = await apiContext.repository.count('storageFiles', { _id: { $in: attachmentIds } });
	if (fileCount != attachmentIds.length) {
		apiContext.response(400, 'some attachmentIds are invalid value');
		return;
	}

	let postStatus = await apiContext.postsService.createStatusPost(userId, text, attachmentIds);
	if (postStatus == null) {
		apiContext.response(500, 'failed to create postStatus');
		return;
	}

	const serialized = await apiContext.postsService.serialize(postStatus, true);

	// RedisEvent posting.chat を発行
	const eventSender = new RedisEventEmitter('frost-api', false);
	await eventSender.emit(DataTypeIdHelper.build(['redis', 'posting', 'chat']), {
		posting: serialized
	});
	await eventSender.dispose();

	apiContext.response(200, { postMessage: serialized });
};

/** @param {ApiContext} apiContext */
exports['create-article'] = async (apiContext) => {
	await apiContext.proceed({
		body: {
			title: { cafy: $().string() },
			text: { cafy: $().string() }
		},
		scopes: ['post.write']
	});
	if (apiContext.responsed) return;

	const { title, text } = apiContext.body;

	if (/^\s*$/.test(title) || getStringSize(text) > 64) {
		apiContext.response(400, 'title is invalid format. max 64bytes');
		return;
	}

	if (/^\s*$/.test(text) || getStringSize(text) > 10000) {
		apiContext.response(400, 'text is invalid format. max 10,000bytes');
		return;
	}

	const postArticle = await apiContext.postsService.createArticlePost(apiContext.user._id, text, title);
	if (postArticle == null) {
		apiContext.response(500, 'failed to create postArticle');
		return;
	}

	apiContext.response(200, { postArticle: await apiContext.postsService.serialize(postArticle, true) });
};

/** @param {ApiContext} apiContext */
exports['create-reference'] = async (apiContext) => {
	await apiContext.proceed({
		body: {},
		scopes: ['post.write']
	});
	if (apiContext.responsed) return;

	apiContext.response(501, 'not implemented yet');
};
