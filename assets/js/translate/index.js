const run = (config) => {

	const _ = (message, number, params) => {
		let s = config.all[message];

		if (s === undefined) {
			return null;
		}

		if (params) {
			for (const key in params) {
				s = s.replaceAll(`%${key}%`, params[key]);
			}
		}

		if (s.includes("|")) {
			const options = s.split("|");
			if (Math.abs(number) === 1) {
				s = options[0];
			} else if (Math.abs(number) >= 2 && Math.abs(number) <= 4) {
				s = options[1];
			} else {
				s = options[2];
			}
		}

		return s;
	}

	window._ = _;
}

export default {run};