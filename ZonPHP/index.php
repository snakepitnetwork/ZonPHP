<?php
global $params, $colors, $con;
include_once "inc/init.php";
include_once "inc/connect.php";

if ($_SESSION['STARTDATE'] == NODATE) {
    prepareFarm($params, $con);
}
$aoplopendkwdag[] = 0;
include_once "inc/header.php";

$daytext = getTxt("chart_day_view");
if ($params['useWeewx']) {
    $daytext = getTxt("chart_solar_temp");
}
if (isset($_GET['date'])) {
    $chartdatestring = html_entity_decode($_GET['date']);
    $chartdate = strtotime($chartdatestring);
} else {
    $chartdate = time();
}
$_SESSION['CHARTDATE'] = $chartdate;

?>

<script src="https://jqwidgets.com/public/jqwidgets/jqxcore.js"></script>
<script src="https://jqwidgets.com/public/jqwidgets/jqxscrollbar.js"></script>
<script src="https://jqwidgets.com/public/jqwidgets/jqxbuttons.js"></script>
<script src="https://jqwidgets.com/public/jqwidgets/jqxpanel.js"></script>
<script src="https://jqwidgets.com/public/jqwidgets/jqxchart.js"></script>
<script src="https://jqwidgets.com/public/jqwidgets/jqxgauge.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="<?= HTML_PATH ?>inc/js/chart_support.js"></script>

<div id="page-content">
    <script>
        $(function () {
            // pass txt to JavaScript
            txt = <?= json_encode($_SESSION['txt']); ?>;
            theme = <?= json_encode($_SESSION['colors']); ?>;
            cardlayout = <?= json_encode($_SESSION['CARDS']); ?>;
            plants = <?= json_encode(PLANT_NAMES); ?>;
            farm = <?= json_encode($params['farm']); ?>;
            daytext = <?= '"' . $daytext . '"'; ?>;
            chartdatestring = <?= '"' . date("Y-m-d", $chartdate) . '"' ?>;
            chartmonthstring = <?= '"' . date("F", $chartdate) . '"'; ?>;
            chartyearstring = <?= '"' . date("Y", $chartdate) . '"'; ?>;
            charts = <?= json_encode(array("chart_date_format" => "")); ?>;
            colors = <?= json_encode($colors); ?>;
            images = <?= json_encode($params['images']); ?>;
        });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/velocity/1.5.0/velocity.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/muuri/0.4.0/muuri.min.js"></script>
    <script src="inc/js/index_charts.js"></script>

    <script>
        $(document).ready(function () {
            docReady(load_charts);
        });

    </script>
    <!-- here comes all the charts-->

    <div id="menu_header_index">
        <?php include_once ROOT_DIR . "/inc/topmenu.php"; ?>
    </div>
    <div class="grid"><!-- The Modal --></div>

    <div id="footer_index">
        <?php include_once "inc/footer.php"; ?>
    </div>
    <br>
</div><!-- closing ".page-content" -->

<!-- The image popup modal -->
<div id="myModal" class="modal">
    <span class="close">&times;</span>
    <img class="modal-content" alt="" src="" id="modal-image">
    <div id="caption"></div>
</div>

</body>
</html>