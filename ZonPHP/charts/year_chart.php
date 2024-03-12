<?php
global $params, $con, $colors, $shortMonthLabels;
include_once "../inc/init.php";
include_once ROOT_DIR . "/inc/connect.php";
include_once "chart_support.php";

if (isset($_GET['date'])) {
    $chartdatestring = html_entity_decode($_GET['date']);
    $chartdate = strtotime($chartdatestring);
} else {
    $chartdate = $_SESSION['CHARTDATE'] ?? time();
}
$chartdatestring = date("Y-m-d", $chartdate);

$isIndexPage = false;
if (isset($_POST['action']) && ($_POST['action'] == "indexpage")) {
    $isIndexPage = true;
}
// -------------------------------------------------------------------------------------------------------------
$current_year = date('Y', $chartdate);
$expectedYield = $params['expectedYield'];
$totalExpectedMonth = $params['totalExpectedMonth'];

$sql = "SELECT MAX( Datum_Maand ) AS maxi, SUM( Geg_Maand ) AS som, COUNT(Geg_Maand) AS aantal, naam
	FROM " . TABLE_PREFIX . "_maand
	where DATE_FORMAT(Datum_Maand,'%y')='" . date('y', $chartdate) . "'
	GROUP BY naam, month(Datum_Maand)
	ORDER BY naam ASC";
$result = mysqli_query($con, $sql) or die("Query failed. jaar " . mysqli_error($con));
$all_valarray = array();
$inveter_list = array();
if (mysqli_num_rows($result) == 0) {
    $datum = getTxt("nodata") . date("Y", $chartdate);
} else {
    while ($row = mysqli_fetch_array($result)) {
        $inverter_name = $row['naam'];
        $all_valarray[$inverter_name][date("n", strtotime($row['maxi']))] = $row['som'];

        if (!in_array($inverter_name, $inveter_list)) {
            if (in_array($inverter_name, PLANT_NAMES)) {
                // add to list only if it configured (ignore db entries)
                $inveter_list[] = $inverter_name;
            }
        }
    }
    $datum = date("Y", $chartdate);
}

$sqlavg = "SELECT ROUND( SUM( Geg_Maand ) / ( COUNT( Geg_Maand ) /30 ) , 0 ) AS aantal
FROM " . TABLE_PREFIX . "_maand
WHERE DATE_FORMAT( Datum_Maand,  '%y' ) =  '" . date('y', $chartdate) . "'
GROUP BY naam
ORDER BY naam ASC";

$avg_data = array();
$result = mysqli_query($con, $sqlavg) or die("Query failed (gemiddelde) " . mysqli_error($con));
while ($row = mysqli_fetch_array($result)) {
    $avg_data[] = $row['aantal'];
}

// fetch max values for all months and inverters
$sqlmax = "SELECT maand,jaar,som, Name FROM (SELECT naam as Name, month(Datum_Maand) AS maand,year(Datum_Maand) AS jaar,sum(Geg_Maand) AS som FROM " .
    TABLE_PREFIX . "_maand GROUP BY naam, maand,jaar ) AS somquery JOIN (SELECT maand as tmaand, max( som ) AS maxgeg FROM ( SELECT naam, maand, jaar, som FROM ( SELECT naam, month( Datum_Maand ) AS maand, year( Datum_Maand ) AS jaar, sum( Geg_Maand ) AS som FROM " .
    TABLE_PREFIX . "_maand GROUP BY naam, maand, jaar ) AS somqjoin ) AS maxqjoin GROUP BY naam,tmaand )AS maandelijks ON (somquery.maand= maandelijks.tmaand AND maandelijks.maxgeg = somquery.som) ORDER BY Name, maand";

$resultmax = mysqli_query($con, $sqlmax) or die("Query failed. jaar-max " . mysqli_error($con));

