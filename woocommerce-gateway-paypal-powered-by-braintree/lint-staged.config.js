module.exports = {
	'*.php': ( filenames ) => {
		// Use phpcs-changed with --git flag to check only changed lines
		return `./vendor/bin/phpcs-changed -s --git --git-staged ${ filenames.join(
			' '
		) }`;
	},
};
