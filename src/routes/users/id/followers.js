const ApiContext = require('../../../modules/ApiContext');
const { ObjectId } = require('mongodb');
const v = require('validator');
const $ = require('cafy').default;

// TODO: カーソル送り

/** @param {ApiContext} apiContext */
exports.get = async (apiContext) => {
	await apiContext.proceed({
		query: {
			limit: { cafy: $().string().pipe(i => v.isInt(i, { min: 0, max: 100 })), default: '30' },
			cursor: { cafy: $().string().pipe(i => ObjectId.isValid(i)), default: null }
		},
		permissions: ['userRead']
	});
	if (apiContext.responsed) return;

	// convert query value
	const limit = v.toInt(apiContext.query.limit);
	const cursor = new ObjectId(apiContext.query.cursor);

	// user
	const user = await User.findByIdAsync(apiContext.params.id, apiContext.db, apiContext.config);
	if (user == null) {
		return apiContext.response(404, 'user as premise not found');
	}

	// このユーザーがフォロー元であるフォロー関係をすべて取得
	const userFollowings = await UserFollowing.findSourcesAsync(user.document._id, limit, apiContext.db, apiContext.config);
	if (userFollowings == null || userFollowings.length == 0) {
		apiContext.response(204);
		return;
	}

	// fetch and serialize users
	const promises = userFollowings.map(async following => {
		const user = await User.findByIdAsync(following.document.source, apiContext.db, apiContext.config);
		if (user == null) {
			console.log(`notfound following source userId: ${following.document.source.toString()}`);
			return;
		}
		return await user.serializeAsync();
	});
	const pureSerializedUsers = await Promise.all(promises);

	// sort in original order
	const serializedUsers = userFollowings.map(following => pureSerializedUsers.find(u => u.id == following.document.source));

	apiContext.response(200, { users: serializedUsers });
};