$maxPerMonth = array();
if (mysqli_num_rows($resultmax) > 0) {
    while ($row = mysqli_fetch_array($resultmax)) {
        $maxPerMonth[$row['maand']][$row['Name']] = $row['som'];
    }
}

?>

<?php
// -----------------------------  build data for chart -----------------------------------------------------------------
$labels = $shortMonthLabels;
$myColors = colorsPerInverter();
$my_year = date("Y", $chartdate);
$myurl = HTML_PATH . "pages/month.php?date=";

$maxIndex = 99;
$strdataseries = "";
$totalYear = 0.0;
$sumAverage = 0.0;
$sumExpected = 0.0;

$totalsumCumArray = array();
$totalsumMaxArray = array();
$totalsumRefArray = array();
$sumPerMonthArray = array();
for ($i = 1; $i <= 12; $i++) {
    $totalsumCumArray[$i] = 0.0;
    $totalsumMaxArray[$i] = 0.0;
    $totalsumRefArray[$i] = 0.0;
    $sumPerMonthArray[$i] = 0.0;
}

foreach (PLANT_NAMES as $key => $inverter_name) {
    $cumSum = 0;
    $dash = '';
    $strdata = "";
    $maxData = "";
    $cumData = "";
    $refData = "";

    $inverterAverage = array_sum($all_valarray[$inverter_name]) / 12;
    $sumAverage += $inverterAverage;
    for ($i = 1; $i <= 12; $i++) {
        $maxVal = round($maxPerMonth[$i][$inverter_name], 2);
        $formattedHref = sprintf("%s%04d-%02d-%02d", $myurl, $my_year, $i, 1);
        $maxData .= " { x: $i, y:  $maxVal},";
        $totalsumMaxArray[$i] += $maxVal;

        $myColor1 = $myColors[$inverter_name]['min'];
        $myColor2 = $myColors[$inverter_name]['max'];
        $myMaxColor1 = "'" . $colors['color_chartbar_piek1'] . "'";
        $myMaxColor2 = "'" . $colors['color_chartbar_piek2'] . "'";

        $val = 0.0;
        if (isset($all_valarray[$inverter_name][$i])) {
            $val = round($all_valarray[$inverter_name][$i], 2);
            $sumPerMonthArray[$i] += $val;
            $totalYear += $val;
        }
        $formattedHref = sprintf("%s%04d-%02d-%02d", $myurl, $my_year, $i, 1);
        $strdata .= " { x: $i, y: $val, url: \"$formattedHref\"},";
        $cumSum += $val;
        $cumData .= " { x: $i, y: $cumSum},";
        $totalsumCumArray[$i] = $totalsumCumArray[$i] + $cumSum;

        $refValue = $totalExpectedMonth[$i][$inverter_name];
        $refData .= " { x: $i, y: $refValue},";
        $totalsumRefArray[$i] = $totalsumRefArray[$i] + $refValue;
    }

    $strdataseries .= " {
                    order: 1,  
                    datasetId: '" . $inverter_name . "', 
                    label: '" . $inverter_name . "', 
                    inverter: '" . $inverter_name . "', 
                    type: 'bar',                               
                    stack: 'Stack 0',
                    borderWidth: 1,
                    data: [" . $strdata . "],                    
                    dataCUM: [" . $cumData . "],
                    dataMAX: [" . $maxData . "],                    
                    dataREF: [" . $refData . "],                    
                    averageValue: " . $inverterAverage . ",
                    expectedValue: 0.0,
                    maxIndex: " . $maxIndex . ",
                    fill: true,
                    backgroundColor: customGradientBackground,
                    yAxisID: 'y',
                    isData: true,
                },
    ";
}
$maxMonthVal = max($sumPerMonthArray);
$maxIndex = 99;
for ($i = 1; $i <= 12; $i++) {
    // find max value
    if ($sumPerMonthArray[$i] == $maxMonthVal) {
        $maxIndex = $i-1;
    }
}

