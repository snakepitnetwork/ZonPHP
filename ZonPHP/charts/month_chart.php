<?php
global $con, $params, $formatter, $colors, $chart_options;
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
$showAllInverters = true;
if (isset($_POST['action']) && ($_POST['action'] == "indexpage")) {
    $isIndexPage = true;
}
// -----------------------------  get data from DB -----------------------------------------------------------------
$current_year = date('Y', $chartdate);
$current_month = intval(date('m', $chartdate));
$current_year_month = date('Y-m', $chartdate);

// get reference values
$nfrefmaand = array();
foreach (PLANT_NAMES as $plant) {
    $tmp = $params[$plant]['expectedYield'][$current_month - 1] / 30;
    $nfrefmaand[$plant] = $tmp;
}

$DaysPerMonth = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

$sql = "SELECT Datum_Maand, Geg_Maand, naam
        FROM " . TABLE_PREFIX . "_maand
        where Datum_Maand like '" . $current_year_month . "%'
        GROUP BY Naam, Datum_Maand, Geg_Maand
        ORDER BY Naam, Datum_Maand ASC";
$result = mysqli_query($con, $sql) or die("Query failed. maand " . mysqli_error($con));
$daycount = 0;
$all_valarray = array();
$inveter_list = array();
$monthTotal = 0.0;
if (mysqli_num_rows($result) == 0) {
    $formatter->setPattern('LLLL yyyy');
    $datum = getTxt("nodata") . datefmt_format($formatter, $chartdate);
    $agegevens[] = 0;
    $geengevmaand = 0;
    $fgemiddelde = 0;
} else {
    $formatter->setPattern('LLL yyyy');
    $datum = datefmt_format($formatter, $chartdate);
    $geengevmaand = 1;
    $agegevens = array();
    // fill empty days
    for ($i = 1; $i <= $DaysPerMonth; $i++) {
        $agegevens[$i] = 0;
    }
    for ($k = 0; $k < count(PLANT_NAMES); $k++) {
        for ($i = 1; $i <= $DaysPerMonth; $i++) {
            $all_valarray[$i][PLANT_NAMES[$k]] = 0;
        }
    }
    while ($row = mysqli_fetch_array($result)) {
        $inverter_name = $row['naam'];
        $adatum[] = date("j", strtotime($row['Datum_Maand']));
        $agegevens[date("j", strtotime($row['Datum_Maand']))] += $row['Geg_Maand'];
        $all_valarray[date("j", strtotime($row['Datum_Maand']))] [$inverter_name] = $row['Geg_Maand'];
        $monthTotal += $row['Geg_Maand'];
        $dmaandjaar[] = $row['Datum_Maand'];

    }
    $daycount = 0;
    for ($i = 1; $i <= $DaysPerMonth; $i++) {
        if ($agegevens[$i] > 0) {
            $daycount++;
        }
    }
    if ($daycount == 0) {
        $daycount = 1;
    }
    $fgemiddelde = array_sum($agegevens) / $daycount;
    $iyasaanpassen = round(0.5 + max($agegevens) / 5) * 5;
}
//  new average
$sqlavg = "SELECT Naam, MONTH( Datum_Maand ) AS Maand, ROUND( SUM( Geg_Maand ) / COUNT( DISTINCT (
YEAR( Datum_Maand ) ) ) , 0
) AS AVG
FROM " . TABLE_PREFIX . "_maand
WHERE MONTH( Datum_Maand ) = " . $current_month . "
GROUP BY Naam, MONTH( Datum_Maand ) 
ORDER BY naam ASC ";
$result = mysqli_query($con, $sqlavg) or die("Query failed (gemiddelde) " . mysqli_error($con));
while ($row = mysqli_fetch_array($result)) {
    $avg_data[$row['Naam']] = $row['AVG'];
}

?>

<?php
// -----------------------------  build data for chart -----------------------------------------------------------------
// build colors per inverter array
$myColors = colorsPerInverter();
// collect data array
$myurl = HTML_PATH . "pages/day.php?date=";
$strdataseries = "";
$maxval_yaxis = 0;
$myColor1 = "'#FFAABB'";
$myColor2 = "'#FFAABB'";
$labels = "";

