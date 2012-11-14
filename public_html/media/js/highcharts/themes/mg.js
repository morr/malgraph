Highcharts.theme = {
	colors: ['#058DC7', '#50B432', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],
	chart: {
		/*plotBackgroundColor: 'rgba(255, 255, 255, .9)',*/
		plotShadow: false,
		/*plotBorderWidth: 1*/
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
		lineColor: '#000',
		lineWidth: 1,
		tickWidth: 1,
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
	}
};

// Apply the theme
var highchartsOptions = Highcharts.setOptions(Highcharts.theme);
