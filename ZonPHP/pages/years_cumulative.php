<?php
global $params, $title, $colors;
include_once "../inc/init.php";
include_once ROOT_DIR . "/inc/connect.php";
include_once ROOT_DIR . "/inc/header.php";
include_once ROOT_DIR . "/charts/chart_support.php";
include_once ROOT_DIR . "/charts/years_cumulative_chart.php";

$padding = '- 0px';
$corners = 'border-bottom-left-radius: 0px !important; border-bottom-right-radius: 0px;';
?>
<div id="page-content">
    <div id='resize' class="bigCharts"
         style="<?= WINDOW_STYLE_CHART ?>; padding-bottom: calc(136px <?= $padding; ?>); ">
        <div id="menu_header">
            <?php include_once ROOT_DIR . "/inc/topmenu.php"; ?>
        </div>
        <div id="chart_header" class="<?= HEADER_CLASS ?>">
            <h2>
                <?= getTxt("chart_cumulativeoverview") ?>
            </h2>
            <div class="inner" id="filter" style="z-index: 999 !important; position:relative">
                <a onclick="myPrompt()" class="p-1 btn btn-zonphp" data-bs-toggle="collapse"
                   id="myPrompt" href="#hiddenContent" role="button" aria-expanded="false"
                   aria-controls="collapseExample"
                   style="border-top-width: 1px; border-bottom-width: 1px; height: 27px;  vertical-align: top; "><?= getTxt("filter"); ?></a>
            </div>
        </div>
        <script>
            function myPrompt(text, O, cancel, defaultValue) {
                let dialog = document.querySelector("#prompt");
                dialog.show();
            }
            function myOK() {
                let inverters = document.getElementsByName("inverters")
                selectedInverters = "";
                inverters.forEach(function (inverter) {
                    if (inverter.checked) {
                        selectedInverters = selectedInverters + inverter.value + ","
                    }
                })
                let years = document.getElementsByName("years")
                let selectedYears = "";
                years.forEach(function (year) {
                    if (year.checked) {
                        selectedYears = selectedYears + year.value + ","
                    }
                })

                window.location.href =
                    "?inverters=" + selectedInverters +
                    "&years=" + stripLastChar(selectedYears);
            }

            function myCancel() {
                var dialog = document.querySelector("#prompt");
                dialog.close();
            }
        </script>
        <dialog id="prompt" role="dialog" aria-labelledby="prompt-dialog-heading">
            <h2 id="prompt-dialog-heading"><?= getTxt("filter"); ?></h2>
            <div class="table_component">
                <table>
                    <thead>
                    <tr>
                        <th><?= getTxt("inverter"); ?></th>
                        <th><?= getTxt("jaar"); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <?php
                            foreach (PLANT_NAMES as $inverter) {
                                echo "<input type='checkbox' id='$inverter' name='inverters' value='$inverter'" . getIsCheckedString($inverter, $visibleInvertersArray) . ">";
                                echo "<label for='$inverter'>" .$inverter . "</label><br>";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            foreach ($years as $year) {
                                echo "<input type='checkbox' id='$year' name='years' value='$year'" . getIsCheckedString($year, $selectedYears) . ">";
                                echo "<label for='$year'>" . $year . "</label><br>";
                            }
                            ?>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <br>
            <p class="button-row">
                <button class="p-1 btn btn-zonphp" name="cancel" onclick="myCancel()"><?= getTxt("cancel"); ?></button> &nbsp; &nbsp;
                <button class="p-1 btn btn-zonphp" name="ok" onclick="myOK()"><?= getTxt("ok"); ?></button>
            </p>
        </dialog>
        <div id="universal"
             style="width:100%; background-color: <?= $colors['color_chartbackground'] ?>;height:100%; <?= $corners; ?>">
            <canvas id="cumulative_chart_canvas"></canvas>
        </div>
        <?php include_once ROOT_DIR . "/inc/footer.php"; ?>
    </div>
    <br>
</div><!-- closing ".page-content" -->
<script>
    $(document).ready(function () {
        $("#resize ").height(<?= BIG_CHART_HIGHT ?>);
    });
</script>
</body>
</html>