// max bars
$strdataseries .= " {
                    order: 5,  
                    datasetId: 'max', 
                    label: 'max', 
                    type: 'bar',                                                                                           
                    data: [" . convertValueArrayToDataString($totalsumMaxArray) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_max_bar'] . "',
                    backgroundColor: '" . $colors['color_chart_max_bar'] . "',                   
                    borderWidth: 1,
                    pointStyle: false,   
                    xAxisID: 'maxbar',
                    yAxisID: 'y',
                    fill: false,   
                    showLine: true,
                    isData: false,               
                },
    ";

// average
$strdataseries .= " {
                    datasetId: 'avg', 
                    label: '" . getTxt("average") . "', 
                    type: 'line',      
                    stack: 'Stack 1',                                                                 
                    data: [" . buildConstantDataString($sumAverage, 12) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_average_line'] . "',
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y',
                    xAxisID: 'x-axis-lines',
                    fill: false,   
                    showLine: true,
                    isData: false,               
                },
    ";

// cumulative
$strdataseries .= " {
                    order: 10,  
                    datasetId: 'cum', 
                    label: '" . getTxt("cum") . "', 
                    type: 'line',      
                    stack: 'Stack 1',                                                                 
                    data: [" . convertValueArrayToDataString($totalsumCumArray) . "],
                    fill: true,                    
                    backgroundColor: '" . $colors['color_chart_cum_fill'] . "',                
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y-axis-cum',      
                    xAxisID: 'x-axis-lines',                 
                    showLine: false,
                    isData: false,               
                },
    ";

// reference per month
$strdataseries .= " {
                    order: 0,    
                    datasetId: 'ref', 
                    label: '" . getTxt("reference") . "', 
                    type: 'line',         
                    radius:function(context) { 
                       let width = 12;
                       try {
                            let chart = context.chart;                                  
                            let meta = chart.getDatasetMeta(0);
                            width = meta.data[0].width -30;
                       } catch (e) {
                       } finally {
                          if (width == null) width = 12;         
                       }                                                    
                       if (width < 0) width = 12;
                       return width;
                    },
                    hoverRadius: 25,
                    pointStyle: 'line',
                    borderWidth: 4,
                    stepped: true,
                    showLine: false,                                                             
                    data: [" . convertValueArrayToDataString($totalsumRefArray) . "],
                    fill: false,                    
                    borderColor: '" . $colors['color_chart_reference_line'] . "',                
                    borderWidth: 1,                    
                    yAxisID: 'y',      
                    xAxisID: 'x-axis-ref',                                     
                    isData: false,               
                },
    ";


$show_legende = "true";
if ($isIndexPage) {
    echo '<div class = "index_chart" id="year_chart">
            <canvas id="year_chart_canvas"></canvas>
          </div>';
    $show_legende = "false";
}


$subtitle = getTxt("total") . ": $totalYear kWh";

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4/dist/chart.umd.min.js"></script>
<script src="<?= HTML_PATH ?>inc/js/chart_support.js"></script>
<script>

    $(function () {

            const ctx = document.getElementById('year_chart_canvas').getContext("2d");

            Chart.defaults.color = '<?= $colors['color_chart_text_title'] ?>';
            new Chart(ctx, {
                data: {
                    labels: [<?= $labels ?>],
                    datasets: [<?= $strdataseries  ?>],
                    myColors: <?= json_encode(colorsPerInverterJS()) ?>,
                    maxIndex: <?= $maxIndex ?>
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '<?= getTxt("month") ?> (kWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return value
                                }
                            },
                        },
                        'x-axis-lines': {
                            offset: false,
                            display: false,
                        },
                        'x-axis-ref': {
                            offset: true,
                            display: false,
                        },
                        'y-axis-cum': {
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
                                text: '<?= getTxt("total") ?> (kWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return value.toFixed(0)
                                }
                            },
                        },
                        maxbar: {
                            offset: true,
                            display: false,
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
