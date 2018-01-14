const ApiContext = require('../../../modules/ApiContext');
const StoreAdapter = require('../../../modules/MongoAdapter');
// const $ = require('cafy').default;

/** @param {ApiContext} apiContext */
exports.get = async (apiContext) => {
	await apiContext.proceed({
		permissions: ['application']
	});
	if (apiContext.responsed) return;

	let application;
	try {
		application = await apiContext.repository.findById('applications', apiContext.params.id);
	}
	catch (err) {
		console.log(err);
	}

	if (application == null) {
		apiContext.response(204);
		return;
	}

	apiContext.response(200, { application: apiContext.applicationsService.serialize(application) });
};
