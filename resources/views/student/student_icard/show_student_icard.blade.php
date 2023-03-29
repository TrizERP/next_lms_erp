@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<style type="text/css">
    * {
        -webkit-print-color-adjust: exact !important;   /* Chrome, Safari, Edge */
        color-adjust: exact !important;                 /*Firefox*/
    } 
    .page-break-clear { 
      clear: both;
    }  
    .row-4-column-1{
        display: grid;
        grid-template-columns: 1fr;
        grid-template-rows: 4fr;
        grid-column-gap: 10px;
        grid-row-gap: 5px;
        width: 48vh;
        margin-right: auto;
        margin-left: auto;
    }
    .row-2-column-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        grid-template-rows: repeat(2, 1fr);
        grid-column-gap: 10px;
        grid-row-gap: 5px;
        width: 48vh;
        margin-right: auto;
        margin-left: auto;
    }
    .row-1-column-1 {
        display: grid;
        grid-template-columns: 1fr;
        grid-template-rows: 1fr;
        grid-column-gap: 10px;
        grid-row-gap: 5px;
        width: 48vh;
        margin-right: auto;
        margin-left: auto;
    } 
    .row-3-column-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: repeat(3, 1fr);
        grid-column-gap: 10px;
        grid-row-gap: 5px;
        width: 48vh;
        margin-right: auto;
        margin-left: auto;
    }
    .row-3-column-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: repeat(3, 1fr);
        grid-column-gap: 10px;
        grid-row-gap: 5px;
        width: 48vh;
        margin-right: auto;
        margin-left: auto;
    }
    .icard-item{
         /*content:url(http://dev.triz.co.in/icard/templates/card-bg.jpg); */
         /*background: url(http://dev.triz.co.in/icard/templates/card-bg.jpg) !important; */
        background-size: 100% !important;
        background-repeat: no-repeat !important;
        display: -webkit-box;
        display: -moz-box;
        display: -ms-flexbox;
        display: -webkit-flex;
        display: flex;
        position: relative;
    }
    .row-4-column-1 .item:nth-child(3) > div .icard-item {
        margin-top: -8px;
    }
    .icard-item > img{
        max-width: 100%;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }
    #printPage::before {
        content: "";
        /*background: #000000;*/
        width: 800px;
        height: 1387px;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
    }
    .item{
        position: relative;
        z-index: 9;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Student I-Card</h4>
            </div>
        </div>
        <div id="printPage" class="card">
        @php
        $grade_id = $standard_id = $division_id = '';
            if(isset($data['grade_id'])){
                $grade_id = $data['grade_id'];
                $standard_id = $data['standard_id'];
                $division_id = $data['division_id'];
            }
        @endphp
        <?php
            $file_name = "icard/templates/" . $data['template'] . ".html";
            $fin = fopen($file_name, 'r') or die("Selected Icard Template is not in proper format .");
            $string = fread($fin, filesize($file_name));
            fclose($fin);
        ?>
        @if(isset($data['data']))
        @php
            if(isset($data['data'])){
                $student_data = $data['data'];
            }
            $bootstrapColumn = 12 /$data['column'];
            $pageBreakCount = $data['column'] * $data['row'];
            $j=1;
        @endphp
        <div class="row-{{$data['row']}}-column-{{$data['column']}}">            
        @foreach($student_data as $key => $value)
        <?php
            $icard_icon = '';
            if(isset($value['icard_icon']) && $value['icard_icon'] != '')
            {
                $icard_icon = "/storage/driver/" . $value['icard_icon'];
            }

            $add_val = trim($value['address']);
            $add_val = substr(strtoupper($add_val), 0,38).'..';

            $distance_rate = '';
            if(isset($value['distance_rate']) && $value['distance_rate'] != '')
            {
                $distance_rate = 'Rs '.$value['distance_rate'];
            }

            $from_distance = '';
            if(isset($value['from_distance']) && $value['from_distance'] != '')
            {
                $from_distance = $value['from_distance'].'K.M.';
            }

            $str = str_replace(htmlspecialchars("<<student_name>>"), $value['student_name'], $string);
            $str = str_replace(htmlspecialchars("<<short_student_name>>"), strtoupper($value['short_student_name']), $string);
            $str = str_replace(htmlspecialchars("<<enrollment_no>>"), $value['enrollment_no'], $str);
            $str = str_replace(htmlspecialchars("<<standard_name>>"), $value['standard_name'], $str);
            $str = str_replace(htmlspecialchars("<<division_name>>"), $value['division_name'], $str);
            $str = str_replace(htmlspecialchars("<<father_name>>"), $value['father_name'], $str);
            $str = str_replace(htmlspecialchars("<<mother_name>>"), $value['mother_name'], $str);
            $str = str_replace(htmlspecialchars("<<address>>"), $add_val, $str);
            $str = str_replace(htmlspecialchars("<<mobile>>"), $value['mobile'], $str);
            $str = str_replace(htmlspecialchars("<<mother_mobile>>"), $value['mother_mobile'], $str);
            $str = str_replace(htmlspecialchars("<<student_image>>"), "/storage/student/" . $value['image'], $str);
            $str = str_replace(htmlspecialchars("<<gender>>"), $value['gender'], $str);
            $str = str_replace(htmlspecialchars("<<driver_name>>"), strtoupper($value['driver_name']), $str);
            $str = str_replace(htmlspecialchars("<<driver_mobile>>"), $value['driver_mobile'], $str);
            $str = str_replace(htmlspecialchars("<<icard_icon>>"), $icard_icon, $str);
            $str = str_replace(htmlspecialchars("<<distance_from_school>>"), $value['distance_from_school'], $str);
            $str = str_replace(htmlspecialchars("<<from_distance>>"), $from_distance, $str);
            $str = str_replace(htmlspecialchars("<<distance_rate>>"), $distance_rate, $str);            
            $str = str_replace(htmlspecialchars("<<school_name>>"), $value['school_name'], $str);
            $str = str_replace(htmlspecialchars("<<school_mobile>>"), $value['school_mobile'], $str);
            $str = str_replace(htmlspecialchars("<<school_image>>"), "/storage/school/" . $value['school_image'], $str);
            $str = str_replace(htmlspecialchars("<<school_address>>"), $value['school_address'], $str);
            $str = str_replace(htmlspecialchars("<<years>>"), session()->get('syear') . "-" . (session()->get('syear') + 1), $str);
        ?>
                <div class="item">
                    <?php echo $str; ?>
                </div>
                <?php
                if ($j == $pageBreakCount)
                {
                ?>
                    <div class="page-break-clear"></div>
                    <div class="page-break">&nbsp;</div>
                <?php
                    $j = 0;
                }
                    $j++;
                ?>
            @endforeach
            </div>
        </div>
        <div class="page-break"> </div>
        <div class="row">            
            <div class="col-md-12 form-group">
                <center>
                    <button class="btn btn-success" onclick="printdiv('printPage');">Print</button>
                </center>
            </div>
        </div>
        @endif
    </div>
</div>

@include('includes.footerJs')
<script type="text/javascript">
    window.onafterprint = function(){
      window.location.reload(true);
    }
</script>
@include('includes.footer')
