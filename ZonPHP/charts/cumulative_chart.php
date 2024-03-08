<?php
global $con, $shortmonthcategories, $chart_options, $colors, $chart_lang;
include_once "../inc/init.php";
include_once ROOT_DIR . "/inc/connect.php";

$isIndexPage = false;
$showAllInverters = true;
if (isset($_POST['action']) && ($_POST['action'] == "indexpage")) {
    $isIndexPage = true;
}

$currentdate = date("Y-m-d");
$inClause = "'" . implode("', '", PLANT_NAMES) . "'";
$sql = "SELECT date(`Datum_Maand`) as Date,`Geg_Maand` as Yield, YEAR(`Datum_Maand`) as Year, `Naam` as Name 
        FROM `" . TABLE_PREFIX . "_maand`  
        WHERE naam in ($inClause) 
        ORDER BY `Datum_Maand`,`Naam`";

//WHERE YEAR(`Datum_Maand`) IN (2023, 2024)
//make array with values from query
$result = mysqli_query($con, $sql) or die("Query failed. maand " . mysqli_error($con));
$querydata = array();
$totaldata = array();
$names = array();
$years = array();
$array = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        //echo $row['Date'],' ',$row['Name'],'  ',$row['Yield'],' ',$row['Year'],' <BR>';
        $querydata[$row['Date']][$row['Name']] = $row['Yield'];
        $names[] = $row["Name"];
        $years[] = date("Y", strtotime($row["Date"]));
    }
}

$names = array_values(array_unique($names));
$years = array_values(array_unique($years));

//make array with all dates and inverter names from start to end
//this will fill the gaps when no data available
$startDate = $years[0] . '-01-01';
$endDate = array_key_last($querydata);
$period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), new DateTime($endDate));
foreach ($period as $key => $value) {
    foreach ($names as $name) {
        $read = $value->format('Y-m-d');
        $year = $value->format('Y');
        $yield = 0;
        if (isset($querydata[$read][$name])) {
            $yield = $querydata[$read][$name];
        }
        $totaldata[$read][$name] = $yield;
    }
}

//sort array on date
ksort($totaldata);

//flip array -> data ordered by inverter name
$mistral = [];
foreach ($totaldata as $outerkey => $outerArr) {
    foreach ($outerArr as $key => $innerArr) {
        $mistral[$key][$outerkey] = $innerArr;
    }
}

//$total = array();
//running total per inverter array
$runningSum = 0;
for ($i = 0; $i < count($years); $i++) {
    //$keys=0;
    foreach ($mistral as $keys => $sums) {
        $runningSum = 0;
        foreach ($sums as $key => $number) {
            $yearkey = substr($key, 0, 4);
            if ($years[$i] == $yearkey) {
                //echo $yearkey,' nb ',$number,' rs ',$runningSum,' array ',$keys, '<BR>';
                $runningSum += $number;
                $total[$keys][$key] = $runningSum;
            }
            $cumulus = $total;
        }
    }
}

//reverse flip -> data ordered on date
$foehn = [];
foreach ($total as $outerkey1 => $outerArr1) {
    foreach ($outerArr1 as $key1 => $innerArr1) {
        $foehn[$key1][$outerkey1] = $innerArr1;
    }
}

$value = array();
$strdataseries = "";
$strdata = "";
$mouseover = "";
for ($i = 0; $i < count($years); $i++) {
    $strdata = "";
    foreach ($foehn as $allsum => $value) {
        $yearkey = substr($allsum, 0, 4);
        foreach ($names as $name => $val) {
            if ($yearkey == $years[($i)]) {
                if (isset($value[$val])) {
                    $strdata .= "{  y: $value[$val], inverter: '$val' },";
                }
            }
        }
    }
    $strdata = substr($strdata, 0, -1);
    $strdataseries .= " year" . $years[($i)] . ": [" . $strdata . "],";
}
$myColors = colorsPerInverter();
$strdataseries = substr($strdataseries, 0, -1);
$strseriestxt = "";
$strnametxt = "";
for ($i = 0; $i < count($years); $i++) {
    $strseriestxt .= "{id: 'year" . $years[($i)] . "', name: '" . $years[($i)] . "', data:[]},";
}

$i = 0;
foreach ($names as $name) {
    $col1 = $myColors[$name]['min'];
    $col2 = $myColors[$name]['max'];
    $line = "";
    if ($i == 0) $line = 'newLine: true,';
    $i++;
    $strnametxt .= "{" . $line . " name: '" . $name . "', legendSymbol: 'rectangle', color: { linearGradient: {x1: 0, x2: 0, y1: 1, y2: 0}, stops: [ [0, $col1], [1, $col2]] }, id: '" . $name . "'},";
    $mouseover .= "item.name==='" . $name . "'||";
}

$strtotaaltxt = $strseriestxt . $strnametxt;
$strtotaaltxt = substr($strtotaaltxt, 0, -1);
$mouseover = substr($mouseover, 0, -2);
$show_legende = "true";
if ($isIndexPage) {
    echo '<div class = "index_chart" id="universal">
            <canvas id="cumulative_chart_canvas"></canvas>
          </div>';
    $show_legende = "false";
}

$categories = $shortmonthcategories;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            stacked: true,
                        },
                        y: {
                            stacked: true
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

