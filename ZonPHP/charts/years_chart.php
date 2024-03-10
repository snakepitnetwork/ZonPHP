<?php
global $con, $colors, $params, $chart_options;
include_once "../inc/init.php";
include_once ROOT_DIR . "/inc/connect.php";
include_once "chart_support.php";

$isIndexPage = false;
$showAllInverters = true;
if (isset($_POST['action']) && ($_POST['action'] == "indexpage")) {
    $isIndexPage = true;
}

// -----------------------------  get data from DB -----------------------------------------------------------------
$datum = "";
$inveter_list = array();
$inClause = "'" . implode("', '", PLANT_NAMES) . "'";
// load sum per month for all years --------------------------------------------------------------------------------
$sql = "SELECT SUM( Geg_Maand ) AS sum_month, year( Datum_Maand ) AS year, month( Datum_Maand ) AS month, naam, 
            count( Datum_Maand ) AS tdag_maand
        FROM " . TABLE_PREFIX . "_maand     
        WHERE naam in ($inClause) 
        GROUP BY year, month, naam";

$result = mysqli_query($con, $sql) or die("Query failed. totaal " . mysqli_error($con));
$sum_per_year = array();
$total_sum_for_all_years = 0;

if (mysqli_num_rows($result) == 0) {
    $sum_per_year[date('Y-m-d', time())] = 0;
} else {
    while ($row = mysqli_fetch_array($result)) {
        $inverter_name = $row['naam'];
        if (!isset($sum_per_year[$row['year']])) {
            $sum_per_year[$row['year']][$inverter_name] = 0;
        }
        if (!isset($sum_per_year[$row['year']][$inverter_name])) {
            $sum_per_year[$row['year']][$inverter_name] = 0;
        }
        $sum_per_year[$row['year']][$inverter_name] += $row['sum_month'];

        $days_per_month = cal_days_in_month(CAL_GREGORIAN, $row['month'], $row['year']);
        if (!in_array($inverter_name, $inveter_list)) {
            if (in_array($inverter_name, PLANT_NAMES)) {
                // add to list only if it configured (ignore db entries)
                $inveter_list[] = $inverter_name;
            }
        }
    }

    foreach ($sum_per_year as $inverter_name => $val) {
        $total_sum_for_all_years += array_sum($val);
    }
}

// Average per inverter
$avg_data = array();
$sqltotal = "SELECT naam, ROUND((SUM( Geg_Maand ) /  COUNT( Geg_Maand ))* 365 , 0 ) AS grand_total_average 
FROM " . TABLE_PREFIX . "_maand
WHERE naam in ($inClause) 
GROUP BY naam";
$result = mysqli_query($con, $sqltotal) or die("Query failed (total average) " . mysqli_error($con));
while ($row = mysqli_fetch_array($result)) {
    $avg_data[$row['naam']] = $row['grand_total_average'];
}

?>
<?php
// ----------------------------- build data for chart -----------------------------------------------------------------
$myurl = HTML_PATH . "pages/year.php?date=";
$my_year = date("Y", time());

$yearcount = count($sum_per_year);
$strdataseries = "";
$labels = "";
$cumData = "";
$cumSum = 0.0;
$sumAverage = 0.0;
$sumExpected = 0.0;
$myColors = colorsPerInverter();

$totalsumCumArray = array();
foreach ($sum_per_year as $year => $fkw) {
    $labels .= '"' . $year . '",';
    $totalsumCumArray[$year] = 0.0;
}

