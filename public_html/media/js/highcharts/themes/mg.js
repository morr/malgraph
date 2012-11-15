Highcharts.theme = {
	colors: ['#1969CB', '#50B432', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],

	credits: {'enabled': false},

	chart: {
		plotShadow: false,
	},

	title: {
		style: {
			color: '#000',
			font: 'bold 1.25em Verdana, Dejavu Sans, sans-serif'
		}
	},

	subtitle: {
		style: {
			color: '#666666',
			font: 'bold 1.15em Verdana, Dejavu Sans, sans-serif'
		}
	},

	xAxis: {
		gridLineWidth: 1,
		minorGridLineColor: '#f5f5f5',
		gridLineColor: '#f5f5f5',
		lineColor: '#000',
		tickColor: '#000',
		labels: {
			style: {
				color: '#000',
				font: '9pt Verdana, Dejavu Sans, sans-serif'
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

	legend: {
		itemStyle: {
			font: '9pt Verdana, Dejavu Sans, sans-serif',
			color: 'black'

		},
		itemHoverStyle: {
			color: '#039'
		},
		itemHiddenStyle: {
			color: 'gray'
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
	},

};

// Apply the theme
var highchartsOptions = Highcharts.setOptions(Highcharts.theme);
