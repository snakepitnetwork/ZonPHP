<?php
global $con, $shortmonthcategories, $chart_options, $colors, $chart_lang;
include_once "../inc/init.php";
include_once ROOT_DIR . "/inc/connect.php";
include_once "chart_support.php";

$isIndexPage = false;
$showAllInverters = true;
if (isset($_POST['action']) && ($_POST['action'] == "indexpage")) {
    $isIndexPage = true;
}

$currentdate = date("Y-m-d");
$currentYear = date("Y");
$visibleInvertersJS = "";
if (isset($_GET['inverters']) && $_GET['inverters'] != "undefined" && $_GET['inverters'] != "") {
    $visibleInvertersArray = explode(',', $_GET['inverters']);
    $visibleInvertersString = "'" . implode("', '", $visibleInvertersArray) . "'";
    $visibleInvertersJS = implode(",", $visibleInvertersArray);
} else {
    $visibleInvertersArray = PLANT_NAMES;
    $visibleInvertersString = "'" . implode("', '", PLANT_NAMES) . "'";
    $visibleInvertersJS = implode(",", PLANT_NAMES);
}


$sql = "SELECT date(`Datum_Maand`) as Date,`Geg_Maand` as Yield, YEAR(`Datum_Maand`) as Year, `Naam` as Name 
        FROM `" . TABLE_PREFIX . "_maand`  
        WHERE naam in ($visibleInvertersString) 
        ORDER BY `Datum_Maand`,`Naam`";


// make array with values from query
$result = mysqli_query($con, $sql) or die("Query failed. maand " . mysqli_error($con));
$querydata = array();
$totaldata = array();
$names = array();
$years = array();
$array = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $querydata[$row['Date']][$row['Name']] = $row['Yield'];
        $names[] = $row["Name"];
        $years[] = date("Y", strtotime($row["Date"]));
    }
}

$names = array_values(array_unique($names));
$years = array_values(array_unique($years));

if (isset($_GET['years']) && $_GET['years'] != "undefined" && $_GET['years'] != "") {
    $selectedYears = explode(",", $_GET['years']);
} else {
    $selectedYears = $years;
}

// make array with all dates and and sum of visible inverter from start to end
// this will fill the gaps when no data available
$startDate = $years[0] . '-01-01';
$endDate = $years[count($years) - 1] . '-12-31';  // last year until 31.12
$period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), new DateTime($endDate));
foreach ($period as $key => $value) {
    foreach ($names as $name) {
        $read = $value->format('Y-m-d');
        if (!isset($totaldata[$read])) {
            $totaldata[$read] = 0;
        }
        $yield = 0;
        if (isset($querydata[$read][$name])) {
            $yield = $querydata[$read][$name];
        }
        $totaldata[$read] += $yield;
    }
}

//sort array on date
ksort($totaldata);

$valuesPerYear = array();
foreach ($years as $year) {
    // initialize Array values per year
    $valuesPerYear[$year] = array();
}

// split per year
foreach ($totaldata as $date => $value) {
    $yearkey = substr($date, 0, 4);
    $valuesPerYear[$yearkey][$date] = $value;
}

$strdataseries = "";
foreach ($valuesPerYear as $year => $allvalue) {
    $strdata = "";
    $cumVal = 0;
    foreach ($allvalue as $date => $value) {
        // normalize all dates to the current year
        $normalizedDate = updateDate($date);
        $cumVal += $value;
        if ($normalizedDate != "$currentYear-02-29") {   // ignore leap year 29.2.
            if ($value > 0) {
                $strdata .= "{ x: '$normalizedDate', y: $cumVal },";
            } else {
                $strdata .= "{ x: '$normalizedDate', y: NaN },";
            }
        }
    }

    $isHidden = getIsHidden($year, $selectedYears);
    $strdataseries .= " {
                    datasetId: '" . $year . "', 
                    label: '" . $year . "',
                    hidden: " . getIsHidden($year, $selectedYears) . ",                     
                    type: 'line',                                                  
                    borderWidth: 1,
                    data: [" . strip($strdata) . "],                    
                    dataCUM: [],
                    dataMAX: [], 
                    dataREF: [],
                    averageValue: 0,
                    expectedValue: 0,
                    maxIndex: 0,
                    fill: false,
                    pointStyle: false,                         
                    yAxisID: 'y',
                    xAxisID: 'x',
                    isData: true,
                },
    ";
}

$show_legende = "true";
if ($isIndexPage) {
    echo '<div class = "index_chart" id="universal">
            <canvas id="cumulative_chart_canvas"></canvas>
          </div>';
    $show_legende = "false";
}

$subtitle = strip($visibleInvertersJS);
$labels = "";
for ($i = 1; $i <= 12; $i++) {
    $labels .= "new Date('$currentYear-$i-01'),";
}
$labels = strip($labels);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="<?= HTML_PATH ?>inc/js/chart_support.js"></script>
<script>

    $(function () {

            const ctx = document.getElementById('cumulative_chart_canvas').getContext("2d");

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
                            stacked: false,
                            display: true,
                            beginAtZero: true,
                            type: 'time',
                            time: {
                                unit: 'hour',
                                unitStepSize: 1,
                                displayFormats: {
                                    'hour': 'MMM'
                                },
                            },
                            ticks: {
                                maxTicksLimit: 11,
                            },
                        },
                        y: {
                            stacked: false
                        },
                    },
                    plugins: {
                        customCanvasBackgroundColor: {
                            color: '<?= $colors['color_chartbackground'] ?>',
                        },
                        legend: {
                            display: <?= $show_legende ?>,
                            position: 'bottom',
                        },
                        subtitle: {
                            display: true,
                            text: '<?= $subtitle ?>',
                        },
                    },
                },
                plugins: [getPlugin()],
            });
        }
    )

</script>
