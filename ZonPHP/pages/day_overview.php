<?php
include_once "../parameters.php";
include_once ROOT_DIR."/inc/sessionstart.php";
include_once ROOT_DIR."/inc/load_cache.php";
include_once ROOT_DIR."/inc/import_data.php";
include_once ROOT_DIR."/inc/header.php";
include_once "../charts/day_chart.php";
?>
<?php include_once ROOT_DIR."/inc/menu.php"; ?>
<script>
	var start = '<?php echo date('Y-m-d', $date_minimum) ?>';
    $(document).ready(function () {
              
        $("#startdate, #enddate").datepicker({
            changeMonth: true,
            changeYear: true,
            showOn: "button",
            gotoCurrent: false,
            maxDate: 0,
            
            minDate: new Date(start),
            buttonImage: "../inc/image/calendar.gif",
            buttonImageOnly: true,
            buttonText: "Select date",
            onSelect: function () {
                var url = "day_overview.php?dag=" + $("#startdate").val();
                window.open(url, "_self");
            },
        });
        $(".ui-datepicker-trigger").mouseover(function() {
    		$(this).css('cursor', 'pointer');
		});
		$(".ui-datepicker-trigger").css("margin-bottom","-5px");
        
        var _gotoToday = jQuery.datepicker._gotoToday;
        jQuery.datepicker._gotoToday = function (a) {
            var target = jQuery(a);
            var inst = this._getInst(target[0]);
            _gotoToday.call(this, a);
            jQuery.datepicker._selectDate(a, jQuery.datepicker._formatDate(inst, inst.selectedDay, inst.selectedMonth, inst.selectedYear));
        };
    });
    </script>
<?php
$paramstr_choose = '';
$paramstr_day = '';
# remove naam parameter
if (sizeof($_GET) > 0) {
    foreach ($_GET as $key => $value) {
        if (!(($key == "naam") || ($key == "type"))) {
            $paramstr_choose .= $key . "=" . $value . "&";
        }
        if ($key != "dag") {
            $paramstr_day .= $key . "=" . $value . "&";
        }
    }
}
if (strpos($paramstr_day, "?") == 0) {
    $paramstr_day = '?' . $paramstr_day;
}
if (strpos($paramstr_choose, "?") == 0) {
    $paramstr_choose = '?' . $paramstr_choose;
}

?>
<div id="page-content">
    <div id='resize' class="bigCharts" style="<?= WINDOW_STYLE_CHART ?>; padding-bottom: 57px; ">
        <div id="week_chart_header" class="<?= HEADER_CLASS ?>">
               <h2>
                <button class="btn btn-zonphp " onclick="window.location.href='<?php echo '?dag=' . date('Y-m-d', strtotime("-1 day", $chartdate)) . '\'"' ;
				if ($date_minimum >= $chartdate) echo " hidden";	?>><i class="arrow left"></i></button>
				<?php echo $datum ?>
                <button class="btn btn-zonphp" onclick="window.location.href='<?php echo '?dag=' . date('Y-m-d', strtotime("+1 day", $chartdate)) . '\'"' ;
				if ($date_maximum <= $chartdate) echo " hidden";	?>><i class="arrow right text-align-top" ></i></button>
               </h2> 
        </div>
  		<div class="backtoday align-top" style="none:left; position: absolute;  left: 15px;">
            		<button class="btn btn-zonphp" onclick="window.location.href='<?php echo '?dag='.date('Y-m-d', $chartcurrentdate); ?>'"><?php echo getTxt("back_to_today") ?></button>
		     			<div class="buttonbox" >
        					<input type="hidden" id="startdate" readonly>
        				</div>
 		</div>
        <div id="mycontainer" style="width:100%; height:100%;"></div>
    </div>
</div>
<script>
    $(document).ready(function () {
        $("#resize ").height(<?php echo $big_chart_height ?>);
    });
</script>
</div><!-- closing ".page-content" -->
<?php include_once ROOT_DIR."/inc/footer.php"; ?>
</body>
</html>