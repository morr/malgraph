Highcharts.theme = {
	colors: ['#1969CB', '#ED561B', '#50B432', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],

	credits: {'enabled': false},

	chart: {
		plotShadow: false,
		backgroundColor: 'rgba(255, 255, 255, 0)',
		spacingTop: 0,
		spacingBottom: 0,
		spacingLeft: 0,
		spacingRight: 0,
	},

	title: false,
	legend: false,

	xAxis: {
		gridLineWidth: 1,
		minorGridLineColor: '#f5f5f5',
		gridLineColor: '#f5f5f5',
		lineColor: '#000',
		tickColor: '#000',
		labels: {
			style: {
				color: '#000',
				font: '8pt Verdana, Dejavu Sans, sans-serif'
			}
		},
		title: {
			style: {
				color: '#333',
				fontWeight: 'bold',
				fontSize: '9pt',
				fontFamily: 'Verdana, Dejavu Sans, sans-serif'

			}
		}
	},

	yAxis: {
		minorTickInterval: 'auto',
		gridLineColor: '#ddd',
		minorGridLineColor: '#f5f5f5',
		lineColor: '#000',
		lineWidth: 1,
		tickWidth: 1,
		tickColor: '#000',
		labels: {
			style: {
				color: '#000',
				font: '8pt Verdana, Dejavu Sans, sans-serif'
			}
		},
		title: {
			style: {
				color: '#333',
				fontWeight: 'bold',
				fontSize: '9pt',
				fontFamily: 'Verdana, Dejavu Sans, sans-serif'
			}
		}
	},

	labels: {
		style: {
			color: '#99b'
		}
	},

	plotOptions: {
		bar: {
			pointWidth: 18,
			borderWidth: 1,
			shadow: false,
		},
		column: {
			pointWidth: 18,
			borderWidth: 1,
			shadow: false,
		},
		line: {
			shadow: false,
		}
	},

	tooltip: {
		style: {
			pointerEvents: 'none'
		}
	}

};

// Apply the theme
var highchartsOptions = Highcharts.setOptions(Highcharts.theme);
