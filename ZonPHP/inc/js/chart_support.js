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
    let maxSum = [];
    let refSum = [];
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
            // max
            for (ii in dataset.data) {
                if (maxSum[ii] == null) maxSum[ii] = 0;
                maxSum[ii] = maxSum[ii] + dataset.dataMAX[ii].y;
            }
            // cum
            for (ii in dataset.data) {
                if (cumSum[ii] == null) cumSum[ii] = 0;
                cumSum[ii] = cumSum[ii] + dataset.dataCUM[ii].y;
            }
            // ref per month
            for (ii in dataset.data) {
                if (refSum[ii] == null) refSum[ii] = 0;
                refSum[ii] = refSum[ii] + dataset.dataREF[ii].y;
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
        let maxIDX = findDatasetById(data.datasets, "max");
        if (maxIDX > 0) {
            data.datasets[maxIDX].data = maxSum;
        }
        let refIDX = findDatasetById(data.datasets, "ref");
        if (refIDX > 0) {
            data.datasets[refIDX].data = refSum;
        }
        chart.update();
    }
};