$totalsumCumArray = array();
for ($i = 1; $i <= $DaysPerMonth; $i++) {
    $labels .= '"' . $i . '",';
    $totalsumCumArray[$i] = 0.0;
}
$cnt = 0;
$cumData = "";
$sumAverage = 0.0;
$sumExpected = 0.0;

foreach (PLANT_NAMES as $inverter_name) {
    $cnt++;
    $strdata = "";
    $local_max = 0;
    $cumSum = 0;
    $inverterAverage = $avg_data[$inverter_name] / $DaysPerMonth;
    $inverterExpected = $nfrefmaand[$inverter_name];
    // sum average for all inverter
    $sumAverage += $inverterAverage;
    $sumExpected += $inverterExpected;

    $myColor1 = $myColors[$inverter_name]['min'];
    $myColor2 = $myColors[$inverter_name]['max'];
    $myMaxColor1 = "'" . $colors['color_chartbar_piek1'] . "'";
    $myMaxColor2 = "'" . $colors['color_chartbar_piek2'] . "'";
    $maxIndex = 0;
    for ($i = 1; $i <= $DaysPerMonth; $i++) {
        if (array_key_exists($i, $agegevens)) {
            if ($agegevens[$i] == max($agegevens)) {
                $maxIndex = $i;
            }
            $val = round($all_valarray[$i][$inverter_name], 2);
            if ($val > $local_max) $local_max = $val;
            $formattedHref = sprintf("%s%04d-%02d-%02d", $myurl, $current_year, $current_month, $i);
            $strdata .= " { x: $i, y: $val, url: \"$formattedHref\"},";
            $cumSum += $val;
            $cumData .= " { x: $i, y: $cumSum},";
            $totalsumCumArray[$i] = $totalsumCumArray[$i] + $cumSum;
        }
    }

    $maxval_yaxis += $local_max;
    $local_max = 0;
    $strdata = substr($strdata, 0, -1);
    $strdataseries .= " {
                    datasetId: '" . $inverter_name . "', 
                    label: '" . $inverter_name . "', 
                    inverter: '" . $inverter_name . "',
                    type: 'bar',                               
                    stack: 'Stack 0',
                    borderWidth: 1,
                    data: [" . $strdata . "],                    
                    dataCUM: [" . $cumData . "],
                    dataMAX: [], 
                    dataREF: [],
                    averageValue: " . $inverterAverage . ",
                    expectedValue: " . $nfrefmaand[$inverter_name] . ",
                    maxIndex: " . $maxIndex . ",
                    fill: true,
                    backgroundColor: customGradientBackground,
                    yAxisID: 'y',
                    xAxisID: 'x',
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
                    data: [" . buildConstantDataString($sumAverage, $DaysPerMonth) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_average_line'] . "',
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y',
                    xAxisID: 'x1',
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
                    data: [" . buildConstantDataString($sumExpected, $DaysPerMonth) . "],
                    fill: false,
                    borderColor: '" . $colors['color_chart_reference_line'] . "',
                    borderWidth: 1,
                    pointStyle: false,   
                    yAxisID: 'y',
                    xAxisID: 'x1',
                    fill: false,   
                    showLine: true,
                    isData: false,               
                },
    ";

// cumulative
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
                    xAxisID: 'x1',                 
                    showLine: false,
                    isData: false,               
                },
    ";

$show_legende = "true";
if ($isIndexPage) {
    echo '<div class = "index_chart" id="month_chart" style="background-color: ' . $colors['color_chartbackground'] . '">
              <canvas id="month_chart_canvas"></canvas>
          </div>';
    $show_legende = "false";
}
$monthTotal = round($monthTotal, 2);

$subtitle = getTxt("total") . ": $monthTotal kWh";


?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4/dist/chart.umd.min.js"></script>
<script src="<?= HTML_PATH ?>inc/js/chart_support.js"></script>
<script>
    $(function () {
            const ctx = document.getElementById('month_chart_canvas').getContext("2d");

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
                            title: {
                                display: true,
                                text: '<?= getTxt("day") ?> (kWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return value
                                }
                            }
                        },
                        x1: {
                            offset: false,
                            display: false,
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
                            title: {
                                display: true,
                                text: '<?= getTxt("total") ?> (kWh)'
                            },
                            ticks: {
                                callback: function (value, index, ticks) {
                                    return value
                                }
                            },
                            stacked: true,
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
