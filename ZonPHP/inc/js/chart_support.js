const defaultLegendClickHandler = Chart.defaults.plugins.legend.onClick;

function findDatasetById(datasets, name) {
    for (i in datasets) {
        let label = datasets[i].datasetId;
        if (name === label) {
            return i;
        }
    }
    return -1; // not found
}

const plugin = {
    id: 'customCanvasBackgroundColor',
    beforeDraw: (chart, args, options) => {
        const {ctx} = chart;
        ctx.save();
        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = options.color || '#99ffff';
        ctx.fillRect(0, 0, chart.width, chart.height);
        ctx.restore();
    }
};
const newLegendClickHandler = function (e, legendItem, legend) {
    let chart = legend.chart;
    defaultLegendClickHandler(e, legendItem, legend);
    let avgSum = [];
    let expectedSum = [];
    let cumSum = [];
    let data = chart.data;

    for (i in data.datasets) {
        let meta = chart.getDatasetMeta(i);
        let dataset = chart.data.datasets[i];
        let isHidden = meta.hidden === null ? false : meta.hidden;
        if (dataset.isData && !isHidden) {
            // avg
            for (ii in dataset.data) {
                if (avgSum[ii] == null) avgSum[ii] = 0;
                avgSum[ii] = avgSum[ii] + dataset.averageValue;
            }
            // expected
            for (ii in dataset.data) {
                if (expectedSum[ii] == null) expectedSum[ii] = 0;
                expectedSum[ii] = expectedSum[ii] + dataset.expectedValue;
            }
            // cum
            for (ii in dataset.data) {
                if (cumSum[ii] == null) cumSum[ii] = 0;
                cumSum[ii] = cumSum[ii] + dataset.dataCUM[ii].y;
            }
        }
        let avgIDX = findDatasetById(data.datasets, "avg");
        if (avgIDX > 0) {
            data.datasets[avgIDX].data = avgSum;
        }
        let expectedIDX = findDatasetById(data.datasets, "expected");
        if (expectedIDX > 0) {
            data.datasets[expectedIDX].data = expectedSum;
        }
        let cumIDX = findDatasetById(data.datasets, "cum");
        if (cumIDX > 0) {
            data.datasets[cumIDX].data = cumSum;
        }
        chart.update();
    }
};