foreach ($inveter_list as $inverter_name) {
    $inverterAverage = $avg_data[$inverter_name];
    $inverterExpected = $params[$inverter_name]['totalExpectedYield'];
    $strdata = "";
    $maxIndex = 0;
    $myColor1 = $myColors[$inverter_name]['min'];
    $myColor2 = $myColors[$inverter_name]['max'];
    $myMaxColor1 = "'" . $colors['color_chartbar_piek1'] . "'";
    $myMaxColor2 = "'" . $colors['color_chartbar_piek2'] . "'";
    $sumAverage += $inverterAverage;
    $sumExpected += $inverterExpected;
    $cumSum = 0;
    $idx = 0;
    foreach ($sum_per_year as $ijaar => $fkw) {
        if ($fkw >= max($sum_per_year)) {
            $maxIndex = $idx;
        }
        // normal chart, $val throws errors when missing inverter index
        @$val = round($fkw[$inverter_name], 2);
        $formattedHref = sprintf("%s%02d-%02d-%04d", $myurl, 1, 1, $ijaar);
        $strdata .= " { x: $ijaar, y: $val, url: \"$formattedHref\"},";
        $cumSum += $val;
        $cumData .= " { x: $ijaar, y: $cumSum},";
        $totalsumCumArray[$ijaar] = $totalsumCumArray[$ijaar] + $cumSum;
        $idx++;
    }

    $strdataseries .= " {
                    datasetId: '" . $inverter_name . "', 
                    label: '" . $inverter_name . "', 
                    type: 'bar',                               
                    stack: 'Stack 0',
                    borderWidth: 1,
                    data: [" . $strdata . "],                    
                    dataCUM: [" . $cumData . "],
                    dataMAX: [], 
                    dataREF: [],
                    averageValue: " . $inverterAverage . ",
                    expectedValue: " . $inverterExpected . ",
                    maxIndex: " . $maxIndex . ",
                    fill: true,
                    backgroundColor: function(context) {                         
                       var gradientFill = ctx.createLinearGradient(0, 0, 0, 500);                                   
                       if (context.index == (context.dataset.maxIndex)) {
                          gradientFill.addColorStop(0, " . $myMaxColor1 . ");
                          gradientFill.addColorStop(1, " . $myMaxColor2 . ");
                       } else {
                          gradientFill.addColorStop(0, " . $myColor1 . ");
                          gradientFill.addColorStop(1, " . $myColor2 . ");
                       }
                       return gradientFill;
                    },
                    yAxisID: 'y',
                    isData: true,
                },
    ";
    $cumData = "";
}

// average
$strdataseries .= " {
                    datasetId: 'avg', 
                    label: '" . getTxt("average") . "', 
                    type: 'line',      
                    stack: 'Stack 1',                                                                 
                    data: [" . buildConstantDataString($sumAverage, count($sum_per_year)) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_average_line'] . "',
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y',
                    fill: false,   
                    showLine: true,
                    isData: false,               
                },
    ";

// expected
$strdataseries .= " {
                    datasetId: 'expected', 
                    label: '" . getTxt("ref") . "', 
                    type: 'line',      
                    stack: 'Stack 2',                                                                 
                    data: [" . buildConstantDataString($sumExpected, count($sum_per_year)) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_reference_line'] . "',
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y',
                    fill: false,   
                    showLine: true,
                    isData: false,               
                },
    ";

//cumulative
$strdataseries .= " {
                    datasetId: 'cum', 
                    label: '" . getTxt("cum") . "', 
                    type: 'line',      
                    stack: 'Stack 1',                                                                 
                    data: [" . convertValueArrayToDataString($totalsumCumArray) . "],
                    fill: true,                    
                    backgroundColor: '" . $colors['color_chart_cum_fill'] . "',                
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y1',                       
                    showLine: false,
                    isData: false,               
                },
    ";

$show_legende = "true";
if ($isIndexPage) {
    echo ' <div class = "index_chart" id="total_chart">
              <canvas id="total_chart_canvas"></canvas>
           </div>';
    $show_legende = "false";
}

$subtitle = getTxt("total") . ": " . round($total_sum_for_all_years, 0) . " kWh";

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= HTML_PATH ?>inc/js/chart_support.js"></script>
<script>
    $(function () {
            const ctx = document.getElementById('total_chart_canvas').getContext("2d");

            Chart.defaults.color = '<?= $colors['color_chart_text_title'] ?>';
            new Chart(ctx, {
                data: {
                    labels: [<?= $labels ?>],
                    datasets: [<?= $strdataseries  ?>]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: '<?= getTxt("year") ?> (MWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return (value/1000).toFixed(0)
                                }
                            },
                        },
                        y1: {
                            type: 'linear',
                            min: 0,
                            display: true,
                            position: 'right',
                            // grid line settings
                            grid: {
                                drawOnChartArea: false, // only want the grid lines for one axis to show up
                            },
                            stacked: true,
                            title: {
                                display: true,
                                text: '<?= getTxt("total") ?> (MWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return (value/1000).toFixed(0)
                                }
                            },
                        },
                    },
                    plugins: {
                        customCanvasBackgroundColor: {
                            color: '<?= $colors['color_chartbackground'] ?>',
                        },
                        legend: {
                            display: <?= $show_legende ?>,
                            position: 'bottom',
                            labels: {
                                filter: item => !item.text.includes('line')
                            },
                            onClick: getCustomLegendClickHandler()
                        },
                        subtitle: {
                            display: true,
                            text: '<?= $subtitle ?>',
                            padding: {top: 5, left: 0, right: 0, bottom: 3},
                        },
                    },
                    onClick: (event, elements, chart) => {
                        if (elements[0]) {
                            const i = elements[0].index;
                            const url = chart.data.datasets[0].data[i].url;
                            if (url.length > 0) {
                                location.href = url;
                            }
                        }
                    }
                },
                plugins: [getPlugin()],
            });
        }
    )

</script>
