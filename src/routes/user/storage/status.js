const ApiContext = require('../../../modules/ApiContext');
const MongoAdapter = require('../../../modules/MongoAdapter');
const { getUsedSpace } = require('../../../modules/helpers/UserStorageHelper');
const $ = require('cafy').default;

/** @param {ApiContext} apiContext */
exports.get = async (apiContext) => {
	await apiContext.proceed({
		body: {
			userId: { cafy: $().string().pipe(i => MongoAdapter.validateId(i)) }
		},
		scopes: ['storage.read']
	});
	if (apiContext.responsed) return;

	// user
	const user = await apiContext.repository.findById('users', apiContext.body.userId);
	if (user == null) {
		apiContext.response(404, 'user as premise not found');
		return;
	}

	const isOwned = user._id.equals(apiContext.user._id);
	if (!isOwned) {
		apiContext.response(403, 'this operation is not permitted');
		return;
	}

	const usedSpace = await getUsedSpace(user._id, apiContext.storageFilesService);
	const availableSpace = apiContext.config.storage.spaceSize - usedSpace;

	apiContext.response(200, {
		storage: {
			spaceSize: apiContext.config.storage.spaceSize,
			usedSpace: usedSpace,
			availableSpace: availableSpace
		}
	});
};